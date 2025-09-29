<?php
/**
 * @package    WT Amo CRM library package
 * @version    1.3.0
 * @Author     Sergey Tolkachyov, https://web-tolk.ru
 * @copyright  (c) 2022 - September 2025 Sergey Tolkachyov. All rights reserved.
 * @license    GNU/GPL3 http://www.gnu.org/licenses/gpl-3.0.html
 * @since      1.3.0
 */

namespace Joomla\Plugin\User\Wtamocrmusersync\Extension;

use Exception;
use Joomla\CMS\Application\ApplicationHelper;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Date\Date;
use Joomla\CMS\Form\Form;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Mail\MailTemplate;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\String\PunycodeHelper;
use Joomla\CMS\User\User;
use Joomla\CMS\User\UserFactoryAwareTrait;
use Joomla\CMS\User\UserHelper;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Database\ParameterType;
use Joomla\CMS\Event\AbstractEvent;
use Joomla\Event\Dispatcher;
use Joomla\Event\Event;
use Joomla\Event\SubscriberInterface;
use Joomla\CMS\Uri\Uri;
use Joomla\Filter\OutputFilter;
use Joomla\Registry\Registry;
use Joomla\Utilities\ArrayHelper;
use RuntimeException;
use Webtolk\Amocrm\Amocrm;
use Webtolk\Amocrm\AmocrmClientException;
use Webtolk\Amocrm\Event\WebhookEvent;
use Webtolk\Amocrm\Helper\UserHelper as AmocrmUserHelper;

defined('_JEXEC') or die;

class Wtamocrmusersync extends CMSPlugin implements SubscriberInterface
{
    use DatabaseAwareTrait;
    use UserFactoryAwareTrait;

    protected $autoloadLanguage = true;

    /**
     * AmoCRM to Joomla fields mapping.
     * $mapping[$amocrm_contact_id] = ['type'=> '', 'custom_field_id','user_param_name'];
     *
     * @var array $mapping
     * 
     * @since 1.3.0
     */
    private static array $mapping = [];

    /**
     * AmoCRM library object
     *
     * @var Amocrm $amocrm
     *
     * @since 1.3.0
     */
    private Amocrm $amocrm;

    /**
     * Add Amocrm class and fill fields mapping
     *
     * @param  $subject
     * @param  $config
     *
     * @since  1.3.0
     */
    public function __construct($subject, $config)
    {
        parent::__construct($subject, $config);
        $this->amocrm = new Amocrm();
        $this->fillJoomlaToAmoFieldsMapping();
    }

    /**
     * Fill Joomla to AmoCRM fields mapping
     *
     * @return  void
     *
     * @since   1.3.0
     */
    private function fillJoomlaToAmoFieldsMapping(): void
    {
        $fields_mapping = (new Registry($this->params->get('fields_mapping', [])))->toArray();

        if (empty($fields_mapping)) {
            return;
        }

        foreach ($fields_mapping as $row) {
            $amocrm_contact_field_id = $row['amocrm_contact_field_id'];
            unset($row['amocrm_contact_field_id']);
            self::$mapping[$amocrm_contact_field_id] = $row;
        }
    }

    /**
     * Returns an array of events this subscriber will listen to.
     *
     * @return  array
     *
     * @since   1.3.0
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onUserAfterSave' => 'onUserAfterSave',
            'onUserAfterDelete' => 'onUserAfterDelete',
            'onAmocrmIncomingWebhook' => 'onAmocrmIncomingWebhook',
            'onContentPrepareForm' => 'onContentPrepareForm',
            'onContentPrepareData' => 'onContentPrepareData',
        ];
    }

    /**
     * On saving user data.
     * This method can be fired on:
     * - incoming webhook if enabled
     * - CLI job
     * - manual editing in Administrator panel
     *
     * Method is called after user data is stored in the database.
     * This method logs who created/edited any user's data
     *
     * @param   Event  $event
     *
     * @return  void
     *
     * @throws  AmocrmClientException
     * @since   1.3.0
     */
    public function onUserAfterSave(Event $event): void
    {
        /**
         * @var   array   $user     Holds the new user data.
         * @var   bool    $isnew    True if a new user is stored.
         * @var   bool    $success  True if user was successfully stored in the database.
         * @var   string  $msg      Message.
         */
        [$user, $isnew, $success, $msg] = array_values($event->getArguments());

        if (!$success) {
            return;
        }

        $amocrm = $this->amocrm;

        $link_to_user = (new Uri(Uri::root()))->setPath('/administrator/index.php');
        $link_to_user->setQuery([
            'option' => 'com_users',
            'view' => 'users',
            'filter[search]' => 'id:' . $user['id'],
        ]);
        $link_to_user = $link_to_user->toString();

        /**
         * Это событие также вызывается:
         * - при создании пользователя
         * - при изменении пользователя в панели администратора
         * - при изменении пользователя на сайте
         * - по вебхуку со стороны AmoCRM
         * - при CLI импорте
         */

        /**
         * - Ищем в объекте пользователя временный флаг, сообщающий нам о создании нового
         * или об ассоциации с уже существующим пользователем.
         * - Создаём ассоциацию Joomla user - AmoCRM contact
         * - удаляем флаг(и)
         */
        if (!empty($user['amocrm_new_user_from_webhook_contact_id'])
            && ($isnew || !empty($user['need_to_link_to_existing_joomla_user']))
        ) {
            // Создаём пользователя из вебхука. Просто добавляем ассоциацию.
            $is_temporary_user = $user['is_temporary_user'] ?? false;
            if ($this->params->get('force_temporary_user_status_when_create', 0) == 1) {
                $is_temporary_user = true;
            }
            AmocrmUserHelper::addJoomlaAmoCRMUserSync(
                $user['id'],
                $user['amocrm_new_user_from_webhook_contact_id'],
                $is_temporary_user
            );

            if($this->params->get('send_notes_to_amocrm', 1) == 1) {
                // Информируем AmoCRM, что всё хорошо
                $note_text = $isnew ? 'PLG_WTAMOCRMUSERSYNC_WEBHOOK_NOTIFY_AMOCRM_NEW_USER_FROM_WEBHOOK_SUCCESSFULLY_CREATED' : 'PLG_WTAMOCRMUSERSYNC_WEBHOOK_NOTIFY_AMOCRM_NEW_USER_FROM_WEBHOOK_SUCCESSFULLY_LINKED';

                $notes = [
                    [
                        'created_by' => 0, // 0 - создал робот
                        'note_type'  => 'service_message',
                        'params'     => [
                            'text'    => Text::sprintf(
                                $note_text,
                                $user['id'],
                                Uri::root()
                            ),
                            'service' => 'WT AmoCRM for Joomla'
                        ]
                    ],
                ];

                $amocrm->notes()->addNotes('contacts', $user['amocrm_new_user_from_webhook_contact_id'], $notes);
            }

            // Link to Joomla user profile
            if (!empty($joomla_profile_link_amo_field_id = (int)$this->params->get('amocrm_contact_joomla_profile_link_field_id', -1))
                && $joomla_profile_link_amo_field_id > 0
            ) {
                $custom_fields_data = [
                    'custom_fields_values' => [
                        [
                            'field_id' => $joomla_profile_link_amo_field_id,
                            'values' => [
                                [
                                    'value' => $link_to_user
                                ]
                            ]
                        ],
                    ]
                ];

                $amocrm->contacts()->editContact($user['amocrm_new_user_from_webhook_contact_id'], $custom_fields_data);
            }

            // Уходим. Больше ничего не нужно. Чистим за собой.
            unset($user['amocrm_new_user_from_webhook_contact_id']);
            if (isset($user['need_to_link_to_existing_joomla_user'])) {
                unset($user['need_to_link_to_existing_joomla_user']);
            }

            return;
        }

        /** @var  $joomla_user_id int Joomla user id */
        $joomla_user_id = ArrayHelper::getValue($user, 'id', 0, 'int');
        /** @var int $amocrm_contact_id ID контакта Amo из поля выбора контакта в модальном окне */
        $amocrm_contact_id = ArrayHelper::getValue($user, 'amocrm_contact_id', 0, 'int');

        /**
         * Пользователь создаётся вручную в панели администратора
         * или же самостоятельно регистрируется на сайте.
         */
        if (!$this->params->get('create_amocrm_contact', false)) {
            /**
             * Ручное изменение привязки AmoCRM контакта у Joomla пользователя в панели администратора
             */
            $updateFlag = $user['amocrm_update_user_from_webhook'] ?? false;
            $dataSyncMode = $user['data_sync_mode'] ?? 0;
            $this->processEditJoomlaAmoCRMUserSync($dataSyncMode, $updateFlag, $joomla_user_id, $amocrm_contact_id);
            return;
        }

        $firstname = $user['name'];
        $lastname = $user['name'];

        if (trim($user['name']) && stripos($user['name'], ' ') !== false) {
            $tmp_name = explode(' ', $user['name']);
            $firstname = $tmp_name[0];
            unset($tmp_name[0]);
            // If name consist of more then 2 parts
            $lastname = implode(' ', $tmp_name);
        }

        $user_data = [
            'name' => $user['name'],
        ];
        if (!empty($firstname)) {
            $user_data['first_name'] = $firstname;
        }
        if (!empty($lastname)) {
            $user_data['last_name'] = $lastname;
        }

        $user_data['custom_fields_values'] = [
            [
                'field_code' => 'EMAIL',
                'values' => [
                    [
                        'enum_code' => 'WORK',
                        'value' => $user['email']
                    ]
                ]
            ]
        ];

        // Link to Joomla user profile
        if (!empty($joomla_profile_link_amo_field_id = (int)$this->params->get('amocrm_contact_joomla_profile_link_field_id', -1))
            && $joomla_profile_link_amo_field_id > 0
        ) {
            $user_data['custom_fields_values'][] = [
                'field_id' => $joomla_profile_link_amo_field_id,
                'values' => [
                    [
                        'value' => $link_to_user
                    ]
                ]
            ];
        }

        // We have a new user. Let's register he in AmoCRM
        if ($isnew) {
            if (!empty($amocrm_contact_tags = $this->params->get('amocrm_contact_tags', []))) {
                $user_data['_embedded']['tags'] = [];
                foreach ($amocrm_contact_tags as $tag_id) {
                    $user_data['_embedded']['tags'][] = [
                        'id' => (int)$tag_id
                    ];
                }
            }

            // заполнение полей AmoCRM контакта значениями полей Joomla пользователя
            $this->fillAmoCRMContactFields($user_data, $joomla_user_id);

            // Если контакт Amo НЕ указан  вручную - создаём контакт
            // и пытаемся получить его ID.
            if (!$amocrm_contact_id) {
                $amocrm_users = $amocrm->contacts()->addContacts([$user_data]);

                if (!property_exists($amocrm_users, 'error_code')) {
                    $amocrm_contact_id = $amocrm_users->_embedded->contacts[0]->id;
                }
            }

            // Если id есть - сохраняем
            if ($amocrm_contact_id) {
                if (AmocrmUserHelper::addJoomlaAmoCRMUserSync($joomla_user_id, $amocrm_contact_id)) {
                    $message = 'PLG_WTAMOCRMUSERSYNC_ONUSERAFTERSAVE_JOOMLA_AMOCRM_USER_SYNC_CREATED';
                    $type = 'success';
                } else {
                    $message = 'PLG_WTAMOCRMUSERSYNC_ONUSERAFTERSAVE_JOOMLA_AMOCRM_USER_SYNC_CREATE_ERROR';
                    $type = 'error';
                }

                if ($this->getApplication()->isClient('administrator')) {
                    $this->getApplication()->enqueueMessage(Text::_($message), $type);
                }
            } else {
                $amocrm->saveToLog(
                    "WT AmoCRM user sync plugin, onUserAfterSave: for Joomla user with id $joomla_user_id haven't created related AmoCRM contact",
                    'ERROR'
                );
            }
        } else {
            /**
             * Ручное изменение привязки AmoCRM контакта у Joomla пользователя в панели администратора
             */
            // проверяем, изменился ли контакт Amo по сравнению с текущим значением из БД
            $contactIdChanged = false;

            if ($finded_amocrm_contact_id = AmocrmUserHelper::checkIsAmoCRMUser($joomla_user_id)) {
                if ($finded_amocrm_contact_id !== $amocrm_contact_id) {
                    // Есть предыдущее id в БД, но он не равен новому => контакт AmoCRM изменился или очищен
                    $contactIdChanged = true;
                }
            } else if ($amocrm_contact_id) {
                // Предыдущего id в БД нет, но есть новый, значит создана привязка => контакт AmoCRM изменился
                $contactIdChanged = true;
            }

            $updateFlag = $user['amocrm_update_user_from_webhook'] ?? false;
            $dataSyncMode = $user['data_sync_mode'] ?? 0;

            $result = $this->processEditJoomlaAmoCRMUserSync($dataSyncMode, $updateFlag, $joomla_user_id, $amocrm_contact_id);

            // Редактируем данные в AmoCRM из Joomla, если нет конфликта перепривязки
            if ($result) {
                if ($amocrm_contact_id) {
                    // если контакт Amo изменился
                    if ($contactIdChanged) {
                        // синхронизация данных между Joomla пользователем и контактом AmoCRM
                        $this->processDataSync($dataSyncMode, $user_data, $joomla_user_id, $amocrm_contact_id);
                    } else {
                        // если контакт Amo не изменился, то просто обновляем его поля из Joomla пользователя
                        $this->fillAmoCRMContactFields($user_data, $joomla_user_id);
                    }
                    // ===== //
                }

                if ($this->params->get('update_amocrm_contact_data_by_joomla', false)) {
                    if ($amocrm_contact_id) {
                        $amocrm->contacts()->editContact($amocrm_contact_id, $user_data);

                        $amocrm->saveToLog(
                            Text::sprintf(
                                'PLG_WTAMOCRMUSERSYNC_ONUSERAFTERSAVE_AMOCRM_CONTACT_HAS_BEEN_UPDATED',
                                $joomla_user_id
                            ),
                            'info'
                        );
                    } else {
                        // We loose AmoCRM user id :((
                        $amocrm->saveToLog(
                            Text::sprintf(
                                'PLG_WTAMOCRMUSERSYNC_ONUSERAFTERSAVE_NO_AMOCRM_CONTACT_ID_FOR_JOOMLA_USER_ID',
                                $joomla_user_id
                            ),
                            'warning'
                        );
                    }
                }
            }
        }
    }

    /**
     * Заполнение полей AmoCRM контакта значениями полей Joomla пользователя
     *
     * @param   array  $user_data       Ссылка на массив данных о AmoCRM контакте
     * @param   int    $joomla_user_id  Joomla user ID
     *
     * @return  void
     *
     * @since   1.3.0
     */
    private function fillAmoCRMContactFields(array &$user_data, int $joomla_user_id): void
    {
        if (!$this->params->get('create_amocrm_contact', false)
            || !$this->params->get('update_amocrm_contact_data_by_joomla', false)
        ) {
            $this->getApplication()->enqueueMessage(Text::_('PLG_WTAMOCRMUSERSYNC_ONUSERAFTERSAVE_FILL_AMOCRM_CONTACT_ERROR'), 'warning');
            return;
        }

        // заполнение полей AmoCRM контакта значениями полей Joomla пользователя

        $joomla_fields = array_column(self::$mapping, 'com_users_custom_field_id');
        $db = $this->getDatabase();
        $query = $db->getQuery()->clear();

        $query->select('*')
            ->from($db->quoteName('#__fields_values'))
            ->where($db->quoteName('field_id') . ' IN(' . implode(',', $joomla_fields) . ')')
            ->where($db->quoteName('item_id') . ' = ' . $db->quote($joomla_user_id));

        $db->setQuery($query);
        $fieldValues = $db->loadAssocList('field_id', 'value');

        foreach (self::$mapping as $amo_field_id => $field) {
            if ($field['joomla_field_type'] !== 'user_custom_field') {
                continue;
            }

            $joomla_field_id = $field['com_users_custom_field_id'];

            if (empty($joomla_field_id)) {
                continue;
            }

            if (!array_key_exists($joomla_field_id, $fieldValues)) {
                continue;
            }

            $user_data['custom_fields_values'][] = [
                'field_id' => $amo_field_id,
                'values' => [
                    [
                        'value' => $fieldValues[$joomla_field_id]
                    ]
                ]
            ];
        }
        $db->disconnect();
    }

    /**
     * Заполнение полей Joomla пользователя или AmoCRM контакта
     *
     * @param   int    $mode               Режим синхронизации пользовательских полей
     * @param   array  $user_data          Ссылка на массив данных о AmoCRM контакте
     * @param   int    $joomla_user_id     Joomla user ID
     * @param   int    $amocrm_contact_id  AmoCRM contact ID
     *
     * @return  void
     *
     * @throws  AmocrmClientException
     * @since   1.3.0
     */
    private function processDataSync(int $mode, array &$user_data, int $joomla_user_id, int $amocrm_contact_id): void
    {
        if ($mode === 1) {
            // заполнение полей AmoCRM контакта значениями полей Joomla пользователя
            $this->fillAmoCRMContactFields($user_data, $joomla_user_id);
        } else if ($mode === 2) {
            if (!$this->params->get('allow_update_user_data', false)) {
                $this->getApplication()->enqueueMessage(Text::_('PLG_WTAMOCRMUSERSYNC_ONUSERAFTERSAVE_FILL_JOOMLA_USER_ERROR'), 'warning');
                return;
            }

            // заполнение полей Joomla пользователя значениями полей AmoCRM контакта

            $contactObj = $this->amocrm->contacts()->getContactById($amocrm_contact_id);

            $customFields = [];
            foreach ($contactObj->custom_fields_values as $contact_field) {
                if (array_key_exists($contact_field->field_id, self::$mapping) && self::$mapping[$contact_field->field_id]['joomla_field_type'] === 'user_custom_field') {
                    $field_id = self::$mapping[$contact_field->field_id]['com_users_custom_field_id'];
                    $field_value = implode(', ', array_column($contact_field->values, 'value'));

                    $customFields[$field_id] = $field_value;
                }
            }

            $this->saveCustomFieldsData($joomla_user_id, $customFields);
        }
    }

    /**
     * Очищение полей Joomla пользователя или AmoCRM контакта
     *
     * @param   int    $mode                  Режим синхронизации пользовательских полей
     * @param   array  $custom_fields_values  Ссылка на массив значений полей AmoCRM контакта
     * @param   int    $joomla_user_id        Joomla user ID
     *
     * @return  void
     *
     * @since   1.3.0
     */
    private function clearDataSync(int $mode, array &$custom_fields_values, int $joomla_user_id): void
    {
        if ($mode === 1) {
            if (!$this->params->get('create_amocrm_contact', false)
                || !$this->params->get('update_amocrm_contact_data_by_joomla', false)
            ) {
                $this->getApplication()->enqueueMessage(Text::_('PLG_WTAMOCRMUSERSYNC_ONUSERAFTERSAVE_CLEAR_AMOCRM_CONTACT_ERROR'), 'warning');
                return;
            }

            // очищение полей контакта AmoCRM

            foreach (self::$mapping as $amo_field_id => $field) {
                $custom_fields_values[] = [
                    'field_id' => $amo_field_id,
                    'values' => [
                        [
                            'value' => ''
                        ]
                    ]
                ];
            }
        } else if ($mode === 2) {
            if (!$this->params->get('allow_update_user_data', false)) {
                $this->getApplication()->enqueueMessage(Text::_('PLG_WTAMOCRMUSERSYNC_ONUSERAFTERSAVE_CLEAR_JOOMLA_USER_ERROR'), 'warning');
                return;
            }

            // очищение полей Joomla пользователя

            $fieldsToClear = [];
            foreach (self::$mapping as $field) {
                if ($field['joomla_field_type'] === 'user_custom_field') {
                    $fieldsToClear[] = $field['com_users_custom_field_id'];
                }
            }

            $db = $this->getDatabase();
            $query = $db->getQuery()->clear();
            $query->delete($db->quoteName('#__fields_values'))
                ->where($db->quoteName('item_id') . ' = ' . $joomla_user_id)
                ->where($db->quoteName('field_id') . ' IN (' . implode(',', $fieldsToClear) . ')');

            $db->setQuery($query);
            $db->execute();
            $db->disconnect();
        }
    }

    /**
     * Обработка ручного изменения привязки AmoCRM контакта у Joomla пользователя в панели администратора
     *
     * @param   int   $mode                             Режим синхронизации пользовательских полей
     * @param   bool  $amocrmUpdateUserFromWebhookFlag  Флаг обновления Joomla пользователя от входящего вебхука AmoCRM
     * @param   int   $joomla_user_id                   Joomla user ID
     * @param   int   $amocrm_contact_id                AmoCRM contact ID
     *
     * @return  bool  True в случае успешной привязки, False в случае ошибки
     *
     * @throws  AmocrmClientException
     * @since   1.3.0
     */
    private function processEditJoomlaAmoCRMUserSync(int $mode, bool $amocrmUpdateUserFromWebhookFlag, int $joomla_user_id, int $amocrm_contact_id): bool
    {
        if (!$this->getApplication()->isClient('administrator')) {
            return false;
        }

        // если контекст - обновление Joomla пользователя из входящего вебхука AmoCRM - ничего не делаем
        if ($amocrmUpdateUserFromWebhookFlag) {
            return false;
        }

        if (!$joomla_user_id) {
            $this->getApplication()->enqueueMessage(Text::_('PLG_WTAMOCRMUSERSYNC_ONUSERAFTERSAVE_NO_JOOMLA_USER_ID'), 'error');
            return false;
        }

        // Если в форме пользователя не выбран вручную контакт AmoCRM
        // - значит привязку хотят удалить
        if (!$amocrm_contact_id) {
            // если очищается привязка контакта AmoCRM, необходимо удалить из ранее привязанного контакта ссылку на профиль Joomla пользователя
            // находим связанный ID контакта AmoCRM
            if ($finded_amocrm_contact_id = AmocrmUserHelper::checkIsAmoCRMUser($joomla_user_id)) {
                $this->clearAmoCRMContactFields($mode, $finded_amocrm_contact_id, $joomla_user_id);
            }
            //

            // если у пользователя существует привязка
            if (AmocrmUserHelper::checkIsAmoCRMUser($joomla_user_id)) {
                // удаляем связь Joomla пользователь - AmoCRM контакт
                if (AmocrmUserHelper::removeJoomlaAmoCRMUserSync([$joomla_user_id])) {
                    $message = 'PLG_WTAMOCRMUSERSYNC_ONUSERAFTERSAVE_JOOMLA_AMOCRM_USER_SYNC_REMOVED';
                    $type = 'success';
                } else {
                    $message = 'PLG_WTAMOCRMUSERSYNC_ONUSERAFTERSAVE_JOOMLA_AMOCRM_USER_SYNC_REMOVE_ERROR';
                    $type = 'error';
                }

                $this->getApplication()->enqueueMessage(Text::_($message), $type);
            }

            return true;
        }

        // Возможно хотят привязать другой контакт Amo.
        // Проверим, занят ли привязываемый AmoCRM контакт другим пользователем
        if ($finded_joomla_user_id = AmocrmUserHelper::checkIsJoomlaUser($amocrm_contact_id)) {
            // Если найденный пользователь не мы, то выводим сообщение об ошибке
            if ($finded_joomla_user_id !== $joomla_user_id) {
                $this->getApplication()->enqueueMessage(Text::sprintf('PLG_WTAMOCRMUSERSYNC_ONUSERAFTERSAVE_JOOMLA_AMOCRM_USER_SYNC_CONFLICT', $finded_joomla_user_id), 'error');

                // дальнейшие операции обновления/добавления невозможны
                return false;
            } else {
                // дальнейшие операции не нужны, т.к. контакт Amo не изменился
                return true;
            }
        }

        // Проверяем, есть ли у данного Joomla пользователя старая ассоциация с AmoCRM контактом
        if ($old_amocrm_contact_id = AmocrmUserHelper::checkIsAmoCRMUser($joomla_user_id)) {
            // т.к. происходит перепривязка, необходимо удалить из старого AmoCRM контакта ссылку на профиль Joomla пользователя
            $this->clearAmoCRMContactFields($mode, $old_amocrm_contact_id, $joomla_user_id);

            // проверяем состояние флага is_temporary_user
            $db = $this->getDatabase();
            $query = $db->getQuery()->clear();
            $query->select($db->quoteName('is_temporary_user'))
                ->from($db->quoteName('#__lib_wt_amocrm_users_sync'))
                ->where($db->quoteName('joomla_user_id') . ' = ' . $db->quote($joomla_user_id));

            $db->setQuery($query);
            $is_temporary_user = (bool) $db->loadResult();

            // Если есть, обновляем ассоциацию
            if (AmocrmUserHelper::updateJoomlaAmoCRMUserSync($joomla_user_id, $amocrm_contact_id, $is_temporary_user)) {
                $message = 'PLG_WTAMOCRMUSERSYNC_ONUSERAFTERSAVE_JOOMLA_AMOCRM_USER_SYNC_UPDATED';
                $type = 'success';
            } else {
                $message = 'PLG_WTAMOCRMUSERSYNC_ONUSERAFTERSAVE_JOOMLA_AMOCRM_USER_SYNC_UPDATE_ERROR';
                $type = 'error';
            }
            $db->disconnect();
        } else {
            // Старой ассоциации нет. В объекте данные есть - создаём новую ассоциацию.
            if (AmocrmUserHelper::addJoomlaAmoCRMUserSync($joomla_user_id, $amocrm_contact_id)) {
                $message = 'PLG_WTAMOCRMUSERSYNC_ONUSERAFTERSAVE_JOOMLA_AMOCRM_USER_SYNC_CREATED';
                $type = 'success';
            } else {
                $message = 'PLG_WTAMOCRMUSERSYNC_ONUSERAFTERSAVE_JOOMLA_AMOCRM_USER_SYNC_CREATE_ERROR';
                $type = 'error';
            }
        }

        $this->getApplication()->enqueueMessage(Text::_($message), $type);

        return true;
    }

    /**
     * Очищение пользовательских полей и поля ссылки на профиль Joomla пользователя
     * у заданного AmoCRM контакта
     *
     * @param   int  $mode               Режим синхронизации пользовательских полей
     * @param   int  $amocrm_contact_id  AmoCRM contact ID
     * @param   int  $joomla_user_id     Joomla user ID
     *
     * @return  void
     *
     * @throws  AmocrmClientException
     * @since   1.3.0
     */
    private function clearAmoCRMContactFields(int $mode, int $amocrm_contact_id, int $joomla_user_id): void
    {
        $custom_fields_values = [];

        // очищение кастомных полей joomla пользователя
        $this->clearDataSync($mode, $custom_fields_values, $joomla_user_id);
        //

        // эти проверки расположены не в начале функции, т.к. метод clearDataSync
        // должен выполниться в любом случае, т.к. существует состояние, при котором
        // ВЫКЛЮЧЕНА опция update_amocrm_contact_data_by_joomla и происходит очищение привязки
        // Joomla user - Amo contact с режимом "очистить поля Joomla пользователя".
        // В таком случае мы даем возможность полям Joomla измениться, однако не трогаем AmoCRM контакт
        if (!$amocrm_contact_id) {
            return;
        }

        if (!$this->params->get('create_amocrm_contact', false)
            || !$this->params->get('update_amocrm_contact_data_by_joomla', false)
        ) {
            $this->getApplication()->enqueueMessage(Text::_('PLG_WTAMOCRMUSERSYNC_ONUSERAFTERSAVE_CLEAR_AMOCRM_CONTACT_JOOMLA_PROFILE_LINK_ERROR'), 'warning');
            return;
        }

        $joomla_profile_link_amo_field_id = (int)$this->params->get('amocrm_contact_joomla_profile_link_field_id', -1);

        if ($joomla_profile_link_amo_field_id > 0) {
            $custom_fields_values[] = [
                'field_id' => $joomla_profile_link_amo_field_id,
                'values' => [
                    [
                        'value' => ''
                    ]
                ]
            ];
        }

        if (empty($custom_fields_values)) {
            return;
        }

        $response = $this->amocrm->contacts()->editContact($amocrm_contact_id, [
            'custom_fields_values' => $custom_fields_values
        ]);

        $this->getApplication()->enqueueMessage(Text::sprintf('PLG_WTAMOCRMUSERSYNC_ONUSERAFTERSAVE_AMOCRM_CONTACT_FIELDS_CLEARED', $amocrm_contact_id));
    }

    /**
     * On deleting user data logging method
     *
     * Method is called after user data is deleted from the database
     *
     * @param   Event  $event
     *
     * @return  void
     *
     * @throws  AmocrmClientException
     * @since   1.3.0
     */
    public function onUserAfterDelete(Event $event): void
    {
        /**
         * @var  array   $user     Holds the user data
         * @var  bool    $success  True if user was successfully stored in the database
         * @var  string  $msg      Message
         */
        [$user, $success, $msg] = array_values($event->getArguments());

        if (!$success) {
            return;
        }

        $joomla_user_id = ArrayHelper::getValue($user, 'id', 0, 'int');

        if (empty($joomla_user_id)) {
            return;
        }

        $amocrm_contact_id = AmocrmUserHelper::checkIsAmoCRMUser($joomla_user_id);

        if (!$amocrm_contact_id) {
            return;
        }

        if (empty($user['amocrm_delete_user_from_webhook']) && $this->params->get('send_notes_to_amocrm', 1) == 1) {
            // Если установлен этот флаг - удаление произошло на стороне AmoCRM.
            // Тогда мы просто молча удаляем, не отправляя уведомление в AmoCRM.
            $amocrm = $this->amocrm;
            $notes  = [
                [
                    'created_by' => 0, // 0 - создал робот
                    'note_type' => 'common',
                    'params' => [
                        'text' => Text::sprintf(
                            'PLG_WTAMOCRMUSERSYNC_JOOMLA_USER_HAS_BEEN_REMOVED',
                            HTMLHelper::date('now', Text::_('DATE_FORMAT_LC5'))
                        )
                    ],
                ]
            ];

            $amocrm->notes()->addNotes('contacts', $amocrm_contact_id, $notes);
        }

        // Remove from Joomla-to-AmoCRM user link in database
        AmocrmUserHelper::removeJoomlaAmoCRMUserSync([$joomla_user_id]);
    }

    /**
     * @param   WebhookEvent  $event
     *
     * @return  void
     *
     * @throws  AmocrmClientException
     * @see     WebhookEvent
     * @since   1.3.0
     */
    public function onAmocrmIncomingWebhook(WebhookEvent $event): void
    {
        if (!$this->params->get('allow_webhook_actions', false)) {
            return;
        }

        /** @var array $contacts Array of contacts from webhook if exists */
        $contacts = $event->getContacts();

        if (empty($contacts)) {
            return;
        }

        if ($this->params->get('allow_create_user', false) && array_key_exists('add', $contacts)) {
            $this->createUsers($contacts['add']);
        }
        if ($this->params->get('allow_update_user_data', false) && array_key_exists('update', $contacts)) {
            $this->updateUsers($contacts['update']);
        }
        if ($this->params->get('allow_delete_user_data', false) && array_key_exists('delete', $contacts)) {
            $this->deleteUsers($contacts['delete']);
        }
        /**
         * -всегда надо проверять amocrm contact id, так как может быть настроен И вебхук И создание юзеров внутри джумлы
         * - создавать пользователей при вебхуке из Амо
         * - обновлять пользователей при вебхуке из амо
         * - удалять пользователей при вебхуке из амо
         */
    }

    /**
     * Вызывается при вебхуке на создании пользователя
     * на стороне AmoCRM
     * ИЛИ
     * при CLI-импорте из плагина группы Console
     *
     * @param   array  $contacts  AmoCRM contacts data from webhook
     *
     * @return  void
     *
     * @throws  AmocrmClientException
     * @since   1.3.0
     */
    public function createUsers(array $contacts): void
    {
        if (empty($contacts)) {
            return;
        }

        $amocrm = $this->amocrm;
        $contacts = $this->preprocessAmoData('createUsers', $contacts);
        foreach ($contacts as $contact) {
            if ($contact['type'] !== 'contact') {
                continue;
            }

            /** @var int|bool $joomla_user_id Joomla user id or false */
            $joomla_user_id = AmocrmUserHelper::checkIsJoomlaUser($contact['id']);

            /**
             * We try to create a NEW user in Joomla.
             * If we already have an association - skip following code
             */
            if ($joomla_user_id) {
                continue;
            }
            /** @var bool $isNew Is new Joomla user */
            $isNew = true;

            $user_data = [
                'name' => OutputFilter::stringUrlSafe($contact['name']),
                'groups' => [$this->params->get('default_user_group', 2)],
                'amocrm_new_user_from_webhook_contact_id' => $contact['id']
                // Для добавления ассоциации на триггере onUserAfterSave
            ];

            /** @var bool $temp_email Flag we haven't a real email for this contact */
            $temp_email = true;

            if (!empty($contact['custom_fields'])) {

                $contact_emails = [];
                foreach ($contact['custom_fields'] as $custom_field) {
                    if ($custom_field['code'] == 'EMAIL') {
                        $contact_emails = array_column($custom_field['values'], 'value');
                        if (!empty($contact_emails)) {
                            try {
                                $user_data['email'] = PunycodeHelper::emailToPunycode($contact_emails[0]);
                                $temp_email = false;
                            } catch (Exception $e) {
                                $error_contact_email_message = 'createUsers: Error with email for AmoCRM contact id '.$contact['id'].', email: '.$contact_emails[0]
                                    .'. code: '.$e->getCode().' message: '. $e->getMessage().' in '.$e->getFile().': '.$e->getLine();
                                $amocrm->saveToLog($error_contact_email_message,'WARNING','amo_contacts_failed_emails_due_import');
                            }

                        }
                    }
                }

                /**
                 * We have not an association Joomla user <-> AmoCRM contact,
                 * but we would have a Joomla user(s) with one or several emails from contact.
                 *
                 * - Try to find a user(s) with emails from contact.
                 * - If a single Joomla user is found - link to it.
                 * - if contact has several emails - try to find Joomla users by all of them
                 * - If several Joomla users found - log this and skip. Solve this issue by manual
                 */
                $joomla_user_ids = $this->findJoomlaUserByEmail($contact_emails);

                if (count($joomla_user_ids) > 1) {

                    array_walk($joomla_user_ids, function(&$value, $key) {
                        $value = 'Joomla user id: '. $value['id'].' (email: '.$value['email'].')';
                    });

                    $note_text = Text::sprintf(
                        'PLG_WTAMOCRMUSERSYNC_WEBHOOK_NOTIFY_AMOCRM_NEW_USER_FROM_WEBHOOK_DUPLICATES_FOUND',
                        Uri::root(),
                        implode(', ', $joomla_user_ids)
                    );

                    // пишем лог
                    $amocrm->saveToLog($note_text,'WARNING');
                    // Пишем в отдельный файл логов дублей
                    $amocrm->saveToLog($note_text,'NOTICE', 'amocrm_to_joomla_contacts_doubles');
                    if($this->params->get('send_notes_to_amocrm', 1) == 1) {
                        // пишем уведомление в контакт, что найдено несколько
                        // юзеров Joomla с емейлами этого контакта.
                        $notes = [
                            [
                                'created_by' => 0, // 0 - создал робот
                                'note_type'  => 'common',
                                'params'     => [
                                    'text' => $note_text,
                                ]
                            ],
                        ];

                        $amocrm->notes()->addNotes('contacts', $contact['id'], $notes);
                    }
                    // Пропускаем этот контакт
                    continue;
                } elseif (count($joomla_user_ids) == 1) {
                    // Найден один не ассоциированный пользователь.
                    // 2 сценария:
                    // 1. у юзера нет ассоциации вообще.
                    // 2. данный e-mail оказался в нескольких контактах на стороне Amo.
                    // Если не обнаружена ассоциация для другого юзера Joomla - связываем их по e-mail
                    $exists_amocrm_contact_id = AmocrmUserHelper::checkIsAmoCRMUser($joomla_user_ids[0]['id']);
                    if (!$exists_amocrm_contact_id) {
                        $user_data['id'] = $joomla_user_ids[0]['id'];
                        $user_data['need_to_link_to_existing_joomla_user'] = true;
                        // Для добавления ассоциации к существующему пользователю
                        // на триггере onUserAfterSave
                        $isNew = false;
                    } else {
                        // Данный e-mail уже был в другом контакте AmoCRM
                        // и ассоциация по нему уже раньше была выполнена.
                        // Информируем.

                        $note_text = Text::sprintf(
                            'PLG_WTAMOCRMUSERSYNC_WEBHOOK_NOTIFY_AMOCRM_NEW_USER_FROM_WEBHOOK_SINGLE_DUPLICATE_PREVIOUS_ASSOCIATION_FOUND',
                            Uri::root(),
                            $joomla_user_ids[0]['email'],
                            $contact['id'],
                            $joomla_user_ids[0]['id'],
                            $exists_amocrm_contact_id
                        );

                        // пишем лог
                        $amocrm->saveToLog($note_text,'WARNING');
                        // Пишем в отдельный файл логов дублей
                        $amocrm->saveToLog($note_text,'NOTICE', 'amocrm_to_joomla_contacts_doubles');
                        if($this->params->get('send_notes_to_amocrm', 1) == 1) {
                            // пишем уведомление в контакт, что найдено несколько
                            // юзеров Joomla с емейлами этого контакта.
                            $notes = [
                                [
                                    'created_by' => 0, // 0 - создал робот
                                    'note_type' => 'common',
                                    'params' => [
                                        'text' => $note_text,
                                    ]
                                ],
                            ];

                            $amocrm->notes()->addNotes('contacts', $contact['id'], $notes);
                        }
                        // пропускаем этот контакт
                        continue;
                    }
                }

                $this->preprocessUserParams($contact, $user_data);
            }

            /**
             * Если есть емейл - проблем нет.
             * Если емейла нет - создаём фейковые логин и емейл
             * ставим пользователю флаг, что у него фейковые данные
             * Далее отдельным плагином нужно обрабатывать ДО-заполнение данных
             */
            if ($temp_email) {
                $host = (new Uri(Uri::root()))->getHost();
                $temporary_user_email = 'change-this-fake-email-amocrm-' . $contact['id'] . '@' . $host;
                $user_data['email'] = $temporary_user_email;
                $user_data['username'] = $temporary_user_email;
                $user_data['is_temporary_user'] = true;
            } else {
                $user_data['username'] = $user_data['email'];
            }

            $user_data['block'] = $this->params->get('auto_enable_new_user', 0) ? 0 : 1;
            $comUsersParams = ComponentHelper::getParams('com_users');
            $useractivation = $comUsersParams->get('useractivation');
            if ($this->params->get('notify_new_user', 0) == 1) {
                // Check if the user needs to activate their account.
                if (($useractivation == 1) || ($useractivation == 2)) {
                    $user_data['activation'] = ApplicationHelper::getHash(UserHelper::genRandomPassword());
                    $user_data['block'] = 1;
                }
            }

            /** @var bool|User $savedUser false or successfully saved user object */
            $savedUser = $this->saveUser($user_data, $isNew);

            if (!$savedUser) {
                $amocrm->saveToLog(
                    'Error create Joomla user for AmoCRM contact id:' . $contact['id'] . ', user data: ' . print_r(
                        $user_data,
                        true
                    ),
                    'error'
                );

                continue;
            }

            $this->processUserCustomFields($savedUser->id, $contact, $user_data);

            if (!$temp_email && $this->params->get('notify_new_user', 0) == 1) {
                // отправляем уведомления пользователю о создании аккаунта
                // с учётом параметров com_users.
                $this->userNotify($savedUser, $comUsersParams);
            }
        }
    }

    /**
     * Save Joomla user data. Fired on incoming AmoCRM webhooks
     *
     * @param   array  $user_data  User data array
     * @param   bool   $isNew      Create new user (true) or update existing one (false)?
     *
     * @return  bool|User  False or saved $user object
     *
     * @since   1.3.0
     */
    private function saveUser(array $user_data = [], bool $isNew = false): bool|User
    {
        if (empty($user_data)) {
            return false;
        }

        if ($isNew) {
            $user = new User();
        } else {
            $user = $this->getUserFactory()->loadUserById($user_data['id']);
            $user_params = new Registry($user->params);
            $user_params->merge(new Registry($user_data['params']), true);
            $user_data['params'] = $user_params->toArray();
        }

        // Bind the data.
        if (!$user->bind($user_data)) {
            return false;
        }

        // Store the data.
        if (!$user->save()) {
            return false;
        }

        return $user;
    }

    /**
     * @param   User      $user            Created user object
     * @param   Registry  $comUsersParams  Params of the `com_users` component
     *
     * @return  bool  True on success
     *
     * @since   1.3.0
     */
    private function userNotify(User $user, Registry $comUsersParams): bool
    {
        $app = $this->getApplication();
        $db = $this->getDatabase();
        $app->getLanguage()->load('com_users');
        $query = $db->getQuery()->clear();
        $useractivation = $comUsersParams->get('useractivation');
        $sendpassword = $comUsersParams->get('sendpassword', 1);

        // Compile the notification mail values.
        $data = get_object_vars($user);
        $data['fromname'] = $app->get('fromname');
        $data['mailfrom'] = $app->get('mailfrom');
        $data['sitename'] = $app->get('sitename');
        $data['siteurl'] = Uri::root();

        $mailtemplate = 'com_users.registration.user.registration_mail';

        // Handle account activation/confirmation emails.
        if ($useractivation == 2) {
            // Set the link to confirm the user email.
            $linkMode = $app->get('force_ssl', 0) == 2 ? Route::TLS_FORCE : Route::TLS_IGNORE;

            $data['activate'] = Route::link(
                'site',
                'index.php?option=com_users&task=registration.activate&token=' . $data['activation'],
                false,
                $linkMode,
                true
            );

            $mailtemplate = 'com_users.registration.user.admin_activation';
        } elseif ($useractivation == 1) {
            // Set the link to activate the user account.
            $linkMode = $app->get('force_ssl', 0) == 2 ? Route::TLS_FORCE : Route::TLS_IGNORE;

            $data['activate'] = Route::link(
                'site',
                'index.php?option=com_users&task=registration.activate&token=' . $data['activation'],
                false,
                $linkMode,
                true
            );

            $mailtemplate = 'com_users.registration.user.self_activation';
        }

        if ($sendpassword) {
            $mailtemplate .= '_w_pw';
        }

        // Try to send the registration email.
        try {
            $mailer = new MailTemplate($mailtemplate, $app->getLanguage()->getTag());
            $mailer->addTemplateData($data);
            $mailer->addRecipient($data['email']);
            $mailer->addUnsafeTags(['username', 'password_clear', 'name']);
            $return = $mailer->send();
        } catch (Exception $exception) {
            try {
                Log::add(Text::_($exception->getMessage()), Log::WARNING, 'jerror');
                $this->amocrm->saveToLog(Text::_($exception->getMessage()), 'error');
                $return = false;
            } catch (RuntimeException $exception) {
                $app->enqueueMessage(Text::_($exception->errorMessage()), 'warning');

                $this->amocrm->saveToLog(Text::_('COM_MESSAGES_ERROR_MAIL_FAILED'), 'warning');

                $return = false;
            }
        }

        // Send mail to all users with user creating permissions and receiving system emails
        if (($useractivation < 2) && ($comUsersParams->get('mail_to_admin') == 1)) {
            // Get all admin users
            $query->clear()
                ->select($db->quoteName(['name', 'email', 'sendEmail', 'id']))
                ->from($db->quoteName('#__users'))
                ->where($db->quoteName('sendEmail') . ' = 1')
                ->where($db->quoteName('block') . ' = 0');

            $db->setQuery($query);

            try {
                $rows = $db->loadObjectList();
            } catch (RuntimeException $e) {
                $this->amocrm->saveToLog(Text::sprintf('COM_USERS_DATABASE_ERROR', $e->getMessage()), 'error');
                $db->disconnect();
                return false;
            }

            // Send mail to all superadministrators id
            foreach ($rows as $row) {
                $usercreator = $this->getUserFactory()->loadUserById($row->id);

                if (!$usercreator->authorise('core.create', 'com_users')
                    || !$usercreator->authorise('core.manage', 'com_users')
                ) {
                    continue;
                }

                try {
                    $mailer = new MailTemplate(
                        'com_users.registration.admin.new_notification',
                        $app->getLanguage()->getTag()
                    );
                    $mailer->addTemplateData($data);
                    $mailer->addRecipient($row->email);
                    $mailer->addUnsafeTags(['username', 'name']);
                    $return = $mailer->send();
                } catch (Exception $exception) {
                    try {
                        Log::add(Text::_($exception->getMessage()), Log::WARNING, 'jerror');

                        $return = false;
                    } catch (RuntimeException $exception) {
                        $app->enqueueMessage(Text::_($exception->errorMessage()), 'warning');

                        $return = false;
                    }
                }

                // Check for an error.
                if ($return !== true) {
                    $this->amocrm->saveToLog(
                        Text::_('COM_USERS_REGISTRATION_ACTIVATION_NOTIFY_SEND_MAIL_FAILED'),
                        'warning'
                    );
                    $db->disconnect();
                    return false;
                }
            }
        }

        // Check for an error.
        if ($return !== true) {
            $this->amocrm->saveToLog(Text::_('COM_USERS_REGISTRATION_SEND_MAIL_FAILED'), 'error');

            // Send a system message to administrators receiving system mails
            $query->clear()
                ->select($db->quoteName('id'))
                ->from($db->quoteName('#__users'))
                ->where($db->quoteName('block') . ' = 0')
                ->where($db->quoteName('sendEmail') . ' = 1');
            $db->setQuery($query);

            try {
                $userids = $db->loadColumn();
            } catch (RuntimeException $e) {
                $this->amocrm->saveToLog(Text::sprintf('COM_USERS_DATABASE_ERROR', $e->getMessage()), 'error');
                $db->disconnect();
                return false;
            }

            if (count($userids) > 0) {
                $jdate = new Date();
                $dateToSql = $jdate->toSql();
                $subject = Text::_('COM_USERS_MAIL_SEND_FAILURE_SUBJECT');
                $message = Text::sprintf('COM_USERS_MAIL_SEND_FAILURE_BODY', $data['username']);

                // Build the query to add the messages
                foreach ($userids as $userid) {
                    $values = [
                        ':user_id_from',
                        ':user_id_to',
                        ':date_time',
                        ':subject',
                        ':message',
                    ];
                    $query->clear()
                        ->insert($db->quoteName('#__messages'))
                        ->columns($db->quoteName(['user_id_from', 'user_id_to', 'date_time', 'subject', 'message']))
                        ->values(implode(',', $values));
                    $query->bind(':user_id_from', $userid, ParameterType::INTEGER)
                        ->bind(':user_id_to', $userid, ParameterType::INTEGER)
                        ->bind(':date_time', $dateToSql)
                        ->bind(':subject', $subject)
                        ->bind(':message', $message);

                    $db->setQuery($query);

                    try {
                        $db->execute();
                    } catch (RuntimeException $e) {
                        $this->amocrm->saveToLog(Text::sprintf('COM_USERS_DATABASE_ERROR', $e->getMessage()), 'error');
                        $db->disconnect();
                        return false;
                    }
                }
            }
            $db->disconnect();
            return false;
        }
        $db->disconnect();
        return $return;
    }

    /**
     * Update Joomla user data from the AmoCRM webhook data
     *
     * @param   array  $contacts  AmoCRM contacts data from webhook
     *
     * @return  void
     *
     * @since   1.3.0
     */
    private function updateUsers(array $contacts): void
    {
        if (empty($contacts)) {
            return;
        }
        $contacts = $this->preprocessAmoData('updateUsers', $contacts);
        foreach ($contacts as $contact) {
            if ($contact['type'] == 'contact'
                && ($joomla_user_id = AmocrmUserHelper::checkIsJoomlaUser($contact['id']))
            ) {
                $user_data = [
                    'id'   => $joomla_user_id,
                    'name' => $contact['name'],
                    'amocrm_update_user_from_webhook' => true
                    // Для триггера onUserAfterSave
                ];

                $this->preprocessUserParams($contact, $user_data);
                $this->saveUser($user_data);
                $this->processUserCustomFields($joomla_user_id, $contact, $user_data);
            }
        }
    }

    /**
     * Fired BEFORE user created or updated
     *
     * @param   array  $contact    AmoCRM contact data from webhook
     * @param   array  $user_data  User data for bind
     *
     * @return  void
     *
     * @since   1.3.0
     */
    private function preprocessUserParams(array $contact, array &$user_data): void
    {
        if (!array_key_exists('custom_fields', $contact)) {
            return;
        }
        $user_params = [];
        foreach ($contact['custom_fields'] as $custom_field) {
            // Update main user email on update webhook
            if ($custom_field['code'] == 'EMAIL'
                && $this->params->get('update_user_email', false)
                && !empty($custom_field['values'][0]['value'])
            ) {
                try {
                    $user_data['email'] = PunycodeHelper::emailToPunycode(
                        $custom_field['values'][0]['value']
                    );
                } catch (Exception $e) {
                    $error_contact_email_message = 'preprocessUserParams: Error with email for AmoCRM contact id '.$contact['id'].', email: '.$custom_field['values'][0]['value']
                        .'. code: '.$e->getCode().' message: '. $e->getMessage().' in '.$e->getFile().': '.$e->getLine();
                    $this->amocrm->saveToLog($error_contact_email_message,'WARNING','amo_contacts_failed_emails_due_import');
                }
            }

            $amo_custom_field_id = $custom_field['id'];

            if (array_key_exists($amo_custom_field_id, self::$mapping) && self::$mapping[$amo_custom_field_id]['joomla_field_type'] == 'user_params') {
                if ($custom_field['code'] == 'SMART_ADDRESS') {
                    $values = array_column($custom_field['values'], 'value');
                    $amo_custom_field_value = implode(', ', $values);
                } else {
                    $amo_custom_field_value = $custom_field['values'][0]['value'];
                }

                if (!empty($param_name = trim(self::$mapping[$amo_custom_field_id]['user_params_param_name']))) {
                    $user_params['amocrm'][$param_name] = $amo_custom_field_value;
                }
            }
        }

        if (!empty($user_params)) {
            $user_data['params'] = $user_params;
        }
    }

    /**
     * Fill AmoCRM contact fields to $user['params']
     * Fired AFTER user created or updated.
     *
     * @param   int    $joomla_user_id  Joomla user ID
     * @param   array  $contact         AmoCRM contact data from webhook
     * @param   array  $user_data
     *
     * @return  void
     *
     * @since   1.3.0
     */
    private function processUserCustomFields(int $joomla_user_id, array $contact, array $user_data): void
    {
        if (!array_key_exists('custom_fields', $contact)) {
            return;
        }
        $user_custom_fields = [];
        foreach ($contact['custom_fields'] as $custom_field) {

            $amo_custom_field_id = $custom_field['id'];

            if (array_key_exists($amo_custom_field_id, self::$mapping) && self::$mapping[$amo_custom_field_id]['joomla_field_type'] == 'user_custom_field') {
                if ($custom_field['code'] == 'SMART_ADDRESS') {
                    $values = array_column($custom_field['values'], 'value');
                    $amo_custom_field_value = implode(', ', $values);
                } else {
                    $amo_custom_field_value = $custom_field['values'][0]['value'];
                }

                if (!empty($field_id = trim(self::$mapping[$amo_custom_field_id]['com_users_custom_field_id']))) {
                    $user_custom_fields[$field_id] = $amo_custom_field_value;
                }
            }
        }

        if (!empty($user_custom_fields)) {
            $this->saveCustomFieldsData($joomla_user_id, $user_custom_fields);
        }
    }

    /**
     * Save the custom fields data for User.
     *
     * Not using the standart way with `$user['com_fields']['field_name'] = $value`
     * because FieldsHelper needs an active user session.
     * But we don't have it here
     *
     * @param   int    $joomla_user_id      Joomla User ID
     * @param   array  $user_custom_fields  User custom fields data array. For example: ['field_id' => 'field_value']
     *
     * @return  void
     *
     * @since   1.3.0
     */
    private function saveCustomFieldsData(int $joomla_user_id, array $user_custom_fields): void
    {
        $db = $this->getDatabase();
        // Delete exists fields first
        $conditions = [
            $db->quoteName('field_id') . ' IN(' . implode(',', $db->quote(array_keys($user_custom_fields))) . ')',
            $db->quoteName('item_id') . ' = ' . $db->quote($joomla_user_id),
        ];

        $query = $db->getQuery()->clear();
        $query->delete($db->quoteName('#__fields_values'))
            ->where($conditions);
        $db->setQuery($query);

        try {
            $db->execute();
        } catch (RuntimeException $e) {
            $this->amocrm->saveToLog(Text::sprintf('COM_USERS_DATABASE_ERROR', $e->getMessage()), 'error');
            return;
        }

        $query->clear();
        $query->insert($db->quoteName('#__fields_values'))
            ->columns([
                $db->quoteName('field_id'),
                $db->quoteName('item_id'),
                $db->quoteName('value'),
            ]);

        foreach ($user_custom_fields as $field_id => $field_value) {
            $query->values(implode(',', [$db->quote($field_id), $db->quote($joomla_user_id), $db->quote($field_value)]));
        }

        $db->setQuery($query);

        try {
            $db->execute();
            $db->disconnect();
        } catch (RuntimeException $e) {
            $this->amocrm->saveToLog(Text::sprintf('COM_USERS_DATABASE_ERROR', $e->getMessage()), 'error');
            return;
        }
    }

    /**
     * Delete Joomla users by AmoCRM incoming webhook
     *
     * @param   array  $contacts  AmoCRM contacts data from webhook
     *
     * @return  void
     *
     * @since   1.3.0
     */
    private function deleteUsers(array $contacts): void
    {
        if (empty($contacts)) {
            return;
        }
        $contacts = $this->preprocessAmoData('deleteUsers', $contacts);
        foreach ($contacts as $contact) {
            if ($contact['type'] == 'contact'
                && ($joomla_user_id = AmocrmUserHelper::checkIsJoomlaUser($contact['id']))
            ) {
                $user = $this->getUserFactory()->loadUserById($joomla_user_id);
                $user->amocrm_delete_user_from_webhook = true;
                $this->getApplication()->logout($joomla_user_id);
                $user->delete();
            }
        }
    }

    /**
     * Add an AmoCRM contact Joomla Form field to user edit view in
     * admin panel.
     *
     * @param   Event  $event
     *
     * @return  void
     *
     * @since   1.3.0
     */
    public function onContentPrepareForm(Event $event): void
    {
        $form = $event->getArgument(0);
        $formName = $form->getName();

        // Проверяем имя формы, чтобы не добавить таб в материалы или ещё куда-нибудь
        if ($formName !== 'com_users.user') {
            return;
        }

        Form::addFormPath(JPATH_SITE . '/plugins/user/wtamocrmusersync/form');
        // amocrm - это имя файла в указанной папке - amocrm.xml
        $form->loadFile('amocrm', false);
        // грузим языковые константы для формы
        $lang = $this->getApplication()->getLanguage();
        $extension = 'lib_webtolk_amocrm';
        $base_dir = JPATH_SITE;
        $lang->load($extension, $base_dir);
    }

    /**
     * Bind 3d party data to user object
     *
     * @param   Event  $event
     *
     * @return  void
     *
     * @since   1.3.0
     */
    public function onContentPrepareData(Event $event): void
    {
        [$context, $data, $form] = array_values($event->getArguments());

        if (!$this->getApplication()->isClient('administrator') || $context !== 'com_users.profile') {
            return;
        }

        $amocrm_contact_id = AmocrmUserHelper::checkIsAmoCRMUser($data->id);

        if ($amocrm_contact_id) {
            $data->amocrm_contact_id = $amocrm_contact_id;
        }

        $event->updateData($data);
    }

    /**
     * Find Joomla users by emails
     *
     * @param   array  $emails  AmoCRM contact emails array
     *
     * @return  array  [ ['id' => 111, 'email' => 'user@email.ru'] ]
     *
     * @since   1.3.0
     */
    private function findJoomlaUserByEmail(array $emails): array
    {
        $users_found = [];

        if (empty($emails)) {
            return $users_found;
        }

        $db = $this->getDatabase();
        $query = $db->getQuery()->clear();
        $query->select('*')
            ->from('#__users')
            ->whereIn('email', $emails, ParameterType::STRING);

        $db->setQuery($query);

        try {
            $users_found = $db->loadAssocList();
        } catch (RuntimeException $e) {
            $this->amocrm->saveToLog(Text::sprintf('COM_USERS_DATABASE_ERROR', $e->getMessage()), 'error');
        }
        $db->disconnect();
        return $users_found;
    }


    /**
     * Preprocess data on create, update and delete users
     *
     * @param   string  $context
     * @param   array   $data
     *
     * @return array
     *
     * @since 1.3.0
     */
    private function preprocessAmoData(string $context, array $data): array
    {
        $dispatcher = new Dispatcher();
        PluginHelper::importPlugin('amocrm', null, true, $dispatcher);
        $event = AbstractEvent::create(
            'preprocessAmocrmWebhookData',
            [
                'subject' => $this,
                'context' => $context,
                'data'    => $data
            ]
        );

        $eventResult = $dispatcher->dispatch($event->getName(), $event);

        return $eventResult->getArgument('result');
    }
}

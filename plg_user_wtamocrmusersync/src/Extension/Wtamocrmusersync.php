<?php
/**
 * @package        WT Amocrm Library
 * @version        1.3.0-alpha2
 * @Author         Sergey Tolkachyov, https://web-tolk.ru
 * @copyright  (c) 2022 - May 2025 Sergey Tolkachyov. All rights reserved.
 * @license        GNU/GPL3 http://www.gnu.org/licenses/gpl-3.0.html
 * @since          1.0.0
 */

namespace Joomla\Plugin\User\Wtamocrmusersync\Extension;

use Joomla\CMS\Form\Form;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\String\PunycodeHelper;
use Joomla\CMS\User\User;
use Joomla\CMS\User\UserFactoryAwareTrait;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Event\Event;
use Joomla\Event\SubscriberInterface;
use Joomla\CMS\Uri\Uri;
use Joomla\Utilities\ArrayHelper;
use Webtolk\Amocrm\Amocrm;
use Webtolk\Amocrm\Event\WebhookEvent;
use Webtolk\Amocrm\Helper\UserHelper as AmocrmUserHelper;

use function defined;

// No direct access
defined('_JEXEC') or die;

class Wtamocrmusersync extends CMSPlugin implements SubscriberInterface
{
    use DatabaseAwareTrait;
    use UserFactoryAwareTrait;

    protected $allowLegacyListeners = false;

    protected $autoloadLanguage = true;

    /**
     * Returns an array of events this subscriber will listen to.
     *
     * @return  array
     *
     * @since   4.0.0
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onUserAfterSave'         => 'onUserAfterSave',
            'onUserAfterDelete'       => 'onUserAfterDelete',
            'onAmocrmIncomingWebhook' => 'onAmocrmIncomingWebhook',
            'onContentPrepareForm'    => 'onContentPrepareForm',
        ];
    }


    /**
     * On saving user data logging method
     *
     * Method is called after user data is stored in the database.
     * This method logs who created/edited any user's data
     *
     * @param   Event  $event
     *
     * @return  void
     *
     * @since   1.3.0
     */
    public function onUserAfterSave(Event $event): void
    {
        /**
         * @var   array  $user    Holds the new user data.
         * @var   bool   $isnew   True if a new user is stored.
         * @var   bool   $success True if user was successfully stored in the database.
         * @var   string $msg     Message.
         */
        [$user, $isnew, $success, $msg] = array_values($event->getArguments());

        if (!$success) {
            return;
        }

        $amocrm = new Amocrm();

        /**
         * ЭТот метод также вызывается при создании пользователя
         * по вебхуку со стороны AmoCRM.
         * - Ищем в объекте пользователя временный флаг, сообщающий нам об этом.
         * - Создаём ассоциацию Joomla user - AmoCRM contact
         * - удаляем флаг
         */
        if ($isnew && !empty($user['amocrm_new_user_from_webhook_contact_id'])) {
            // Создаём пользователя из вебхука. Просто добавляем ассоциацию.
            $is_temporary_user = isset($user['is_temporary_user']) ? $user['is_temporary_user'] : false;
            AmocrmUserHelper::addJoomlaAmoCRMUserSync($user['id'], $user['amocrm_new_user_from_webhook_contact_id'], $is_temporary_user);

            // Информируем AmoCRM, что всё хорошо
            $notes  = [
                [
                    'created_by' => 0, // 0 - создал робот
                    'note_type'  => 'service_message',
                    'params'     => [
                        'text'    => Text::sprintf('PLG_WTAMOCRMUSERSYNC_WEBHOOK_NOTIFY_AMOCRM_NEW_USER_FROM_WEBHOOK_SUCCESSFULLY_CREATED', $user['id'], Uri::root()),
                        'service' => 'WT AmoCRM for Joomla'
                    ]
                ]
            ];

            $amocrm->notes()->addNotes('contacts', $user['amocrm_new_user_from_webhook_contact_id'], $notes);
            // Уходим. Больше ничего не нужно. Чистим за собой.
            unset($user['amocrm_new_user_from_webhook_contact_id']);

            return;
        }

        /**
         * Пользователь создаётся вручную в панели администратора
         * или же самостоятельно регистрируется на сайте.
         *
         */
        if (!$this->params->get('create_amocrm_contact', false)) {
            return;
        }

        /** @var  $joomla_user_id int Joomla user id */
        $joomla_user_id = ArrayHelper::getValue($user, 'id', 0, 'int');

        $firstname = $user['name'];
        $lastname  = $user['name'];

        if (trim($user['name']) && stripos($user['name'], ' ') !== false) {
            $tmp_name  = explode(' ', $user['name']);
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
                'values'     => [
                    [
                        'enum_code' => 'WORK',
                        'value'     => $user['email']
                    ]
                ]
            ],
        ];

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
            $amocrm_users = $amocrm->contacts()->addContacts([$user_data]);
            if (!property_exists($amocrm_users, 'error_code')) {
                $amocrm_user_id = $amocrm_users->_embedded->contacts[0]->id;
                // Save relations
                AmocrmUserHelper::addJoomlaAmoCRMUserSync($joomla_user_id, $amocrm_user_id);
            } else {
                $amocrm->saveToLog(
                    "WT AmoCRM user sync plugin, onUserAfterSave: for Joomla user with id $joomla_user_id haven't created related AmoCRM contact",
                    'ERROR'
                );
            }
        } elseif ($this->params->get('update_amocrm_contact_data_by_joomla', false)) {
            // Have we AmoCRM user id for this Joomla user? False or (int) AmoCRM user id.
            $amocrm_user_id = AmocrmUserHelper::checkIsAmoCRMUser($joomla_user_id);

            if ($amocrm_user_id) {
                $result = $amocrm->contacts()->editContact($amocrm_user_id, $user_data);

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

    /**
     * On deleting user data logging method
     *
     * Method is called after user data is deleted from the database
     *
     * @param   Event  $event
     *
     * @return  void
     *
     * @since   1.3.0
     */
    public function onUserAfterDelete($event): void
    {
        /**
         * @var   array  $user    Holds the user data
         * @var   bool   $success True if user was successfully stored in the database
         * @var   string $msg     Message
         */
        [$user, $success, $msg] = array_values($event->getArguments());

        if (!$success) {
            return;
        }

        $joomla_user_id = ArrayHelper::getValue($user, 'id', 0, 'int');

        if (!empty($joomla_user_id)) {
            $amocrm_contact_id = AmocrmUserHelper::checkIsAmoCRMUser($joomla_user_id);

            if ($amocrm_contact_id) {

                if(empty($user['amocrm_delete_user_from_webhook'])) {
                    // Если установлен этот флаг - удаление произошло на стороне AmoCRM.
                    // Тогда мы просто молча удаляем, не отправляя уведомление в AmoCRM.
                    $amocrm = new Amocrm();
                    $notes  = [
                        [
                            'created_by' => 0, // 0 - создал робот
                            'note_type'  => 'common',
                            'params'     => [
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
        }
    }

    /**
     * @param   WebhookEvent  $event
     *
     *
     * @since 1.3.0
     * @see   WebhookEvent
     */
    public function onAmocrmIncomingWebhook($event): void
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
     * на стороне AmoCRM.
     *
     * @param   array  $contacts
     *
     *
     * @since 1.3.0
     */
    private function createUsers(array $contacts)
    {
        if (!empty($contacts && is_array($contacts))) {
            $amocrm = new Amocrm();

            foreach ($contacts as $contact) {
                if ($contact['type'] == 'contact') {
                    /** @var int|bool $joomla_user_id Joomla user id or false */
                    $joomla_user_id = AmocrmUserHelper::checkIsJoomlaUser($contact['id']);

                    /**
                     * We try to create a NEW user in Joomla.
                     * If we already have an association - skip following code
                     */
                    if ($joomla_user_id) {
                        continue;
                    }

                    $user_data = [
                        'name'                                    => $contact['name'],
                        'groups'                                  => [$this->params->get('default_user_group', 2)],
                        'amocrm_new_user_from_webhook_contact_id' => $contact['id']
                        // Для добавления ассоциации на триггере onUserAfterSave
                    ];
                    $email     = '';
                    /** @var bool $temp_email Flag we haven't a real email for this contact */
                    $temp_email = true;

                    if (!empty($contact['custom_fields'])) {
                        foreach ($contact['custom_fields'] as $custom_field) {
                            if ($custom_field['code'] == 'EMAIL' && !empty($custom_field['values'][0]['value'])) {
                                $user_data['email'] = PunycodeHelper::emailToPunycode(
                                    $custom_field['values'][0]['value']
                                );
                                $temp_email         = false;
                            }
//                      $user_data['com_fields']['field_name'] = 'new value';
                        }
                    }

                    /**
                     * Если есть емейл - проблем нет.
                     * Если емейла нет - создаём фейковые логин и емейл
                     * ставим пользователю флаг, что у него фейковые данные
                     * Далее отдельным плагином нужно обрабатывать ДО-заполнение данных
                     */

                    if ($temp_email) {
                        $host = (new Uri(Uri::root()))->getHost();
                        $user_data['email']    = 'change-this-fake-email-amocrm-' . $contact['id'] . '@' . $host;
                        $user_data['username'] = 'change-this-fake-login-amocrm-' . $contact['id'];
                        $user_data['is_temporary_user'] = true;
                    } else {
                        $user_data['username'] = $user_data['email'];
                    }
                    // $user_data['params']; // user params json

                    $user_data['block'] = $this->params->get('auto_enable_new_user', 0) ? 0 : 1;
                    // Check if the user needs to activate their account.
//                    if (($useractivation == 1) || ($useractivation == 2)) {
//                        $user_data['activation'] = ApplicationHelper::getHash(UserHelper::genRandomPassword());
//                        $user_data['block']      = 1;
//                    }

                    /** @var bool $isSaved User successfully saved or not */
                    $isSaved = $this->saveUser($user_data, true);

                    if (!$temp_email) {
                        // отправляем уведомления пользователю о создании аккаунта
                    }
                }
            }
        }
    }

    /**
     * Save Joomla user data. Fired on incoming AmoCRM webhooks
     *
     * @param   array  $user_data
     * @param   bool   $isNew  Create new user (true) or update existing one (false)?
     *
     *
     * @since 1.3.0
     */
    private function saveUser(array $user_data = [], bool $isNew = false): bool
    {
        if (empty($user_data)) {
            return false;
        }
        if ($isNew) {
            $user = new User();
        } else {
            $user = $this->getUserFactory()->loadUserById($user_data['id']);
        }
        // Bind the data.
        if (!$user->bind($user_data)) {
            return false;
        }

        // Store the data.
        if (!$user->save()) {

            return false;
        }

        return true;
    }

    /**
     * Update Joomla user data from the AmoCRM webhook data
     *
     * @param   array  $contacts
     *
     *
     * @since 1.3.0
     */
    private function updateUsers(array $contacts)
    {
        if (!empty($contacts && is_array($contacts))) {
            foreach ($contacts as $contact) {
                if ($contact['type'] == 'contact' && ($joomla_user_id = AmocrmUserHelper::checkIsJoomlaUser(
                        $contact['id']
                    ))) {
                    $user_data = [
                        'id'   => $joomla_user_id,
                        'name' => $contact['name']
                    ];
                    foreach ($contact['custom_fields'] as $custom_field) {
                        if ($custom_field['code'] == 'EMAIL' &&
                            $this->params->get('update_user_email', false) &&
                            !empty($custom_field['values'][0]['value'])) {
                            $user_data['email'] = trim($custom_field['values'][0]['value']);
                        }
//                        $user_data['com_fields']['field_name'] = 'new value';
                    }
                    $this->saveUser($user_data);
                }
            }
        }
    }

    /**
     * Delete Joomla users by AmoCRM incoming webhook
     *
     * @param   array  $contacts
     *
     *
     * @since 1.3.0
     */
    private function deleteUsers(array $contacts)
    {
        if (!empty($contacts && is_array($contacts))) {
            foreach ($contacts as $contact) {
                if ($contact['type'] == 'contact' && ($joomla_user_id = AmocrmUserHelper::checkIsJoomlaUser(
                        $contact['id']
                    ))) {
                    $user = $this->getUserFactory()->loadUserById($joomla_user_id);
                    $user->amocrm_delete_user_from_webhook = true;
                    $user->delete();
                    $this->getApplication()->logout($joomla_user_id);
                }
            }
        }
    }

    /**
     * Add a
     *
     * @param   Event  $event
     *
     *
     * @since 1.3.0
     */
    public function onContentPrepareForm(Event $event): void
    {
        $form     = $event->getArgument(0);
        $formName = $form->getName();

        // Проверяем имя формы, чтобы не добавить таб в материалы или ещё куда-нибудь
        if ($formName === 'com_users.user') {
            Form::addFormPath(JPATH_SITE . '/plugins/user/wtamocrmusersync/form');
            // amocrm - это имя файла в указанной папке - amocrm.xml
            $form->loadFile('amocrm', false);
            // грузим языковые константы для формы
            $lang      = $this->getApplication()->getLanguage();
            $extension = 'lib_webtolk_amocrm';
            $base_dir  = JPATH_SITE;
            $lang->load($extension, $base_dir);
        }
    }
}

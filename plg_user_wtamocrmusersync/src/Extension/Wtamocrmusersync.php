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

use Exception;
use Joomla\CMS\Application\ApplicationHelper;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Date\Date;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Mail\MailTemplate;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Router\Route;
use Joomla\CMS\String\PunycodeHelper;
use Joomla\CMS\User\User;
use Joomla\CMS\User\UserFactoryAwareTrait;
use Joomla\CMS\User\UserHelper;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Database\ParameterType;
use Joomla\Event\Event;
use Joomla\Event\SubscriberInterface;
use Joomla\CMS\Uri\Uri;
use Joomla\Registry\Registry;
use Joomla\Utilities\ArrayHelper;
use RuntimeException;
use Webtolk\Amocrm\Amocrm;
use Webtolk\Amocrm\Event\WebhookEvent;
use Webtolk\Amocrm\Helper\UserHelper as AmocrmUserHelper;

use function count;
use function defined;

// No direct access
defined('_JEXEC') or die;

class Wtamocrmusersync extends CMSPlugin implements SubscriberInterface
{
    use DatabaseAwareTrait;
    use UserFactoryAwareTrait;

    /**
     * AmoCRM to Joomla fields mapping.
     * $mapping[$amocrm_contact_id] = ['type'=> '', 'custom_field_id','user_param_name'];
     *
     * @var array
     * @since 1.3.0
     */
    private static array $mapping = [];
    protected $autoloadLanguage = true;

    /**
     * AmoCRM library object
     *
     * @var Amocrm
     * @since 1.3.0
     */
    private Amocrm $amocrm;

    /**
     * Add Amocrm class and fill fields mapping
     *
     * @param $subject
     * @param $config
     *
     * @since 1.3.0
     */
    public function __construct($subject, $config)
    {
        parent::__construct($subject, $config);
        $this->amocrm = new Amocrm();
        $this->fillJoomlaToAmoFieldsMapping();
    }

    /**
     *
     * @return array
     *
     * @since 1.3.0
     */
    private function fillJoomlaToAmoFieldsMapping()
    {
        $fields_mapping = (new Registry($this->params->get('fields_mapping', [])))->toArray();

        if (!empty($fields_mapping)) {
            foreach ($fields_mapping as $row) {
                $amocrm_contact_field_id = $row['amocrm_contact_field_id'];
                unset($row['amocrm_contact_field_id']);
                self::$mapping[$amocrm_contact_field_id] = $row;
            }
        }
    }

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

        $amocrm = $this->amocrm;

        /**
         * Этот метод также вызывается при создании пользователя
         * по вебхуку со стороны AmoCRM.
         * - Ищем в объекте пользователя временный флаг, сообщающий нам об этом.
         * - Создаём ассоциацию Joomla user - AmoCRM contact
         * - удаляем флаг
         */
        if ($isnew && !empty($user['amocrm_new_user_from_webhook_contact_id'])) {
            // Создаём пользователя из вебхука. Просто добавляем ассоциацию.
            $is_temporary_user = isset($user['is_temporary_user']) ? $user['is_temporary_user'] : false;
            AmocrmUserHelper::addJoomlaAmoCRMUserSync(
                $user['id'],
                $user['amocrm_new_user_from_webhook_contact_id'],
                $is_temporary_user
            );

            // Информируем AmoCRM, что всё хорошо
            $notes = [
                [
                    'created_by' => 0, // 0 - создал робот
                    'note_type'  => 'service_message',
                    'params'     => [
                        'text'    => Text::sprintf(
                            'PLG_WTAMOCRMUSERSYNC_WEBHOOK_NOTIFY_AMOCRM_NEW_USER_FROM_WEBHOOK_SUCCESSFULLY_CREATED',
                            $user['id'],
                            Uri::root()
                        ),
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
                if (empty($user['amocrm_delete_user_from_webhook'])) {
                    // Если установлен этот флаг - удаление произошло на стороне AmoCRM.
                    // Тогда мы просто молча удаляем, не отправляя уведомление в AmoCRM.
                    $amocrm = $this->amocrm;
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
            $amocrm = $this->amocrm;

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
                        $host                           = (new Uri(Uri::root()))->getHost();
                        $user_data['email']             = 'change-this-fake-email-amocrm-' . $contact['id'] . '@' . $host;
                        $user_data['username']          = 'change-this-fake-login-amocrm-' . $contact['id'];
                        $user_data['is_temporary_user'] = true;
                    } else {
                        $user_data['username'] = $user_data['email'];
                    }
                    // $user_data['params']; // user params json

                    $user_data['block'] = $this->params->get('auto_enable_new_user', 0) ? 0 : 1;
                    $comUsersParams     = ComponentHelper::getParams('com_users');
                    $useractivation     = $comUsersParams->get('useractivation');
                    if ($this->params->get('notify_new_user', 0) == 1) {
                        // Check if the user needs to activate their account.
                        if (($useractivation == 1) || ($useractivation == 2)) {
                            $user_data['activation'] = ApplicationHelper::getHash(UserHelper::genRandomPassword());
                            $user_data['block']      = 1;
                        }
                    }

                    /** @var bool|User $savedUser false or successfully saved user object */
                    $savedUser = $this->saveUser($user_data, true);

                    if (!$savedUser) {
                        $amocrm->saveToLog(
                            'Error create Joomla user for AmoCRM contact id:' . $contact['id'] . ', user data: ' . print_r(
                                $user_data,
                                true
                            ),
                            'error'
                        );
                    }

                    if (!$temp_email && $this->params->get('notify_new_user', 0) == 1) {
                        // отправляем уведомления пользователю о создании аккаунта
                        // с учётом параметров com_users.
                        $this->userNotify($savedUser, $comUsersParams);
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
     * @return bool|User false or saved $user object
     *
     * @since 1.3.0
     */
    private function saveUser(array $user_data = [], bool $isNew = false)
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

        return $user;
    }

    /**
     * @param   User      $user            Created user object
     * @param   Registry  $comUsersParams  Params of the `com_users` component
     *
     * @return bool
     *
     * @throws Exception
     * @since 1.3.0
     */
    private function userNotify($user, $comUsersParams): bool
    {
        $app = $this->getApplication();
        $db  = $this->getDatabase();
        $app->getLanguage()->load('com_users');
        $query          = $db->getQuery(true);
        $useractivation = $comUsersParams->get('useractivation');
        $sendpassword   = $comUsersParams->get('sendpassword', 1);

        // Compile the notification mail values.
        $data             = get_object_vars($user);
        $data['fromname'] = $app->get('fromname');
        $data['mailfrom'] = $app->get('mailfrom');
        $data['sitename'] = $app->get('sitename');
        $data['siteurl']  = Uri::root();

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
                Factory::getApplication()->enqueueMessage(Text::_($exception->errorMessage()), 'warning');

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

                return false;
            }

            // Send mail to all superadministrators id
            foreach ($rows as $row) {
                $usercreator = $this->getUserFactory()->loadUserById($row->id);

                if (!$usercreator->authorise('core.create', 'com_users') || !$usercreator->authorise(
                        'core.manage',
                        'com_users'
                    )) {
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
                        Factory::getApplication()->enqueueMessage(Text::_($exception->errorMessage()), 'warning');

                        $return = false;
                    }
                }

                // Check for an error.
                if ($return !== true) {
                    $this->amocrm->saveToLog(
                        Text::_('COM_USERS_REGISTRATION_ACTIVATION_NOTIFY_SEND_MAIL_FAILED'),
                        'warning'
                    );

                    return false;
                }
            }
        }

        // Check for an error.
        if ($return !== true) {
            $this->amocrm->saveToLog(Text::_('COM_USERS_REGISTRATION_SEND_MAIL_FAILED'), 'error');

            // Send a system message to administrators receiving system mails
            $db = $this->getDatabase();
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

                return false;
            }

            if (count($userids) > 0) {
                $jdate     = new Date();
                $dateToSql = $jdate->toSql();
                $subject   = Text::_('COM_USERS_MAIL_SEND_FAILURE_SUBJECT');
                $message   = Text::sprintf('COM_USERS_MAIL_SEND_FAILURE_BODY', $data['username']);

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

                        return false;
                    }
                }
            }

            return false;
        }

        return $return;
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
                    $user_data          = [
                        'id'   => $joomla_user_id,
                        'name' => $contact['name']
                    ];
                    $user_params        = [];
                    $user_custom_fields = [];
                    foreach ($contact['custom_fields'] as $custom_field) {
                        if ($custom_field['code'] == 'EMAIL' &&
                            $this->params->get('update_user_email', false) &&
                            !empty($custom_field['values'][0]['value'])) {
                            $user_data['email'] = trim($custom_field['values'][0]['value']);
                        }
                        $amo_custom_field_id = $custom_field['id'];

                        if (array_key_exists($amo_custom_field_id, self::$mapping)) {
                            if ($custom_field['code'] == 'SMART_ADDRESS') {
                                $values                 = array_column($custom_field['values'], 'value');
                                $amo_custom_field_value = implode(', ', $values);
                            } else {
                                $amo_custom_field_value = $custom_field['values'][0]['value'];
                            }

                            // смотрим тип хранилища на стороне Joomla и сохраняем

                            switch (self::$mapping[$amo_custom_field_id]['joomla_field_type']) {
                                case 'user_custom_field':
                                    if (!empty(
                                    $field_id = trim(
                                        self::$mapping[$amo_custom_field_id]['com_users_custom_field_id']
                                    )
                                    )) {
                                        $user_custom_fields[$field_id] = $amo_custom_field_value;
                                    }
                                    break;
                                case 'user_params':
                                default:
                                    if (!empty(
                                    $param_name = trim(
                                        self::$mapping[$amo_custom_field_id]['user_params_param_name']
                                    )
                                    )) {
                                        $user_params['amocrm'][$param_name] = $amo_custom_field_value;
                                    }
                                    break;
                            }
                        }
                    }

                    if (!empty($user_params)) {
                        $user_data['params'] = $user_params;
                    }

                    $this->saveUser($user_data);
                    if (!empty($user_custom_fields)) {
                        $this->saveCustomFieldsData($joomla_user_id, $user_custom_fields);
                    }
                }
            }
        }
    }

    /**
     * Save the custom fields data for User.
     *
     * Not using the standart way with `$user['com_fields']['field_name'] = $value`
     * because FieldsHelper needs an active user session.
     * But we have not it here
     *
     * @param   int    $joomla_user_id
     * @param   array  $user_custom_fields
     *
     * @return void
     * @since 1.3.0
     */
    private function saveCustomFieldsData($joomla_user_id, array $user_custom_fields): void
    {
        $db = $this->getDatabase();
        // Delete exists fields first
        $conditions = [
            $db->quoteName('field_id') . ' IN(' . implode(',', $db->quote(array_keys($user_custom_fields))) . ')',
            $db->quoteName('item_id') . ' = ' . $db->quote($joomla_user_id),
        ];

        $query = $db->getQuery(true);
        $query->delete($db->quoteName('#__fields_values'))
            ->where($conditions);
        $db->setQuery($query);

        $db->execute();

        $query->clear();
        $query->insert($db->quoteName('#__fields_values'))
            ->columns([
                $db->quoteName('field_id'),
                $db->quoteName('item_id'),
                $db->quoteName('value'),
            ]);

        foreach ($user_custom_fields as $field_id => $field_value) {
            $query->values(implode(',', [$db->quote($field_id), $db->quote($joomla_user_id), $db->quote($field_value)])
            );
        }

        $db->setQuery($query)->execute();
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
                    $user                                  = $this->getUserFactory()->loadUserById($joomla_user_id);
                    $user->amocrm_delete_user_from_webhook = true;
                    $user->delete();
                    $this->getApplication()->logout($joomla_user_id);
                }
            }
        }
    }

    /**
     * Add an AmoCRM contact Joomla Form field to user edit view in
     * admin panel.
     *
     * @param   Event  $event
     *
     * @return void
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

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
use Joomla\CMS\User\UserFactoryAwareTrait;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Event\Event;
use Joomla\Event\SubscriberInterface;
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
            'onContentPrepareForm' => 'onContentPrepareForm',
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

        if (!$this->params->get('create_amocrm_contact', false)) {
            return;
        }

        $amocrm = new Amocrm();

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

    private function createUsers(array $contacts)
    {
    }

    /**
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
     * Save Joomla user data
     *
     * @param   array  $user_data
     *
     *
     * @since 1.3.0
     */
    private function saveUser(array $user_data = []):void
    {
        if (empty($user_data)) {
            return;
        }
        $userModel = $this->getUserFactory()->loadUserById($user_data['id']);
        $userModel->bind($user_data);
        $userModel->save();
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
        if ($formName === 'com_users.user')
        {
            Form::addFormPath(JPATH_SITE . '/plugins/user/wtamocrmusersync/form');
            // fields - это имя файла в указанной папке - fields.xml
            $form->loadFile('amocrm', false);
            // грузим языковые константы для формы
            $lang      = $this->getApplication()->getLanguage();
            $extension = 'lib_webtolk_amocrm';
            $base_dir  = JPATH_SITE;
            $lang->load($extension, $base_dir);
        }
    }
}

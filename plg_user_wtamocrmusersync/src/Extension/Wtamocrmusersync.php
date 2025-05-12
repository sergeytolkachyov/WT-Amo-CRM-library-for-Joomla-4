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

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
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
     */
    public function onAmocrmIncomingWebhook($event)
    {
        file_put_contents(
            JPATH_SITE . '/amocrm_webhook.txt',
            '!!!!! - ' . __METHOD__ . PHP_EOL . print_r($event->getData(), true) . PHP_EOL . PHP_EOL,
            FILE_APPEND
        );
    }
}

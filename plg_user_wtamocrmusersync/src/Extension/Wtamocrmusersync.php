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
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\CMS\User\UserHelper;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Event\Event;
use Joomla\Event\SubscriberInterface;
use Joomla\Utilities\ArrayHelper;
use Webtolk\Amocrm\Amocrm;

use function defined;

// No direct access
defined('_JEXEC') or die;

class Wtamocrmusersync extends CMSPlugin implements SubscriberInterface
{
    use DatabaseAwareTrait;

    protected $allowLegacyListeners = false;

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
            'onUserAfterSave'        => 'onUserAfterSave',
            'onUserAfterDelete'      => 'onUserAfterDelete',
            'onUserBeforeSave'       => 'onUserBeforeSave',
            'onAjaxWtamocrmusersync' => 'onAjaxWtamocrmusersync',
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

        if (trim($user['name']) && str_contains($user['name'], ' ')) {
            $tmp_name  = explode(' ', $user['name']);
            $firstname = $tmp_name[0];
            unset($tmp_name[0]);
            // If name consist of more then 2 parts
            $lastname = implode(' ', $tmp_name);
        }

        // We have a new user. Let's register he in AmoCRM
        if ($isnew) {
            $user_data = [
               'name' => $user['name'],
            ];
            if(!empty($firstname))
            {
                $user_data['first_name'] = $firstname;
            }
            if(!empty($lastname))
            {
                $user_data['last_name'] = $lastname;
            }

            /**
             * @var $amocrm_users                  array An array of AmoCRM users created
             *                                     Array
             *                                     (
             *                                     [0] =>
             *                                     Array
             *                                     (
             *                                     [id] => int
             *                                     [username] => string
             *                                     )
             *                                     )
             */
            $amocrm_users = $amocrm->addContacts([$user_data]);
            if (count($amocrm_users) > 0 && !array_key_exists('error_code', $amocrm_users)) {
                $amocrm_user_id = $amocrm_users[0]['id'];
                // Save relations
                $amocrm->addJoomlaAmoCRMUserSync($joomla_user_id, $amocrm_user_id);
            } else {
                $amocrm->saveToLog(
                    "WT AmoCRM user sync plugin, onUserAfterSave: for Joomla user with id $joomla_user_id haven't created related AmoCRM user",
                    'ERROR'
                );
            }
        } else {
            // Have we AmoCRM user id for this Joomla user? False or (int) AmoCRM user id.
            $amocrm_user_id = $amocrm->checkIsAmoCRMUser($joomla_user_id);

            if ($amocrm_user_id) {
                $user_data = [
                    'users' => [
                        [
                            'id'        => $amocrm_user_id,
                            'username'  => strtolower($user['username']),
                            'firstname' => $firstname,
                            'lastname'  => $lastname,
                            'email'     => $user['email'],
                        ]
                    ]
                ];
                // New password if specified
                if (!empty($user['password_clear'])) {
                    $user_data['password'] = $user['password_clear'];
                }

                $result = $amocrm->request('core_user_update_users', $user_data);
                $amocrm->saveToLog(
                    "WT AmoCRM user sync plugin, onUserAfterSave: AmoCRM user with id $amocrm_user_id has been updated",
                    'info'
                );
            } else {
                // We loose AmoCRM user id :((
                $amocrm->saveToLog(
                    "WT AmoCRM user sync plugin, onUserAfterSave: Joomla user with id $joomla_user_id haven't related AmoCRM user id in database",
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
     * @param $event Event
     *
     * @return  void
     *
     * @since   3.9.0
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
            $amocrm         = new AmoCRM();
            $amocrm_user_id = $amocrm::checkIsAmoCRMUser($joomla_user_id);

            if ($amocrm_user_id) {
                $data = [
                    'userids' => [$amocrm_user_id]
                ];
                // Delete the user in AmoCRM first
                $result = $amocrm->request('core_user_delete_users', $data);

                // AmoCRM returns an empty array if it was a successful request
                if (!array_key_exists('error_code', $result)) {
                    // Remove from Joomla-to-AmoCRM user link in database
                    $amocrm::removeJoomlaAmoCRMUserSync([$joomla_user_id]);

                    $log_message = 'WT AmoCRM user sync plugin, onUserAfterDelete: User id ' . $joomla_user_id . ' has been deleted from AmoCRM too (id ' . $amocrm_user_id . ')';
                    $amocrm::saveToLog($log_message, 'notice');
                    $this->getApplication()->enqueueMessage($log_message, 'notice');
                }
            } else {
                $log_message = 'WT AmoCRM user sync plugin, onUserAfterDelete: User id ' . $joomla_user_id . ' has not related AmoCRM user id';
                $amocrm::saveToLog($log_message, 'notice');
                $this->getApplication()->enqueueMessage($log_message, 'notice');
            }
        }
    }

    /**
     * Method to log user login success action
     *
     * @param $event Event
     *
     * @return  void
     *
     * @since   3.9.0
     */
    public function onUserAfterLogin($event): void
    {
        if (!$this->params->get('use_sso')) {
            // SSO is disabled
            return;
        }

        /**
         * @var  array $options Array holding options (user, responseType)
         * @var  array $subject Array Response object with status variable filled in for last plugin or first successful plugin.
         */
        [$options, $subject] = array_values($event->getArguments());
        $user = $options['user'];
        $data = [
            'username' => strtolower($user->username)
        ];

        $amocrm = new AmoCRM();

        $config = Factory::getContainer()->get('config');

        $curl_options = [
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_HEADER         => 1,
            CURLOPT_SSL_VERIFYPEER => false
        ];

        $amocrm_reposnse = $amocrm->customRequest('/auth/AmoCRM/AmoCRM_login.php', $data, 'POST', $curl_options);

        $cookies = $amocrm_reposnse->getHeader('Set-Cookie');

        foreach ($cookies as $cookie) {
            $parts = explode(';', $cookie);
            $part  = explode('=', $parts[0]);
            $name  = $part[0];
            $value = !empty($part[1]) ? $part[1] : true;

            setcookie($name, $value, 0, $config->get('cookie_path', '/'), $config->get('cookie_domain', ''));
        }
    }

    /**
     * Method to log user login failed action
     *
     * @param   array  $response  Array of response data.
     *
     * @return  void
     *
     * @since   3.9.0
     */
    public function onUserLoginFailure($response): void
    {
    }

    /**
     * Method to log user's logout action
     *
     * @param $event Event
     *
     * @return  void
     *
     * @since   3.9.0
     */
    public function onUserLogout($event): void
    {
        if (!$this->params->get('use_sso')) {
            // SSO is disabled
            return;
        }
        /**
         * @var   array $user    Holds the user data
         * @var   array $options Array holding options (remember, autoregister, group)
         */
        [$user, $options] = array_values($event->getArguments());
    }

    /**
     * Method to log user's logout action
     *
     * @param $event Event
     *
     * @return  void
     *
     * @since   3.9.0
     */
    public function onUserAfterLogout($event): void
    {
        if (!$this->params->get('use_sso')) {
            // SSO is disabled
            return;
        }

        /**
         * @var   array $user    Holds the user data
         * @var   array $options Array holding options (remember, autoregister, group)
         */
        [$user, $options] = array_values($event->getArguments());
        $amocrm = new AmoCRM();

        $data = [
            'username' => strtolower($options['username'])
        ];

        $amocrm->customRequest('/auth/AmoCRM/AmoCRM_logout.php', $data, 'POST');
    }

    /**
     * On after Reset password request
     *
     * Method is called after user request to reset their password.
     *
     * @param $event Event
     *
     * @return  void
     *
     * @since   4.2.9
     */
    public function onUserAfterResetRequest($event): void
    {
        /**
         * @var   array $user Holds the user data.
         */
        $user = $event->getArgument(0);
    }

    /**
     * On after Completed reset request
     *
     * Method is called after user complete the reset of their password.
     *
     * @param   array  $user  Holds the user data.
     *
     * @return  void
     *
     * @since   4.2.9
     */
    public function onUserAfterResetComplete($user)
    {
    }

    public function onAjaxWtAmoCRMusersync($event): void
    {
        $app    = $this->getApplication();
        $token  = $app->getInput()->json->getCmd('token');
        $action = $app->getInput()->getCmd('action');
        $amocrm = new AmoCRM();

        // Check we have income request from true AmoCRM host
        if ($token !== $amocrm::getAmoCRMToken()) {
            $amocrm::saveToLog(
                'There was an attempt to make an external request to the WT AmoCRM user sync plugin with an invalid token',
                ''
            );
            throw new Exception('Wrong token', 403);
        }


        // Check action
        if ($action == 'check_joomla_user_session' && $this->params->get('use_sso')) {
            $username = $app->getInput()->json->get('username', '', 'raw');
            $user     = Factory::getContainer()->get(UserFactoryInterface::class)->loadUserByUsername($username);

            $db             = Factory::getContainer()->get('DatabaseDriver');
            $query          = $db->getQuery(true)
                ->select('username')
                ->from($db->quoteName('#__session'))
                ->where($db->quoteName('userid') . ' = ' . $db->quote($user->id))
                ->where(
                    'time > '
                    . Factory::getDate(
                        '- ' . Factory::getContainer()->get('config')->get('lifetime', 15) . 'minute'
                    )->toUnix()
                )
                ->where($db->quoteName('client_id') . ' = ' . $db->quote('0')) // frontend login
                ->where($db->quoteName('guest') . ' = ' . $db->quote('0'));
            $logged_in_user = $db->setQuery($query)->loadResult();
            $logged_in      = [
                'username'  => $username,
                'logged_in' => false
            ];
            if (!empty($logged_in_user)) {
                $logged_in['logged_in'] = true;
            }

            $event->setArgument('result', $logged_in);
        }
    }
}

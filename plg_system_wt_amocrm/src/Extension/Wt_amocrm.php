<?php
/**
 * @package        WT Amocrm Library
 * @version        1.3.0-alpha2
 * @Author         Sergey Tolkachyov, https://web-tolk.ru
 * @copyright  (c) 2022 - May 2025 Sergey Tolkachyov. All rights reserved.
 * @license        GNU/GPL3 http://www.gnu.org/licenses/gpl-3.0.html
 * @since          1.0.0
 */

namespace Joomla\Plugin\System\Wt_amocrm\Extension;

use JLoader;
use Joomla\CMS\Helper\LibraryHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Session\Session;
use Joomla\Event\DispatcherAwareInterface;
use Joomla\Event\DispatcherAwareTrait;
use Joomla\Event\SubscriberInterface;
use Joomla\Registry\Registry;

use Webtolk\Amocrm\Event\WebhookEvent;

use function defined;

// No direct access
defined('_JEXEC') or die;

class Wt_amocrm extends CMSPlugin implements SubscriberInterface, DispatcherAwareInterface
{
    use DispatcherAwareTrait;

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
            'onAfterInitialise' => 'onAfterInitialise',
            'onAjaxWt_amocrm'   => 'onAjaxWt_amocrm',
        ];
    }

    /**
     * Will be removed. Minimum Joomla version has been rised to 4.2.7
     *
     * @deprecated 1.3.0 Will be removed in 2.0.0
     * @since      1.0.0
     */
    public function onAfterInitialise(): void
    {
        JLoader::registerNamespace('Webtolk\Amocrm', JPATH_LIBRARIES . '/Webtolk/Amocrm/src');
    }


    /**
     * @param $event
     *
     *
     * @since 1.0.0
     */
    public function onAjaxWt_amocrm($event): void
    {
        $app = $this->getApplication();
        /** @var string $token_from_request token from GET request */
        $token_from_request = $app->getInput()->get->get('token', '', 'raw');
        /** @var string $webhook_token Token from plugin params */
        $webhook_token = $this->params->get('webhook_token', '');
        $action        = $app->getInput()->getCmd('action');
        /** @var string $action_type 'internal' (Joomla) or 'external' (outside Joomla) */
        $action_type = $app->getInput()->getCmd('action_type', 'internal');

        $allow_amocrm_webhooks = $this->params->get('allow_amocrm_webhooks', false);

        if ($allow_amocrm_webhooks && // incoming webhooks are enabled
            !empty($token_from_request) && // token is exists in incoming request
            !empty($webhook_token) && // we have a token in plugin params
            $webhook_token == $token_from_request && // check tokens match
            $action_type === 'external'// we have an action param
        ) {
            $action_result_message = $this->handleWebhook($action);
        } elseif (Session::checkToken('GET') && $action_type === 'internal') {
            $action_result_message = $this->callJoomlaInternal($action);
        } else {
            $this->getApplication()->setHeader('status', 403);
            die(Text::_('JINVALID_TOKEN'));
        }

        if (!empty($action_result_message)) {
            $event->setArgument('result', $action_result_message);
        }

        $this->getApplication()->setHeader('status', 200);
    }

    /**
     *
     * AmoCRM webhooks handler
     *
     * @param   string  $action
     *
     * @return mixed
     *
     * @since 1.3.0
     */
    private function handleWebhook(string $action)
    {
        switch ($action) {
            case 'webhook':
            default:

                $remove     = ['option', 'plugin', 'group', 'format', 'action', 'action_type', 'token'];
                $data       = array_diff_key($this->getApplication()->getInput()->getArray(), array_flip($remove));
                $dispatcher = $this->getDispatcher();
                PluginHelper::importPlugin('system', null, true, $dispatcher);
                PluginHelper::importPlugin('user', null, true, $dispatcher);
                PluginHelper::importPlugin('amocrm', null, true, $dispatcher);

                $event = WebhookEvent::create(
                    'onAmocrmIncomingWebhook',
                    [
                        'eventClass' => WebhookEvent::class,
                        'subject'    => (new Registry($data)),
                    ]
                );

                $dispatcher->dispatch($event->getName(), $event);

                break;
        }
    }

    /**
     * Call internal Joomla methods
     *
     * @param   string  $action
     *
     * @return mixed
     *
     * @since 1.3.0
     */
    private function callJoomlaInternal(string $action)
    {
        if (!Session::checkToken('GET')) {
            return Text::_('JINVALID_TOKEN');
        }

        switch ($action) {
            case 'clear_refresh_token': // Clear AmoCRM refresh token from Joomla database
            default:
                $result = $this->clearRefreshToken();
                break;
        }

        return $result;
    }

    /**
     * Clear old refhresh token from database
     *
     * @return string
     *
     * @since 1.3.0
     */
    private function clearRefreshToken(): string
    {
        /**
         * @param $lib_params Registry
         */
        $lib_params = LibraryHelper::getParams('Webtolk/Amocrm');
        $lib_params->set('refresh_token', '');
        $lib_params->set('refresh_token_date', '');
        $action_result_message = 'AmoCRM refresh token has been cleared';

        if (!LibraryHelper::saveParams('Webtolk/Amocrm', $lib_params)) {
            $action_result_message = 'Failed to remove AmoCRM refresh token from database';
        }

        return $action_result_message;
    }
}

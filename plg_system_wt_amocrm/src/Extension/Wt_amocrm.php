<?php
/**
 * @package    WT Amocrm Library
 * @version    1.3.0
 * @Author     Sergey Tolkachyov, https://web-tolk.ru
 * @copyright  (c) 2022 - May 2025 Sergey Tolkachyov. All rights reserved.
 * @license    GNU/GPL3 http://www.gnu.org/licenses/gpl-3.0.html
 * @since      1.0.0
 */

namespace Joomla\Plugin\System\Wt_amocrm\Extension;

use JLoader;
use Joomla\CMS\Factory;
use Joomla\CMS\Helper\LibraryHelper;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Session\Session;
use Joomla\CMS\WebAsset\WebAssetRegistry;
use Joomla\Event\DispatcherAwareInterface;
use Joomla\Event\DispatcherAwareTrait;
use Joomla\Event\SubscriberInterface;
use Joomla\Registry\Registry;
use Webtolk\Amocrm\Amocrm;
use Webtolk\Amocrm\AmocrmClientException;
use Webtolk\Amocrm\Event\WebhookEvent;

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
     * @since   1.0.0
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onAfterInitialise' => 'onAfterInitialise',
            'onAfterInitialiseDocument' => 'addLibraryWebAssets',
            'onAjaxWt_amocrm' => 'onAjaxWt_amocrm'
        ];
    }

    /**
     * Will be removed. Minimum Joomla version has been rised to 4.2.7
     *
     * @deprecated  1.3.0 Will be removed in 2.0.0
     *
     * @since       1.0.0
     */
    public function onAfterInitialise(): void
    {
        JLoader::registerNamespace('Webtolk\Amocrm', JPATH_LIBRARIES . '/Webtolk/Amocrm/src');
    }

    /**
     * AJAX event
     *
     * @param  $event
     *
     * @since  1.0.0
     */
    public function onAjaxWt_amocrm($event): void
    {
        $app = $this->getApplication();

        /** @var string $token_from_request token from GET request */
        $token_from_request = $app->getInput()->get->get('token', '', 'raw');
        /** @var string $webhook_token Token from plugin params */
        $webhook_token = $this->params->get('webhook_token', '');
        $action = $app->getInput()->getString('action','');

        if (empty($action)) {
            return;
        }

        /** @var string $action_type 'internal' (Joomla) or 'external' (outside Joomla) */
        $action_type = $app->getInput()->getString('action_type', 'internal');

        $allow_amocrm_webhooks = $this->params->get('allow_amocrm_webhooks', false);

        if ($action_type === 'internal') {

            $action_result_message = $this->callJoomlaInternal($action);

        } elseif ($action_type === 'external' &&
            $allow_amocrm_webhooks && // incoming webhooks are enabled
            !empty($token_from_request) && // token exists in incoming request
            !empty($webhook_token) && // we have a token in plugin params
            $webhook_token == $token_from_request // check tokens match
        ) {

            $action_result_message = $this->handleWebhook($action);
        }

        if (!empty($action_result_message)) {
            $event->addResult($action_result_message);
        }
    }

    /**
     * Call internal Joomla methods
     *
     * @param   string  $action
     *
     * @return  string
     *
     * @since   1.3.0
     */
    private function callJoomlaInternal(string $action): string
    {
        if (!Session::checkToken('GET')) {
            return Text::_('JINVALID_TOKEN');
        }

        switch ($action) {
            case 'modalselect': // Clear AmoCRM refresh token from Joomla database
                $result = $this->modalSelect();
                break;
            case 'clear_refresh_token': // Clear AmoCRM refresh token from Joomla database
                // no break
            default:
                $result = $this->clearRefreshToken();
                break;
        }

        return $result;
    }

    /**
     * Clear old refresh token from database
     *
     * @return  string
     *
     * @since   1.3.0
     */
    private function clearRefreshToken(): string
    {
        $lib_params = LibraryHelper::getParams('Webtolk/Amocrm');
        $lib_params->set('refresh_token', '');
        $lib_params->set('refresh_token_date', '');
        $action_result_message = 'AmoCRM refresh token has been cleared';

        if (!LibraryHelper::saveParams('Webtolk/Amocrm', $lib_params)) {
            $action_result_message = 'Failed to remove AmoCRM refresh token from database';
        }

        return $action_result_message;
    }

    /**
     *
     * AmoCRM webhooks handler
     *
     * @param   string  $action
     *
     * @return  string
     *
     * @since   1.3.0
     */
    private function handleWebhook(string $action): string
    {
        switch ($action) {
            case 'webhook':
                // no break
            default:
                $remove = ['option', 'plugin', 'group', 'format', 'action', 'action_type', 'token'];
                $data = array_diff_key($this->getApplication()->getInput()->getArray(), array_flip($remove));

                $dispatcher = $this->getDispatcher();
                PluginHelper::importPlugin('system', null, true, $dispatcher);
                PluginHelper::importPlugin('user', null, true, $dispatcher);
                PluginHelper::importPlugin('amocrm', null, true, $dispatcher);
                $data = new Registry($data);

                $event = WebhookEvent::create(
                    'onAmocrmIncomingWebhook',
                    [
                        'eventClass' => WebhookEvent::class,
                        'subject' => $data,
                    ]
                );

                $dispatcher->dispatch($event->getName(), $event);

                break;
        }

        return 'success';
    }

    /**
     * Select items list for modal select window
     *
     * @return  string
     *
     * @throws  AmocrmClientException
     * @since   1.3.0
     */
    private function modalSelect(): string
    {
        $entity = $this->getApplication()->getInput()->get('entity','');
        if (empty($entity)) {
            return 'There is no AmoCRM entity specified.';
        }
        $amocrm = new Amocrm();
        $remove = ['option', 'plugin', 'group', 'format', 'action', 'action_type', 'token', 'entity', 'entity_type', 'tmpl', Session::getFormToken()];
        $data = array_diff_key($this->getApplication()->getInput()->getArray(), array_flip($remove));

        switch ($entity) {
            case 'leads':
                $displayData = $amocrm->leads()->getLeads($data);
                break;
            case 'contacts':
                // no break
            default:
                $displayData = $amocrm->contacts()->getContacts($data);
                break;
        }

        $displayData = (new Registry($displayData))->toArray();

        return LayoutHelper::render('libraries.webtolk.amocrm.fields.entitymodalselect', ['entity' => $entity, 'data' => $displayData]);
    }

    public function addLibraryWebAssets(): void
    {
        // Only trigger in frontend
        if ($this->getApplication()->isClient('site')) {
            /** @var \Joomla\CMS\WebAsset\WebAssetRegistry $wa */
            $wa = Factory::getContainer()->get(WebAssetRegistry::class);
            $wa->addRegistryFile('media/plg_system_wt_amocrm/joomla.assets.json');
        }
    }
}

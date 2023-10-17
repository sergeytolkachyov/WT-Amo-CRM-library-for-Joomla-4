<?php
/**
 * @package       WT Amocrm Library
 * @version       1.2.1
 * @Author        Sergey Tolkachyov, https://web-tolk.ru
 * @сopyright (c) 2022 - October 2023 Sergey Tolkachyov. All rights reserved.
 * @license       GNU/GPL3 http://www.gnu.org/licenses/gpl-3.0.html
 * @since         1.0.0
 */

namespace Joomla\Plugin\System\Wt_amocrm\Extension;
// No direct access
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Helper\LibraryHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Session\Session;
use Joomla\Event\SubscriberInterface;
use Joomla\Registry\Registry;

class Wt_amocrm extends CMSPlugin implements SubscriberInterface
{
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
			'onAjaxWt_amocrm' => 'onAjaxWt_amocrm',
		];
	}

	public function onAfterInitialise() : void
	{
		\JLoader::registerNamespace('Webtolk\Amocrm', JPATH_LIBRARIES.'/Webtolk/Amocrm/src');
	}


	public function onAjaxWt_amocrm($event) : void
	{

		if(!Session::checkToken('GET')){
			$event->setArgument('result', Text::_('JINVALID_TOKEN'));
			die();
		}

		$action = Factory::getApplication()->getInput()->getCmd('action');

		$action_result_message = '';
		/**
		 * Clear AmoCRM refresh token from Joomla database
		 */
		if($action == 'clear_refresh_token')
		{
			/**
			 * @param $lib_params Registry
			 */
			$lib_params = LibraryHelper::getParams('Webtolk/Amocrm');
			$lib_params->set('refresh_token', '');
			$lib_params->set('refresh_token_date', '');
			LibraryHelper::saveParams('Webtolk/Amocrm', $lib_params);
			$action_result_message = 'AmoCRM refresh token has been cleared';
		}

		$event->setArgument('result', $action_result_message);
	}
}

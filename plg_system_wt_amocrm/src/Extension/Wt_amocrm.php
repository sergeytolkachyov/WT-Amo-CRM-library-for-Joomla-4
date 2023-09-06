<?php
/**
 * @package       WT Amocrm Library
 * @version       1.1.2
 * @Author        Sergey Tolkachyov, https://web-tolk.ru
 * @сopyright (c) 2022 - September 2023 Sergey Tolkachyov. All rights reserved.
 * @license       GNU/GPL3 http://www.gnu.org/licenses/gpl-3.0.html
 * @since         1.0.0
 */

namespace Joomla\Plugin\System\Wt_amocrm\Extension;
// No direct access
defined('_JEXEC') or die;

use Joomla\CMS\Plugin\CMSPlugin;

class Wt_amocrm extends CMSPlugin
{

	public function onAfterInitialise()
	{
		\JLoader::registerNamespace('Webtolk\Amocrm', JPATH_LIBRARIES.'/Webtolk/Amocrm/src');
	}

}

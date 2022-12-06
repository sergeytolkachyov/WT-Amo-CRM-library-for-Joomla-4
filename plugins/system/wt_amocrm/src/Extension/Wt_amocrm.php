<?php
/**
 * @package     WT Amocrm Library
 * @version     1.0.0
 * @Author      Sergey Tolkachyov, https://web-tolk.ru
 * @copyright   Copyright (C) 2022 Sergey Tolkachyov
 * @license     GNU/GPL3
 * @since       1.0
 */

namespace Joomla\Plugin\System\Wt_amocrm\Extension;
// No direct access
defined('_JEXEC') or die;

use Joomla\CMS\Plugin\CMSPlugin;

class Wt_amocrm extends CMSPlugin
{

	public function onAfterInitialise()
	{
		/**
		 * Fix library autoloading
		 * @see https://github.com/joomla/joomla-cms/pull/39348
		 * @see https://github.com/joomla/joomla-cms/issues/39347
		 */
		if(version_compare(JVERSION, '4.2.5', 'le')){
			JLoader::registerNamespace('Webtolk\Amocrm', JPATH_LIBRARIES.'/Webtolk/Amocrm/src');
		}

	}

}

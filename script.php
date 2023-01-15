<?php
// No direct access to this file
defined('_JEXEC') or die('Restricted access');
use Joomla\CMS\Factory;
use Joomla\CMS\Installer\Installer;
use Joomla\CMS\Installer\InstallerHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Version;
use Joomla\CMS\Helper\LibraryHelper;
use Joomla\CMS\Cache\Cache;

/**
 * Script file of HelloWorld component.
 *
 * The name of this class is dependent on the component being installed.
 * The class name should have the component's name, directly followed by
 * the text InstallerScript (ex:. com_helloWorldInstallerScript).
 *
 * This class will be called by Joomla!'s installer, if specified in your component's
 * manifest file, and is used for custom automation actions in its installation process.
 *
 * In order to use this automation script, you should reference it in your component's
 * manifest file as follows:
 * <scriptfile>script.php</scriptfile>
 *
 * @package     Joomla.Administrator
 * @subpackage  com_helloworld
 *
 * @copyright   Copyright (C) 2005 - 2018 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */
class pkg_lib_wt_amocrmInstallerScript
{
    /**
     * This method is called after a component is installed.
     *
     * @param  \stdClass $installer - Parent object calling this method.
     *
     * @return void
     */
    public function install($installer)
    {
	

    }

    /**
     * This method is called after a component is uninstalled.
     *
     * @param  \stdClass $installer - Parent object calling this method.
     *
     * @return void
     */
    public function uninstall($installer) 
    {

		
    }

    /**
     * This method is called after a component is updated.
     *
     * @param  \stdClass $installer - Parent object calling object.
     *
     * @return void
     */
    public function update($installer) 
    {
		
		
    }

    /**
     * Runs just before any installation action is performed on the component.
     * Verifications and pre-requisites should run in this function.
     *
     * @param  string    $type   - Type of PreFlight action. Possible values are:
     *                           - * install
     *                           - * update
     *                           - * discover_install
     * @param  \stdClass $installer - Parent object calling object.
     *
     * @return void
     */
    public function preflight($type, $installer) 
    {
		/**
		 *  в версии 1.0.0 не правильно наименовал element библиотеки, 
		 *  из-за чего получалось задваивание 'pkg_pkg_'. Удаляем запись в базе.
		 *  
		 */
		$db = Factory::getContainer()->get('DatabaseDriver');
		$query = $db->getQuery(true);
			$query->select($db->quoteName(array('extension_id', 'enabled')))
						->from($db->quoteName('#__extensions'))
						->where($db->quoteName('element') . ' = ' . $db->quote('pkg_pkg_lib_wt_amocrm'));
		$result = $db->setQuery($query)->loadObject();
		
		if(isset($result->extension_id) && !empty($result->extension_id))
		{
			$query->clear();
			$query->delete($db->quoteName('#__extensions'))
                        ->where($db->quoteName('extension_id') . ' = '.$db->quote($result->extension_id));
			$db->setQuery($query)->execute();
		}
		
		/**
		 *  
		 *  Joomla при обновлении расширений типа library по факту удаляет их (вместе с данными в базе), 
		 *  а потом устанавливает заново. 
		 *  Дабы избежать потерь данных библиотеки из базы пишем этот костыль. 
		 *  
		 *  @see https://github.com/joomla/joomla-cms/issues/39360
		 *  
		 */
		
		if($type == 'update'){
			$lib_params = LibraryHelper::getParams('Webtolk/Amocrm');
			$jconfig = Factory::getConfig();
			$options = array(
				'defaultgroup' => 'wt_amo_crm_temp',
				'caching'      => true,
				'cachebase'    => $jconfig->get('cache_path'),
				'storage'      => $jconfig->get('cache_handler'),
			);
			$cache   = Cache::getInstance('', $options);
			$cache->store($lib_params, 'wt_amo_crm_temp');

		}

    }
	


    /**
     * Runs right after any installation action is performed on the component.
     *
     * @param  string    $type   - Type of PostFlight action. Possible values are:
     *                           - * install
     *                           - * update
     *                           - * discover_install
     * @param  \stdClass $installer - Parent object calling object.
     *
     * @return void
     */
    function postflight($type, $installer)
    {
	    $smile = '';
	    if($type != 'uninstall')
	    {
		    $smiles    = ['&#9786;', '&#128512;', '&#128521;', '&#128525;', '&#128526;', '&#128522;', '&#128591;'];
		    $smile_key = array_rand($smiles, 1);
		    $smile     = $smiles[$smile_key];
	    }

	    $element = strtoupper($installer->getElement());
		echo "
		<div class='row bg-white m-3 p-3 shadow-sm border'>
		<div class='col-12 col-lg-8'>
		<h2>".$smile." ".Text::_($element."_AFTER_".strtoupper($type))." <br/>".Text::_($element)."</h2>
		".Text::_($element."_DESC");
		
		
			echo Text::_($element."_WHATS_NEW");

		echo "</div>
		<div class='col-12 col-lg-4 d-flex flex-column justify-content-start'>
		<img width='200px' src='https://web-tolk.ru/web_tolk_logo_wide.png'>
		<p>Joomla Extensions</p>
		<p class='btn-group'>
			<a class='btn btn-sm btn-outline-primary' href='https://web-tolk.ru' target='_blank'>https://web-tolk.ru</a>
			<a class='btn btn-sm btn-outline-primary' href='mailto:info@web-tolk.ru'><i class='icon-envelope'></i> info@web-tolk.ru</a>
		</p>
		<p><a class='btn btn-info' href='https://t.me/joomlaru' target='_blank'>Joomla Russian Community in Telegram</a></p>
		
		".Text::_($element."_MAYBE_INTERESTING")."
		</div>


		";		
	
		/**
		 *  
		 *  Joomla при обновлении расширений типа library по факту удаляет их (вместе с данными в базе), 
		 *  а потом устанавливает заново. 
		 *  Дабы избежать потерь данных библиотеки из базы пишем этот костыль. 
		 *   Здесь сохраняем заново данные библиотеки в базу данных.
		 *  @see https://github.com/joomla/joomla-cms/issues/39360
		 *  
		 */
	
			$jconfig = Factory::getConfig();
			$options = array(
				'defaultgroup' => 'wt_amo_crm_temp',
				'caching'      => true,
				'cachebase'    => $jconfig->get('cache_path'),
				'storage'      => $jconfig->get('cache_handler'),
			);
			$cache   = Cache::getInstance('', $options);
			$lib_params = $cache->get('wt_amo_crm_temp');
			LibraryHelper::saveParams('Webtolk/Amocrm', $lib_params);
			$cache->clean('wt_amo_crm_temp');
	
    }
}
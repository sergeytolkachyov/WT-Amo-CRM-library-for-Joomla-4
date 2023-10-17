<?php
/**
 * @package       WT Amocrm Library
 * @version       1.2.0
 * @Author        Sergey Tolkachyov, https://web-tolk.ru
 * @сopyright (c) 2022 - October 2023 Sergey Tolkachyov. All rights reserved.
 * @license       GNU/GPL3 http://www.gnu.org/licenses/gpl-3.0.html
 * @since         1.0.0
 */
\defined('_JEXEC') or die;

use Joomla\CMS\Application\AdministratorApplication;
use Joomla\CMS\Cache\Cache;
use Joomla\CMS\Factory;
use Joomla\CMS\Helper\LibraryHelper;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Installer\InstallerScriptInterface;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseDriver;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;

return new class () implements ServiceProviderInterface {
	public function register(Container $container)
	{
		$container->set(InstallerScriptInterface::class, new class ($container->get(AdministratorApplication::class)) implements InstallerScriptInterface {

			/**
			 * The application object
			 *
			 * @var  AdministratorApplication
			 *
			 * @since  1.0.0
			 */
			protected AdministratorApplication $app;

			/**
			 * The Database object.
			 *
			 * @var   DatabaseDriver
			 *
			 * @since  1.0.0
			 */
			protected DatabaseDriver $db;

			/**
			 * Minimum Joomla version required to install the extension.
			 *
			 * @var  string
			 *
			 * @since  1.0.0
			 */
			protected string $minimumJoomla = '4.0';

			/**
			 * Minimum PHP version required to install the extension.
			 *
			 * @var  string
			 *
			 * @since  1.0.0
			 */
			protected string $minimumPhp = '7.4';

			/**
			 * Constructor.
			 *
			 * @param   AdministratorApplication  $app  The application object.
			 *
			 * @since 1.0.0
			 */
			public function __construct(AdministratorApplication $app)
			{
				$this->app = $app;
				$this->db  = Factory::getContainer()->get('DatabaseDriver');
			}

			/**
			 * This method is called after a component is installed.
			 *
			 * @param   \stdClass  $installer  - Parent object calling this method.
			 *
			 * @return void
			 */
			public function install(InstallerAdapter $adapter): bool
			{

				return true;

			}

			/**
			 * Function called after the extension is uninstalled.
			 *
			 * @param   InstallerAdapter  $adapter  The adapter calling this method
			 *
			 * @return  boolean  True on success
			 *
			 * @since   1.0.0
			 */
			public function uninstall(InstallerAdapter $adapter): bool
			{

				return true;
			}

			/**
			 * Function called after the extension is updated.
			 *
			 * @param   InstallerAdapter  $adapter  The adapter calling this method
			 *
			 * @return  boolean  True on success
			 *
			 * @since   1.0.0
			 */
			public function update(InstallerAdapter $adapter): bool
			{

				return true;

			}

			/**
			 * Function called before extension installation/update/removal procedure commences.
			 *
			 * @param   string            $type     The type of change (install or discover_install, update, uninstall)
			 * @param   InstallerAdapter  $adapter  The adapter calling this method
			 *
			 * @return  boolean  True on success
			 *
			 * @since   1.0.0
			 */
			public function preflight(string $type, InstallerAdapter $adapter): bool
			{
				if ($type == 'uninstall')
				{
					return true;
				}

				/**
				 *
				 *  Joomla при обновлении расширений типа library по факту удаляет их (вместе с данными в базе),
				 *  а потом устанавливает заново.
				 *  Дабы избежать потерь данных библиотеки из базы пишем этот костыль.
				 *
				 * @see https://github.com/joomla/joomla-cms/issues/39360
				 *
				 */

				if ($type == 'update')
				{
					$lib_params = LibraryHelper::getParams('Webtolk/Amocrm');
					$jconfig    = $this->app->getConfig();
					$options    = array(
						'defaultgroup' => 'wt_amo_crm_temp',
						'caching'      => true,
						'cachebase'    => $jconfig->get('cache_path'),
						'storage'      => $jconfig->get('cache_handler'),
					);
					$cache      = Cache::getInstance('', $options);
					$cache->store($lib_params, 'wt_amo_crm_temp');

				}

				return true;

			}


			/**
			 * Function called after extension installation/update/removal procedure commences.
			 *
			 * @param   string            $type     The type of change (install or discover_install, update, uninstall)
			 * @param   InstallerAdapter  $adapter  The adapter calling this method
			 *
			 * @return  boolean  True on success
			 *
			 * @since   1.0.0
			 */
			public function postflight(string $type, InstallerAdapter $adapter): bool
			{

				/**
				 *
				 *  Joomla при обновлении расширений типа library по факту удаляет их (вместе с данными в базе),
				 *  а потом устанавливает заново.
				 *  Дабы избежать потерь данных библиотеки из базы пишем этот костыль.
				 *   Здесь сохраняем заново данные библиотеки в базу данных.
				 * @see https://github.com/joomla/joomla-cms/issues/39360
				 *
				 */
				if ($type == 'update')
				{
					$jconfig    = $this->app->getConfig();
					$options    = array(
						'defaultgroup' => 'wt_amo_crm_temp',
						'caching'      => true,
						'cachebase'    => $jconfig->get('cache_path'),
						'storage'      => $jconfig->get('cache_handler'),
					);
					$cache      = Cache::getInstance('', $options);
					$lib_params = $cache->get('wt_amo_crm_temp');
					LibraryHelper::saveParams('Webtolk/Amocrm', $lib_params);
					$cache->clean('wt_amo_crm_temp');
				}

				$smile = '';
				if ($type != 'uninstall')
				{
					$smiles    = ['&#9786;', '&#128512;', '&#128521;', '&#128525;', '&#128526;', '&#128522;', '&#128591;'];
					$smile_key = array_rand($smiles, 1);
					$smile     = $smiles[$smile_key];
				}

				$element = strtoupper($adapter->getElement());
				$type = strtoupper($type);
				$html = '
				<div class="row bg-white m-0">
				<div class="col-12 col-md-8 p-0 pe-2">
				<h2>'.$smile.' '.Text::_($element.'_AFTER_'.$type).' <br/>'.Text::_($element).'</h2>
				'.Text::_($element.'_DESC');

				$html .= Text::_($element.'_WHATS_NEW');

				$html .= '</div>
				<div class="col-12 col-md-4 p-0 d-flex flex-column justify-content-start">
				<img width="180" src="https://web-tolk.ru/web_tolk_logo_wide.png">
				<p>Joomla Extensions</p>
				<p class="btn-group">
					<a class="btn btn-sm btn-outline-primary" href="https://web-tolk.ru" target="_blank"> https://web-tolk.ru</a>
					<a class="btn btn-sm btn-outline-primary" href="mailto:info@web-tolk.ru"><i class="icon-envelope"></i> info@web-tolk.ru</a>
				</p>
				'.Text::_($element."_MAYBE_INTERESTING").'
				</div>

				';
				$this->app->enqueueMessage($html, 'info');

				return true;
			}

			/**
			 * Enable plugin after installation.
			 *
			 * @param   InstallerAdapter  $adapter  Parent object calling object.
			 *
			 * @since  1.0.0
			 */
			protected function enablePlugin(InstallerAdapter $adapter)
			{
				// Prepare plugin object
				$plugin          = new \stdClass();
				$plugin->type    = 'plugin';
				$plugin->element = $adapter->getElement();
				$plugin->folder  = (string) $adapter->getParent()->manifest->attributes()['group'];
				$plugin->enabled = 1;

				// Update record
				$this->db->updateObject('#__extensions', $plugin, ['type', 'element', 'folder']);
			}

		});
	}
};
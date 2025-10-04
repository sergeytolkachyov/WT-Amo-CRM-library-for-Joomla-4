<?php
/**
 * @package    WT Amo CRM library package
 * @subpackage  WT Import AmoCRM contacts
 * @version     1.3.1
 * @Author      Sergey Tolkachyov, https://web-tolk.ru
 * @copyright  (c) 2022 - September 2025 Sergey Tolkachyov. All rights reserved.
 * @license     GNU/GPL http://www.gnu.org/licenses/gpl-3.0.html
 * @since       1.0.0
 */

use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Extension\Service\Provider\MVCFactory;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Event\DispatcherInterface;
use Joomla\Plugin\Console\Wtimportamocrmcontacts\Extension\Wtimportamocrmcontacts;

defined('_JEXEC') or die;

return new class () implements ServiceProviderInterface {

	/**
	 * Registers the service provider with a DI container.
	 *
	 * @param  Container  $container  The DI container.
	 *
	 * @since  1.1.0
	 */
	public function register(Container $container): void
	{
		$container->registerServiceProvider(new MVCFactory('Joomla\\Plugin\\Console\\Wtimportamocrmcontacts'));

		$container->set(PluginInterface::class,
			function (Container $container) {
				$config = (array) PluginHelper::getPlugin('console', 'wtimportamocrmcontacts');

				$subject = $container->get(DispatcherInterface::class);
				$mvcFactory = $container->get(MVCFactoryInterface::class);

				$plugin = new Wtimportamocrmcontacts($subject, $config);
				$plugin->setApplication(Factory::getApplication());
				$plugin->setMVCFactory($mvcFactory);

				return $plugin;
			}
		);
	}
};

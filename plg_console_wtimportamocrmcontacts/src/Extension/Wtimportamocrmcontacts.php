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

namespace Joomla\Plugin\Console\Wtimportamocrmcontacts\Extension;

use Joomla\Application\ApplicationEvents;
use Joomla\CMS\Console\Loader\WritableLoaderInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Factory\MVCFactoryAwareTrait;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Event\SubscriberInterface;
use Joomla\Plugin\Console\Wtimportamocrmcontacts\Extension\Console\WtimportamocrmcontactsCommand;
use Joomla\Plugin\Console\Wtimportamocrmcontacts\Extension\Console\WtshowamocrmtagslistCommand;
use Psr\Container\ContainerInterface;

defined('_JEXEC') or die;

class Wtimportamocrmcontacts extends CMSPlugin implements SubscriberInterface
{
    use MVCFactoryAwareTrait;
    use DatabaseAwareTrait;

    /**
     * Choose which events this plugin is subscribed to and will respond to
     * @return string[]
     *
     * @since 1.3.0
     */
    public static function getSubscribedEvents(): array
    {
        return [
            ApplicationEvents::BEFORE_EXECUTE => 'registerCommands',
        ];
    }

    /**
     * Register custom commands
     *
     * @since 1.3.0
     */
    public function registerCommands(): void
    {
        Factory::getContainer()->share(
            'webtolk.amocrm.contacts.import',
            function (ContainerInterface $container) {
                return new WtimportamocrmcontactsCommand();
            },
            true
        );
        Factory::getContainer()->get(WritableLoaderInterface::class)->add(
            'amocrm:contacts:import',
            'webtolk.amocrm.contacts.import'
        );

        // Tags list
        Factory::getContainer()->share(
            'webtolk.amocrm.tags.show',
            function (ContainerInterface $container) {
                return new WtshowamocrmtagslistCommand();
            },
            true
        );
        Factory::getContainer()->get(WritableLoaderInterface::class)->add(
            'amocrm:tags:show',
            'webtolk.amocrm.tags.show'
        );
    }
}
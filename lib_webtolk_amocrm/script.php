<?php

/**
 * @package       WT Amocrm Library
 * @version       1.3.0-alpha2
 * @Author        Sergey Tolkachyov, https://web-tolk.ru
 * @copyright  (c) 2022 - May 2025 Sergey Tolkachyov. All rights reserved.
 * @license       GNU/GPL3 http://www.gnu.org/licenses/gpl-3.0.html
 * @since         1.3.0
 */
defined('_JEXEC') or die;

use Joomla\CMS\Application\AdministratorApplication;
use Joomla\CMS\Cache\Cache;
use Joomla\CMS\Cache\CacheControllerFactoryInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Helper\LibraryHelper;
use Joomla\CMS\Installer\Installer;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Installer\InstallerScriptInterface;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Version;
use Joomla\Database\DatabaseDriver;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Filesystem\File;
use Joomla\Filesystem\Folder;
use Joomla\Filesystem\Path;

return new class () implements ServiceProviderInterface {
    public function register(Container $container)
    {
        $container->set(
            InstallerScriptInterface::class,
            new class ($container->get(AdministratorApplication::class)) implements InstallerScriptInterface {

                /**
                 * The application object
                 *
                 * @var  AdministratorApplication
                 *
                 * @since  1.3.0
                 */
                protected AdministratorApplication $app;

                /**
                 * The Database object.
                 *
                 * @var   DatabaseDriver
                 *
                 * @since  1.3.0
                 */
                protected DatabaseDriver $db;

                /**
                 * Constructor.
                 *
                 * @param   AdministratorApplication  $app  The application object.
                 *
                 * @since 1.3.0
                 */
                public function __construct(AdministratorApplication $app)
                {
                    $this->app = $app;
                    $this->db  = Factory::getContainer()->get('DatabaseDriver');
                }

                /**
                 * This method is called after a component is installed.
                 *
                 * @param   InstallerAdapter  $installer  - Parent object calling this method.
                 * @since 1.3.0
                 *                                        
                 * @return bool
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
                 * @return  bool  True on success
                 *
                 * @since   1.3.0
                 */
                public function uninstall(InstallerAdapter $adapter): bool
                {
                    // Remove layouts
                    $this->removeLayouts($adapter->getParent()->getManifest()->layouts);
                    return true;
                }

                /**
                 * Function called after the extension is updated.
                 *
                 * @param   InstallerAdapter  $adapter  The adapter calling this method
                 *
                 * @return  bool  True on success
                 *
                 * @since   1.3.0
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
                 * @return  bool  True on success
                 *
                 * @since   1.3.0
                 */
                public function preflight(string $type, InstallerAdapter $adapter): bool
                {

                    return true;
                }


                /**
                 * Function called after extension installation/update/removal procedure commences.
                 *
                 * @param   string            $type     The type of change (install or discover_install, update, uninstall)
                 * @param   InstallerAdapter  $adapter  The adapter calling this method
                 *
                 * @return  bool  True on success
                 *
                 * @since   1.3.0
                 */
                public function postflight(string $type, InstallerAdapter $adapter): bool
                {

                    if ($type != 'uninstall')
                    {
                        $this->parseLayouts($adapter->getParent()->getManifest()->layouts, $adapter->getParent());
                    }

                    return true;
                }

                /**
                 * Method to parse through a layout element of the installation manifest and take appropriate action
                 *
                 * @param \SimpleXMLElement $element The XML node to process
                 * @param Installer $installer Installer calling object
                 *
                 * @return boolean True on success
                 *
                 * @since 1.3.0
                 */
                private function parseLayouts(\SimpleXMLElement $element, Installer $installer): bool
                {
                    if (!$element || !count($element->children()))
                    {
                        return false;
                    }

                    // Get destination
                    $folder = ((string) $element->attributes()->destination) ? '/' . $element->attributes()->destination : null;
                    $destination = Path::clean(JPATH_ROOT . '/layouts' . $folder);

                    // Get source
                    $folder = (string) $element->attributes()->folder;
                    $source = ($folder && file_exists($installer->getPath('source') . '/' . $folder)) ?
                        $installer->getPath('source') . '/' . $folder : $installer->getPath('source');

                    // Prepare files
                    $files = [];
                    foreach ($element->children() as $file)
                    {
                        $path['src'] = Path::clean($source . '/' . $file);
                        $path['dest'] = Path::clean($destination . '/' . $file);

                        // Is this path a file or folder?
                        $path['type'] = $file->getName() === 'folder' ? 'folder' : 'file';
                        if (basename($path['dest']) !== $path['dest'])
                        {
                            $newdir = dirname($path['dest']);
                            if (!Folder::create($newdir))
                            {
                                Log::add(Text::sprintf('JLIB_INSTALLER_ABORT_CREATE_DIRECTORY', $installer->getManifest()->name, $newdir), Log::WARNING, 'jerror');

                                return false;
                            }
                        }

                        $files[] = $path;
                    }

                    return $installer->copyFiles($files);
                }

                /**
                 * Method to parse through a layouts element of the installation manifest and remove the files that were installed
                 *
                 * @param \SimpleXMLElement $element The XML node to process
                 *
                 * @return boolean True on success
                 *
                 * @since 1.3.0
                 */
                private function removeLayouts(\SimpleXMLElement $element): bool
                {
                    if (!$element || !count($element->children()))
                    {
                        return false;
                    }

                    // Get the array of file nodes to process
                    $files = $element->children();

                    // Get source
                    $folder = ((string) $element->attributes()->destination) ? '/' . $element->attributes()->destination : null;
                    $source = Path::clean(JPATH_ROOT . '/layouts' . $folder);

                    // Process each file in the $files array (children of $tagName).
                    foreach ($files as $file)
                    {
                        $path = Path::clean($source . '/' . $file);

                        // Actually delete the files/folders
                        if (is_dir($path))
                        {
                            $val = Folder::delete($path);
                        }
                        else
                        {
                            $val = File::delete($path);
                        }

                        if ($val === false)
                        {
                            Log::add('Failed to delete ' . $path, Log::WARNING, 'jerror');

                            return false;
                        }
                    }

                    if (!empty($folder))
                    {
                        Folder::delete($source);
                    }

                    return true;
                }

            }
        );
    }
};

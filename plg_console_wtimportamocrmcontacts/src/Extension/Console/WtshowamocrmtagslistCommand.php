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

namespace Joomla\Plugin\Console\Wtimportamocrmcontacts\Extension\Console;

use Joomla\CMS\Application\ConsoleApplication;
use Joomla\CMS\Language\Text;
use Joomla\Console\Command\AbstractCommand;
use Joomla\Registry\Registry;
use Joomla\Uri\UriHelper;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Webtolk\Amocrm\Amocrm;

defined('_JEXEC') or die;

class WtshowamocrmtagslistCommand extends AbstractCommand
{
    /**
     * The default command name
     *
     * @var    string
     * @since  4.0.0
     */
    protected static $defaultName = 'amocrm:tags:show';

    /**
     * Configure the command.
     *
     * @return  void
     *
     * @since   4.0.0
     */
    protected function configure(): void
    {
		$this->addOption('entity', 'e', InputOption::VALUE_OPTIONAL, 'Specify AmoCRM entity: leads, contacts, companies or customers. Leads by default.', 'leads');
//		$this->addOption('entity', 'e', InputOption::VALUE_REQUIRED, 'Specify AmoCRM entity: leads, contacts, companies or customers', '');
//		$this->addOption('tags', 't', InputOption::VALUE_OPTIONAL, 'Only contacts tagged with this tags ids will be handled. Separate its via commma without spaces. You can find tags ids in User - WT AmoCRM User sync plugin settings.', '');
//		$this->addOption('excludetags', 'et', InputOption::VALUE_OPTIONAL, 'All categories exclude specified ids. Separate its via commma without spaces.', '');
//		$this->addArgument('test', InputArgument::OPTIONAL, 'Check how article alias will changed', null);
        $this->setDescription("Show AmoCRM tags list.");
        $this->setHelp(
            <<<EOF

			<comment>php joomla.php amocrm:tags:show</comment>
			<comment>php joomla.php amocrm:tags:show --entity=leads</comment>
			
			EOF
        );
    }

    protected function doExecute(InputInterface $input, OutputInterface $output): int
    {
        $symfonyStyle = new SymfonyStyle($input, $output);

        $entity_type = $input->getOption('entity');
        // Get live site parameter
        $live_site = $this->getApplication()->get('live_site');

        // Check that the URL is provided
        if (empty($live_site)) {
            $symfonyStyle->error('ERROR: Missing --live-site option with end slash or live_site param filled in configuration.php');

            return Command::FAILURE;
        }

        // Parse URL and check existence of parts
        $urlParts = UriHelper::parse_url($live_site);

        if (empty($urlParts['scheme']) || empty($urlParts['host'])) {
            $symfonyStyle->error(
                'ERROR: Incomplete --live-site provided; provide scheme and host'
            );

            return Command::FAILURE;
        }

        /** @var ConsoleApplication $app */
        $lang = $this->getApplication()->getLanguage();
        $extension = 'lib_webtolk_amocrm';
        $base_dir = JPATH_SITE;
        $lang->load($extension, $base_dir);

        $amocrm = new Amocrm();
        $tagsList = $amocrm->tags()->getTags($entity_type);

        if (property_exists($tagsList,'error_code')) {
            $symfonyStyle->error($tagsList->error_code.' '.Text::_($tagsList->error_message));
            return Command::FAILURE;
        }
        $tags = (new Registry($tagsList->_embedded->tags))->toArray();
        $symfonyStyle->info('Count AmoCRM tags '.count($tags));
        $headers = ['Tag id', 'Tag name', 'Color'];

        $table = $symfonyStyle->createTable();
        $table
            ->setHeaderTitle('AmoCRM tags list')
            ->setHeaders($headers)
            ->setFooterTitle('Tags for '.$entity_type)
            ->setRows($tags);

        $symfonyStyle->newLine();
        $table->render();
        $symfonyStyle->newLine();

        return Command::SUCCESS;
    }
}

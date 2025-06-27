<?php
/**
 * @package       WT AmoCRM library
 * @subpackage    WT Import AmoCRM contacts
 * @version       1.0.0
 * @Author        Sergey Tolkachyov, https://web-tolk.ru
 * @copyright     Copyright (C) 2024 Sergey Tolkachyov
 * @license       GNU/GPL http://www.gnu.org/licenses/gpl-3.0.html
 * @since         1.0.0
 */
 
namespace Joomla\Plugin\Console\Wtimportamocrmcontacts\Extension\Console;

use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Console\Command\AbstractCommand;
use Joomla\Registry\Registry;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Joomla\CMS\Component\ComponentHelper;
use Symfony\Component\Console\Helper\ProgressBar;
use Webtolk\Amocrm\Amocrm;

use function defined;

defined('_JEXEC') or die;

class WtimportamocrmcontactsCommand extends AbstractCommand
{
	/**
	 * The default command name
	 *
	 * @var    string
	 * @since  4.0.0
	 */
	protected static $defaultName = 'amocrm:contacts:import';

	/**
	 * Configure the command.
	 *
	 * @return  void
	 *
	 * @since   4.0.0
	 */
	protected function configure(): void
	{
//		$this->addOption('domain', 'd', InputOption::VALUE_REQUIRED, 'Domain for check without', '');
		$this->addOption('tags', 't', InputOption::VALUE_OPTIONAL, 'Only contacts tagged with this tags ids will be handled. Separate its via commma without spaces. You can find tags ids in User - WT AmoCRM User sync plugin settings.', '');
		$this->addOption('notags', 'nt', InputOption::VALUE_OPTIONAL, 'All contacts exclude specified tags ids. Separate its via commma without spaces.', '');
        $this->addArgument('test', InputArgument::OPTIONAL, 'Preview contacts list for import with specified params applied.', false);
		$this->setDescription("Create Joomla user for AmoCRM contacts. PLugin works using user sync plugin.");
		$this->setHelp(
			<<<EOF
			Import AmoCRM contacts to Joomla users. You need to <comment>User - WT AmoCRM User sync</comment> plugin has been configured.
			###########
			
			<comment>php joomla.php amocrm:contacts:import --notags=277163,45776 test</comment>
			
			<comment>/usr/bin/php84 joomla.php amocrm:contacts:import --tags=1,14,543</comment>
			
			###########
			EOF
		);
	}


	protected function doExecute(InputInterface $input, OutputInterface $output): int
	{

		$symfonyStyle = new SymfonyStyle($input, $output);
        $symfonyStyle->title('Starting contacts import from AmoCRM...');

        if(!PluginHelper::isEnabled('user','wtamocrmusersync')) {
            $symfonyStyle->caution('User - WT Amocrm user sync plugin is disabled. Process aborted.');
            return Command::FAILURE;
        }
        // Get the WT AmoCRM user sync plugin
        $wtamocrmusersync = Factory::getApplication()->bootPlugin('wtamocrmusersync','user');
        $test_mode = $input->getArgument('test', false);

		$tags = $input->getOption('tags','');
		if(!empty($tags))
		{
			$tags = array_map('intval', explode(',',$tags));
		} else {
			$tags = [];
		}

		$notags = $input->getOption('notags','');
        if(!empty($notags))
        {
            $notags = array_map('intval', explode(',',$notags));
        } else {
            $notags = [];
        }

		$scriptStart = microtime(true);

		$contacts = [];

        $amocrm = new Amocrm();
        $total_contacts = $amocrm->getRequest()->getResponse('/ajax/contacts/list/contacts/',['only_count'=>'Y','skip_filter'=>'Y'],'GET','',true);
        $completed = false;
        $page = 1;
        $limit = 3;
        $data = [];
        $contactsCount = $total_contacts->count;
        $contactsExcludedCount = 0;
        $contactsByTagsCount = 0;


        if ($test_mode) {
            $limit = 5;

        }

        ProgressBar::setFormatDefinition('contactsProgress', '[%bar%] %current%/%total% contacts in batch. Page: <info>%page%</info>. Contacts excluded by tags: <info>%contactsexcludedcount%</info>. Contacts handled: <info>%contactscount%</info>');
        $progressBar = new ProgressBar($output);
        $progressBar->setFormat('contactsProgress');
        $progressBar->start();

        while(!$completed) {

            $filter = [
                'page' => $page,
                'limit' => $limit
            ];
            $contacts = $amocrm->contacts()->getContacts($filter);

            if(!property_exists($contacts, '_embedded')) {
                dump($contacts);
                $completed = true;
                break;
            }
            $contacts = (new Registry($contacts->_embedded->contacts))->toArray();

            dump(count($contacts));
            $progressBar->setMessage($contactsCount,'total');
            $i = 0;
            foreach ($contacts as &$contact) {
                // Get all tags
                $contact_tags = [];
                if(!empty($contact['_embedded']['tags'])) {
                    $contact_tags = array_column($contact['_embedded']['tags'],'id');
                }

                // Skip contacts with tags specified
                if(!empty($contact_tags) && !empty($notags) && !empty(array_intersect($contact_tags, $notags))) {
                    $contactsExcludedCount++;
                    unset($contacts[$i]);
                    continue;
                }

                // Skip contact without tags specified
                if(!empty($tags) && empty(array_values(array_intersect($contact_tags, $tags)))) {
                    unset($contacts[$i]);
                    $contactsExcludedCount++;
                    continue;
                }

                if($test_mode) {
                    $data[] = [
                        $contact['id'],
                        $contact['name'],
                    ];
                }

                // Set data structure like data from the webhook.
                $contact['type'] = 'contact';
                if($contact['custom_fields_values']) {
                    $contact['custom_fields'] = $contact['custom_fields_values'];
                    foreach ($contact['custom_fields'] as &$custom_field) {
                        $custom_field['id'] = $custom_field['field_id'];
                        $custom_field['code'] = $custom_field['field_code'];
                        unset($custom_field['field_code']);
                    }

                    unset($contact['custom_fields_values']);
                }

                $i++;
                $progressBar->setMessage($contactsByTagsCount, 'contactsbytagscount');
                $progressBar->setMessage($contactsExcludedCount, 'contactsexcludedcount');
                $progressBar->advance();
            sleep(1);
            }


            if($test_mode) {
                $table        = $symfonyStyle->createTable();
                $table
                    ->setHeaderTitle('AmoCRM contacts list')
                    ->setHeaders(['Contact id', 'Name'])
                    ->setFooterTitle($contactsCount.' contacts shown')
                    ->setRows($data);

                $symfonyStyle->newLine();
                $table->render();
                $symfonyStyle->newLine();
                $symfonyStyle->writeln('<comment>Tags:</comment> '.(!empty($tags) ? implode(', ', $tags) : 'not specified'));
                $symfonyStyle->writeln('<comment>Exclude tags:</comment> '.(!empty($notags) ? implode(', ', $notags) : 'not specified'));
                $symfonyStyle->writeln('<comment>Total contacts fetched:</comment> '.$limit);
                $symfonyStyle->writeln('<comment>Contacts by tags:</comment> '.$contactsByTagsCount);
                $symfonyStyle->writeln('<comment>Contacts excluded by tags:</comment> '.$contactsExcludedCount);
                $symfonyStyle->newLine();
                $symfonyStyle->info('amocrm:tags:show --entity=contacts     - will display a tags list for contacts');
                $completed = true;
                break;
            }


            // Save AmoCRM contacts to Joomla users
            $wtamocrmusersync->createUsers($contacts);

            $page++;
            $progressBar->setMessage($page,'page');

            if($limit < count($contacts)) {
                $completed = true;
            }


        }
        $progressBar->finish();

		$time = number_format(microtime(true) - $scriptStart, 2, '.', '');
		$symfonyStyle->newLine();
		$symfonyStyle->writeln(
		[
		'============================',
		'FINISHED IN SECONDS ' . $time
		]);

		return Command::SUCCESS;

	}

}

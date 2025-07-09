<?php
/**
 * @package     Webtolk\Amocrm\Traits
 * @subpackage
 *
 * @copyright   A copyright
 * @license     A "Slug" license name e.g. GPL2
 */

namespace Webtolk\Amocrm\Traits;

use Joomla\CMS\Factory;
use Joomla\CMS\Log\Log;

trait LogTrait
{
    /**
     * Function for to log library errors in lib_webtolk_amo_crm.log.php in
     * Joomla log path. Default Log category lib_webtolk_amo_crm
     *
     * @param   string  $data      error message
     * @param   string  $priority  Joomla Log priority
     * @param   string  $log_name  Log filename into `lib_webtolk_amo_crm` category
     *
     * @return void
     * @since 1.3.0
     */
    public function saveToLog(string $data, string $priority = 'NOTICE', string $log_name = 'lib_webtolk_amo_crm'): void
    {
        Log::addLogger(
            [
                // Sets file name
                'text_file' => $log_name.'.log.php',
            ],
            // Sets all but DEBUG log level messages to be sent to the file
            Log::ALL & ~Log::DEBUG,
            ['lib_webtolk_amo_crm']
        );
        Factory::getApplication()->enqueueMessage($data, $priority);
        $priorityName = 'Log::' . $priority;
        if (defined($priorityName)) {
            $priority = constant($priorityName);
        }
        Log::add($data, $priority, 'lib_webtolk_amo_crm');
    }
}
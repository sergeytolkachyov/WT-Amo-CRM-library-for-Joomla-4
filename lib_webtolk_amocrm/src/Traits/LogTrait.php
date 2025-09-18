<?php
/**
 * @package    WT Amocrm Library
 * @version    1.3.0
 * @Author     Sergey Tolkachyov, https://web-tolk.ru
 * @copyright  (c) 2022 - May 2025 Sergey Tolkachyov. All rights reserved.
 * @license    GNU/GPL3 http://www.gnu.org/licenses/gpl-3.0.html
 * @since      1.3.0
 */

namespace Webtolk\Amocrm\Traits;

use Joomla\CMS\Factory;
use Joomla\CMS\Log\Log;

defined('_JEXEC') or die;

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
     * @return  void
     * @since   1.3.0
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
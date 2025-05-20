<?php
/**
 * @package       WT Amocrm Library
 * @version       1.3.0-alpha2
 * @Author        Sergey Tolkachyov, https://web-tolk.ru
 * @copyright  (c) 2022 - May 2025 Sergey Tolkachyov. All rights reserved.
 * @license       GNU/GPL3 http://www.gnu.org/licenses/gpl-3.0.html
 * @since         1.3.0
 */

namespace Webtolk\Amocrm\Helper;

use Joomla\CMS\Factory;

use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;
use Webtolk\Amocrm\Amocrm;
use Webtolk\Amocrm\Traits\LogTrait;

use function defined;

defined('_JEXEC') or die;

class UserHelper
{
    use LogTrait;

    /**
     * Check have we AmoCRM user id for this joomla user id?
     *
     * @param   int  $joomla_user_id
     *
     * @return mixed (bool) false or (int) AmoCRM user id
     *
     * @since 1.3.0
     */
    public static function checkIsAmoCRMUser(int $joomla_user_id): mixed
    {
        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select($db->quoteName('amocrm_contact_id'))
            ->from($db->quoteName('#__lib_wt_amocrm_users_sync'))
            ->where($db->quoteName('joomla_user_id') . ' = ' . $db->quote($joomla_user_id));
        $db->setQuery($query);
        //Get single result
        $amocrm_user_id = $db->loadResult();
        if (!empty($amocrm_user_id)) {
            return (int)$amocrm_user_id;
        }

        return false;
    }

    /**
     * Check have we joomla user id for this AmoCRM user id?
     *
     * @param   int  $amocrm_user_id
     *
     * @return mixed (bool) false or (int) joomla user id
     *
     * @since 1.3.0
     */
    public static function checkIsJoomlaUser(int $amocrm_user_id): mixed
    {
        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select($db->quoteName('joomla_user_id'))
            ->from($db->quoteName('#__lib_wt_amocrm_users_sync'))
            ->where($db->quoteName('amocrm_contact_id') . ' = ' . $db->quote($amocrm_user_id));
        $db->setQuery($query);
        //Get single result
        $joomla_user_id = $db->loadResult();
        if (!empty($joomla_user_id)) {
            return (int)$joomla_user_id;
        }

        return false;
    }

    /**
     * Add new joomla user id to AmoCRM user id mapping
     *
     * @param   int  $joomla_user_id
     * @param   int  $amocrm_contact_id
     *
     * @return bool True or false
     *
     * @since 1.3.0
     */
    public static function addJoomlaAmoCRMUserSync(int $joomla_user_id, int $amocrm_contact_id): bool
    {
        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->insert($db->quoteName('#__lib_wt_amocrm_users_sync'))
            ->columns([$db->quoteName('joomla_user_id'), $db->quoteName('amocrm_contact_id')])
            ->values(implode(',', [$db->quote($joomla_user_id), $db->quote($amocrm_contact_id)]));
        $db->setQuery($query);

        return $db->execute();
    }

    /**
     * Delete Joomla & AmoCRM user ids mapping. Batch method.
     *
     * **Specify Joomla users ids OR AmoCRM users ids**
     * - If Joomla user id specified - delete by Joomla user id.
     * - If AmoCRM user id specified - delete by AmoCRM user id.
     * - If both user ids specified - Reuqest will not be executed
     *
     *
     * @param   array  $joomla_user_ids  Plain array of Joomla users ids like [1, 2, 3, 4...]
     * @param   array  $amocrm_user_ids  Plain array of AmoCRM users ids like [1, 2, 3, 4...]
     *
     * @return bool True or false
     *
     * @since 1.0.0
     */
    public static function removeJoomlaAmoCRMUserSync(array $joomla_user_ids = [], array $amocrm_user_ids = []): bool
    {
        if (count($joomla_user_ids) > 0 && count($amocrm_user_ids) > 0) {
            (new Amocrm())->saveToLog(
                Text::_('LIB_WTAMOCRM_ERROR_HELPER_USERHELPER_REMOVEJOOMLAAMOCRMUSERSYNC'),
                'error'
            );

            return false;
        }

        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true);
        $query->delete($db->quoteName('#__lib_wt_amocrm_users_sync'));

        // delete by Joomla user id
        if (count($joomla_user_ids) > 0 && count($amocrm_user_ids) < 1) {
            $conditions = [
                $db->quoteName('joomla_user_id') . ' IN (' . implode(',', $joomla_user_ids) . ')'
            ];
        } elseif (count($amocrm_user_ids) > 0 && count($joomla_user_ids) < 1)    // delete by AmoCRM user id
        {
            $conditions = [
                $db->quoteName('amocrm_contact_id') . ' IN (' . implode(',', $amocrm_user_ids) . ')'
            ];
        }

        $query->where($conditions);
        $db->setQuery($query);

        return $db->execute();
    }
}
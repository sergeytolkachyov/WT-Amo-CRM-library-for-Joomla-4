<?php
/**
 * @package    WT Amo CRM library package
 * @version    1.3.0
 * @Author     Sergey Tolkachyov, https://web-tolk.ru
 * @copyright  (c) 2022 - September 2025 Sergey Tolkachyov. All rights reserved.
 * @license    GNU/GPL3 http://www.gnu.org/licenses/gpl-3.0.html
 * @since      1.0.0
 */

namespace Webtolk\Amocrm\Fields;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Form\Field\ListField;
use Webtolk\Amocrm\Amocrm;

defined('_JEXEC') or die;

class LeadspipelineslistField extends ListField
{

    protected $type = 'Leadspipelineslist';

    protected function getOptions()
    {
        $amocrm = new Amocrm();
        $result_amo_crm = $amocrm->leads()->getLeadsPiplines();

        $options = [];
        if (empty($result_amo_crm)) {
            $options[] = HTMLHelper::_('select.option', 0, 'there is no tags in Amo CRM');
        }
        if (isset($result_amo_crm->_embedded) && isset($result_amo_crm->_embedded->pipelines)) {
            foreach ($result_amo_crm->_embedded->pipelines as $pipeline) {
                $options[] = HTMLHelper::_(
                    'select.option',
                    $pipeline->id,
                    $pipeline->name . ' (id: ' . $pipeline->id . ')'
                );
            }
        } else if (isset($result_amo_crm->error_code)) {
            Factory::getApplication()->enqueueMessage(
                $result_amo_crm->error_code . ' ' . $result_amo_crm->error_message,
                'error'
            );
        }

        return $options;
    }
}

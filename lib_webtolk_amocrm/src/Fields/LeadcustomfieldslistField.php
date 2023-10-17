<?php
/**
 * @package       WT Amocrm Library
 * @version       1.2.0
 * @Author        Sergey Tolkachyov, https://web-tolk.ru
 * @сopyright (c) 2022 - October 2023 Sergey Tolkachyov. All rights reserved.
 * @license       GNU/GPL3 http://www.gnu.org/licenses/gpl-3.0.html
 * @since         1.0.0
 */

namespace Webtolk\Amocrm\Fields;
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Form\Field\ListField;
use Webtolk\Amocrm\Amocrm;


class LeadcustomfieldslistField extends ListField
{

	protected $type = 'Leadcustomfieldslist';

	protected function getOptions()
	{

		$amocrm         = new Amocrm();
		$result_amo_crm = $amocrm->getLeadsCustomFields();
		$options        = array();
		if (empty($result_amo_crm))
		{
			return $options[] = HTMLHelper::_('select.option', 0, 'there is no custom_fields in Amo CRM');
		}
		if (isset($result_amo_crm->_embedded) && isset($result_amo_crm->_embedded->custom_fields))
		{
			foreach ($result_amo_crm->_embedded->custom_fields as $lead_custom_field)
			{
				$options[] = HTMLHelper::_('select.option', $lead_custom_field->id, $lead_custom_field->name . ' (type: ' . $lead_custom_field->type . ')');
			}

			return $options;

		}
		elseif (isset($result_amo_crm->error_code))
		{
			Factory::getApplication()->enqueueMessage($result_amo_crm->error . " " . $result_amo_crm->error_description, 'error');

			return $options;
		}
	}
}

?>
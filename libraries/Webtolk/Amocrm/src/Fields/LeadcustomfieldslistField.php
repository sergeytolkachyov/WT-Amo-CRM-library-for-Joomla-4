<?php
/**
 * Library to connect to Amo CRM service.
 * @package     Webtolk
 * @subpackage  AmoCRM
 * @author      Sergey Tolkachyov
 * @copyright   Copyright (C) Sergey Tolkachyov, 2022-2023. All rights reserved.
 * @version     1.1.1
 * @license     GNU General Public License version 3 or later.
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

		$amocrm = new Amocrm();
		$result_amo_crm = $amocrm->getLeadsCustomFields();
		$options = array();
		if(empty($result_amo_crm)){
			return $options[] = HTMLHelper::_('select.option', 0, 'there is no custom_fields in Amo CRM');
		}
		if (isset($result_amo_crm->_embedded) && isset($result_amo_crm->_embedded->custom_fields))
		{
			foreach ($result_amo_crm->_embedded->custom_fields as $lead_custom_field)
			{
				$options[] = HTMLHelper::_('select.option', $lead_custom_field->id, $lead_custom_field->name.' (type: '.$lead_custom_field->type.')');
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
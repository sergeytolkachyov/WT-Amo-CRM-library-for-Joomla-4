<?php
/**
 * @package       WT Amocrm Library
 * @version       1.3.0-alpha2
 * @Author        Sergey Tolkachyov, https://web-tolk.ru
 * @copyright  (c) 2022 - May 2025 Sergey Tolkachyov. All rights reserved.
 * @license       GNU/GPL3 http://www.gnu.org/licenses/gpl-3.0.html
 * @since         1.0.0
 */

namespace Webtolk\Amocrm\Fields;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Form\Field\ListField;
use Webtolk\Amocrm\Amocrm;
use  function defined;

defined('_JEXEC') or die;

class CompanycustomfieldslistField extends ListField
{

	protected $type = 'Companycustomfieldslist';

	protected function getOptions()
	{

		$amocrm         = new Amocrm();
		$result_amo_crm = $amocrm->customfields()->getCompaniesCustomFields();
		$options        = [];
		if (empty($result_amo_crm))
		{
			return $options[] = HTMLHelper::_('select.option', 0, 'there is no custom_fields in Amo CRM for companies');
		}
		if (isset($result_amo_crm->_embedded) && isset($result_amo_crm->_embedded->custom_fields))
		{
			foreach ($result_amo_crm->_embedded->custom_fields as $contact_custom_field)
			{
				$options[] = HTMLHelper::_('select.option', $contact_custom_field->id, $contact_custom_field->name . ' (type: ' . $contact_custom_field->type . ')');
			}

			return $options;

		}
		elseif (isset($result_amo_crm->error_code))
		{
			Factory::getApplication()->enqueueMessage($result_amo_crm->error_code . ' ' . $result_amo_crm->error_message, 'error');

			return $options;
		}
	}
}

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


class CompaniestagslistField extends ListField
{

	protected $type = 'Сompaniestagslist';

	protected function getOptions()
	{
		$requset_options = [];
		if(isset($this->element['limit']) && !empty($this->element['limit'])){
			$requset_options['limit'] = (((int) $this->element['limit'] > 250) ? 250 : $this->element['limit'] ); // 250 items max
		}
		$amocrm = new Amocrm();
		$result_amo_crm = $amocrm->getTags('companies',$requset_options);

		$options = array();
		if(empty($result_amo_crm)){
			return $options[] = HTMLHelper::_('select.option', 'there is no tags in Amo CRM');
		}
		if (isset($result_amo_crm->_embedded) && isset($result_amo_crm->_embedded->tags))
		{
			foreach ($result_amo_crm->_embedded->tags as $company_tag)
			{
				$options[] = HTMLHelper::_('select.option', $company_tag->id, $company_tag->name);
			}


		}
		elseif (isset($result_amo_crm->error_code))
		{
			Factory::getApplication()->enqueueMessage($result_amo_crm->error . " " . $result_amo_crm->error_description, 'error');


		}
		return $options;
	}
}

?>
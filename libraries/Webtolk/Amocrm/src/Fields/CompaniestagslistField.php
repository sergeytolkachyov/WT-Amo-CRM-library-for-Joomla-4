<?php
/**
 * @package     WT Amo CRm
 * @version     1.0.0
 * @Author      Sergey Tolkachyov, https://web-tolk.ru
 * @copyright   Copyright (C) 2022 Sergey Tolkachyov
 * @license     GNU/GPL http://www.gnu.org/licenses/gpl-2.0.html
 * @since       1.0.0
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

		$amocrm = new Amocrm();
		$result_amo_crm = $amocrm->getTags('companies');

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
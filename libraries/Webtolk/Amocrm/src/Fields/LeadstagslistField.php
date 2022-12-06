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


class LeadstagslistField extends ListField
{

	protected $type = 'Leadstagslist';

	protected function getOptions()
	{

		$amocrm = new Amocrm();
		$result_amo_crm = $amocrm->getTags('leads');
		$options = array();
		if(empty($result_amo_crm)){
			return $options[] = HTMLHelper::_('select.option', 0, 'there is no tags in Amo CRM');
		}
		if (isset($result_amo_crm->_embedded) && isset($result_amo_crm->_embedded->tags))
		{
			foreach ($result_amo_crm->_embedded->tags as $lead_tag)
			{
				$options[] = HTMLHelper::_('select.option', $lead_tag->id, $lead_tag->name);
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
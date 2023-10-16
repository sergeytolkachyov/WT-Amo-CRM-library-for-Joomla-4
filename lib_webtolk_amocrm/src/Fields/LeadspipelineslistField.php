<?php
/**
 * @package       WT Amocrm Library
 * @version       __DEPLOY_VERSION__
 * @Author        Sergey Tolkachyov, https://web-tolk.ru
 * @сopyright (c) 2022 - September 2023 Sergey Tolkachyov. All rights reserved.
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


class LeadspipelineslistField extends ListField
{

	protected $type = 'Leadspipelineslist';

	protected function getOptions()
	{
		$amocrm         = new Amocrm();
		$result_amo_crm = $amocrm->getLeadsPiplines();

		$options = array();
		if (empty($result_amo_crm))
		{
			return $options[] = HTMLHelper::_('select.option', 0, 'there is no tags in Amo CRM');
		}
		if (isset($result_amo_crm->_embedded) && isset($result_amo_crm->_embedded->pipelines))
		{
			foreach ($result_amo_crm->_embedded->pipelines as $pipeline)
			{
				$options[] = HTMLHelper::_('select.option', $pipeline->id, $pipeline->name . ' (id: ' . $pipeline->id . ')');
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
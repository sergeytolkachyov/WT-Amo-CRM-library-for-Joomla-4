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

class ContactstagslistField extends ListField
{

	protected $type = 'Contactstagslist';

	protected function getOptions()
	{
		$requset_options = [];
		if (isset($this->element['limit']) && !empty($this->element['limit'])) {
			$requset_options['limit'] = (((int) $this->element['limit'] > 250) ? 250 : $this->element['limit']); // 250 items max
		}
		$amocrm = new Amocrm();
		$result_amo_crm = $amocrm->tags()->getTags('contacts', $requset_options);
		$options = [];
		if (empty($result_amo_crm)) {
			return HTMLHelper::_('select.option', 'there is no tags in Amo CRM');
		}
		if (isset($result_amo_crm->_embedded) && isset($result_amo_crm->_embedded->tags)) {
			foreach ($result_amo_crm->_embedded->tags as $contact_tag) {
				$options[] = HTMLHelper::_('select.option', $contact_tag->id, $contact_tag->name . ' (id: ' . $contact_tag->id . ')');
			}

			return $options;
		} else if (isset($result_amo_crm->error_code)) {
			Factory::getApplication()->enqueueMessage($result_amo_crm->error_code . ' ' . $result_amo_crm->error_message, 'error');

			return $options;
		}

        return $options;
	}
}

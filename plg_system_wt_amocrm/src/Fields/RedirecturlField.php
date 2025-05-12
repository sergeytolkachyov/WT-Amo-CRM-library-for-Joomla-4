<?php
/**
 * @package       WT Amocrm Library
 * @version       1.3.0-alpha2
 * @Author        Sergey Tolkachyov, https://web-tolk.ru
 * @copyright  (c) 2022 - May 2025 Sergey Tolkachyov. All rights reserved.
 * @license       GNU/GPL3 http://www.gnu.org/licenses/gpl-3.0.html
 * @since         1.0.0
 */

namespace Joomla\Plugin\System\Wt_amocrm\Fields;
defined('_JEXEC') or die;

use Joomla\CMS\Form\FormField;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;


class RedirecturlField extends FormField
{

	protected $type = 'Redirecturl';

	/**
	 * Method to get the field input markup for a spacer.
	 * The spacer does not have accept input.
	 *
	 * @return  string  The field input markup.
	 *
	 * @since   1.7.0
	 */
	protected function getInput()
	{
		$wa      = Factory::getApplication()->getDocument()->getWebAssetManager();
		$wa->useScript('plg_system_wt_amocrm.copytextfield');

		return '<div class="input-group">
					<input
						type="text"
						class="form-control"
						name="'. $this->__get('name').'"
						id="'.$this->__get('id').'"
						readonly
						value="'.Uri::root().'index.php?option=com_ajax&plugin=wt_amocrm&group=system&format=raw" 
					>
					<button
						class="btn btn-primary"
						type="button"
						id="link-copy"
						data-webtolk-amocrm-copy-field-value
						title="'. Text::_('JLIB_HTML_BATCH_COPY').'"> '.Text::_('JLIB_HTML_BATCH_COPY').'
					</button>
				</div>';
	}

	/**
	 * @return  string  The field label markup.
	 *
	 * @since   1.7.0
	 */
	protected function getLabel()
	{
		return Text::_(($this->element['label'] ? (string) $this->element['label'] : (string) $this->element['name']));
	}

	/**
	 * @return  string  The field label markup.
	 *
	 * @since   1.7.0
	 */
	protected function getTitle()
	{
		return $this->getLabel();
	}
}


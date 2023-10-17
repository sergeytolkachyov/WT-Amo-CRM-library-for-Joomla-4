<?php
/**
 * @package       WT Amocrm Library
 * @version       1.2.0
 * @Author        Sergey Tolkachyov, https://web-tolk.ru
 * @сopyright (c) 2022 - October 2023 Sergey Tolkachyov. All rights reserved.
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
		$wa->addInlineStyle("
			.plugin-info-img-svg:hover * {
				cursor:pointer;
			}
		")->addInlineScript(
			"
				if (!window.Joomla) {
				  throw new Error('Joomla API was not properly initialised!');
				}
				
				const copyToClipboardFallback = input => {
				  input.focus();
				  input.select();
				
				  try {
					const copy = document.execCommand('copy');
				
					if (copy) {
					  Joomla.renderMessages({
						message: [Joomla.Text._('Copied!')]
					  });
					} else {
					  Joomla.renderMessages({
						error: [Joomla.Text._('Copy failed!')]
					  });
					}
				  } catch (err) {
					Joomla.renderMessages({
					  error: [err]
					});
				  }
				};
				
				const copyToClipboard = () => {
				  const button = document.getElementById('link-copy');
				  button.addEventListener('click', ({
					currentTarget
				  }) => {
					const input = currentTarget.previousElementSibling;
				
					if (!navigator.clipboard) {
					  copyToClipboardFallback(input);
					  return;
					}
				
					navigator.clipboard.writeText(input.value).then(() => {
					  Joomla.renderMessages({
						message: [Joomla.Text._('Copied!')]
					  });
					}, () => {
					  Joomla.renderMessages({
						error: [Joomla.Text._('Copy fail!')]
					  });
					});
				  });
				};
				
				const onBoot = () => {
				  copyToClipboard();
				  document.removeEventListener('DOMContentLoaded', onBoot);
				};
				
				document.addEventListener('DOMContentLoaded', onBoot);
				"
		);



		return $html = '<div class="input-group">
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


?>




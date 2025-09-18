<?php
/**
 * @package    WT Amocrm Library
 * @version    1.3.0
 * @Author     Sergey Tolkachyov, https://web-tolk.ru
 * @copyright  (c) 2022 - May 2025 Sergey Tolkachyov. All rights reserved.
 * @license    GNU/GPL3 http://www.gnu.org/licenses/gpl-3.0.html
 * @since      1.3.0
 */

namespace Joomla\Plugin\System\Wt_amocrm\Fields;

use Joomla\CMS\Form\FormField;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Factory;
use Webtolk\Amocrm\Amocrm;

defined('_JEXEC') or die;

class WebhookurlField extends FormField
{

    protected $type = 'Redirecturl';

    /**
     * Method to get the field input markup for a spacer.
     * The spacer does not accept input.
     *
     * @return  string  The field input markup.
     *
     * @since   1.3.0
     */
    protected function getInput()
    {
        $wa = Factory::getApplication()->getDocument()->getWebAssetManager();
        $wa->useScript('plg_system_wt_amocrm.copytextfield');

        $data = $this->form->getData();
        $webhook_token = $data->get('params.webhook_token', '');

        if (empty($webhook_token)) {
            $html = '<div class="alert alert-info">' . Text::_('PLG_WT_AMOCRM_FIELD_WEBHOOK_URL_EMPTY_WEBHOOK_TOKEN_DESC') . '</div>';
        } else {
            $url = (new Amocrm())->webhooks()->getJoomlaWebhookUrl();

            $html = '<div class="input-group">
                <input
                    type="text"
                    class="form-control"
                    name="' . $this->__get('name') . '"
                    id="' . $this->__get('id') . '"
                    readonly
                    value="' . $url . '" 
                >
                <button
                    class="btn btn-primary"
                    type="button"
                    data-webtolk-amocrm-copy-field-value
                    title="' . Text::_('JLIB_HTML_BATCH_COPY') . '"> ' . Text::_('JLIB_HTML_BATCH_COPY') . '
                </button>
            </div>';
        }

        return $html;
    }

    /**
     * @return  string  The field label markup.
     *
     * @since   1.3.0
     */
    protected function getTitle()
    {
        return $this->getLabel();
    }

    /**
     * @return  string  The field label markup.
     *
     * @since   1.3.0
     */
    protected function getLabel()
    {
        return Text::_(($this->element['label'] ? (string)$this->element['label'] : (string)$this->element['name']));
    }
}

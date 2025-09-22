<?php
/**
 * @package    WT Amocrm Library
 * @version    1.3.0
 * @Author     Sergey Tolkachyov, https://web-tolk.ru
 * @copyright  (c) 2022 - May 2025 Sergey Tolkachyov. All rights reserved.
 * @license    GNU/GPL3 http://www.gnu.org/licenses/gpl-3.0.html
 * @since      1.0.0
 */

namespace Webtolk\Amocrm\Fields;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Form\Field\ListField;
use Webtolk\Amocrm\Amocrm;

defined('_JEXEC') or die;

class ContactcustomfieldslistField extends ListField
{

	protected $type = 'Contactcustomfieldslist';
    
    /**
     * @var bool $hidenone
     * @since 1.3.0
     */
    private bool $hidenone = false;

    /**
     * Method to attach a Form object to the field.
     *
     * @param   \SimpleXMLElement  $element  The SimpleXMLElement object representing the `<field>` tag for the form field object.
     * @param   mixed              $value    The form field value to validate.
     * @param   string             $group    The field name group control value. This acts as an array container for the field.
     *                                       For example if the field has name="foo" and the group value is set to "bar" then the
     *                                       full field name would end up being "bar[foo]".
     *
     * @return  bool  True on success.
     *
     * @see     FormField::setup()
     * @since   5.1.0
     */
    public function setup(\SimpleXMLElement $element, $value, $group = null)
    {
        $return = parent::setup($element, $value, $group);

        if ($return) {
            // Check if it's using the old way
            $this->hidenone = (string) $this->element['hidenone'] == 'true' ? true : false;
        }

        return $return;
    }
    
	protected function getOptions()
	{
		$amocrm = new Amocrm();
		$result_amo_crm = $amocrm->customfields()->getContactsCustomFields();
		$options = [];
		if (empty($result_amo_crm)) {
			return $options[] = HTMLHelper::_('select.option', 0, 'there is no custom_fields in Amo CRM for contacts');
        }

        if (!$this->hidenone) {
            $options[] = HTMLHelper::_('select.option', '-1', Text::alt('JOPTION_DO_NOT_USE', preg_replace('/[^a-zA-Z0-9_\-]/', '_', $this->fieldname)));
        }
        
		if (isset($result_amo_crm->_embedded) && isset($result_amo_crm->_embedded->custom_fields)) {
			foreach ($result_amo_crm->_embedded->custom_fields as $contact_custom_field) {
				$options[] = HTMLHelper::_('select.option', $contact_custom_field->id, $contact_custom_field->name . ' (type: ' . $contact_custom_field->type . ')');
			}

			return $options;

		} else if (isset($result_amo_crm->error_code)) {
			Factory::getApplication()->enqueueMessage($result_amo_crm->error_code . ' ' . $result_amo_crm->error_message, 'error');

			return $options;
		}

        return $options;
	}
}

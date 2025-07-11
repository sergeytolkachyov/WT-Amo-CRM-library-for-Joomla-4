<?php
/**
 * @package     Webtolk\Amocrm\Fields
 * @subpackage
 *
 * @copyright   A copyright
 * @license     A "Slug" license name e.g. GPL2
 */

namespace Webtolk\Amocrm\Fields;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\ModalSelectField;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;

use Webtolk\Amocrm\Amocrm;

use function defined;

defined('_JEXEC') or die;

class ContactselectmodalField extends ModalSelectField
{
    protected $type = 'Contactselectmodal';

    /**
     * Method to attach a Form object to the field.
     *
     * @param   \SimpleXMLElement  $element  The SimpleXMLElement object representing the `<field>` tag for the form field object.
     * @param   mixed              $value    The form field value to validate.
     * @param   string             $group    The field name group control value.
     *
     * @return  boolean  True on success.
     *
     * @see     FormField::setup()
     * @since   5.0.0
     */
    public function setup(\SimpleXMLElement $element, $value, $group = null)
    {

        // Получаем само поле
        $result = parent::setup($element, $value, $group);

        if (!$result)
        {
            return $result;
        }

        $urlSelect = (new Uri())->setPath(Uri::base(true) . '/index.php');
        $urlSelect->setQuery([
            'option'                => 'com_ajax',
            'plugin'                => 'wt_amocrm',
            'group'                 => 'system',
            'format'                => 'html',
            'tmpl'                  => 'component',
            'action'                => 'modalselect',
            'entity'                => 'contacts',
            'action_type'           => 'internal',
            Session::getFormToken() => '1'
        ]);

        $modalTitle = Text::_('LIB_WTAMOCRM_FIELD_CONTACT_MODAL_SELECT_CHOOSE_CONTACT');
        $this->urls['select'] = (string) $urlSelect;

        $this->modalTitles['select'] = $modalTitle;

        // hint - подсказка placeholder в HTML поля.
        $this->hint = $this->hint ?: Text::_('LIB_WTAMOCRM_FIELD_CONTACT_MODAL_SELECT_CHOOSE_CONTACT');

        return $result;
    }

    /**
     * Метод показывает название выбранного контакта в поле-плейсхолдере.
     *
     * @return string
     *
     * @since   5.0.0
     */
    protected function getValueTitle()
    {
        $value = (int) $this->value ?: ''; // Это id материала или товара или...
        $title = '';

        if ($value)
        {
            try
            {
                $amocrm = new Amocrm();
                $contact = $amocrm->contacts()->getContactById($value);
                if(isset($contact->error_code)) {
                    $title = $contact->error_code.' - '.$contact->error_message;
                } else {
                    $title = $contact->name;
                }
            }
            catch (\Throwable $e)
            {
                Factory::getApplication()->enqueueMessage($e->getMessage(), 'error');
            }
        }

        return $title ?: $value;
    }
}
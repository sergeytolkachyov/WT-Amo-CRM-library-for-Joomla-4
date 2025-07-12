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

class EntitymodalselectField extends ModalSelectField
{
    protected $type = 'Entitymodalselect';

    /**
     * Entity by default. Set it by `entity = "leads|contacts|tags"` in XML
     *
     * @var string
     * @since 1.3.0
     */
    protected string $entity = 'contacts';
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
        $result = parent::setup($element, $value, $group);

        if (!$result)
        {
            return $result;
        }
        /** @var string $entity leads, contacts etc. */
        $entity = $this->entity = (!empty($this->element['entity'])) ? (string)$this->element['entity'] : 'contacts';

        $urlSelect = (new Uri())->setPath(Uri::base(true) . '/index.php');
        $query_params = [
            'option'                => 'com_ajax',
            'plugin'                => 'wt_amocrm',
            'group'                 => 'system',
            'format'                => 'html',
            'tmpl'                  => 'component',
            'action'                => 'modalselect',
            'entity'                => $entity,
            'action_type'           => 'internal',
            Session::getFormToken() => '1'
        ];

        $urlSelect->setQuery($query_params);
        $title = Text::_('LIB_WTAMOCRM_FIELD_ENTITY_MODAL_SELECT_CHOOSE_'.strtoupper($entity));

        $this->urls['select'] = (string) $urlSelect;

        $this->modalTitles['select'] = $title;

        // hint - подсказка placeholder в HTML поля.
        $this->hint = $this->hint ?: $title;

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
        $value = (int) $this->value ?: ''; // Это id сущности

        $title = '';

        if ($value)
        {
            try
            {
                $amocrm = new Amocrm();
                switch ($this->entity) {
                    case 'leads':
                        $entity = $amocrm->leads()->getLeadById($value);
                        break;
                    case 'contacts':
                    default:
                        $entity = $amocrm->contacts()->getContactById($value);
                        break;
                }

                if(isset($contact->error_code)) {
                    $title = $entity->error_code.' - '.$entity->error_message;
                } else {
                    $title = $entity->name;
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
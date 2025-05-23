<?php
/**
 * @package       WT Amocrm Library
 * @version       1.3.0-alpha2
 * @Author        Sergey Tolkachyov, https://web-tolk.ru
 * @copyright  (c) 2022 - May 2025 Sergey Tolkachyov. All rights reserved.
 * @license       GNU/GPL3 http://www.gnu.org/licenses/gpl-3.0.html
 * @since         1.0.0
 */

namespace Webtolk\Amocrm\Fields;
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\FormField;
use Joomla\CMS\Language\Text;
use Webtolk\Amocrm\Amocrm;
use Webtolk\Amocrm\Helper\UserHelper as AmocrmUserHelper;

class ContactinfoField extends FormField
{

	protected $type = 'Contactinfo';
    protected $layout = 'libraries.webtolk.amocrm.fields.contactinfo';

    /**
     * Method to get the data to be passed to the layout for rendering.
     *
     * @return  array
     *
     * @since 1.3.0
     */
    protected function getLayoutData(): array
    {
        $layoutData = parent::getLayoutData();

        $joomlauseridsource = (!empty($this->element['joomlauseridsource'])) ? (string)$this->element['joomlauseridsource'] : '';

        // `joomlauserid` param has a higher priority
        if(!empty($this->element['joomlauserid'])) {
            $joomlauserid = (int)$this->element['joomlauserid'];
        } elseif(!isset($this->element['joomlauserid']) && !empty($joomlauseridsource)) {
            $joomlauserid = Factory::getApplication()->getInput()->getInt($joomlauseridsource, 0);
        }

        $layoutData['has_joomla_user_id'] = false;
        $layoutData['has_amocrm_contact_id'] = false;
        $layoutData['has_error'] = false;
        $layoutData['amocrm_error'] = false;
        $layoutData['contact_info'] = false;
        $layoutData['showtags'] = (!empty($this->element['showtags']) && (string)$this->element['showtags'] == 'true') ? true : false;
        $layoutData['with'] = !empty($this->element['with']) ? (string)$this->element['with'] : '';

        if(empty($joomlauserid)) {
            $layoutData['label'] = '<span class="badge bg-danger"><i class="fa-solid fa-triangle-exclamation"></i></span> '.$layoutData['label'];
            $layoutData['message'] = Text::_('LIB_WTAMOCRM_FIELD_AMOCRMCONTACTINFO_NO_JOOMLA_USER_ID');
            $layoutData['description'] = Text::_('LIB_WTAMOCRM_FIELD_AMOCRMCONTACTINFO_NO_JOOMLA_USER_ID_DESC');
            return $layoutData;
        }

        $layoutData['has_joomla_user_id'] = true;
        $contact_id = AmocrmUserHelper::checkIsAmoCRMUser($joomlauserid);

        if(!$contact_id) {
            $layoutData['label'] = '<span class="badge bg-warning"><i class="fa-solid fa-triangle-exclamation"></i></span> '.$layoutData['label'];
            $layoutData['message'] = Text::_('LIB_WTAMOCRM_FIELD_AMOCRMCONTACTINFO_NO_AMOCRM_CONTACT_ID');
            return $layoutData;
        }

        $layoutData['has_amocrm_contact_id'] = true;
        $amocrm         = new Amocrm();
        $result_amo_crm = $amocrm->contacts()->getContactById($contact_id, $layoutData['with']);
        if (!empty($result_amo_crm->error_code))
        {
            $layoutData['label'] = '<span class="badge bg-danger"><i class="fa-solid fa-triangle-exclamation"></i></span> '.$layoutData['label'];
            $layoutData['has_error'] = true;
            $layoutData['amocrm_error'] = $result_amo_crm;

        } else {

            $layoutData['contact_info'] = $result_amo_crm;
            $layoutData['contact_link'] = $amocrm->getRequest()->getAmoCRMHost()->setPath('/contacts/detail/'.$contact_id);

        }

        return $layoutData;
    }
}

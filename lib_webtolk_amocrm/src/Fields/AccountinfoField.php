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

use Joomla\CMS\Form\FormField;
use Webtolk\Amocrm\Amocrm;
use Webtolk\Amocrm\AmocrmClientException;

defined('_JEXEC') or die;

class AccountinfoField extends FormField
{

	protected $type = 'Accountinfo';
    protected $layout = 'libraries.webtolk.amocrm.fields.accountinfo';

    /**
     * Method to get the field input markup.
     *
     * @return  string  The field input markup.
     *
     * @since   1.7.0
     */
	protected function getInput()
	{
        return ' ';
	}

    /**
     * Method to get the field label markup.
     *
     * @return  string  The field label markup.
     *
     * @since   1.7.0
     */
    protected function getLabel()
    {
        if (empty($this->layout)) {
            throw new \UnexpectedValueException(\sprintf('%s has no layout assigned.', $this->name));
        }

        return $this->getRenderer($this->layout)->render($this->collectLayoutData());
    }

    /**
     * Method to get the data to be passed to the layout for rendering.
     *
     * @return  array
     *
     * @throws  AmocrmClientException
     * @since   1.3.0
     */
    protected function getLayoutData(): array
    {
        $layoutData = parent::getLayoutData();

        $layoutData['has_error'] = false;
        $layoutData['amocrm_error'] = false;
        $layoutData['amocrm_account_info'] = false;
        $layoutData['user_info'] = false;

        $amocrm = new Amocrm();

        $result_amo_crm = $amocrm->account()->getAccountInfo();
        if (!empty($result_amo_crm->error_code)) {
            $layoutData['has_error'] = true;
            $layoutData['amocrm_error'] = $result_amo_crm;

        } else {
            $layoutData['amocrm_account_info'] = $result_amo_crm;
        }

        if (!$layoutData['has_error']) {
            $layoutData['user_info'] = $amocrm->users()->getUserById($result_amo_crm->current_user_id);
        }

        return $layoutData;
    }
}

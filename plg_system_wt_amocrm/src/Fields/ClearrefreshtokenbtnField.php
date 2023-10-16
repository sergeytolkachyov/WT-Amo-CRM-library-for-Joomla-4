<?php
/**
 * @package       WT Amocrm Library
 * @version       __DEPLOY_VERSION__
 * @Author        Sergey Tolkachyov, https://web-tolk.ru
 * @сopyright (c) 2022 - September 2023 Sergey Tolkachyov. All rights reserved.
 * @license       GNU/GPL3 http://www.gnu.org/licenses/gpl-3.0.html
 * @since         1.0.0
 */

namespace Joomla\Plugin\System\Wt_amocrm\Fields;
defined('_JEXEC') or die;

//use Joomla\CMS\Form\Field\NoteField;
use Joomla\CMS\Form\FormField;
use Joomla\CMS\Helper\LibraryHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Factory;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;


class ClearrefreshtokenbtnField extends FormField
{

	protected $type = 'Clearrefreshtokenbtn';

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

		$url = new Uri(Uri::root());
		$url->setScheme('https');
		$url->setPath('/administrator/index.php');
		$url->setVar('option','com_ajax');
		$url->setVar('plugin','wt_amocrm');
		$url->setVar('group','system');
		$url->setVar('format','json');
		$url->setVar('action','clear_refresh_token');
		$url->setVar(Session::getFormToken(),'1');

		$wa      = Factory::getApplication()->getDocument()->getWebAssetManager();
		$wa->addInlineScript(
			"
				document.addEventListener('DOMContentLoaded', () => {
                let clearBtn = document.getElementById('clear_refresh_token_btn');
                clearBtn.addEventListener('click', () => {
                    Joomla.request({
                        url: '".$url->toString()."',
                        method: 'POST',
                        onSuccess: function (response, xhr){
                            if (response !== ''){
                                let responseData = JSON.parse(response);
                                let answerContainer = document.getElementById('clear_refresh_token_response_container');
                                answerContainer.innerText = responseData.data;
                            }
                        },
                    })
                });
            });
				"
		);
		$lib_params = LibraryHelper::getParams('Webtolk/Amocrm');
		$refresh_token_date = $lib_params->get('refresh_token_date','');
		$html = ['<div class="d-flex align-items-center">'];

		if(property_exists($refresh_token_date,'date') && !empty($refresh_token_date->date)){
			$html[] = '<span class="badge bg-success">Refresh token date:</span> <span class="badge bg-info text-white">'.$refresh_token_date->date.'</span><br/>';
		}

		$html[] = '<button type="button" class="btn btn-sm button-apply btn-warning ms-2" id="clear_refresh_token_btn">Clear</button>
			<span id="clear_refresh_token_response_container" class="text-success ms-2"></span>
			';
		$html[] = '</div>';


		return implode('',$html);
	}

	/**
	 * @return  string  The field label markup.
	 *
	 * @since   1.7.0
	 */
	protected function getLabel()
	{
		return $this->element['label'] ? (string) $this->element['label'] : (string) $this->element['name'];
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




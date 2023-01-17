<?php
/**
 * Library to connect to Amo CRM service.
 * @package     Webtolk
 * @subpackage  AmoCRM
 * @author      Sergey Tolkachyov
 * @copyright   Copyright (C) Sergey Tolkachyov, 2022-2023. All rights reserved.
 * @version     1.1.1
 * @license     GNU General Public License version 3 or later.
 */

namespace Webtolk\Amocrm\Fields;
defined('_JEXEC') or die;

use Joomla\CMS\Date\Date;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\NoteField;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\PluginHelper;
use Webtolk\Amocrm\Amocrm;
use Webtolk\Amocrm\Chat;

class AccountinfoField extends NoteField
{

	protected $type = 'Accountinfo';

	protected function getInput()
	{

		$amocrm = new Amocrm();
		$result_amo_crm = $amocrm->getAccountInfo();
		if(isset($result_amo_crm->error_code) && !empty($result_amo_crm->error_code)){
			return '<div class="alert alert-danger row">
						<div class="col-2 h1">'.$result_amo_crm->error_code.'</div>
						<div class="col-10">'.$result_amo_crm->error_message.'</div>
					</div>';
		}

		if(!empty($result_amo_crm)){

			$user_info = $amocrm->getUserById($result_amo_crm->current_user_id);
			$created_at = (new Date($result_amo_crm->created_at));
			$updated_at = (new Date($result_amo_crm->updated_at));
			$user_name = $user_info->name;
			$user_email = $user_info->email;
			$user_is_admin = $user_info->rights->is_admin;
		} else {
			$created_at = 'no data';
			$updated_at = 'no data';
			$user_name = 'no data';
			$user_email = 'no data';
			$user_is_admin = 'no data';
		}

		return $html = '<div class="d-flex shadow p-4">
			<div class="flex-shrink-0">
				<h3>'.$result_amo_crm->name.'</h3>
			</div>
			<div class="flex-grow-1 ms-3">
				<span class="badge bg-success text-white">Created: ' .  $created_at . '</span>
				<span class="badge bg-success text-white">Updated: ' . $updated_at . '</span>
			</div>
			<div class="flex-shrink-0">
				<h3>User info</h3>
			</div>
			<div class="flex-grow-1 ms-3">
				<span class="badge bg-primary text-white">Name: ' .  $user_name . '</span>
				<span class="badge bg-secondary text-white">Email: ' . $user_email . '</span>
				<span class="badge bg-warning text-white">Is admin: ' . (($user_is_admin == '1') ? Text::_('JYES') : Text::_('JNO')) . '</span>

			</div>
		</div>';


	}
}

?>
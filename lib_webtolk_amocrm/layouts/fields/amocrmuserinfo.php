<?php
/**
 * @package    WT Amocrm Library
 * @version    1.3.0
 * @Author     Sergey Tolkachyov, https://web-tolk.ru
 * @copyright  (c) 2022 - May 2025 Sergey Tolkachyov. All rights reserved.
 * @license    GNU/GPL3 http://www.gnu.org/licenses/gpl-3.0.html
 * @since      1.0.0
 */

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

defined('_JEXEC') or die;

extract($displayData);

/**
 * Layout variables
 * -----------------
 * @var  bool        $has_error            A flag if the AmoCRM response contains errors
 * @var  array|bool  $amocrm_error         AmoCRM response errors if exists
 * @var  array|bool  $amocrm_account_info  AmoCRM account info
 * @var  array|bool  $user_info            AmoCRM user info for selected integration
 */

// we need a `</div>` for a full width without standard Joomla field label
?>
<?php
// Uncomment it to see all the field data.
// dump($displayData);
?>
<?php
if ($has_error): ?>
    <div class="alert alert-danger row w-100">
        <div class="col-2 h1"><?php echo $amocrm_error->error_code; ?></div>
        <div class="col-10"><?php echo $amocrm_error->error_message; ?></div>
    </div>
<?php
else:
    $user_is_admin = $user_info->rights->is_admin;
?>
    <div class="row g-0 shadow w-100 p-3">
        <div class="col-12 col-md-5 col-lg-3 col-xl-2">
            <h4 class="text-wrap"><?php echo $amocrm_account_info->name; ?></h4>
        </div>
        <div class="col-12 col-md-7 col-lg-3 col-xl-4 d-flex flex-lg-column mb-3">
            <span class="me-1">
                <span class="badge bg-secondary"><?php echo Text::_('JGLOBAL_CREATED'); ?>:</span> <span class="badge bg-primary"><?php echo HTMLHelper::date($amocrm_account_info->created_at, Text::_('DATE_FORMAT_LC5')); ?></span>
            </span>
            <span class="me-1">
                <span class="badge bg-secondary"><?php echo Text::_('JGLOBAL_MODIFIED'); ?>:</span> <span class="badge bg-primary"><?php echo HTMLHelper::date($amocrm_account_info->updated_at, Text::_('DATE_FORMAT_LC5')); ?></span>
            </span>
        </div>
        <div class="col-12 col-md-5 col-lg-3 col-xl-2">
            <h4><?php echo Text::_('LIB_WTAMOCRM_FIELD_ACCOUNTINFO_USER_INFO'); ?></h4>
        </div>
        <div class="col-12 col-md-7 col-lg-3 col-xl-4 d-flex flex-lg-column mb-3">
            <span class="me-1">
                <span class="badge bg-primary"><?php echo Text::_('LIB_WTAMOCRM_FIELD_ACCOUNTINFO_NAME'); ?>:</span> <span class="badge bg-secondary"><?php
                    echo $user_info->name; ?>
                </span>
            </span>
            <span class="me-1">
                <span class="badge bg-primary"><?php echo Text::_('LIB_WTAMOCRM_FIELD_ACCOUNTINFO_EMAIL'); ?>:</span> <span class="badge bg-secondary"> <?php
                    echo $user_info->email; ?>
                </span>
            </span>
            <span class="me-1">
                <span class="badge bg-primary"><?php echo Text::_('LIB_WTAMOCRM_FIELD_ACCOUNTINFO_IS_ADMIN'); ?></span> <span class="badge bg-<?php echo ($user_is_admin) ? 'success' : 'warning'; ?>"><?php
                    echo Text::_(($user_is_admin) ? 'JYES' : 'JNO'); ?>
                </span>
            </span>
        </div>
    </div>
<?php
endif; ?>
<?php
// we need an unclosed `<div>` here for a full width without standard Joomla field label
?>
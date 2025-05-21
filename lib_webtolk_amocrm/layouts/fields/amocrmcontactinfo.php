<?php
/**
 * @package        WT Amocrm Library
 * @version        1.3.0-alpha2
 * @Author         Sergey Tolkachyov, https://web-tolk.ru
 * @copyright  (c) 2022 - May 2025 Sergey Tolkachyov. All rights reserved.
 * @license        GNU/GPL3 http://www.gnu.org/licenses/gpl-3.0.html
 * @since          1.0.0
 */

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

defined('_JEXEC') or die;

extract($displayData);

/**
 * Layout variables
 * -----------------
 * @var   bool       $has_joomla_user_id
 * @var   bool       $has_amocrm_contact_id
 * @var   array|bool $has_error    A flag if the AmoCRM response contains errors
 * @var   array|bool $amocrm_error AmoCRM error info
 * @var   array|bool $contact_info AmoCRM contact info
 * @var   string     $message      Field message
 * @var   string     $contact_link Link to contact in AmoCRM
 * @var   bool       $showtags     Hide or show contact tags
 *
 */

?>

<?php
// Uncomment it to see all the field data.
// dump($displayData);
?>

<?php
if (!$has_joomla_user_id && !empty($message)) : ?>
    <p class="text-danger fw-bold"><?php
        echo $message; ?></p>
    <?php
    return;
endif; ?>

<?php
if (!$has_amocrm_contact_id && !empty($message)) : ?>
    <p><?php
        echo $message; ?></p>
    <?php
    return;
endif; ?>

<?php

if ($has_error): ?>
    <p class="text-danger"><?php
        echo $amocrm_error->error_code; ?>: <?php
        echo $amocrm_error->error_message; ?></p>
    <?php
    return;
endif; ?>

<?php echo HTMLHelper::link($contact_link, $contact_info->name, ['target' => '_blank']); ?> <span class="badge bg-primary">id: <?php echo $contact_info->id; ?></span>

<?php
if($showtags && !empty($contact_info->_embedded->tags)) :
    foreach ($contact_info->_embedded->tags as $tag): ?>
        <span class="badge bg-light text-dark border border-1">#<?php echo $tag->name;?></span>
<?php endforeach;
endif;

<?php
/**
 * @package    WT Amo CRM library package
 * @version    1.3.1
 * @Author     Sergey Tolkachyov, https://web-tolk.ru
 * @copyright  (c) 2022 - September 2025 Sergey Tolkachyov. All rights reserved.
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
 * @var  bool    $onlycurrentsite  A list of webhooks for the current site or a complete one
 * @var  object  $amocrm_webhooks  List of AmoCRM webhooks. if $onlycurrentsite is true then
 *                                 the $amocrm_webhooks array contains webhooks only for
 *                                 the current site.
 */

$total_webhooks = $amocrm_webhooks->_total_items ?? 0;
$webhooks = $amocrm_webhooks->_embedded->webhooks ?? null;

?>

<div class="p-3 border border-1">
    <div class="d-flex flex-column flex-md-row justify-content-md-between">
        <span class="mb-3">
            <span class="badge bg-secondary"><?php
                echo Text::_('LIB_WTAMOCRM_FIELD_WEBHOOKSLIST_WEBHOOKS_TOTAL'); ?></span>
            <span class="badge bg-primary"><?php
                echo $total_webhooks; ?></span>
        </span>
        <span>
            <span class="badge bg-secondary"><?php
                echo Text::_('LIB_WTAMOCRM_FIELD_WEBHOOKSLIST_WEBHOOKS_SHOWN_FOR'); ?></span>
            <span class="badge bg-primary"><?php
                echo Text::_(
                    'LIB_WTAMOCRM_FIELD_WEBHOOKSLIST_WEBHOOKS_SHOWN_ONLYCURRENTSITE_' . ($onlycurrentsite ? 'TRUE' : 'FALSE')
                ); ?></span>
        </span>
    </div>
    <?php
    if (!empty($webhooks)): ?>
        <ul class="list-group list-group-flush">
            <?php
            foreach ($webhooks as $webhook) : ?>
                <li class="list-group-item">
                    <p class="mb-3"><strong>URL:</strong> <code class="text-wrap"><?php
                            echo $webhook->destination; ?></code></p>
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <span><span class="badge bg-secondary"><?php
                                echo Text::_('JSTATUS'); ?>:</span>
                            <?php
                            if ($webhook->disabled) {
                                $webhook_status_css  = 'danger';
                                $webhook_status_text = Text::_('JDISABLED');
                            } else {
                                $webhook_status_css  = 'success';
                                $webhook_status_text = Text::_('JENABLED');
                            }
                            ?>
                        <span class="badge bg-<?php
                        echo $webhook_status_css ?>"><?php
                            echo $webhook_status_text; ?></span>
                        </span>

                        <span>
                            <span class="badge bg-secondary"><?php
                                echo Text::_('JGLOBAL_CREATED'); ?>:</span> <span class="badge bg-primary"><?php
                                echo HTMLHelper::date($webhook->created_at, Text::_('DATE_FORMAT_LC5')); ?></span>
                        </span>
                        <span>
                            <span class="badge bg-secondary"><?php
                                echo Text::_('JGLOBAL_MODIFIED'); ?>:</span> <span class="badge bg-primary"><?php
                                echo HTMLHelper::date(
                                    $webhook->updated_at,
                                    Text::_('DATE_FORMAT_LC5')
                                ); ?></span></span>
                    </div>
                    <div><span class="badge bg-warning"><?php
                            echo Text::_('LIB_WTAMOCRM_FIELD_WEBHOOKSLIST_WEBHOOK_AMOCRM_EVENTS'); ?>:</span> <?php
                        echo !empty($webhook->settings) ? '<span class="badge bg-info">' . implode(
                                '</span> <span class="badge bg-info">',
                                $webhook->settings
                            ) . '</span>' : ''; ?> </div>

                </li>
            <?php
            endforeach; ?>
        </ul>
    <?php
    else: ?>
        <div class="alert alert-info">
            <?php
            echo Text::_('LIB_WTAMOCRM_FIELD_WEBHOOKSLIST_THERE_IS_NO_WEBHOOKS'); ?>
        </div>
    <?php
    endif; ?>
</div>

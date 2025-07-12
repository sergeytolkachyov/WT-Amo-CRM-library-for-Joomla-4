<?php
/**
 * @package        WT Amocrm Library
 * @version        1.3.0-alpha2
 * @Author         Sergey Tolkachyov, https://web-tolk.ru
 * @copyright  (c) 2022 - May 2025 Sergey Tolkachyov. All rights reserved.
 * @license        GNU/GPL3 http://www.gnu.org/licenses/gpl-3.0.html
 * @since          1.0.0
 */

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;

defined('_JEXEC') or die;

extract($displayData);
/**
 * @var string $entity contacts, leads etc
 * @var array  $data   request result data
 */

$hasData = !empty($data);

if ($hasData) {
    extract($data);
    /**
     * Layout variables
     * -----------------
     * @var   string $error_code
     * @var   string $error_message
     *
     * @var   int    $_page     A page of items list pagintation
     * @var   array  $_links    AmoCRM error info
     * @var   array  $_embedded Main AmoCRM data array
     *
     */

// Uncomment it to see all the field data.

    $amoData = $data['_embedded'];
}


$app = Factory::getApplication();
$page = $app->getInput()->getInt('page',1);

$doc = $app->getDocument();
$doc->getWebAssetManager()
    ->useScript('core')
    ->registerAndUseScript(
        'plg_system_wt_amocrm.entitymodalselect',
        'plg_system_wt_amocrm/entitymodalselect.js'
    );
?>

<?php
if (array_key_exists('error_code', $displayData)): ?>
    <div class="alert alert-danger">
        <h4><?php
            echo $displayData['error_code']; ?></h4>
        <p><?php
            echo $displayData['error_message']; ?></p>
    </div>

    <?php
    return;
endif; ?>

<form
        action="index.php"
        id="adminForm"
        name="adminForm" class="container">
    <input type="hidden" name="option" value="com_ajax"/>
    <input type="hidden" name="plugin" value="wt_amocrm"/>
    <input type="hidden" name="group" value="system"/>
    <input type="hidden" name="format" value="html"/>
    <input type="hidden" name="tmpl" value="component"/>
    <input type="hidden" name="action" value="modalselect"/>
    <input type="hidden" name="entity" value="contacts"/>
    <input type="hidden" name="action_type" value="internal"/>
    <?php
    if ($hasData && (array_key_exists('next', $data['_links']) || array_key_exists('prev', $data['_links']))): ?>
        <input type="hidden" name="page" value="<?php
        echo $_page; ?>"/>
    <?php
    else: ?>
        <input type="hidden" name="page" value="<?php
        echo $_page; ?>"/>
    <?php
    endif; ?>
    <input type="hidden" name="<?php
    echo Session::getFormToken(); ?>" value="1"/>
    <div class="row mb-3">

        <div class="col-12 col-lg-3 ms-auto">
            <div class="input-group mb-3">
                <span class="input-group-text"><?php
                    echo Text::_('JGLOBAL_DISPLAY_NUM'); ?></span>
                <input type="number"
                       min="1"
                       max="250"
                       name="limit"
                       value="<?php
                       echo $app->getInput()->getInt('limit', 50); ?>"
                       class="form-control"/>
                <button class="btn btn-primary" type="button" onclick="Joomla.submitform();return false;"><span
                            class="filter-search-bar__button-icon icon-search" aria-hidden="true"></span></button>
            </div>
        </div>
    </div>
    <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 row-cols-xl-5 g-3">
        <?php
        if ($hasData) :
            foreach ($amoData[$entity] as $item): ?>
                <div class="col">
                    <div class="card h-100 shadow-sm">
                        <div class="card-body">
                            <h4><?php
                                echo $item['name']; ?></h4>
                        </div>
                        <div class="card-footer bg-transparent d-flex justify-content-between">
                            <a href="#" data-entity-id="<?php
                            echo $item['id']; ?>" data-entity-title="<?php
                            echo htmlspecialchars($item['name']); ?>" class="stretched-link"><?php
                                echo Text::_('JSELECT'); ?></a>
                            <span class="text-muted">#<?php
                                echo $item['id']; ?></span>
                        </div>
                    </div>
                </div>
            <?php
            endforeach;
        else :
            ?>
            <div class="alert alert-warning w-100"><?php
                echo Text::_('JLIB_FORM_ERROR_NO_DATA'); ?></div>
        <?php
        endif;
        ?>
    </div>
    <div class="fixed-bottom bg-white border border-top">
        <div class="container">
            <div class="d-flex">
                <div class="btn-group">
                    <?php
                    if (!$hasData || array_key_exists('first', $data['_links'])): ?>
                        <button class="btn btn-outline-secondary"
                                onclick="document.adminForm.page.value=1; Joomla.submitform();return false;"><span
                                    class="icon-angle-double-left" aria-hidden="true"></span> <?php
                            echo Text::_('JLIB_HTML_START'); ?></button>
                    <?php
                    endif; ?>

                    <?php
                    if (!$hasData || array_key_exists('prev', $data['_links'])): ?>
                        <button class="btn btn-outline-primary" onclick="document.adminForm.page.value=<?php
                        echo $hasData ? $_page - 1 : $page - 1; ?>; Joomla.submitform();return false;"><span class="icon-angle-left"
                                                                                      aria-hidden="true"></span> <?php
                            echo Text::_('JPREV'); ?></button>
                    <?php
                    endif; ?>
                    <?php
                    if ($hasData && array_key_exists('next', $data['_links'])): ?>
                        <button class="btn btn-outline-primary" onclick="document.adminForm.page.value=<?php
                        echo $_page + 1; ?>; Joomla.submitform();return false;"><?php
                            echo Text::_('JNEXT'); ?> <span class="icon-angle-right" aria-hidden="true"></span></button>
                    <?php
                    endif; ?>
                </div>
                <div class="ms-auto d-flex align-items-center">
                    <span><?php
                        echo Text::sprintf('JLIB_HTML_PAGE_CURRENT', $_page ?? $page); ?></span>
                </div>
            </div>
        </div>

    </div>
</form>
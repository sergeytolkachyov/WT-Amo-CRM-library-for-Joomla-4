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
 * @var string $entity contacts, leads etc
 * @var array $data requst result data
 */
extract($data);
/**
 * Layout variables
 * -----------------
 * @var   string     $error_code
 * @var   string     $error_message
 *
 * @var   array|bool $has_error    A flag if the AmoCRM response contains errors
 * @var   array|bool $amocrm_error AmoCRM error info
 * @var   array|bool $contact_info AmoCRM contact info
 * @var   string     $message      Field message
 * @var   string     $contact_link Link to contact in AmoCRM
 * @var   bool       $showtags     Hide or show contact tags
 * @var   string     $with         catalog_elements, leads and customers linked with contact
 *                                 see https://www.amocrm.ru/developers/content/crm_platform/contacts-api#with-88398e14-be90-44b7-91e0-6371e268833b-params
 *                                 This data will be in `_embedded` array in $contact_info
 */

// Uncomment it to see all the field data.
dump($displayData);
$amoData = $data['_embedded'];

?>

<?php
if (array_key_exists('error_code',$displayData)): ?>
    <div class="alert alert-danger">
        <h4><?php echo $displayData['error_code']; ?></h4>
        <p><?php echo $displayData['error_message']; ?></p>
    </div>

    <?php
    return;
endif; ?>

<form>
<div class="row mb-3">
    <div class="col-12 col-lg-3">поиск</div>
    <div class="col-12 col-lg-3">фильтр какой-нибудь</div>
</div>
<div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 row-cols-xl-5 g-3">
    <?php foreach ($amoData[$entity] as $item):?>
        <div class="col">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h4><?php echo $item['name'];?></h4>
                    <p class="text-muted">#<?php echo $item['id'];?></p>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
    <div class="fixed-bottom bg-white py-2 border border-top">Тут пагинация</div>
</form>
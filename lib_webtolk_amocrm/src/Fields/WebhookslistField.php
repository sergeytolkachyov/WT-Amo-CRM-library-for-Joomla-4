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

class WebhookslistField extends FormField
{

	protected $type = 'Webhookslist';

    protected $layout = 'libraries.webtolk.amocrm.fields.webhookslist';

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

        $webhooks = (new Amocrm())->webhooks();
        $onlycurrentsite = !empty($this->element['onlycurrentsite']) && (string) $this->element['onlycurrentsite'] == 'true';
        $filter = [];

        if ($onlycurrentsite && !empty($url = $webhooks->getJoomlaWebhookUrl())) {
            $filter['filter']['destination'] = $url;
        }

        $webhooks_list = $webhooks->getWebhooks($filter);
        $layoutData['onlycurrentsite'] = $onlycurrentsite;
        $layoutData['amocrm_webhooks'] = $webhooks_list;
        return $layoutData;
    }
}

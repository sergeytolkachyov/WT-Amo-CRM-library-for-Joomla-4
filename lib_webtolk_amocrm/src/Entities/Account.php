<?php
/**
 * AmoCRM account params.
 *
 * @see https://www.amocrm.ru/developers/content/crm_platform/account-info
 *
 * @package           WT Amocrm Library
 * @version           1.3.0-alpha2
 * @Author            Sergey Tolkachyov, https://web-tolk.ru
 * @copyright  (c)    2022 - May 2025 Sergey Tolkachyov. All rights reserved.
 * @license           GNU/GPL3 http://www.gnu.org/licenses/gpl-3.0.html
 * @since             1.3.0
 */

namespace Webtolk\Amocrm\Entities;

use Webtolk\Amocrm\AmocrmRequest;
use Webtolk\Amocrm\Interfaces\EntityInterface;

use function defined;

defined('_JEXEC') or die;

class Account implements EntityInterface
{
    /** @param AmocrmRequest $request */
    private AmocrmRequest $request;

    /**
     * Account constructor.
     * @param AmocrmRequest $request
     * @since 1.3.0
     */
    public function __construct(AmocrmRequest $request)
    {
        $this->request = $request;
    }

    /**
     * Get Amo CRM account info
     *
     * @return object
     *
     * @since 1.0.0
     */

    public function getAccountInfo(): object
    {
        return $this->request->getResponse('/account', null, 'GET');
    }
}
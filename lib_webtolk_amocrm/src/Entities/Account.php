<?php
/**
 * AmoCRM account params.
 *
 * @see        https://www.amocrm.ru/developers/content/crm_platform/account-info
 *
 * @package    WT Amo CRM library package
 * @version    1.3.1
 * @Author     Sergey Tolkachyov, https://web-tolk.ru
 * @copyright  (c) 2022 - September 2025 Sergey Tolkachyov. All rights reserved.
 * @license    GNU/GPL3 http://www.gnu.org/licenses/gpl-3.0.html
 * @since      1.3.0
 */

namespace Webtolk\Amocrm\Entities;

use Webtolk\Amocrm\AmocrmClientException;
use Webtolk\Amocrm\AmocrmRequest;
use Webtolk\Amocrm\Interfaces\EntityInterface;

defined('_JEXEC') or die;

class Account implements EntityInterface
{
    /** @var AmocrmRequest $request */
    private AmocrmRequest $request;

    /**
     * Account constructor.
     *
     * @param  AmocrmRequest  $request
     *
     * @since  1.3.0
     */
    public function __construct(AmocrmRequest $request)
    {
        $this->request = $request;
    }

    /**
     * Get Amo CRM account info
     *
     * @return  object
     *
     * @throws  AmocrmClientException
     * @since   1.0.0
     */
    public function getAccountInfo(): object
    {
        return $this->request->getResponse('/account', null, 'GET');
    }
}
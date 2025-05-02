<?php
/**
 * @package           WT Amocrm Library
 * @version           1.3.0-alpha2
 * @Author            Sergey Tolkachyov, https://web-tolk.ru
 * @copyright  (c) 2022 - May 2025 Sergey Tolkachyov. All rights reserved.
 * @license           GNU/GPL3 http://www.gnu.org/licenses/gpl-3.0.html
 * @since             1.0.0
 */

namespace Webtolk\Amocrm\Interface;

use Webtolk\Amocrm\AmocrmRequest;

use function defined;

defined('_JEXEC') or die;

interface EntityInterface
{
    public function __construct(AmocrmRequest $request);
}
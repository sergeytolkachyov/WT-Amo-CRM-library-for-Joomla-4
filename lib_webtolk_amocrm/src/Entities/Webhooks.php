<?php
/**
 * AmoCRM users
 *
 * @see        https://www.amocrm.ru/developers/content/crm_platform/users-api
 *
 * @package    WT Amo CRM library package
 * @version    1.3.1
 * @Author     Sergey Tolkachyov, https://web-tolk.ru
 * @copyright  (c) 2022 - September 2025 Sergey Tolkachyov. All rights reserved.
 * @license    GNU/GPL3 http://www.gnu.org/licenses/gpl-3.0.html
 * @since      1.3.0
 */

namespace Webtolk\Amocrm\Entities;

use Joomla\CMS\Uri\Uri;
use Webtolk\Amocrm\AmocrmClientException;
use Webtolk\Amocrm\AmocrmRequest;
use Webtolk\Amocrm\Interfaces\EntityInterface;
use Webtolk\Amocrm\Traits\DataErrorTrait;
use Webtolk\Amocrm\Traits\LogTrait;

defined('_JEXEC') or die;

class Webhooks implements EntityInterface
{
    use LogTrait;
    use DataErrorTrait;

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
     * Список вебхуков аккаунта.
     * ## Метод
     * GET /api/v4/webhooks
     *
     * Метод позволяет получить список установленных вебхуков в аккаунте.
     *  ## Ограничения
     * Метод доступен с правами администратора аккаунта.
     *
     * ## GET Параметры
     * - filter object Фильтр. Фильтрация данных в AmoCRM **оплачивается отдельно**.
     * - filter[destination] string Фильтр по точному адресу вебхука
     *
     * @param   array  $data
     *
     * @return  object
     *
     * @throws  AmocrmClientException
     * @since   1.3.0
     * @link    https://www.amocrm.ru/developers/content/crm_platform/webhooks-api
     */
    public function getWebhooks(array $data = []): object
    {
        if (isset($data['filter']['destination'])) {
            $data['filter']['destination'] = urlencode($data['filter']['destination']);
        }

        return $this->request->getResponse('/webhooks', $data, 'GET');
    }

    /**
     * Подписка на вебхук
     * ## Метод
     * POST /api/v4/webhooks
     * ## Описание
     * Метод позволяет подписываться на события, информация о которых придет на указанный адрес.
     * ## Ограничения
     * - Метод доступен только с правами администратора аккаунта.
     *
     * ## Параметры запроса
     * В данном методе параметры запроса имеют зависимости. Подробнее на странице документации.
     *
     * - destination string Валидный URL, на который необходимо присылать уведомления.
     * - settings array Действия, на которые подписан вебхук. Передается в виде массива cо списком возможных действий. Список доступных действий смотрите по ссылке к данному методу
     *
     * @param   array  $data  Массив с массивами данных пользователей.
     *
     * @return  object
     *
     * @throws  AmocrmClientException
     * @link    https://www.amocrm.ru/developers/content/crm_platform/webhooks-api
     * @link    https://www.amocrm.ru/developers/content/crm_platform/webhooks-api#webhooks-available-actions
     * @since   1.3.0
     */
    public function addWebhook(array $data): object
    {
        if (empty($data)) {
            return $this->receivedEmptyData(__METHOD__);
        }

        return $this->request->getResponse('/webhooks', $data, 'POST', 'application/json');
    }

    /**
     * Отписка от событий
     *
     * ## Метод
     * DELETE /api/v4/webhooks
     *
     * ## Описание
     * Метод позволяет отписать вебхук от получения любых событий.
     * ## Ограничения
     * Метод доступен только с правами администратора аккаунта.
     *
     * @param   string  $destination  Точный адрес вебхука, который необходимо удалить из списка. Например, https://example.test
     *
     * @return  object
     *
     * @throws  AmocrmClientException
     * @since   1.3.0
     * @link    https://www.amocrm.ru/developers/content/crm_platform/webhooks-api
     */
    public function deleteWebhook(string $destination): object
    {
        return $this->request->getResponse('/webhooks', ['destination' => $destination], 'DELETE', 'application/json');
    }

    /**
     * Получаем URL вебхука для указания на стороне AmoCRM
     *
     * @return string
     *
     * @since 1.3.0
     */
    public function getJoomlaWebhookUrl(): string
    {
        $url = '';
        $webhook_token = $this->request->getPluginParams()->get('webhook_token','');
        if (!empty($webhook_token)) {
            $url = new Uri(Uri::root());
            $url->setPath('/index.php');
            $url->setQuery([
                'option' => 'com_ajax',
                'plugin' => 'wt_amocrm',
                'group' => 'system',
                'format' => 'raw',
                'action' => 'webhook',
                'action_type' => 'external',
                'token' => $webhook_token,
            ]);

            $url = $url->toString();
        }

        return $url;
    }
}
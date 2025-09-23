<?php
/**
 * @package    WT Amocrm Library
 * @version    1.3.0
 * @Author     Sergey Tolkachyov, https://web-tolk.ru
 * @copyright  (c) 2022 - May 2025 Sergey Tolkachyov. All rights reserved.
 * @license    GNU/GPL3 http://www.gnu.org/licenses/gpl-3.0.html
 * @since      1.0.0
 */

namespace Webtolk\Amocrm;

use Joomla\CMS\Cache\CacheControllerFactoryInterface;
use Joomla\CMS\Cache\Controller\OutputController;
use Joomla\CMS\Factory;
use Joomla\CMS\Helper\LibraryHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Http\HttpFactory;
use Joomla\CMS\Uri\Uri;
use Joomla\Http\Response;
use Joomla\Registry\Registry;
use Joomla\CMS\Date\Date;
use Webtolk\Amocrm\Entities\Account;
use Webtolk\Amocrm\Entities\Contacts;
use Webtolk\Amocrm\Entities\Customfields;
use Webtolk\Amocrm\Entities\Leads;
use Webtolk\Amocrm\Entities\Notes;
use Webtolk\Amocrm\Entities\Tags;
use Webtolk\Amocrm\Entities\Users;
use Webtolk\Amocrm\Entities\Webhooks;
use Webtolk\Amocrm\Interfaces\EntityInterface;
use Webtolk\Amocrm\Traits\LogTrait;

defined('_JEXEC') or die;

/**
 * @method Account account()
 * @method Contacts contacts()
 * @method Customfields customfields()
 * @method Leads leads()
 * @method Notes notes()
 * @method Tags tags()
 * @method Users users()
 * @method Webhooks webhooks()
 *
 * @since 1.0.0
 */
class Amocrm
{
    use LogTrait;

    /**
     * @var int $api_version
     * @since 1.3.0
     * @deprecated 1.3.0 Will be removed in 2.0.0
     */
    public static int $api_version = 4;

    /**
     * @var string $token_type Token type. Default 'Bearer'
     * @since  1.0.0
     * @deprecated 1.3.0 Will be removed in 2.0.0
     */
    public string $token_type = 'Bearer';

    /**
     * @var int $expires_in Token expires time
     * @since  1.0.0
     * @deprecated 1.3.0 Will be removed in 2.0.0
     */
    public int $expires_in = 0;

    /**
     * @var string $token
     * @since  1.0.0
     * @deprecated 1.3.0 Will be removed in 2.0.0
     */
    protected string $token = '';

    /**
     * WT AmoCRM plugin params
     *
     * @var Registry $plugin_params
     * @since 1.3.0
     * @deprecated 1.3.0 Will be removed in 2.0.0
     */
    private Registry $plugin_params;

    /**
     * @var string $client_id
     * @since 1.3.0
     * @deprecated 1.3.0 Will be removed in 2.0.0
     */
    private string $client_id = '';

    /**
     * @var string $client_secret
     * @since 1.3.0
     * @deprecated 1.3.0 Will be removed in 2.0.0
     */
    private string $client_secret = '';

    /**
     * @var string $amocrm_domain
     * @since 1.3.0
     * @deprecated 1.3.0 Will be removed in 2.0.0
     */
    private string $amocrm_domain = '';

    /**
     * @var array<string, EntityInterface> $instances
     * @since 1.3.0
     */
    private array $instances = [];

    /**
     * @var AmocrmRequest $request
     * @since 1.3.0
     */
    private AmocrmRequest $request;

    public function __construct()
    {
        $this->plugin_params = new Registry();

        $lang = Factory::getApplication()->getLanguage();
        $extension = 'lib_webtolk_amocrm';
        $base_dir = JPATH_SITE;
        $lang->load($extension, $base_dir);

        $this->request = new AmocrmRequest();
    }

    /**
     * Get Amo CRM account info
     *
     * @return  object
     *
     * @throws  AmocrmClientException
     * @since   1.0.0
     * @deprecated 1.3.0 Will be removed in 2.0.0
     */
    public function getAccountInfo(): object
    {
        return $this->getRequest()->getResponse('/account', null, 'GET');
    }

    /**
     * @param   string  $endpoint        AmoCRM API endpoint
     * @param   ?array  $data            request data array
     * @param   string  $request_method  GET, POST, PUT, DELETE etc
     * @param   string  $content_type    application/x-www-form-urlencoded or application/json
     *
     * @return  object
     *
     * @throws  AmocrmException
     * @since   1.0.0
     * @deprecated 1.3.0 Will be removed in 2.0.0
     */
    private function getResponse(
        string $endpoint,
        ?array $data = null,
        string $request_method = 'POST',
        string $content_type = 'application/x-www-form-urlencoded'
    ): object {
        /**
         * Check if the library system plugin is enabled and credentials data are filled
         */

        if (!$this->getRequest()->canDoRequest()) {
            return (object) [
                'error_code' => 400,
                'error_message' => Text::_('LIB_WTAMOCRM_ERROR_GETRESPONSE_CANT_DO_REQUEST')
            ];
        }

        if (!$this->loadTokenData()) {
            return (object) [
                'error_code' => 400,
                'error_message' => Text::_('LIB_WTAMOCRM_ERROR_GETRESPONSE_NO_TOKEN_DATA')
            ];
        }

        $url = new Uri();
        $url->setHost($this->amocrm_domain)->setScheme('https');
        $url->setPath('/api/v' . self::$api_version . $endpoint);

        $headers = [
            'Authorization' => $this->token_type . ' ' . $this->token,
            'Content-Type' => $content_type,
            'charset' => 'UTF-8',
        ];

        $http = (new HttpFactory())->getHttp([], ['curl', 'stream']);
        if ($request_method != 'GET') {
            $request_method = strtolower($request_method);

            // $url, $data, $headers, $timeout
            $response = $http->$request_method($url, json_encode($data), $headers);
        } else {
            if (!empty($data)) {
                $url->setQuery($data);
            }

            // $url, $headers, $timeout
            $response = $http->get($url, $headers);
        }

        return $this->responseHandler($response, $endpoint);
    }

    /**
     * Check if AmoCRM credentials are filled in the plugin params
     * and not empty.
     *
     * @return  bool
     *
     * @since   1.0.0
     * @deprecated 1.3.0 Will be removed in 2.0.0
     */
    public function canDoRequest(): bool
    {
        if (!empty($this->amocrm_domain) &&
            !empty($this->client_id) &&
            !empty($this->client_secret) &&
            !empty($this->expires_in) &&
            !empty($this->token)) {
            return true;
        }

        $plugin_params = $this->getPluginParams();
        if (empty($plugin_params->get('amocrm_client_id', '')) ||
            empty($plugin_params->get('amocrm_client_secret', ''))
        ) {
            $this->saveToLog('There is no credentials found. Check theirs in plugin System - WT AmoCRM', 'WARNING');

            return false;
        }
        $this->client_id = trim($plugin_params->get('amocrm_client_id'));
        $this->client_secret = trim($plugin_params->get('amocrm_client_secret'));
        $this->amocrm_domain = trim($plugin_params->get('amocrm_domain'));

        return true;
    }

    /**
     * Get plugin System - WT AmoCRM params
     *
     * @return  Registry
     *
     * @since   1.3.0
     * @deprecated 1.3.0 Will be removed in 2.0.0
     */
    private function getPluginParams(): Registry
    {
        if(count($this->plugin_params) == 0) {
            if (!PluginHelper::isEnabled('system', 'wt_amocrm')) {
                $this->saveToLog('Plugin System - WT AmoCRM is disabled', 'WARNING');

                return $this->plugin_params;
            }

            $plugin = PluginHelper::getPlugin('system', 'wt_amocrm');

            if (!empty($plugin->params)) {
                $this->plugin_params->loadString($plugin->params);
            }
        }

        return $this->plugin_params;
    }

    /**
     * @return  AmocrmRequest
     *
     * @since   1.3.0
     */
    public function getRequest(): AmocrmRequest
    {
        return $this->request;
    }

    /**
     * Грузим $token_data из кэша. Если просрочен - вызываем авторизацию заново.
     *
     * @return  bool
     *
     * @throws  AmocrmException
     * @since   1.0.0
     * @deprecated 1.3.0 Will be removed in 2.0.0
     */
    private function loadTokenData(): bool
    {
        if (!empty($this->token) && !empty($this->token_type) && !empty($this->expires_in)) {
            return true;
        }

        $cache = $this->getCache();
        $token_data = $cache->get('wt_amo_crm');

        /**
         * Если есть файл кэша с данными токена, иначе авторизация
         */
        if (!empty($token_data)) {
            $token_data = json_decode($token_data);
        } else {
            $response = $this->authorize();

            if (isset($response->error_code)) {
                $this->saveToLog($response->error_code . ' - ' . $response->error_message, 'ERROR');

                return false;
            } else {
                return $this->loadTokenData();
            }
        }

        $date = (new Date())->toUnix();
        /**
         * Если текущая дата больше или равна времени окончания действия токена - получаем новый.
         */
        if (isset($token_data->token_end_time) && $token_data->token_end_time <= $date) {
            unset($token_data);
            $cache->remove('wt_amo_crm');
            $this->authorize();
            $this->loadTokenData();
        } else {
            $this->setToken((string)$token_data->token);
            $this->setTokenType((string)$token_data->token_type);
            unset($token_data);

            return true;
        }
        unset($token_data);

        return true;
    }

    /**
     * Return the library pre-configured cache object
     *
     * @param   array  $cache_options
     *
     * @return  OutputController
     *
     * @since   1.3.0
     * @deprecated 1.3.0 Will be removed in 2.0.0
     */
    public function getCache(array $cache_options = []): OutputController
    {
        $jconfig = Factory::getContainer()->get('config');
        $options = [
            'defaultgroup' => 'wt_amo_crm',
            'caching' => true,
            'cachebase' => $jconfig->get('cache_path'),
            'storage' => $jconfig->get('cache_handler'),
        ];
        $options = array_merge($options, $cache_options);

        return Factory::getContainer()->get(CacheControllerFactoryInterface::class)->createCacheController(
            'output',
            $options
        );
    }

    /**
     * Получение токена
     * Формат ответа JSON
     * {
     *      "access_token": "JWT will be here",
     *      "expires_in": 60, //время действия токена в секундах
     *      "token_type": "Bearer",
     *      "scope": "principal.integration.api.full"
     * }
     *
     * По истечении этого времени или при получении HTTP ошибки с кодом 401,
     * вам нужно повторить процедуру получения access_token.
     * В ином случае API будет отвечать с HTTP кодом 401 (unauthorized).
     *
     * @return  mixed  объект Response или объект ошибки в виде ['error_code' => code, 'error_message' => message]
     *
     * @throws  AmocrmException
     * @since   1.0.0
     * @deprecated 1.3.0 Will be removed in 2.0.0
     */
    public function authorize(): mixed
    {
        $plugin_params = $this->getPluginParams();

        $amocrm_code = $plugin_params->get('amocrm_code', '');
        if (empty($amocrm_code)) {
            $error_message = Text::_('LIB_WTAMOCRM_ERROR_AUTHORIZE_EMPTY_CLIENT_OR_SECRET');
            $this->saveToLog(
                $error_message,
                'ERROR'
            );

            return (object) [
                'error_code' => 500,
                'error_message' => $error_message
            ];
        }

        $authorize_data = [
            'client_id' => $this->client_id,
            'client_secret' => $this->client_secret,
            'redirect_uri' => Uri::root() . 'index.php?option=com_ajax&plugin=wt_amocrm&group=system&format=raw',
        ];
        $refresh_token = $this->getRefreshToken();
        /**
         * Если $refresh_token не получен, то скорее всего это первый запуск.
         * Подключаемся через код авторизации.
         */
        if (!$refresh_token) {
            $authorize_data['code'] = $amocrm_code;
            $authorize_data['grant_type'] = 'authorization_code';
        } else {
            $authorize_data['refresh_token'] = $refresh_token;
            $authorize_data['grant_type'] = 'refresh_token';
        }

        $http = (new HttpFactory())->getHttp([], ['curl', 'stream']);
        $headers = [
            'Content-Type' => 'application/json'
        ];
        $authUrl = new Uri();
        $authUrl->setScheme('https')->setHost($this->amocrm_domain);
        $authUrl->setPath('/oauth2/access_token');

        try {
            $response = $http->post(
                $authUrl,
                json_encode($authorize_data),
                $headers
            );

            $response_body = json_decode($response->body);

            if ($response->code == 200) {
                /**
                 * Set access token
                 */

                if (!$response_body->access_token) {
                    $error_message = Text::_('LIB_WTAMOCRM_ERROR_AUTHORIZE_NO_TOKEN');
                    $this->saveToLog($error_message, 'ERROR');
                    $error_array = [
                        'error_code' => 500,
                        'error_message' => $error_message
                    ];

                    return (object) $error_array;
                } else {
                    $this->setToken($response_body->access_token);
                }

                /**
                 * Set access token type. Bearer by default
                 */
                if (!$response_body->token_type) {
                    $this->setTokenType('Bearer');
                } else {
                    $this->setTokenType($response_body->token_type);
                }

                /**
                 * Set token expires period. 86400 by default
                 */
                if (!$response_body->expires_in) {
                    $this->setTokenExpiresIn(86400);
                } else {
                    $this->setTokenExpiresIn($response_body->expires_in);
                }

                /**
                 * Сохраняем токен в кэше. Жизнь кэша - 86400 секунд по умолчанию
                 * или же значение, равное $response_body->expires_in
                 */
                $this->storeTokenData([
                    'token' => $response_body->access_token,
                    'token_type' => $response_body->token_type,
                    'expires_in' => $response_body->expires_in,
                ]);

                /**
                 * Сохраняем в базу refresh_token
                 */
                if ($response_body->refresh_token) {
                    $this->storeRefreshToken($response_body->refresh_token);
                }

                return $response;
            } elseif ($response->code >= 400 && $response->code < 500) {
                // API работает. Ошибка отдается в json

                if ($response_body->title || $response_body->detail || $response_body->{'validation-errors'}) {
                    $error_message = $this->errorHandler($response_body);
                } else {
                    $error_message = 'no error description';
                }
                $this->saveToLog(
                    $response->code . ' - Error while trying to authorize to Amo CRM. Amo CRM API response: ' . htmlspecialchars(
                        $error_message
                    ),
                    'ERROR'
                );
                $error_array = [
                    'error_code' => $response->code,
                    'error_message' => __FUNCTION__ . ' function: Error while trying to authorize to Amo CRM. Amo CRM API response: ' . $error_message
                ];

                return (object) $error_array;
            } elseif ($response->code >= 500) {
                // API не работает, сервер лёг. В $response->body отдаётся HTML
                $this->saveToLog(
                    $response->code . ' - Error while trying to authorize to Amo CRM. Amo CRM API response: ' . $response->body,
                    'ERROR'
                );
                $error_array = [
                    'error_code' => $response->code,
                    'error_message' => __FUNCTION__ . ' function: Error while trying to authorize to Amo CRM. Amo CRM API response: ' . $response->body
                ];

                return (object) $error_array;
            }
        } catch (AmocrmException $e) {
            throw new AmocrmException('Error while trying to authorize to Amo CRM', 500, $e);
        }

        return (object) [
            'error_code' => 500,
            'error_message' => 'Error while trying to authorize to Amo CRM. The response code is less than 200 or between 201 and 399. The response code: ' . $response->code
        ];
    }

    /**
     * Get refresh token from library params in database
     *
     * @return  string|bool  $refresh_token on success or false if not
     *
     * @since   1.0.0
     * @deprecated 1.3.0 Will be removed in 2.0.0
     */
    public function getRefreshToken(): string|bool
    {
        /**
         * @var Registry $lib_params
         */
        $lib_params = LibraryHelper::getParams('Webtolk/Amocrm');
        if ($refresh_token = $lib_params->get('refresh_token')) {
            return $refresh_token;
        }

        return false;
    }

    /**
     * Set token from Amo CRM API response to $this->$token
     *
     * @param   string  $token  token from Amo CRM API reponse
     *
     * @return  void
     *
     * @since   1.0.0
     * @deprecated 1.3.0 Will be removed in 2.0.0
     */
    public function setToken(string $token): void
    {
        $this->token = $token;
    }

    /**
     * Set token type from Amo CRM API response to $this->$token_type
     *
     * @param   string  $token_type  Token type from Amo CRM API response
     *
     * @return  void
     *
     * @since   1.0.0
     * @deprecated 1.3.0 Will be removed in 2.0.0
     */
    public function setTokenType(string $token_type): void
    {
        $this->token_type = $token_type;
    }

    /**
     * Set token expires period (in seconds) from Amo CRM API response to $this->$token_expires_in
     *
     * @param   int  $token_expires_in
     *
     * @return  void
     *
     * @since   1.0.0
     * @deprecated 1.3.0 Will be removed in 2.0.0
     */
    public function setTokenExpiresIn(int $token_expires_in): void
    {
        $this->expires_in = $token_expires_in;
    }

    /**
     * Stores token data to Joomla Cache
     *
     * @param   array  $tokenData  Access token, token type, token expires in (seconds), token start time in Unix format
     *
     * @return  bool  true
     *
     * @since   1.0.0
     * @deprecated 1.3.0 Will be removed in 2.0.0
     */
    public function storeTokenData(array $tokenData): bool
    {
        // 60 seconds token lifetime by default - 1 minute
        if ($tokenData['expires_in']) {
            $lifetime = (int)$tokenData['expires_in'] / 60;
        } else {
            $lifetime = 1;
        }

        /**
         * Указываем время окончания действия токена.
         */
        $date = (new Date('now +' . $lifetime . ' minutes'))->toUnix();
        $tokenData['token_end_time'] = $date;
        $cache = $this->getCache();
        $cache->store(json_encode($tokenData), 'wt_amo_crm');

        return true;
    }

    /**
     * Save refresh token to library params in database
     *
     * @param   string  $refresh_token  Amo CRM Refresh token
     *
     * @return  void
     *
     * @since   1.0.0
     * @deprecated 1.3.0 Will be removed in 2.0.0
     */
    public function storeRefreshToken(string $refresh_token): void
    {
        /**
         * @var Registry $lib_params
         */
        $lib_params = LibraryHelper::getParams('Webtolk/Amocrm');
        $lib_params->set('refresh_token', $refresh_token);
        $lib_params->set('refresh_token_date', (new Date('now')));
        LibraryHelper::saveParams('Webtolk/Amocrm', $lib_params);
    }

    /**
     * ОБработка ошибок из API Amo CRM, вывод ошибок.
     *
     * @param   $response_body
     *
     * @return  string
     *
     * @since   1.0.0
     * @deprecated 1.3.0 Will be removed in 2.0.0
     */
    private function errorHandler($response_body): string
    {
        $error_message = '';
        foreach ($response_body as $k => $v) {
            if (is_array($v) || is_object($v)) {
                $error_message .= $this->errorHandler($v);
                continue;
            }
            $error_message .= '<b>' . $k . '</b>: ' . $v . PHP_EOL;
        }

        return $error_message;
    }

    /**
     * @param   Response  $response
     * @param   string    $endpoint
     *
     * @return  object
     *
     * @since   1.0.0
     * @deprecated 1.3.0 Will be removed in 2.0.0
     */
    private function responseHandler(Response $response, string $endpoint = ''): object
    {
        $body = json_decode($response->getBody());
        switch ($response->getStatusCode()) {
            case ($response->getStatusCode() >= 400 && $response->getStatusCode() < 500):
                if (property_exists($body, 'title') ||
                    property_exists($body, 'detail') ||
                    property_exists($body, 'validation-errors')
                ) {
                    $error_message = $this->errorHandler($body);
                } else {
                    $error_message = Text::_('LIB_WTAMOCRM_ERROR_RESPONSEHANDLER_NO_ERROR_DESC');
                }

                $this->saveToLog($error_message, 'ERROR');

                return (object) [
                    'error_code' => $response->code,
                    'error_message' => Text::sprintf(
                        'LIB_WTAMOCRM_ERROR_RESPONSEHANDLER_ERROR_400',
                        $endpoint,
                        $error_message
                    )
                ];
            case ($response->getStatusCode() >= 500):
                $error_message = Text::sprintf('LIB_WTAMOCRM_ERROR_RESPONSEHANDLER_ERROR_500', print_r($body, true));
                $this->saveToLog($error_message, 'ERROR');

                return (object) [
                    'error_code' => $response->code,
                    'error_message' => $error_message
                ];
            case 200:
                // no break
            default:
                return (object) $body;
        }
    }

    /**
     * Get lead form Amo CRM by id
     *
     * @param   int  $id
     *
     * @return  object
     *
     * @since   1.0.0
     * @deprecated 1.3.0 Will be removed in 2.0.0
     */
    public function getLeadById(int $id): object
    {
        return $this->leads()->getLeadById($id);
    }

    /**
     * Create a lead in Amo CRM.
     * Позволяет пакетно добавлять лиды в Амо. Структура массива:
     *
     * [
     *  {
     *      "name": "Сделка для примера 1",
     *      "created_by": 0,
     *      "price": 20000,
     *      "custom_fields_values": [
     *      {
     *      "field_id": 294471,
     *      "values": [
     *      {
     *      "value": "Наш первый клиент"
     *      }
     *      ]
     *      }
     *      ]
     *      },
     *      {
     *      "name": "Сделка для примера 2",
     *      "price": 10000,
     *      "_embedded": {
     *      "tags": [
     *      {
     *      "id": 2719
     *      }
     *      ]
     *      }
     *  }
     * ]
     *
     * @param   array  $data
     *
     * @return  object
     *
     * @see     https://www.amocrm.ru/developers/content/crm_platform/leads-api
     * @since   1.0.0
     * @deprecated 1.3.0 Will be removed in 2.0.0
     */
    public function createLeads(array $data): object
    {
        return $this->leads()->createLeads($data);
    }

    /**
     * Комплексное добавление сделок с контактом и компанией.
     * Метод позволяет добавлять сделки с контактом и компанией в аккаунт пакетно. Добавялемые данные могут быть проверены в контроле дублей.
     * ## Ограничения
     * - Метод доступен в соответствии с правами пользователя.
     * - Для одной сделки можно указать не более 1 связанного контакта и 1 связанной компании.
     * - Для добавялемых сущностей (сделка, контакт, компания), можно передать не более 40 значений дополнительных полей.
     * - Добавляемые данные участвуют в контроле дублей, если он включен для интеграции, которая добавляет данные.
     * - Метод не производит дедубликацию переданных данных, а только ищет дубли среди уже добавленных данных.
     * - За один запрос можно передать не более 50 сделок.
     * - При создании нового контакта и компании, они будут связаны между собой.
     * ## Структура массива:
     *
     * {
     *      "name": "Название сделки",
     *      "price": 3422,
     *      "_embedded": {
     *              "metadata":{
     *                      "category": "forms",
     *                      "form_id": 123,
     *                      "form_name": "Форма на сайте",
     *                      "form_page": "https://example.com",
     *                      "form_sent_at": 1608905348,
     *                      "ip": "8.8.8.8",
     *                      "referer": "https://example.com/form.html"
     *                  },
     *              "contacts": [
     *                  {
     *                      "first_name":"Евгений",
     *                      "custom_fields_values": [
     *                          {
     *                              "field_code":"EMAIL",
     *                              "values": [
     *                                      {
     *                                          "enum_code":"WORK",
     *                                          "value":"unsorted_example@example.com"
     *                                      }
     *                              ]
     *                          },
     *                          {
     *                              "field_code":"PHONE",
     *                              "values": [
     *                                  {
     *                                      "enum_code":"WORK",
     *                                      "value":"+79129876543"
     *                                  }
     *                              ]
     *                          }
     *                      ]
     *                  }
     *              ]
     *          },
     *      "status_id":33929749,
     *      "pipeline_id":3383152,
     *      "request_id": "uns_qweasd"
     *  }
     *
     * @param   array  $data
     *
     * @return  object
     * @see     https://www.amocrm.ru/developers/content/crm_platform/leads-api
     * @since   1.0.0
     * @deprecated 1.3.0 Will be removed in 2.0.0
     */
    public function createLeadsComplex(array $data = []): object
    {
        return $this->leads()->createLeadsComplex($data);
    }

    /**
     * Список тегов для сущности
     * ## Общая информация
     * - Справочник тегов разделен по сущностям, то есть тег с одним названием будет иметь различные ID в разных типах сущностей
     * - Цвет тегов доступен только для тегов сделок
     * - Цвет тегов доступен только с обновления Весна 2022
     * - Функционал тегов доступен для следующих сущностей: сделки, контакты, компании и покупатели
     * ## Метод
     * GET /api/v4/{entity_type:leads|contacts|companies|customers}/tags
     * ## Параметры
     * - page int Страница выборки
     * - limit int Количество возвращаемых сущностей за один запрос (Максимум – 250)
     * - filter object Фильтр
     * - filter[name] string Фильтр по точному названию тега. Можно передать только одно название
     * - filter[id] int|array Фильтр по ID тега. Можно передать как один ID, так и массив из нескольких ID
     * - query string Позволяет осуществить полнотекстовый поиск по названию тега
     *
     * @param   string  $entity_type
     * @param   array   $data
     *
     * @return  object
     * @see     https://www.amocrm.ru/developers/content/crm_platform/tags-api
     * @since   1.0.0
     * @deprecated 1.3.0 Will be removed in 2.0.0
     */
    public function getTags(string $entity_type = 'leads', array $data = []): object
    {
        return $this->tags()->getTags($entity_type, $data);
    }

    /**
     * Получение списка воронок продаж для сделок
     * ## Общая информация
     * - В каждой воронке есть 3 системных статуса: Неразобранное, Успешно реализовано (ID = 142), Закрыто и не реализовано (ID = 143)
     * - В аккаунте может быть не более 50 воронок.
     * - В одной воронке может быть не более 100 статусов, включая системные.
     * ## Метод
     * GET /api/v4/leads/pipelines
     *
     * @return  object
     *
     * @see     https://www.amocrm.ru/developers/content/crm_platform/leads_pipelines
     * @since   1.0.0
     * @deprecated 1.3.0 Will be removed in 2.0.0
     */
    public function getLeadsPiplines(): object
    {
        return $this->leads()->getLeadsPiplines();
    }

    /**
     * Получение списка полей для **сделок**
     * ## Ограничения
     * -    Метод возвращает до 250 полей за один запрос.
     * -    Метод доступен всем пользователям аккаунта.
     * ## Метод
     * GET /api/v4/leads/custom_fields
     *
     * @param   array  $data
     *
     * @return  object
     * @see     https://www.amocrm.ru/developers/content/crm_platform/custom-fields
     * @since   1.0.0
     * @deprecated 1.3.0 Will be removed in 2.0.0
     */
    public function getLeadsCustomFields(array $data = []): object
    {
        return $this->getCustomFields('leads', $data);
    }

    /**
     * Список полей для указанной сущности.
     *
     * ## Параметры
     * * - page int Страница выборки
     * * - limit int Количество возвращаемых сущностей за один запрос (Максимум – 250)
     * * - filter[type][0] string Тип поля.
     * * - order object Сортировка результатов списка. Доступные поля для сортировки: sort, id.
     * Доступные значения для сортировки: asc, desc.
     * Пример: /api/v4/leads/custom_fields?order[sort]=asc
     *
     * @param   string  $entity_type
     * @param   array   $data
     *
     * @return  object
     *
     * @throws  AmocrmClientException
     * @since   1.3.0
     * @deprecated 1.3.0 Will be removed in 2.0.0
     */
    public function getCustomFields(string $entity_type = 'leads', array $data = []): object
    {
        $allowed_entites = ['leads', 'contacts', 'companies', 'customers'];
        if (!in_array($entity_type, $allowed_entites)) {
            return (object) [
                'error_code' => 500,
                'error_message' => Text::sprintf(
                    'LIB_WTAMOCRM_ERROR_GETCUSTOMFIELDS_WRONG_ENTITY_TYPE',
                    $entity_type,
                    implode(', ', $allowed_entites)
                )
            ];
        }

        $endpoint = '/' . $entity_type . '/custom_fields';

        return $this->customfields()->getCustomFields($endpoint, $data);
    }

    /**
     * Получение списка полей для **контактов**
     * ## Ограничения
     * -    Метод возвращает до 250 полей за один запрос.
     * -    Метод доступен всем пользователям аккаунта.
     * ## Метод
     * GET /api/v4/contacts/custom_fields
     *
     * @param   array  $data
     *
     * @return  object
     *
     * @throws  AmocrmClientException
     * @see     https://www.amocrm.ru/developers/content/crm_platform/custom-fields
     * @since   1.0.0
     * @deprecated 1.3.0 Will be removed in 2.0.0
     */
    public function getContactsCustomFields(array $data = []): object
    {
        return $this->customfields()->getCustomFields('contacts', $data);
    }

    /**
     * Получение списка полей для **контактов**
     * ## Ограничения
     * -    Метод возвращает до 250 полей за один запрос.
     * -    Метод доступен всем пользователям аккаунта.
     * ## Метод
     * GET /api/v4/companies/custom_fields
     *
     * @param   array  $data
     *
     * @return  object
     *
     * @throws  AmocrmClientException
     * @see     https://www.amocrm.ru/developers/content/crm_platform/custom-fields
     * @since   1.0.0
     * @deprecated 1.3.0 Will be removed in 2.0.0
     */
    public function getCompaniesCustomFields(array $data = []): object
    {
        return $this->customfields()->getCustomFields('companies', $data);
    }

    /**
     * Получение списка полей для **контактов**
     * ## Ограничения
     * -    Метод возвращает до 50 полей за один запрос.
     * -    Метод доступен всем пользователям аккаунта.
     * ## Метод
     * GET /api/v4/companies/custom_fields
     *
     * @param   array  $data
     *
     * @return  object
     *
     * @throws  AmocrmClientException
     * @see     https://www.amocrm.ru/developers/content/crm_platform/custom-fields
     * @since   1.0.0
     * @deprecated 1.3.0 Will be removed in 2.0.0
     */
    public function getCustomersCustomFields(array $data = []): object
    {
        return $this->customfields()->getCustomFields('customers', $data);
    }

    /**
     * Получение списка **контактов**
     * ## Метод
     * GET /api/v4/contacts
     * ## Параметры
     * - with string Данный параметр принимает строку, в том числе из нескольких значений, указанных через запятую. Данный метод поддерживает параметры (см ссылки ниже).
     * - page int Страница выборки
     * - limit int Количество возвращаемых сущностей за один запрос (Максимум – 250)
     * - query string|int Поисковый запрос (Осуществляет поиск по заполненным полям сущности)
     * - filter object Фильтр. Подробней про фильтры читайте в отдельной статье
     * - order object Сортировка результатов списка.
     * -- Доступные поля для сортировки: updated_at, id.
     * -- Доступные значения для сортировки: asc, desc.
     *  -- Пример: /api/v4/contacts?order[updated_at]=asc
     *
     * @param   array  $data
     *
     * @return  object
     *
     * @throws  AmocrmClientException
     * @see     https://www.amocrm.ru/developers/content/crm_platform/contacts-api
     * @link    https://www.amocrm.ru/developers/content/crm_platform/contacts-api#with-88398e14-be90-44b7-91e0-6371e268833b-params
     * @link    https://www.amocrm.ru/developers/content/crm_platform/filters-api
     * @since   1.0.0
     * @deprecated 1.3.0 Will be removed in 2.0.0
     */
    public function getContacts(array $data = []): object
    {
        return $this->contacts()->getContacts($data);
    }

    /**
     * Метод позволяет добавлять **контакты** в аккаунт AmoCRM пакетно.
     * ## Метод
     * POST /api/v4/contacts
     * ## Параметры
     * Обязательные поля отсутствуют
     * - name string Название контакта
     * - first_name string Имя контакта
     * - last_name string Фамилия контакта
     * - responsible_user_id int ID пользователя AmoCRM, ответственного за контакт
     * - created_by int ID пользователя, создавший контакт
     * - updated_by int ID пользователя, изменивший контакт
     * - created_at int Дата создания контакта, передается в Unix Timestamp
     * - updated_at int Дата изменения контакта, передается в Unix Timestamp
     * - custom_fields_values array Массив, содержащий информацию по значениям дополнительных полей, заданных для данного контакта
     * - tags_to_add array Массив тегов для добавления.
     * - _embedded object Данные вложенных сущностей
     *
     * @param   array  $data  Array of arrays. Users data.
     *
     * @return  object
     *
     * @throws  AmocrmException
     * @see     https://www.amocrm.ru/developers/content/crm_platform/contacts-api#contacts-add
     * @link    https://www.amocrm.ru/developers/content/crm_platform/custom-fields#cf-fill-examples
     * @link    https://www.amocrm.ru/developers/content/crm_platform/filters-api
     * @since   1.0.0
     * @deprecated 1.3.0 Will be removed in 2.0.0
     */
    public function addContacts(array $data = []): object
    {
        $endpoint = '/contacts';

        return $this->getResponse($endpoint, $data, 'POST', 'application/json');
    }

    /**
     * Get Amo CRM user info by user id
     * ## Описание
     * Метод позволяет получить данные конкретного пользователя, состоящего в аккаунте, по ID.
     * ## Ограничения
     * Метод доступен только с правами администратора аккаунта.
     *
     * @param   int  $user_id  Amo CRM user id
     *
     * @return  object
     *
     * @link    https://www.amocrm.ru/developers/content/crm_platform/users-api#user-detail
     * @since   1.0.0
     * @deprecated 1.3.0 Will be removed in 2.0.0
     */
    public function getUserById(int $user_id): object
    {
        return $this->users()->getUserById($user_id);
    }

    /**
     * Список примечаний по конкретной сущности, по ID сущности
     * ## Метод
     * GET /api/v4/{entity_type}/{entity_id}/notes
     * ## Описание
     * Метод позволяет получить примечания по ID родительской сущности.
     * ## Ограничения
     * Метод доступен всем пользователям аккаунта. Возвращаемые данные зависят от прав на сущность.
     *
     * @param   string  $entity_type  Amo CRM user id
     * @param   int     $entity_id    Amo CRM user id
     * @param   array   $params       Amo CRM user id
     *                                - page int Страница выборки
     *                                - limit int Количество возвращаемых сущностей за один запрос (Максимум – 250)
     *                                - filter array Фильтр
     *                                - filter[id] int|array Фильтр по ID примечаний. Можно передать как один ID, так и массив из нескольких ID
     *                                - filter[note_type] string|array Фильтр по типу примечания.
     *                                - filter[updated_at] int|object Фильтр по дате последнего изменения примечания.
     *                                Можно передать timestamp, в таком случае будут возвращены примечания, которые были изменены после переданного значения.
     *                                Также можно передать массив вида filter[updated_at][from]=… и filter[updated_at][to]=…,
     *                                для фильтрации по значениям ОТ и ДО.
     *                                - filter[order] array Сортировка результатов списка.
     *                                Доступные поля для сортировки: updated_at, id.
     *                                Доступные значения для сортировки: asc, desc.
     *                                Пример: /api/v4/leads/notes?order[updated_at]=asc
     *
     * @return  object
     *
     * @link    https://www.amocrm.ru/developers/content/crm_platform/events-and-notes#notes-list
     * @since   1.1.0
     * @deprecated 1.3.0 Will be removed in 2.0.0
     */
    public function getNotes(string $entity_type, int $entity_id, array $params = []): object
    {
        return $this->notes()->getNotes($entity_type, $entity_id, $params);
    }

    /**
     * Добавление примечаний
     * ## Метод
     * POST /api/v4/{entity_type}/notes
     * POST /api/v4/{entity_type}/{entity_id}/notes
     * ## Описание
     * Метод позволяет добавлять примечания в аккаунт пакетно.
     * ## Ограничения
     * Метод доступен всем пользователям аккаунта. Успешность выполнения действия зависит от прав на сущность.
     *
     * @param   string  $entity_type  Amo CRM entity type: lead|contact etc
     * @param   int     $entity_id    ID сущности, в которую добавляется примечание. Обязателен при использовании метода создания примечания в сущности,
     *                                если создание идет через метод /api/v4/{entity_type}/{entity_id}/notes, то данный параметр передавать не нужно
     * @param   array   $notes        Массив с примечаниями. Пример
     *                                [
     *                                {
     *                                "entity_id": 167353,
     *                                "note_type": "call_in",
     *                                "params": {
     *                                "uniq": "8f52d38a-5fb3-406d-93a3-a4832dc28f8b",
     *                                "duration": 60,
     *                                "source": "onlinePBX",
     *                                "link": "https://example.com",
     *                                "phone": "+79999999999"
     *                                }
     *                                },
     *                                {
     *                                "entity_id": 167353,
     *                                "note_type": "call_out",
     *                                "params": {
     *                                "uniq": "8f52d38a-5fb3-406d-93a3-a4832dc28f8b",
     *                                "duration": 60,
     *                                "source": "onlinePBX",
     *                                "link": "https://example.com",
     *                                "phone": "+79999999999"
     *                                }
     *                                },
     *                                {
     *                                "entity_id": 167353,
     *                                "note_type": "geolocation",
     *                                "params": {
     *                                "text": "Примечание с геолокацией",
     *                                "address": "ул. Пушкина, дом Колотушкина, квартира Вольнова",
     *                                "longitude": "53.714816",
     *                                "latitude": "91.423146"
     *                                }
     *                                }
     *                                ]
     *
     * @return  object
     *
     * @link    https://www.amocrm.ru/developers/content/crm_platform/events-and-notes#notes-list
     * @since   1.1.0
     * @deprecated 1.3.0 Will be removed in 2.0.0
     */
    public function addNotes(string $entity_type = 'leads', int $entity_id = 0, array $notes = []): object
    {
        return $this->notes()->addNotes($entity_type, $entity_id, $notes);
    }

    /**
     * @param   string                   $name
     * @param   array<array-key, mixed>  $_
     *
     * @return  EntityInterface
     * @throws  AmocrmException
     * @since   1.3.0
     */
    public function __call(string $name, array $_): EntityInterface
    {
        $name = strtolower($name);

        $class = '\\Webtolk\\Amocrm\\Entities\\' . ucfirst($name);
        if (!class_exists($class)) {
            throw new AmocrmException(0, "Class {$class} not found");
        }

        if (!array_key_exists($name, $this->instances)) {
            $this->instances[$name] = new $class($this->request);
        }

        return $this->instances[$name];
    }
}
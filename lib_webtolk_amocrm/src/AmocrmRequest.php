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
use Joomla\CMS\Log\Log;
use Webtolk\Amocrm\Traits\LogTrait;

defined('_JEXEC') or die;

class AmocrmRequest
{
    use LogTrait;

    /**
     * @var int $api_version
     * @since 1.3.0
     */
    public static int $api_version = 4;

    /**
     * @var string $token_type Token type. Default 'Bearer'
     * @since 1.0.0
     */
    public string $token_type = 'Bearer';

    /**
     * @var int $expires_in Token expires time
     * @since 1.0.0
     */
    public int $expires_in = 0;

    /**
     * @var string $token
     * @since 1.0.0
     */
    protected string $token = '';

    /**
     * WT AmoCRM plugin params
     *
     * @var Registry $plugin_params
     * @since 1.3.0
     */
    private Registry $plugin_params;

    /**
     * @var string $client_id
     * @since 1.3.0
     */
    private string $client_id = '';

    /**
     * @var string $client_secret
     * @since 1.3.0
     */
    private string $client_secret = '';

    /**
     * @var string $amocrm_domain
     * @since 1.3.0
     */
    private string $amocrm_domain = '';

    public function __construct()
    {
        $this->plugin_params = new Registry();
    }

    /**
     * @param   string  $endpoint         AmoCRM API endpoint
     * @param   ?array  $data             request data array
     * @param   string  $request_method   GET, POST, PUT, DELETE etc
     * @param   string  $content_type     application/x-www-form-urlencoded or application/json
     * @param   bool    $custom_endpoint  custom endpoint flag
     *
     * @return  object
     *
     * @throws  AmocrmClientException
     * @since   1.0.0
     */
    public function getResponse(
        string $endpoint,
        ?array $data = null,
        string $request_method = 'POST',
        string $content_type = 'application/x-www-form-urlencoded',
        bool $custom_endpoint = false
    ): object {
        /**
         * Check if the library system plugin is enabled and credentials data are filled
         */

        if (!$this->canDoRequest()) {
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

        $url = $this->getAmoCRMHost();
        if ($custom_endpoint) {
            $url->setPath($endpoint);
        } else {
            $url->setPath('/api/v' . self::$api_version . $endpoint);
        }

        $headers = [
            'Authorization' => $this->token_type . ' ' . $this->token,
            'Content-Type' => $content_type,
            'charset' => 'UTF-8',
            'X-Requested-With' => 'XMLHttpRequest'
        ];

        try {
            $http = (new HttpFactory())->getHttp([], ['curl', 'stream']);
            if ($this->getPluginParams()->get('avoid_rest_api_limits_exceeding', 0) == 1) {
                /**
                 * Избегаем превышения лимита обращений к REST API - 7 вызовов в секунду.
                 * @link https://www.amocrm.ru/developers/content/api/recommendations
                 */
                usleep(150000);
            }

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
        } catch (AmocrmException $e) {
            return (object) [
                'error_code' => $e->getCode(),
                'error_message' => $e->getMessage().'. File: '.$e->getFile().', line: '.$e->getLine()
            ];
        }
    }

    /**
     * Check if AmoCRM credentials are filled in the plugin params
     * and not empty.
     *
     * @return  bool
     *
     * @since   1.0.0
     */
    public function canDoRequest(): bool
    {
        $plugin_params = $this->getPluginParams();
        $token_type = $plugin_params->get('token_type', 'normal');
        $check = [$this->amocrm_domain, $this->client_id, $this->client_secret, $this->token];
        if ($token_type == 'normal') {
            $check[] = $this->expires_in;
        }
        $required_checks = count($check);
        $checked = array_filter($check); // Check if !empty($value)
        if ($required_checks == count($checked)) {
            return true;
        }

        if (empty($plugin_params->get('amocrm_client_id', '')) ||
            empty($plugin_params->get('amocrm_client_secret',''))
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
     */
    public function getPluginParams(): Registry
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
     * Function for to log library errors in lib_webtolk_amo_crm.log.php in
     * Joomla log path. Default Log category lib_webtolk_amo_crm
     *
     * @param   string  $data      error message
     * @param   string  $priority  Joomla Log priority
     *
     * @return  void
     *
     * @since   1.3.2
     */
    public function saveToLog(string $data, string $priority = 'NOTICE'): void
    {
        Log::addLogger(
            [
                // Sets file name
                'text_file' => 'lib_webtolk_amo_crm.log.php',
            ],
            // Sets all but DEBUG log level messages to be sent to the file
            Log::ALL & ~Log::DEBUG,
            ['lib_webtolk_amo_crm']
        );
        Factory::getApplication()->enqueueMessage($data, $priority);
        $priority = 'Log::' . $priority;
        Log::add($data, $priority, 'lib_webtolk_amo_crm');
    }

    /**
     * Грузим $token_data из кэша. Если просрочен - вызываем авторизацию заново.
     *
     * @return  bool
     *
     * @throws  AmocrmClientException
     * @since   1.0.0
     */
    private function loadTokenData(): bool
    {
        if (!empty($this->token) && !empty($this->token_type) && !empty($this->expires_in)) {
            return true;
        }

        $plugin_params = $this->getPluginParams();
        $token_type = $plugin_params->get('token_type', 'normal');

        if ($token_type == 'long_term') {
            $this->setTokenType('Bearer');
            $this->setToken($plugin_params->get('long_term_token', ''));
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
     * Set token from Amo CRM API response to $this->$token
     *
     * @param   string  $token  token from Amo CRM API reponse
     *
     * @return  void
     *
     * @since   1.0.0
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
     */
    public function setTokenType(string $token_type): void
    {
        $this->token_type = $token_type;
    }

    /**
     * Return the library pre-configured cache object
     *
     * @param   array  $cache_options
     *
     * @return  OutputController
     *
     * @since   1.3.0
     */
    public function getCache(array $cache_options = []): OutputController
    {
        $config = Factory::getContainer()->get('config');
        $options = [
            'defaultgroup' => 'wt_amo_crm',
            'caching' => true,
            'cachebase' => $config->get('cache_path'),
            'storage' => $config->get('cache_handler'),
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
     * @throws  AmocrmClientException
     * @since   1.0.0
     */
    private function authorize(): mixed
    {
        $plugin_params = $this->getPluginParams();
        $amocrm_code = $plugin_params->get('amocrm_code', '');
        if (empty($amocrm_code)) {
            $error_message = Text::_('LIB_WTAMOCRM_ERROR_AUTHORIZE_EMPTY_CLIENT_OR_SECRET');
            $this->saveToLog($error_message, 'ERROR');

            return (object) [
                'error_code' => 500,
                'error_message' => $error_message
            ];
        }
        $redirect_uri = Uri::getInstance();
        $redirect_uri->setPath('/index.php');

        $redirect_uri->setQuery([
            'option' => 'com_ajax',
            'plugin' => 'wt_amocrm',
            'group' => 'system',
            'format' => 'raw',
        ]);

        $authorize_data = [
            'client_id' => $this->client_id,
            'client_secret' => $this->client_secret,
            'redirect_uri' => $redirect_uri->toString(),
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
            'Content-Type' => 'application/json',
            'charset' => 'UTF-8',
            'X-Requested-With' => 'XMLHttpRequest'
        ];

        $authUrl = $this->getAmoCRMHost();
        $endpoint = '/oauth2/access_token';
        $authUrl->setPath($endpoint);

        try {
            $response = $http->post(
                $authUrl,
                json_encode($authorize_data),
                $headers
            );

            $response_body = $this->responseHandler($response, $endpoint);

            if (property_exists($response_body,'error_code')) {
                return $response_body;
            }

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
        } catch (AmocrmClientException $e) {
            throw new AmocrmClientException('Error while trying to authorize to Amo CRM', 500, $e);
        }
    }

    /**
     * Get refresh token from library params in database
     *
     * @return  string|bool  $refresh_token on success or false if not
     *
     * @since   1.0.0
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
     * Set token expires period (in seconds) from Amo CRM API response to $this->$token_expires_in
     *
     * @param   int  $token_expires_in
     *
     * @return  void
     *
     * @since   1.0.0
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
     * @link    https://web-tolk.ru
     */
    private function responseHandler(Response $response, string $endpoint = ''): object
    {
        $body = json_decode($response->getBody());
        $status_code = $response->getStatusCode();
        switch ($status_code) {
            case ($status_code >= 400 && $status_code < 500):
                $error_message = Text::_('LIB_WTAMOCRM_ERROR_RESPONSEHANDLER_NO_ERROR_DESC');
                if (is_object($body) && (
                    property_exists($body, 'title') ||
                    property_exists($body, 'detail') ||
                    property_exists($body, 'validation-errors'))
                ) {
                    $error_message = $this->errorHandler($body);
                } else if (empty($body) && strpos((string)$response->getBody(),'Forbidden') !== false) {
                    $error_message = Text::_('LIB_WTAMOCRM_ERROR_RESPONSEHANDLER_FORBIDDEN_DESC');
                }

                $this->saveToLog($error_message, 'ERROR');

                return (object) [
                    'error_code' => $status_code,
                    'error_message' => Text::sprintf(
                        'LIB_WTAMOCRM_ERROR_RESPONSEHANDLER_ERROR_400',
                        $endpoint,
                        $error_message
                    )
                ];
            case ($status_code >= 500):
                $error_message = Text::sprintf('LIB_WTAMOCRM_ERROR_RESPONSEHANDLER_ERROR_500', print_r($body, true));
                $this->saveToLog($error_message, 'ERROR');

                return (object) [
                    'error_code' => $status_code,
                    'error_message' => $error_message
                ];
            case 200:
                // no break
            default:
                return (object) $body;
        }
    }

    /**
     * Get AmoCRM host Joomla\Uri\Uri object
     *
     * @return  Uri
     *
     * @since   1.3.0
     */
    public function getAmoCRMHost(): Uri
    {
        if (preg_match('/^https?:\/\//', $this->amocrm_domain)) {
            $url = new Uri($this->amocrm_domain);
        } else {
            $url = new Uri();
            $url->setHost($this->amocrm_domain);
        }

        $url->setScheme('https');
        return $url;
    }
}

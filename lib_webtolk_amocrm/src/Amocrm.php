<?php

/**
 * @package       WT Amocrm Library
 * @version       1.3.0-alpha1
 * @Author        Sergey Tolkachyov, https://web-tolk.ru
 * @сopyright (c) 2022 - April 2025 Sergey Tolkachyov. All rights reserved.
 * @license       GNU/GPL3 http://www.gnu.org/licenses/gpl-3.0.html
 * @since         1.0.0
 */

namespace Webtolk\Amocrm;

use Joomla\CMS\Cache\CacheControllerFactoryInterface;
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
use Webtolk\Amocrm\AmocrmClientException;

use function defined;

defined('_JEXEC') or die;

class Amocrm
{
    /**
     * @var $token_type string Token type. Default 'Bearer'
     * @since 1.0.0
     */
    public static $token_type = 'Bearer';
    /**
     * @var $expires_in int Token expires time
     * @since 1.0.0
     */
    public static $expires_in;

    /**
     * @var $token string
     * @since 1.0.0
     */
    protected static $token;

    public function __construct()
    {
        $lang      = Factory::getApplication()->getLanguage();
        $extension = 'lib_webtolk_amocrm';
        $base_dir  = JPATH_SITE;
        $lang->load($extension, $base_dir);
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
        $endpoint = '/api/v4/account';

        return $this->responseHandler(self::getResponse($endpoint, null, 'GET'), $endpoint);
    }

    /**
     * @param   Response  $response
     *
     * @return object
     *
     * @since      1.0.0
     * @link       https://web-tolk.ru
     */
    private function responseHandler(Response $response, $endpoint = ''): object
    {
        $body = json_decode($response->body);
        if ($response->code >= 400 && $response->code < 500) {
            // API работает. Ошибка отдается в json
            if (property_exists($body, 'title') ||
                property_exists($body, 'detail') ||
                property_exists($body, 'validation-errors')) {
                $error_message = self::errorHandler($body);
            } else {
                $error_message = Text::_('LIB_WTAMOCRM_ERROR_RESPONSEHANDLER_NO_ERROR_DESC');
            }

            $this->saveToLog($error_message, 'ERROR');

            return (object)[
                'error_code'    => $response->code,
                'error_message' => Text::sprintf(
                    'LIB_WTAMOCRM_ERROR_RESPONSEHANDLER_ERROR_400',
                    $endpoint,
                    $error_message
                )
            ];
        } elseif ($response->code >= 500) {
            $error_message = Text::sprintf('LIB_WTAMOCRM_ERROR_RESPONSEHANDLER_ERROR_500', print_r($body, true));
            $this->saveToLog($error_message, 'ERROR');

            return (object)[
                'error_code'    => $response->code,
                'error_message' => $error_message
            ];
        }

        return (object)$body;
    }

    /**
     * ОБработка ошибок из API Amo CRM, вывод ошибок.
     *
     * @param $response_body
     *
     * @return string
     *
     * @since 1.0.0
     */
    private static function errorHandler($response_body): string
    {
        $error_message = '';
        foreach ($response_body as $k => $v) {
            if (is_array($v) || is_object($v)) {
                $error_message .= self::errorHandler($v);
                continue;
            }
            $error_message .= '<b>' . $k . '</b>: ' . $v . PHP_EOL;
        }

        return $error_message;
    }

    /**
     * Function for to log library errors in lib_webtolk_amo_crm.log.php in
     * Joomla log path. Default Log category lib_webtolk_amo_crm
     *
     * @param   string  $data      error message
     * @param   string  $priority  Joomla Log priority
     *
     * @return void
     * @since 1.3.2
     */
    public static function saveToLog(string $data, string $priority = 'NOTICE'): void
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
     * @param   string  $endpoint        AmoCRM API endpoint
     * @param ?array    $data            request data array
     * @param   string  $request_method  GET, POST, PUT, DELETE etc
     * @param   string  $content_type    application/x-www-form-urlencoded or application/json
     *
     * @return Response|object|void
     *
     * @since 1.0.0
     */
    private static function getResponse(
        string $endpoint,
        ?array $data = null,
        string $request_method = 'POST',
        string $content_type = 'application/x-www-form-urlencoded'
    ) {
        if (!PluginHelper::isEnabled('system', 'wt_amocrm')) {
            self::saveToLog(' Plugin System - WT Amo CRM disabled', 'WARNING');
            $error_array = [
                'error_code'    => 500,
                'error_message' => __FUNCTION__ . ' function: Plugin System - WT Amo CRM disabled'
            ];

            return (object)$error_array;
        }
        $plugin        = PluginHelper::getPlugin('system', 'wt_amocrm');
        $params        = json_decode($plugin->params);
        $amocrm_domain = (!empty($params->amocrm_domain) ? 'https://' . $params->amocrm_domain : '');
        if (empty($amocrm_domain)) {
            self::saveToLog(' Plugin System - WT Amo CRM: there is no credentials data', 'ERROR');
            //@todo Переписать этот массив и работу с ним в файлах полей так, чтобы был универсальный объект по одной структуре
            $error_array = [
                'error_code'    => 500,
                'error_message' => Text::_('LIB_WTAMOCRM_ERROR_GETRESPONSE_CANT_DO_REQUEST')
            ];

            return (object)$error_array;
        }

        $url = new Uri($amocrm_domain);
        $url->setPath($endpoint);

        self::loadTokenData();
        if (!empty(self::$token)) {
            $headers = [
                'Authorization' => self::$token_type . ' ' . self::$token,
                'Content-Type'  => $content_type,
                'charset'       => 'UTF-8',
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

            return $response;
        }
    }

    /**
     * Грузим $token_data из кэша. Если просрочен - вызываем авторизацию заново.
     * @return
     *
     * @since 1.0.0
     */
    public static function loadTokenData()
    {
        if (!empty(self::$token) && !empty(self::$token_type) && !empty(self::$expires_in)) {
            return true;
        }

        $jconfig = Factory::getContainer()->get('config');
        $options = [
            'defaultgroup' => 'wt_amo_crm',
            'caching'      => true,
            'cachebase'    => $jconfig->get('cache_path'),
            'storage'      => $jconfig->get('cache_handler'),
        ];

        $cache      = Factory::getContainer()->get(CacheControllerFactoryInterface::class)->createCacheController(
            '',
            $options
        );
        $token_data = $cache->get('wt_amo_crm');
        /**
         * Если есть файл кэша с данными токена, иначе авторизация
         */

        if (!empty($token_data)) {
            $token_data = json_decode($token_data);
        } else {
            $response = self::authorize();

            if (isset($response->error_code)) {
                self::saveToLog($response->error_code . ' - ' . $response->error_message, 'ERROR');

                return;
            } else {
                if ($token_loaded = self::loadTokenData() == true) {
                    return true;
                }
            }
        }

        $date = Date::getInstance('now')->toUnix();
        /**
         * Если текущая дата больше или равна времени окончания действия токена - получаем новый.
         */
        if (isset($token_data->token_end_time) && $token_data->token_end_time <= $date) {
            unset($token_data);
            $cache->remove('wt_amo_crm');
            self::authorize();
            self::loadTokenData();
        } else {
            self::setToken((string)$token_data->token);
            self::setTokenType((string)$token_data->token_type);

            unset($token_data);

            return;
        }
        unset($token_data);

        return;
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
     * @return mixed
     * @throws AmocrmClientException
     * @since 1.0.0
     */
    public static function authorize()
    {
        if (!PluginHelper::isEnabled('system', 'wt_amocrm')) {
            self::saveToLog('Plugin System - WT Amo CRM disabled', 'WARNING');
            $error_array = [
                'error_code'    => 500,
                'error_message' => __FUNCTION__ . ' function: Plugin System - WT Amo CRM disabled'
            ];

            return (object)$error_array;
        }

        $plugin               = PluginHelper::getPlugin('system', 'wt_amocrm');
        $params               = (!empty($plugin->params) ? json_decode($plugin->params) : '');
        $amocrm_domain        = (!empty($params->amocrm_domain) ? 'https://' . $params->amocrm_domain : '');
        $amocrm_client_id     = (!empty($params->amocrm_client_id) ? $params->amocrm_client_id : '');
        $amocrm_client_secret = (!empty($params->amocrm_client_secret) ? $params->amocrm_client_secret : '');
        $amocrm_code          = (!empty($params->amocrm_code) ? $params->amocrm_code : '');
        $amocrm_redirect_uri  = Uri::root() . 'index.php?option=com_ajax&plugin=wt_amocrm&group=system&format=raw';


        if (empty($amocrm_client_secret) || empty($amocrm_client_secret)) {
            $error_message = Text::_('LIB_WTAMOCRM_ERROR_AUTHORIZE_EMPTY_CLIENT_OR_SECRET');
            self::saveToLog(
                $error_message,
                'ERROR'
            );
            $error_array = [
                'error_code'    => 500,
                'error_message' => $error_message
            ];

            return (object)$error_array;
        }

        $authorize_data = [
            'client_id'     => $amocrm_client_id,
            'client_secret' => $amocrm_client_secret,
            'redirect_uri'  => $amocrm_redirect_uri,
        ];
        $refresh_token  = self::getRefreshToken();
        /**
         * Если $refresh_token не получен, то скорее всего это первый запуск.
         * Подключаемся через код авторизации.
         */
        if (!$refresh_token) {
            $authorize_data['code']       = $amocrm_code;
            $authorize_data['grant_type'] = 'authorization_code';
        } else {
            $authorize_data['refresh_token'] = $refresh_token;
            $authorize_data['grant_type']    = 'refresh_token';
        }

        $http    = (new HttpFactory())->getHttp([], ['curl', 'stream']);
        $headers = [
            'Content-Type' => 'application/json'
        ];
        try {
            $response      = $http->post(
                $amocrm_domain . '/oauth2/access_token',
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
                    self::saveToLog($error_message, 'ERROR');
                    $error_array = [
                        'error_code'    => 500,
                        'error_message' => $error_message
                    ];

                    return (object)$error_array;
                } else {
                    self::setToken($response_body->access_token);
                }
                /**
                 * Set access token type. Bearer by default
                 */
                if (!$response_body->token_type) {
                    self::setTokenType('Bearer');
                } else {
                    self::setTokenType($response_body->token_type);
                }

                /**
                 * Set token expires period. 86400 by default
                 */
                if (!$response_body->expires_in) {
                    self::setTokenExpiresIn(86400);
                } else {
                    self::setTokenExpiresIn($response_body->expires_in);
                }

                /**
                 * Сохраняем токен в кэше. Жизнь кэша - 86400 секунд по умолчанию
                 * или же значение, равное $response_body->expires_in
                 */
                self::storeTokenData([
                    'token'      => $response_body->access_token,
                    'token_type' => $response_body->token_type,
                    'expires_in' => $response_body->expires_in,
                ]);
                /**
                 * Сохраняем в базу refresh_token
                 */
                if ($response_body->refresh_token) {
                    self::storeRefreshToken($response_body->refresh_token);
                }

                return $response;
            } elseif ($response->code >= 400 && $response->code < 500) {
                // API работает. Ошибка отдается в json

                if ($response_body->title || $response_body->detail || $response_body->{'validation-errors'}) {
                    $error_message = self::errorHandler($response_body);
                } else {
                    $error_message = 'no error description';
                }
                self::saveToLog(
                    $response->code . ' - Error while trying to authorize to Amo CRM. Amo CRM API response: ' . htmlspecialchars(
                        $error_message
                    ),
                    'ERROR'
                );
                $error_array = [
                    'error_code'    => $response->code,
                    'error_message' => __FUNCTION__ . ' function: Error while trying to authorize to Amo CRM. Amo CRM API response: ' . $error_message
                ];

                return (object)$error_array;
            } elseif ($response->code >= 500) {
                // API не работает, сервер лёг. В $response->body отдаётся HTML
                self::saveToLog(
                    $response->code . ' - Error while trying to authorize to Amo CRM.Amo CRM API response: ' . $response->body,
                    'ERROR'
                );
                $error_array = [
                    'error_code'    => $response->code,
                    'error_message' => __FUNCTION__ . ' function: Error while trying to authorize to Amo CRM. Amo CRM API response: ' . $response->body
                ];

                return (object)$error_array;
            }
        } catch (AmocrmClientException $e) {
            throw new AmocrmClientException('Error while trying to authorize to Amo CRM', 500, $e);
        }

    }

    /**
     * Get refresh token from library params in database
     *
     * @param $refresh_token string Amo CRM Refresh token
     *
     * @return string|bool $refresh_token on success or false if not
     *
     * @since 1.0.0
     */
    public static function getRefreshToken()
    {
        /**
         * @param $lib_params Registry
         */
        $lib_params = LibraryHelper::getParams('Webtolk/Amocrm');
        if ($refresh_token = $lib_params->get('refresh_token')) {
            return $refresh_token;
        }

        return false;
    }

    /**
     * Set token from Amo CRM API response to self::$token
     *
     * @param   string  $token  token from Amo CRM API reponse
     *
     *
     * @since 1.0.0
     * @retun void
     */
    public static function setToken(string $token): void
    {
        self::$token = $token;
    }

    /**
     * Set token type from Amo CRM API response to self::$token_type
     *
     * @param   string  $token_type  Token type from Amo CRM API response
     *
     *
     * @since 1.0.0
     * @retun void
     */
    public static function setTokenType(string $token_type): void
    {
        self::$token_type = $token_type;
    }

    /**
     * Set token expires period (in seconds) from Amo CRM API response to self::$token_expires_in
     *
     * @param   int  $token_expires_in
     *
     *
     * @since 1.0.0
     * @retun void
     */
    public static function setTokenExpiresIn(int $token_expires_in): void
    {
        self::$expires_in = $token_expires_in;
    }

    /**
     * Stores token data to Joomla Cache
     *
     * @param   array  $tokenData  Access token, token type, token expires in (seconds), token start time in Unix format
     *
     *
     * @since 1.0.0
     * @retun bool true
     */
    public static function storeTokenData(array $tokenData): bool
    {
        $jconfig = Factory::getContainer()->get('config');
        $options = [
            'defaultgroup' => 'wt_amo_crm',
            'caching'      => true,
            'cachebase'    => $jconfig->get('cache_path'),
            'storage'      => $jconfig->get('cache_handler'),
        ];

        // 60 seconds token lifetime by default - 1 minute
        if ($tokenData['expires_in']) {
            $options['lifetime'] = (int)$tokenData['expires_in'] / 60;
        } else {
            $options['lifetime'] = 1;
        }

        /**
         * Указываем время окончания действия токена.
         *
         */
        $date                        = Date::getInstance('now +' . $options['lifetime'] . ' minutes')->toUnix();
        $tokenData['token_end_time'] = $date;
        $cache                       = Factory::getContainer()
            ->get(CacheControllerFactoryInterface::class)
            ->createCacheController('', $options);
        $cache->store(json_encode($tokenData), 'wt_amo_crm');

        return true;
    }

    /**
     * Save refresh token to library params in database
     *
     * @param $refresh_token string Amo CRM Refresh token
     *
     * @return void
     *
     * @since 1.0.0
     */
    public static function storeRefreshToken($refresh_token): void
    {
        /**
         * @param $lib_params Registry
         */
        $lib_params = LibraryHelper::getParams('Webtolk/Amocrm');
        $lib_params->set('refresh_token', $refresh_token);
        $lib_params->set('refresh_token_date', (new Date('now')));
        LibraryHelper::saveParams('Webtolk/Amocrm', $lib_params);
    }

    /**
     * Get lead form Amo CRM by id
     * @return object
     *
     * @since 1.0.0
     */

    public function getLeadById(int $id): object
    {
        $endpoint = '/api/v4/leads/' . $id;

        return $this->responseHandler(self::getResponse($endpoint, null, 'GET'), $endpoint);
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
     * @return object
     * @see   https://www.amocrm.ru/developers/content/crm_platform/leads-api
     * @since 1.0.0
     */

    public function createLeads(array $data): object
    {
        if (empty($data)) {
            return (object)[
                'error_code'    => 500,
                'error_message' => Text::_('LIB_WTAMOCRM_ERROR_CREATELEADS_EMPTY_DATA')
            ];
        }

        $endpoint = '/api/v4/leads';

        return $this->responseHandler(self::getResponse($endpoint, $data, 'POST', 'application/json'), $endpoint);
    }

    /**
     * Комплексное добавление сделок с контактом и компанией.
     * Метод позволяет добавлять сделки c контактом и компанией в аккаунт пакетно. Добавялемые данные могут быть проверены в контроле дублей.
     * ## Ограничения
     * -    Метод доступен в соответствии с правами пользователя.
     * -    Для одной сделки можно указать не более 1 связанного контакта и 1 связанной компании.
     * -    Для добавялемых сущностей (сделка, контакт, компания), можно передать не более 40 значений дополнительных полей.
     * -    Добавляемые данные участвуют в контроле дублей, если он включен для интеграции, которая добавляет данные.
     * -    Метод не производит дедубликацию переданных данных, а только ищет дубли среди уже добавленных данных.
     * -    За один запрос можно передать не более 50 сделок.
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
     * @return object
     * @see   https://www.amocrm.ru/developers/content/crm_platform/leads-api
     * @since 1.0.0
     */

    public function createLeadsComplex(array $data = []): object
    {
        if (empty($data)) {
            return (object)[
                'error_code'    => 500,
                'error_message' => Text::_('LIB_WTAMOCRM_ERROR_CREATELEADSCOMPLEX_EMPTY_DATA')
            ];
        }

        $endpoint = '/api/v4/leads/complex';

        return $this->responseHandler(self::getResponse($endpoint, $data, 'POST', 'application/json'), $endpoint);
    }

    /**
     * Список тегов для сущности
     * ## Общая информация
     * -    Справочник тегов разделен по сущностям, то есть тег с одним названием будет иметь различные ID в разных типах сущностей
     * -    Цвет тегов доступен только для тегов сделок
     * -    Цвет тегов доступен только только с обновления Весна 2022
     * -    Функционал тегов доступен для следующих сущностей: сделки, контакты, компании и покупатели
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
     * @return object
     * @see   https://www.amocrm.ru/developers/content/crm_platform/tags-api
     * @since 1.0.0
     */

    public function getTags(string $entity_type = 'leads', array $data = []): object
    {
        $allowed_entites = ['leads', 'contacts', 'companies', 'customers'];
        if (!in_array($entity_type, $allowed_entites)) {
            return (object)[
                'error_code'    => 500,
                'error_message' => Text::sprintf(
                    'LIB_WTAMOCRM_ERROR_GETTAGS_WRONG_ENTITY_TYPE',
                    $entity_type,
                    implode(
                        ', ',
                        $allowed_entites
                    )
                )
            ];
        }

        $endpoint = '/api/v4/' . $entity_type . '/tags';

        return $this->responseHandler(self::getResponse($endpoint, $data, 'GET', 'application/json'), $endpoint);
    }

    /**
     * Получение списка воронок продаж для сделок
     * ## Общая информация
     * -    В каждой воронке есть 3 системных статуса: Неразобранное, Успешно реализовано (ID = 142), Закрыто и не реализовано (ID = 143)
     * -    В аккаунте может быть не более 50 воронок.
     * -    В одной воронке может быть не более 100 статусов, включая системные.
     * ## Метод
     * GET  /api/v4/leads/pipelines
     * @return object
     * @see   https://www.amocrm.ru/developers/content/crm_platform/leads_pipelines
     * @since 1.0.0
     */

    public function getLeadsPiplines(): object
    {
        $endpoint = '/api/v4/leads/pipelines';

        return $this->responseHandler(self::getResponse($endpoint, null, 'GET', 'application/json'), $endpoint);
    }

    /**
     * Получение списка полей для **сделок**
     * ## Ограничения
     * -    Метод возвращает до 250 полей за один запрос.
     * -    Метод доступен всем пользователям аккаунта.
     * ## Метод
     * GET /api/v4/leads/custom_fields
     * @return object
     * @see   https://www.amocrm.ru/developers/content/crm_platform/custom-fields
     * @since 1.0.0
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
     * @return object
     *
     * @since 1.3.0
     */
    public function getCustomFields(string $entity_type = 'leads', array $data = []): object
    {
        $allowed_entites = ['leads', 'contacts', 'companies', 'customers'];
        if (!in_array($entity_type, $allowed_entites)) {
            return (object)[
                'error_code'    => 500,
                'error_message' => Text::sprintf(
                    'LIB_WTAMOCRM_ERROR_GETCUSTOMFIELDS_WRONG_ENTITY_TYPE',
                    $entity_type,
                    implode(
                        ', ',
                        $allowed_entites
                    )
                )
            ];
        }

        $endpoint = '/api/v4/' . $entity_type . '/custom_fields';

        return $this->responseHandler(self::getResponse($endpoint, $data, 'GET', 'application/json'), $endpoint);
    }

    /**
     * Получение списка полей для **контактов**
     * ## Ограничения
     * -    Метод возвращает до 250 полей за один запрос.
     * -    Метод доступен всем пользователям аккаунта.
     * ## Метод
     * GET /api/v4/contacts/custom_fields
     * @return object
     * @see   https://www.amocrm.ru/developers/content/crm_platform/custom-fields
     * @since 1.0.0
     */

    public function getContactsCustomFields(array $data = []): object
    {
        return $this->getCustomFields('contacts', $data);
    }

    /**
     * Получение списка полей для **контактов**
     * ## Ограничения
     * -    Метод возвращает до 250 полей за один запрос.
     * -    Метод доступен всем пользователям аккаунта.
     * ## Метод
     * GET /api/v4/companies/custom_fields
     * @return object
     * @see   https://www.amocrm.ru/developers/content/crm_platform/custom-fields
     * @since 1.0.0
     */

    public function getCompaniesCustomFields(array $data = []): object
    {
        return $this->getCustomFields('companies', $data);
    }

    /**
     * Получение списка полей для **контактов**
     * ## Ограничения
     * -    Метод возвращает до 50 полей за один запрос.
     * -    Метод доступен всем пользователям аккаунта.
     * ## Метод
     * GET /api/v4/companies/custom_fields
     * @return object
     * @see   https://www.amocrm.ru/developers/content/crm_platform/custom-fields
     * @since 1.0.0
     */

    public function getCustomersCustomFields(array $data = []): object
    {
        return $this->getCustomFields('customers', $data);
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
     * @return object
     * @see   https://www.amocrm.ru/developers/content/crm_platform/contacts-api
     * @link  https://www.amocrm.ru/developers/content/crm_platform/contacts-api#with-88398e14-be90-44b7-91e0-6371e268833b-params
     * @link  https://www.amocrm.ru/developers/content/crm_platform/filters-api
     * @since 1.0.0
     */

    public function getContacts(array $data = []): object
    {
        $endpoint = '/api/v4/contacts';

        return $this->responseHandler(self::getResponse($endpoint, $data, 'GET', 'application/json'), $endpoint);
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
     * @return object
     * @link  https://www.amocrm.ru/developers/content/crm_platform/users-api#user-detail
     * @since 1.0.0
     */

    public function getUserById(int $user_id): object
    {
        if (empty($user_id)) {
            return (object)[
                'error_code'    => 500,
                'error_message' => Text::_('LIB_WTAMOCRM_ERROR_GETUSERBYID_EMPTY_USER_ID')
            ];
        }

        $endpoint = '/api/v4/users/' . $user_id;

        return $this->responseHandler(self::getResponse($endpoint, null, 'GET', 'application/json'), $endpoint);
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
     * @return object
     * @link  https://www.amocrm.ru/developers/content/crm_platform/events-and-notes#notes-list
     * @since 1.1.0
     */

    public function getNotes(string $entity_type, int $entity_id, array $params = [])
    {
        if (empty($entity_type) || empty($entity_id)) {
            return (object)[
                'error_code'    => 500,
                'error_message' => Text::_('LIB_WTAMOCRM_ERROR_GETNOTES_EMPTY_DATA')
            ];
        }

        $endpoint = '/api/v4/' . $entity_type . '/' . $entity_id . '/notes';

        return $this->responseHandler(self::getResponse($endpoint, $params, 'GET', 'application/json'), $endpoint);
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
     * @param   string  $entity_type Amo CRM entity type: lead|contact etc
     * @param   int     $entity_id   ID сущности, в которую добавляется примечание. Обязателен при использовании метода создания примечания в сущности,
     *                               если создание идет через метод /api/v4/{entity_type}/{entity_id}/notes, то данный параметр передавать не нужно
     * @param   string  $notes       Массив с примечаниями. Пример
     *                               [
     *                               {
     *                               "entity_id": 167353,
     *                               "note_type": "call_in",
     *                               "params": {
     *                               "uniq": "8f52d38a-5fb3-406d-93a3-a4832dc28f8b",
     *                               "duration": 60,
     *                               "source": "onlinePBX",
     *                               "link": "https://example.com",
     *                               "phone": "+79999999999"
     *                               }
     *                               },
     *                               {
     *                               "entity_id": 167353,
     *                               "note_type": "call_out",
     *                               "params": {
     *                               "uniq": "8f52d38a-5fb3-406d-93a3-a4832dc28f8b",
     *                               "duration": 60,
     *                               "source": "onlinePBX",
     *                               "link": "https://example.com",
     *                               "phone": "+79999999999"
     *                               }
     *                               },
     *                               {
     *                               "entity_id": 167353,
     *                               "note_type": "geolocation",
     *                               "params": {
     *                               "text": "Примечание с геолокацией",
     *                               "address": "ул. Пушкина, дом Колотушкина, квартира Вольнова",
     *                               "longitude": "53.714816",
     *                               "latitude": "91.423146"
     *                               }
     *                               }
     *                               ]
     *
     * @return object
     * @link  https://www.amocrm.ru/developers/content/crm_platform/events-and-notes#notes-list
     * @since 1.1.0
     */

    public function addNotes(string $entity_type = 'leads', int $entity_id = 0, array $notes = []): object
    {
        if (empty($entity_type)) {
            return (object)[
                'error_code'    => 500,
                'error_message' => Text::_('LIB_WTAMOCRM_ERROR_ADDNOTES_EMPTY_ENTITY')
            ];
        }
        if (!empty($entity_id)) {
            $endpoint = '/api/v4/' . $entity_type . '/' . $entity_id . '/notes';
        } else {
            $endpoint = '/api/v4/' . $entity_type . '/notes';
        }
        if (empty($notes)) {
            return (object)[
                'error_code'    => 500,
                'error_message' => __FUNCTION__ . ' function: Notes array is empty. Request abadoned.'
            ];
        }

        return $this->responseHandler(self::getResponse($endpoint, $notes, 'POST', 'application/json'), $endpoint);
    }

}
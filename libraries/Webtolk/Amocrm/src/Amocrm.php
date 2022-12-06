<?php

/**
 * Library to connect to Amo CRM service.
 * @package     Webtolk
 * @subpackage  Amo CRMrocket
 * @author      Sergey Tolkachyov
 * @copyright   Copyright (C) Sergey Tolkachyov, 2022. All rights reserved.
 * @version     1.3.3
 * @license     GNU General Public License version 3 or later. Only for *.php files!
 */

namespace Webtolk\Amocrm;
defined('_JEXEC') or die;

use Exception;
use Joomla\CMS\Cache\Cache;
use Joomla\CMS\Factory;
use Joomla\CMS\Helper\LibraryHelper;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Http\HttpFactory;
use Joomla\CMS\Uri\Uri;
use Joomla\Registry\Registry;
use Webtolk\Amocrm\AmocrmClientException;
use Joomla\CMS\Date\Date;
use Joomla\CMS\Log\Log;

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


	private static function getResponse($url, $data = null, $request_method, $content_type = null)
	{
		if (PluginHelper::isEnabled('system', 'wt_amocrm'))
		{
			$plugin        = PluginHelper::getPlugin('system', 'wt_amocrm');
			$params        = json_decode($plugin->params);
			$amocrm_domain = (!empty($params->amocrm_domain) ? 'https://' . $params->amocrm_domain : '');
			$url           = $amocrm_domain . $url;
			self::loadTokenData();
			if (!empty(self::$token))
			{
				if (is_null($content_type))
				{
					$content_type = 'application/x-www-form-urlencoded';
				}

				$headers = array(
					'Authorization' => self::$token_type . ' ' . self::$token,
					'Content-Type'  => $content_type,
					'charset'       => 'UTF-8',
				);

				$http = (new HttpFactory)->getHttp([], ['curl', 'stream']);
				if ($request_method != 'GET')
				{
					$request_method = strtolower($request_method);
					// $url, $data, $headers, $timeout

					$response = $http->$request_method($url, $data, $headers);
				}
				else
				{
					if (!empty($data) && is_array($data))
					{
						$data = http_build_query($data);

						$url = $url . '?' . $data;
					}

					// $url, $headers, $timeout
					$response = $http->get($url, $headers);
				}

				return $response;
			}

		}//if(PluginHelper::isEnabled('wt_amocrm'))
		else
		{
			self::saveToLog(' Plugin System - WT Amo CRM disabled', 'WARNING');
			$error_array = array(
				'error_code'    => 500,
				'error_message' => 'Plugin System - WT Amo CRM disabled'
			);

			return (object) $error_array;
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

		if(!empty(self::$token) && !empty(self::$token_type) && !empty(self::$expires_in)){
			return true;
		}

		$jconfig = Factory::getConfig();
		$options = array(
			'defaultgroup' => 'wt_amo_crm',
			'caching'      => true,
			'cachebase'    => $jconfig->get('cache_path'),
			'storage'      => $jconfig->get('cache_handler'),
		);
		$cache   = Cache::getInstance('', $options);

		$token_data = $cache->get('wt_amo_crm');
		/**
		 * Если есть файл кэша с данными токена, иначе авторизация
		 */

		if (!empty($token_data))
		{
			$token_data = json_decode($token_data);
		}
		else
		{

			$response = self::authorize();

			if (isset($response->error_code))
			{

				self::saveToLog($response->error_code . ' - ' . $response->error_message, 'ERROR');

				return;

			}
			else
			{
				if($token_loaded = self::loadTokenData() == true){
					return true;
				}
			}
		}

		$date = Date::getInstance('now')->toUnix();
		/**
		 * Если текущая дата больше или равна времени окончания действия токена - получаем новый.
		 */
		if (isset($token_data->token_end_time) && $token_data->token_end_time <= $date)
		{
			unset($token_data);
			$cache->remove('wt_amo_crm');
			self::authorize();
			self::loadTokenData();
		}
		else
		{

			self::setToken((string) $token_data->token);
			self::setTokenType((string) $token_data->token_type);

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
	 * @throws AmocrmClientException
	 * @return mixed
	 * @since 1.0.0
	 */
	public static function authorize()
	{

		if (PluginHelper::isEnabled('system', 'wt_amocrm'))
		{
			$plugin               = PluginHelper::getPlugin('system', 'wt_amocrm');
			$params               = (!empty($plugin->params) ? json_decode($plugin->params) : '');
			$amocrm_domain        = (!empty($params->amocrm_domain) ? 'https://' . $params->amocrm_domain : '');
			$amocrm_client_id     = (!empty($params->amocrm_client_id) ? $params->amocrm_client_id : '');
			$amocrm_client_secret = (!empty($params->amocrm_client_secret) ? $params->amocrm_client_secret : '');
			$amocrm_code          = (!empty($params->amocrm_code) ? $params->amocrm_code : '');
			$amocrm_redirect_uri  = Uri::root().'index.php?option=com_ajax&plugin=wt_amocrm&group=system&format=raw';


			if (empty($amocrm_client_secret) || empty($amocrm_client_secret))
			{
				self::saveToLog('Client_id or client_secret haven\'t been set. Please, check the plugin System - WT Amo CRM settings', 'ERROR');
				$error_array = array(
					'error_code'    => 500,
					'error_message' => 'Client_id or client_secret haven\'t been set. Please, check the plugin System - WT Amo CRM settings.'
				);

				return (object) $error_array;
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
			if (!$refresh_token)
			{
				$authorize_data['code']       = $amocrm_code;
				$authorize_data['grant_type'] = 'authorization_code';
			}
			else
			{
				$authorize_data['refresh_token'] = $refresh_token;
				$authorize_data['grant_type']    = 'refresh_token';
			}

			$http    = (new HttpFactory)->getHttp([], ['curl', 'stream']);
			$headers = array(
				'Content-Type' => 'application/json'
			);
			try
			{

				$response      = $http->post($amocrm_domain . '/oauth2/access_token', json_encode($authorize_data), $headers);
				$response_body = json_decode($response->body);

				if ($response->code == 200)
				{

					/**
					 * Set access token
					 */
					if (!$response_body->access_token)
					{
						self::saveToLog('Amo CRM response doesn\'t contain token.', 'ERROR');
						$error_array = array(
							'error_code'    => 500,
							'error_message' => 'Amo CRM response doesn\'t contain token.'
						);

						return (object) $error_array;
					}
					else
					{
						self::setToken($response_body->access_token);
					}
					/**
					 * Set access token type. Bearer by default
					 */
					if (!$response_body->token_type)
					{
						self::setTokenType('Bearer');
					}
					else
					{
						self::setTokenType($response_body->token_type);
					}

					/**
					 * Set token expires period. 86400 by default
					 */
					if (!$response_body->expires_in)
					{
						self::setTokenExpiresIn(86400);
					}
					else
					{
						self::setTokenExpiresIn($response_body->expires_in);
					}

					/**
					 * Сохраняем токен в кэше. Жизнь кэша - 86400 секунд по умолчанию
					 * или же значение, равное $response_body->expires_in
					 */
					self::storeTokenData(array(
						'token'      => $response_body->access_token,
						'token_type' => $response_body->token_type,
						'expires_in' => $response_body->expires_in,
					));
					/**
					 * Сохраняем в базу refresh_token
					 */
					if ($response_body->refresh_token)
					{
						self::storeRefreshToken($response_body->refresh_token);
					}

					return $response;

				}
				elseif ($response->code >= 400 && $response->code < 500)
				{
					// API работает. Ошибка отдается в json

					if ($response_body->title || $response_body->detail || $response_body->{'validation-errors'})
					{
						$error_message = self::errorHandler($response_body);
					}
					else
					{
						$error_message = 'no error description';
					}
					self::saveToLog($response->code . ' - Error while trying to authorize to Amo CRM. Amo CRM API response: ' . htmlspecialchars($error_message), 'ERROR');
					$error_array = array(
						'error_code'    => $response->code,
						'error_message' => 'Error while trying to authorize to Amo CRM. Amo CRM API response: ' . $error_message
					);

					return (object) $error_array;
				}
				elseif ($response->code >= 500)
				{
					// API не работает, сервер лёг. В $response->body отдаётся HTML
					self::saveToLog($response->code . ' - Error while trying to authorize to Amo CRM.Amo CRM API response: ' . $response->body, 'ERROR');
					$error_array = array(
						'error_code'    => $response->code,
						'error_message' => 'Error while trying to authorize to Amo CRM. Amo CRM API response: ' . $response->body
					);

					return (object) $error_array;

				}
			}
			catch (AmocrmClientException $e)
			{
				throw new AmocrmClientException('Error while trying to authorize to Amo CRM', 500, $e);
			}

		}//if (PluginHelper::isEnabled('system', 'wt_amocrm'))
		else
		{
			self::saveToLog('Plugin System - WT Amo CRM disabled', 'WARNING');
			$error_array = array(
				'error_code'    => 500,
				'error_message' => 'Plugin System - WT Amo CRM disabled'
			);

			return (object) $error_array;
		}
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
		$jconfig = Factory::getConfig();
		$options = array(
			'defaultgroup' => 'wt_amo_crm',
			'caching'      => true,
			'cachebase'    => $jconfig->get('cache_path'),
			'storage'      => $jconfig->get('cache_handler'),
		);

		// 60 seconds token lifetime by default - 1 minute
		if ($tokenData['expires_in'])
		{
			$options['lifetime'] = (int) $tokenData['expires_in'] / 60;
		}
		else
		{
			$options['lifetime'] = 1;
		}

		/**
		 * Указываем время окончания действия токена.
		 *
		 */
		$date                        = Date::getInstance('now +' . $options['lifetime'] . ' minutes')->toUnix();
		$tokenData['token_end_time'] = $date;
		$cache                       = Cache::getInstance('', $options);
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
		LibraryHelper::saveParams('Webtolk/Amocrm', $lib_params);
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
		if ($refresh_token = $lib_params->get('refresh_token'))
		{
			return $refresh_token;
		}

		return false;
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
			array(
				// Sets file name
				'text_file' => 'lib_webtolk_amo_crm.log.php',
			),
			// Sets all but DEBUG log level messages to be sent to the file
			Log::ALL & ~Log::DEBUG,
			array('lib_webtolk_amo_crm')
		);
		Factory::getApplication()->enqueueMessage($data, $priority);
		$priority = 'Log::' . $priority;
		Log::add($data, $priority, 'lib_webtolk_amo_crm');
	}

	/**
	 * Get Amo CRM account info
	 * @return array
	 *
	 * @since 1.0.0
	 */

	public function getAccountInfo()
	{

		$response = self::getResponse('/api/v4/account', '', 'GET');
		if ($response->code == 200)
		{
			return json_decode($response->body);
		}
		elseif ($response->code >= 400 && $response->code < 500)
		{

			// API работает. Ошибка отдается в json
			$response_body = json_decode($response->body);
			if ($response_body->title || $response_body->detail || $response_body->{'validation-errors'})
			{
				$error_message = self::errorHandler($response_body);
			}
			else
			{
				$error_message = 'no error description';
			}
			self::saveToLog('Error while trying to get Delivery Time via Amo CRM. Amo CRM API response: ' . $error_message, 'ERROR');

			$error_array = array(
				'error_code'    => $response->code,
				'error_message' => 'Error while trying to get Delivery Time via Amo CRM. Amo CRM API response: ' . $error_message
			);

			return (object) $error_array;
		}
		elseif ($response->code >= 500)
		{
			// API не работает, сервер лёг. В $response->body отдаётся HTML
			self::saveToLog('Error while trying to get Delivery Time via Amo CRM. Amo CRM API response: ' . $response->body, 'ERROR');
			$error_array = array(
				'error_code'    => $response->code,
				'error_message' => 'Error while trying to get Delivery Time via Amo CRM. Amo CRM API response: ' . $response->body
			);

			return (object) $error_array;
		}
	}


	/**
	 * Get lead form Amo CRM by id
	 * @return array
	 *
	 * @since 1.0.0
	 */

	public function getLeadById($id)
	{
		if (empty ($id))
		{
			return $error_array = array(
				'error_code'    => 500,
				'error_message' => 'There is no lead id specified'
			);
		}

		$response = self::getResponse('/api/v4/leads/' . $id, '', 'GET');
		if ($response->code == 200)
		{
			return json_decode($response->body);
		}
		elseif ($response->code >= 400 && $response->code < 500)
		{

			// API работает. Ошибка отдается в json
			$response_body = json_decode($response->body);
			if ($response_body->title || $response_body->detail || $response_body->{'validation-errors'})
			{
				$error_message = self::errorHandler($response_body);
			}
			else
			{
				$error_message = 'no error description';
			}
			self::saveToLog('Error while trying to get Delivery Time via Amo CRM. Amo CRM API response: ' . $error_message, 'ERROR');

			$error_array = array(
				'error_code'    => $response->code,
				'error_message' => 'Error while trying to get Delivery Time via Amo CRM. Amo CRM API response: ' . $error_message
			);

			return (object) $error_array;
		}
		elseif ($response->code >= 500)
		{
			// API не работает, сервер лёг. В $response->body отдаётся HTML
			self::saveToLog('Error while trying to get Delivery Time via Amo CRM. Amo CRM API response: ' . $response->body, 'ERROR');
			$error_array = array(
				'error_code'    => $response->code,
				'error_message' => 'Error while trying to get Delivery Time via Amo CRM. Amo CRM API response: ' . $response->body
			);

			return (object) $error_array;
		}
	}

	/**
	 * Create a lead in Amo CRM.
	 * Позволяет пакетно добавлять лиды в Амо. Структура массива:
	 *
	 * [
			{
				"name": "Сделка для примера 1",
				"created_by": 0,
				"price": 20000,
				"custom_fields_values": [
					{
						"field_id": 294471,
						"values": [
								{
									"value": "Наш первый клиент"
								}
							]
					}
				]
			},
			{
				"name": "Сделка для примера 2",
				"price": 10000,
				"_embedded": {
					"tags": [
								{
									"id": 2719
								}
						]
					}
				}
		]
	 *
	 * @return object
	 * @see https://www.amocrm.ru/developers/content/crm_platform/leads-api
	 * @since 1.0.0
	 */

	public function createLeads(array $data = []) : object
	{
		if (count($data) == 0)
		{
			return (object) $error_array = array(
				'error_code'    => 500,
				'error_message' => 'There is no data for creating lead in Amo CRM'
			);
		}
		$response = self::getResponse('/api/v4/leads', json_encode($data), 'POST', 'application/json');
		if ($response->code == 200)
		{
			return (object) json_decode($response->body);
		}
		elseif ($response->code >= 400 && $response->code < 500)
		{

			// API работает. Ошибка отдается в json
			$response_body = json_decode($response->body);
			if ($response_body->title || $response_body->detail || $response_body->{'validation-errors'})
			{
				$error_message = self::errorHandler($response_body);

			}
			else
			{
				$error_message = 'no error description';
			}
			self::saveToLog('Error while trying to create lead in Amo CRM. Amo CRM API response: ' . $error_message, 'ERROR');

			$error_array = array(
				'error_code'    => $response->code,
				'error_message' => 'Error while trying to create lead in Amo CRM. Amo CRM API response: ' . $error_message
			);

			return (object) $error_array;
		}
		elseif ($response->code >= 500)
		{
			// API не работает, сервер лёг. В $response->body отдаётся HTML
			self::saveToLog('Error while trying to create lead in Amo CRM. Amo CRM API response: ' . $response->body, 'ERROR');
			$error_array = array(
				'error_code'    => $response->code,
				'error_message' => 'Error while trying to create lead in Amo CRM. Amo CRM API response: ' . $response->body
			);

			return (object) $error_array;
		}
	}

	/**
	 * Комплексное добавление сделок с контактом и компанией.
	 * Метод позволяет добавлять сделки c контактом и компанией в аккаунт пакетно. Добавялемые данные могут быть проверены в контроле дублей.
	 * ## Ограничения
	 * - 	Метод доступен в соответствии с правами пользователя.
	 * -	Для одной сделки можно указать не более 1 связанного контакта и 1 связанной компании.
	 * -	Для добавялемых сущностей (сделка, контакт, компания), можно передать не более 40 значений дополнительных полей.
	 * -	Добавляемые данные участвуют в контроле дублей, если он включен для интеграции, которая добавляет данные.
	 * -	Метод не производит дедубликацию переданных данных, а только ищет дубли среди уже добавленных данных.
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
	 * @see https://www.amocrm.ru/developers/content/crm_platform/leads-api
	 * @since 1.0.0
	 */

	public function createLeadsComplex(array $data = [])
	{

		if (count($data) == 0)
		{
			return (object) $error_array = array(
				'error_code'    => 500,
				'error_message' => 'There is no data for creating lead in Amo CRM'
			);
		}
		$response = self::getResponse('/api/v4/leads/complex', json_encode($data), 'POST', 'application/json');
		if ($response->code == 200)
		{
			return (object) json_decode($response->body);
		}
		elseif ($response->code >= 400 && $response->code < 500)
		{

			// API работает. Ошибка отдается в json
			$response_body = json_decode($response->body);

			if ($response_body->title || $response_body->detail || $response_body->{'validation-errors'})
			{
				$error_message = self::errorHandler($response_body);

			}
			else
			{
				$error_message = 'no error description';
			}
			self::saveToLog('Error while trying to create lead in Amo CRM. Amo CRM API response: ' . $error_message, 'ERROR');

			$error_array = array(
				'error_code'    => $response->code,
				'error_message' => 'Error while trying to create lead in Amo CRM. Amo CRM API response: ' . $error_message
			);

			return (object) $error_array;
		}
		elseif ($response->code >= 500)
		{
			// API не работает, сервер лёг. В $response->body отдаётся HTML
			self::saveToLog('Error while trying to create lead in Amo CRM. Amo CRM API response: ' . $response->body, 'ERROR');
			$error_array = array(
				'error_code'    => $response->code,
				'error_message' => 'Error while trying to create lead in Amo CRM. Amo CRM API response: ' . $response->body
			);

			return (object) $error_array;
		}
	}

	/**
	 * Список тегов для сущности
	 * ## Общая информация
	 * - 	Справочник тегов разделен по сущностям, то есть тег с одним названием будет иметь различные ID в разных типах сущностей
	 * -	Цвет тегов доступен только для тегов сделок
	 * -	Цвет тегов доступен только только с обновления Весна 2022
	 * -	Функционал тегов доступен для следующих сущностей: сделки, контакты, компании и покупатели
	 * ## Метод
	 * GET /api/v4/{entity_type:leads|contacts|companies|customers}/tags
	 * ## Параметры
	 * - page int Страница выборки
	 * - limit int Количество возвращаемых сущностей за один запрос (Максимум – 250)
	 * - filter object Фильтр
	 * - filter[name] string Фильтр по точному названию тега. Можно передать только одно название
	 * - filter[id] int|array Фильтр по ID тега. Можно передать как один ID, так и массив из нескольких ID
	 * - query string Позволяет осуществить полнотекстовый поиск поиск по названию тега
	 * @return object
	 * @see https://www.amocrm.ru/developers/content/crm_platform/tags-api
	 * @since 1.0.0
	 */

	public function getTags(string $entity_type = 'leads', array $data = [])
	{
		$allowed_entites = ['leads','contacts','companies','customers'];
		if(!in_array($entity_type,$allowed_entites)){
			return (object) $error_array = array(
				'error_code'    => 500,
				'error_message' => 'Specified entity type '.$entity_type.' is not allowed for getting tag in Amo CRM. Choose allowed type from '.implode(', ',$allowed_entites)
			);
		}

		$response = self::getResponse('/api/v4/'.$entity_type.'/tags', json_encode($data), 'GET', 'application/json');
		if ($response->code == 200)
		{
			return (object) json_decode($response->body);
		}
		elseif ($response->code >= 400 && $response->code < 500)
		{

			// API работает. Ошибка отдается в json
			$response_body = json_decode($response->body);

			if (isset($response_body->title) || isset($response_body->detail) || isset($response_body->{'validation-errors'}))
			{
				$error_message = self::errorHandler($response_body);

			}
			else
			{
				$error_message = 'no error description';
			}
			self::saveToLog('Error while trying to get tag(s) from Amo CRM. Amo CRM API response: ' . $error_message, 'ERROR');

			$error_array = array(
				'error_code'    => $response->code,
				'error_message' => 'Error while trying to get tag(s) from Amo CRM. Amo CRM API response: ' . $error_message
			);

			return (object) $error_array;
		}
		elseif ($response->code >= 500)
		{
			// API не работает, сервер лёг. В $response->body отдаётся HTML
			self::saveToLog('Error while trying to get tag(s) from Amo CRM. Amo CRM API response: ' . $response->body, 'ERROR');
			$error_array = array(
				'error_code'    => $response->code,
				'error_message' => 'Error while trying to get tag(s) from Amo CRM. Amo CRM API response: ' . $response->body
			);

			return (object) $error_array;
		}
	}


	/**
	 * Получение списка воронок продаж для сделок
	 * ## Общая информация
	 * - 	В каждой воронке есть 3 системных статуса: Неразобранное, Успешно реализовано (ID = 142), Закрыто и не реализовано (ID = 143)
	 * -	В аккаунте может быть не более 50 воронок.
	 * -	В одной воронке может быть не более 100 статусов, включая системные.
	 * ## Метод
	 * GET  /api/v4/leads/pipelines
	 * @return object
	 * @see https://www.amocrm.ru/developers/content/crm_platform/leads_pipelines
	 * @since 1.0.0
	 */

	public function getLeadsPiplines()
	{

		$response = self::getResponse('/api/v4/leads/pipelines', null, 'GET', 'application/json');
		if ($response->code == 200)
		{
			return (object) json_decode($response->body);
		}
		elseif ($response->code >= 400 && $response->code < 500)
		{

			// API работает. Ошибка отдается в json
			$response_body = json_decode($response->body);

			if (isset($response_body->title) || isset($response_body->detail) || isset($response_body->{'validation-errors'}))
			{
				$error_message = self::errorHandler($response_body);

			}
			else
			{
				$error_message = 'no error description';
			}
			self::saveToLog('Error while trying to get leads piplines list from Amo CRM. Amo CRM API response: ' . $error_message, 'ERROR');

			$error_array = array(
				'error_code'    => $response->code,
				'error_message' => 'Error while trying to get leads piplines list from Amo CRM. Amo CRM API response: ' . $error_message
			);

			return (object) $error_array;
		}
		elseif ($response->code >= 500)
		{
			// API не работает, сервер лёг. В $response->body отдаётся HTML
			self::saveToLog('Error while trying to get leads piplines list from Amo CRM. Amo CRM API response: ' . $response->body, 'ERROR');
			$error_array = array(
				'error_code'    => $response->code,
				'error_message' => 'Error while trying to get leads piplines list from Amo CRM. Amo CRM API response: ' . $response->body
			);

			return (object) $error_array;
		}
	}


	/**
	 * Получение списка полей для **сделок**
	 * ## Ограничения
	 * - 	Метод возвращает до 50 полей за один запрос.
	 * -	Метод доступен всем пользователям аккаунта.
	 * ## Метод
	 * GET /api/v4/leads/custom_fields
	 * @return object
	 * @see https://www.amocrm.ru/developers/content/crm_platform/custom-fields
	 * @since 1.0.0
	 */

	public function getLeadsCustomFields()
	{

		$response = self::getResponse('/api/v4/leads/custom_fields', null, 'GET', 'application/json');
		if ($response->code == 200)
		{
			return (object) json_decode($response->body);
		}
		elseif ($response->code >= 400 && $response->code < 500)
		{

			// API работает. Ошибка отдается в json
			$response_body = json_decode($response->body);

			if (isset($response_body->title) || isset($response_body->detail) || isset($response_body->{'validation-errors'}))
			{
				$error_message = self::errorHandler($response_body);

			}
			else
			{
				$error_message = 'no error description';
			}
			self::saveToLog('Error while trying to get leads custom fields list from Amo CRM. Amo CRM API response: ' . $error_message, 'ERROR');

			$error_array = array(
				'error_code'    => $response->code,
				'error_message' => 'Error while trying to get leads custom fields list from Amo CRM. Amo CRM API response: ' . $error_message
			);

			return (object) $error_array;
		}
		elseif ($response->code >= 500)
		{
			// API не работает, сервер лёг. В $response->body отдаётся HTML
			self::saveToLog('Error while trying to get leads custom fields list from Amo CRM. Amo CRM API response: ' . $response->body, 'ERROR');
			$error_array = array(
				'error_code'    => $response->code,
				'error_message' => 'Error while trying to get leads custom fields list from Amo CRM. Amo CRM API response: ' . $response->body
			);

			return (object) $error_array;
		}
	}


	/**
	 * Получение списка полей для **контактов**
	 * ## Ограничения
	 * - 	Метод возвращает до 50 полей за один запрос.
	 * -	Метод доступен всем пользователям аккаунта.
	 * ## Метод
	 * GET /api/v4/contacts/custom_fields
	 * @return object
	 * @see https://www.amocrm.ru/developers/content/crm_platform/custom-fields
	 * @since 1.0.0
	 */

	public function getContactsCustomFields()
	{

		$response = self::getResponse('/api/v4/contacts/custom_fields', null, 'GET', 'application/json');
		if ($response->code == 200)
		{
			return (object) json_decode($response->body);
		}
		elseif ($response->code >= 400 && $response->code < 500)
		{

			// API работает. Ошибка отдается в json
			$response_body = json_decode($response->body);

			if (isset($response_body->title) || isset($response_body->detail) || isset($response_body->{'validation-errors'}))
			{
				$error_message = self::errorHandler($response_body);

			}
			else
			{
				$error_message = 'no error description';
			}
			self::saveToLog('Error while trying to get contacts custom fields list from Amo CRM. Amo CRM API response: ' . $error_message, 'ERROR');

			$error_array = array(
				'error_code'    => $response->code,
				'error_message' => 'Error while trying to get contacts custom fields list from Amo CRM. Amo CRM API response: ' . $error_message
			);

			return (object) $error_array;
		}
		elseif ($response->code >= 500)
		{
			// API не работает, сервер лёг. В $response->body отдаётся HTML
			self::saveToLog('Error while trying to get contacts custom fields list from Amo CRM. Amo CRM API response: ' . $response->body, 'ERROR');
			$error_array = array(
				'error_code'    => $response->code,
				'error_message' => 'Error while trying to get contacts custom fields list from Amo CRM. Amo CRM API response: ' . $response->body
			);

			return (object) $error_array;
		}
	}

	/**
	 * Получение списка полей для **контактов**
	 * ## Ограничения
	 * - 	Метод возвращает до 50 полей за один запрос.
	 * -	Метод доступен всем пользователям аккаунта.
	 * ## Метод
	 * GET /api/v4/companies/custom_fields
	 * @return object
	 * @see https://www.amocrm.ru/developers/content/crm_platform/custom-fields
	 * @since 1.0.0
	 */

	public function getCompaniesCustomFields()
	{

		$response = self::getResponse('/api/v4/companies/custom_fields', null, 'GET', 'application/json');
		if ($response->code == 200)
		{
			return (object) json_decode($response->body);
		}
		elseif ($response->code >= 400 && $response->code < 500)
		{

			// API работает. Ошибка отдается в json
			$response_body = json_decode($response->body);

			if (isset($response_body->title) || isset($response_body->detail) || isset($response_body->{'validation-errors'}))
			{
				$error_message = self::errorHandler($response_body);

			}
			else
			{
				$error_message = 'no error description';
			}
			self::saveToLog('Error while trying to get companies custom fields list from Amo CRM. Amo CRM API response: ' . $error_message, 'ERROR');

			$error_array = array(
				'error_code'    => $response->code,
				'error_message' => 'Error while trying to get companies custom fields list from Amo CRM. Amo CRM API response: ' . $error_message
			);

			return (object) $error_array;
		}
		elseif ($response->code >= 500)
		{
			// API не работает, сервер лёг. В $response->body отдаётся HTML
			self::saveToLog('Error while trying to get companies custom fields list from Amo CRM. Amo CRM API response: ' . $response->body, 'ERROR');
			$error_array = array(
				'error_code'    => $response->code,
				'error_message' => 'Error while trying to get companies custom fields list from Amo CRM. Amo CRM API response: ' . $response->body
			);

			return (object) $error_array;
		}
	}

	/**
	 * Получение списка полей для **контактов**
	 * ## Ограничения
	 * - 	Метод возвращает до 50 полей за один запрос.
	 * -	Метод доступен всем пользователям аккаунта.
	 * ## Метод
	 * GET /api/v4/companies/custom_fields
	 * @return object
	 * @see https://www.amocrm.ru/developers/content/crm_platform/custom-fields
	 * @since 1.0.0
	 */

	public function getCustomersCustomFields()
	{

		$response = self::getResponse('/api/v4/customers/custom_fields', null, 'GET', 'application/json');
		if ($response->code == 200)
		{
			return (object) json_decode($response->body);
		}
		elseif ($response->code >= 400 && $response->code < 500)
		{

			// API работает. Ошибка отдается в json
			$response_body = json_decode($response->body);

			if (isset($response_body->title) || isset($response_body->detail) || isset($response_body->{'validation-errors'}))
			{
				$error_message = self::errorHandler($response_body);

			}
			else
			{
				$error_message = 'no error description';
			}
			self::saveToLog('Error while trying to get customers custom fields list from Amo CRM. Amo CRM API response: ' . $error_message, 'ERROR');

			$error_array = array(
				'error_code'    => $response->code,
				'error_message' => 'Error while trying to get customers custom fields list from Amo CRM. Amo CRM API response: ' . $error_message
			);

			return (object) $error_array;
		}
		elseif ($response->code >= 500)
		{
			// API не работает, сервер лёг. В $response->body отдаётся HTML
			self::saveToLog('Error while trying to get customers custom fields list from Amo CRM. Amo CRM API response: ' . $response->body, 'ERROR');
			$error_array = array(
				'error_code'    => $response->code,
				'error_message' => 'Error while trying to get customers custom fields list from Amo CRM. Amo CRM API response: ' . $response->body
			);

			return (object) $error_array;
		}
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
	 * @see https://www.amocrm.ru/developers/content/crm_platform/contacts-api
	 * @link https://www.amocrm.ru/developers/content/crm_platform/contacts-api#with-88398e14-be90-44b7-91e0-6371e268833b-params
	 * @link https://www.amocrm.ru/developers/content/crm_platform/filters-api
	 * @since 1.0.0
	 */

	public function getContacts(array $data = []) : object
	{

		$response = self::getResponse('/api/v4/customers/custom_fields', json_encode($data), 'GET', 'application/json');
		if ($response->code == 200)
		{
			return (object) json_decode($response->body);
		}
		elseif ($response->code >= 400 && $response->code < 500)
		{

			// API работает. Ошибка отдается в json
			$response_body = json_decode($response->body);

			if (isset($response_body->title) || isset($response_body->detail) || isset($response_body->{'validation-errors'}))
			{
				$error_message = self::errorHandler($response_body);

			}
			else
			{
				$error_message = 'no error description';
			}
			self::saveToLog('Error while trying to get customers custom fields list from Amo CRM. Amo CRM API response: ' . $error_message, 'ERROR');

			$error_array = array(
				'error_code'    => $response->code,
				'error_message' => 'Error while trying to get customers custom fields list from Amo CRM. Amo CRM API response: ' . $error_message
			);

			return (object) $error_array;
		}
		elseif ($response->code >= 500)
		{
			// API не работает, сервер лёг. В $response->body отдаётся HTML
			self::saveToLog('Error while trying to get customers custom fields list from Amo CRM. Amo CRM API response: ' . $response->body, 'ERROR');
			$error_array = array(
				'error_code'    => $response->code,
				'error_message' => 'Error while trying to get customers custom fields list from Amo CRM. Amo CRM API response: ' . $response->body
			);

			return (object) $error_array;
		}
	}

	/**
	 * Get Amo CRM user info by user id
	 * ## Описание
	 * Метод позволяет получить данные конкретного пользователя, состоящего в аккаунте, по ID.
	 * ## Ограничения
	 * Метод доступен только с правами администратора аккаунта.
	 *
	 * @param   int  $user_id Amo CRM user id
	 *
	 * @return object
	 * @link  https://www.amocrm.ru/developers/content/crm_platform/users-api#user-detail
	 * @since 1.0.0
	 */

	public function getUserById(int $user_id): object
	{

		if(empty($user_id)){
			return (object) $error_array = array(
				'error_code'    => 500,
				'error_message' => 'There is nu Amo CRM user ID specified. Request abadoned.'
			);
		}
		$response = self::getResponse('/api/v4/users/'.$user_id, null, 'GET');
		if ($response->code == 200)
		{
			return (object) json_decode($response->body);
		}
		elseif ($response->code >= 400 && $response->code < 500)
		{

			// API работает. Ошибка отдается в json
			$response_body = json_decode($response->body);
			if ($response_body->title || $response_body->detail || $response_body->{'validation-errors'})
			{
				$error_message = self::errorHandler($response_body);
			}
			else
			{
				$error_message = 'no error description';
			}
			self::saveToLog('Error while trying to get Delivery Time via Amo CRM. Amo CRM API response: ' . $error_message, 'ERROR');

			$error_array = array(
				'error_code'    => $response->code,
				'error_message' => 'Error while trying to get Delivery Time via Amo CRM. Amo CRM API response: ' . $error_message
			);

			return (object) $error_array;
		}
		elseif ($response->code >= 500)
		{
			// API не работает, сервер лёг. В $response->body отдаётся HTML
			self::saveToLog('Error while trying to get Delivery Time via Amo CRM. Amo CRM API response: ' . $response->body, 'ERROR');
			$error_array = array(
				'error_code'    => $response->code,
				'error_message' => 'Error while trying to get Delivery Time via Amo CRM. Amo CRM API response: ' . $response->body
			);

			return (object) $error_array;
		}
	}

	/**
	 * ОБработка ошибок из API Amo CRM, вывод ошибок.
	 * @param $response_body
	 *
	 * @return string
	 *
	 * @since 1.0.0
	 */
	private static function errorHandler($response_body): string
	{
		$error_message = '';
		foreach ($response_body as $k => $v)
		{
			if (is_array($v) || is_object($v))
			{
				$error_message .= self::errorHandler($v);
				continue;
			}
			$error_message .= '<b>' . $k . '</b>: ' . $v . PHP_EOL;
		}
		return $error_message;
	}

}
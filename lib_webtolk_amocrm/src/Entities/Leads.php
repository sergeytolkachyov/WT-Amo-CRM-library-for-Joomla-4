<?php
/**
 * AmoCRM leads
 *
 * @see        https://www.amocrm.ru/developers/content/crm_platform/leads-api
 *
 * @package    WT Amocrm Library
 * @version    1.3.0
 * @Author     Sergey Tolkachyov, https://web-tolk.ru
 * @copyright  (c) 2022 - May 2025 Sergey Tolkachyov. All rights reserved.
 * @license    GNU/GPL3 http://www.gnu.org/licenses/gpl-3.0.html
 * @since      1.3.0
 */

namespace Webtolk\Amocrm\Entities;

use Joomla\CMS\Language\Text;
use Webtolk\Amocrm\AmocrmClientException;
use Webtolk\Amocrm\AmocrmRequest;
use Webtolk\Amocrm\Interfaces\EntityInterface;
use Webtolk\Amocrm\Traits\LogTrait;

defined('_JEXEC') or die;

class Leads implements EntityInterface
{
    use LogTrait;

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
     * Получение списка воронок продаж для сделок
     * ## Общая информация
     * -    В каждой воронке есть 3 системных статуса: Неразобранное, Успешно реализовано (ID = 142), Закрыто и не реализовано (ID = 143)
     * -    В аккаунте может быть не более 50 воронок.
     * -    В одной воронке может быть не более 100 статусов, включая системные.
     * ## Метод
     * GET  /api/v4/leads/pipelines
     *
     * @return  object
     *
     * @throws  AmocrmClientException
     * @see     https://www.amocrm.ru/developers/content/crm_platform/leads_pipelines
     * @since   1.3.0
     */
    public function getLeadsPiplines(): object
    {
        return $this->request->getResponse('/leads/pipelines', null, 'GET', 'application/json');
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
     *
     * @throws  AmocrmClientException
     * @see     https://www.amocrm.ru/developers/content/crm_platform/leads-api
     * @since   1.3.0
     */
    public function createLeadsComplex(array $data = []): object
    {
        if (empty($data)) {
            $error_message = Text::_('LIB_WTAMOCRM_ERROR_CREATELEADSCOMPLEX_EMPTY_DATA');
            $this->saveToLog($error_message, 'error');

            return (object) [
                'error_code' => 500,
                'error_message' => $error_message
            ];
        }

        $endpoint = '/leads/complex';

        return $this->request->getResponse($endpoint, $data, 'POST', 'application/json');
    }

    /**
     * Позволяет пакетно добавлять сделки в Амо.
     *
     * ## Метод
     * POST /api/v4/leads
     * ## Структура массива:
     *
     * [
     *  {
     *      "name": "Сделка для примера 1",
     *      "created_by": 0,
     *      "price": 20000,
     *      "custom_fields_values": [
     *      {
     *          "field_id": 294471,
     *              "values": [
     *                  {
     *                      "value": "Наш первый клиент"
     *                  }
     *              ]
     *          }
     *      ]
     *      },
     *      {
     *      "name": "Сделка для примера 2",
     *      "price": 10000,
     *      "_embedded": {
     *          "tags": [
     *              {
     *                "id": 2719
     *              }
     *          ]
     *      }
     *  }
     * ]
     *
     * @param   array  $data
     *
     * @return  object
     *
     * @throws  AmocrmClientException
     * @see     https://www.amocrm.ru/developers/content/crm_platform/leads-api
     * @since   1.3.0
     */
    public function createLeads(array $data): object
    {
        if (empty($data)) {
            $error_message = Text::sprintf('LIB_WTAMOCRM_ERROR_METHOD_RECEIVED_EMPTY_DATA', __METHOD__);
            $this->saveToLog($error_message, 'error');

            return (object) [
                'error_code' => 500,
                'error_message' => $error_message
            ];
        }

        return $this->request->getResponse('/leads', $data, 'POST', 'application/json');
    }

    /**
     * Метод позволяет получить данные конкретной сделки по ID.
     *
     * ## Метод
     * GET /api/v4/leads/{id}
     *
     * @param   int     $id    AmoCRM lead id
     * @param   string  $with  Данный параметр принимает строку, в том числе из нескольких значений, указанных через запятую.
     *
     * @return  object
     *
     * @throws  AmocrmClientException
     * @since   1.3.0
     * @see     https://www.amocrm.ru/developers/content/crm_platform/leads-api#with-4ddbc99f-7fb3-4c0c-83f5-c58ec7b924be-params
     */
    public function getLeadById(int $id, string $with = ''): object
    {
        if (empty($id) || $id < 1) {
            $error_message = Text::sprintf('LIB_WTAMOCRM_ERROR_METHOD_RECEIVED_EMPTY_DATA', __METHOD__);
            $this->saveToLog($error_message, 'error');

            return (object) [
                'error_code' => 500,
                'error_message' => $error_message
            ];
        }

        return $this->request->getResponse('/leads/' . $id, [$with], 'GET');
    }

    /**
     * Метод позволяет получить список сделок.
     *
     * ## Метод
     * GET /api/v4/leads
     *
     * @param   array  $data  request data array
     *
     * @return  object
     *
     * @throws  AmocrmClientException
     * @since   1.3.0
     * @see     https://www.amocrm.ru/developers/content/crm_platform/leads-api
     */
    public function getLeads(array $data = []): object
    {
        return $this->request->getResponse('/leads', $data, 'GET');
    }

    /**
     * Пакетное редактирование **сделок**.
     *
     *  ## Метод
     *  PATCH /api/v4/leads
     * ## Параметры
     * Обязательные поля отсутствуют
     * - name string Название сделки. Поле не является обязательным
     * - price int Бюджет сделки. Поле не является обязательным
     * - status_id int ID статуса, в который добавляется сделка. Поле не является обязательным, по-умолчанию – первый этап главной воронки
     * - pipeline_id int ID воронки, в которую добавляется сделка. Поле не является обязательным
     * - created_by int ID пользователя, создающий сделку. При передаче значения 0, сделка будет считаться созданной роботом. Поле не является обязательным
     * - updated_by int ID пользователя, изменяющий сделку. При передаче значения 0, сделка будет считаться измененной роботом. Поле не является обязательным
     * - closed_at int Дата закрытия сделки, передается в Unix Timestamp. Поле не является обязательным
     * - created_at int Дата создания сделки, передается в Unix Timestamp. Поле не является обязательным
     * - updated_at int Дата изменения сделки, передается в Unix Timestamp. Поле не является обязательным
     * - loss_reason_id int ID причины отказа. Поле не является обязательным
     * - responsible_user_id int ID пользователя, ответственного за сделку. Поле не является обязательным
     * - custom_fields_values array Массив, содержащий информацию по дополнительным полям, заданным для данной сделки. Поле не является обязательным. Примеры заполнения полей
     * - tags_to_add array Массив тегов для добавления.
     * -- tags_to_add[0][id] array ID тега для добавления. Важно передать или id или name.
     * -- tags_to_add[0][name] array Название тега для добавления. Важно передать или id или name.
     * - tags_to_delete array Массив тегов для удаления.
     * -- tags_to_delete[0][id] array ID тега для удаления. Важно передать или id или name.
     * -- tags_to_delete[0][name] array Название тега для удаления. Важно передать или id или name.
     * - _embedded object Данные вложенных сущностей
     * -- _embedded[tags][0][id] int ID тега, привязанного к контакту
     * -- _embedded[tags][0][name] string Название тега, привязанного к контакту
     *
     * @param   array  $data  Массив с массивами с данными сделки.
     *
     * @return  object
     *
     * @throws  AmocrmClientException
     * @link    https://www.amocrm.ru/developers/content/crm_platform/leads-api#leads-edit
     * @link    https://www.amocrm.ru/developers/content/crm_platform/custom-fields#cf-fill-examples
     * @since   1.3.0
     */
    public function editLeadsBatch(array $data): object
    {
        if (empty($data)) {
            $error_message = Text::sprintf('LIB_WTAMOCRM_ERROR_METHOD_RECEIVED_EMPTY_DATA', __METHOD__);
            $this->saveToLog($error_message,'error');
            return (object) [
                'error_code' => 500,
                'error_message' => $error_message
            ];
        }

        return $this->request->getResponse('/leads', $data, 'PATCH', 'application/json');
    }

    /**
     * Редактирование **единичной сделки**.
     *
     *  ## Метод
     *  PATCH /api/v4/leads/{id}
     * ## Параметры
     * Обязательные поля отсутствуют
     * - name string Название сделки. Поле не является обязательным
     * - price int Бюджет сделки. Поле не является обязательным
     * - status_id int ID статуса, в который добавляется сделка. Поле не является обязательным, по-умолчанию – первый этап главной воронки
     * - pipeline_id int ID воронки, в которую добавляется сделка. Поле не является обязательным
     * - created_by int ID пользователя, создающий сделку. При передаче значения 0, сделка будет считаться созданной роботом. Поле не является обязательным
     * - updated_by int ID пользователя, изменяющий сделку. При передаче значения 0, сделка будет считаться измененной роботом. Поле не является обязательным
     * - closed_at int Дата закрытия сделки, передается в Unix Timestamp. Поле не является обязательным
     * - created_at int Дата создания сделки, передается в Unix Timestamp. Поле не является обязательным
     * - updated_at int Дата изменения сделки, передается в Unix Timestamp. Поле не является обязательным
     * - loss_reason_id int ID причины отказа. Поле не является обязательным
     * - responsible_user_id int ID пользователя, ответственного за сделку. Поле не является обязательным
     * - custom_fields_values array Массив, содержащий информацию по дополнительным полям, заданным для данной сделки. Поле не является обязательным. Примеры заполнения полей
     * - tags_to_add array Массив тегов для добавления.
     * -- tags_to_add[0][id] array ID тега для добавления. Важно передать или id или name.
     * -- tags_to_add[0][name] array Название тега для добавления. Важно передать или id или name.
     * - tags_to_delete array Массив тегов для удаления.
     * -- tags_to_delete[0][id] array ID тега для удаления. Важно передать или id или name.
     * -- tags_to_delete[0][name] array Название тега для удаления. Важно передать или id или name.
     * - _embedded object Данные вложенных сущностей
     * -- _embedded[tags][0][id] int ID тега, привязанного к контакту
     * -- _embedded[tags][0][name] string Название тега, привязанного к контакту
     *
     * @param   int    $lead_id  id сделки в AmoCRM
     * @param   array  $data     Массив с данными сделки.
     *
     * @return  object
     *
     * @throws  AmocrmClientException
     * @link    https://www.amocrm.ru/developers/content/crm_platform/leads-api#leads-edit
     * @link    https://www.amocrm.ru/developers/content/crm_platform/custom-fields#cf-fill-examples
     * @since   1.3.0
     */
    public function editLead(int $lead_id, array $data): object
    {
        if (empty($data)) {
            $error_message = Text::sprintf('LIB_WTAMOCRM_ERROR_METHOD_RECEIVED_EMPTY_DATA', __METHOD__);
            $this->saveToLog($error_message,'warning');
            return (object) [
                'error_code' => 500,
                'error_message' => $error_message
            ];
        }

        return $this->request->getResponse('/leads/'.$lead_id, $data, 'PATCH', 'application/json');
    }
}
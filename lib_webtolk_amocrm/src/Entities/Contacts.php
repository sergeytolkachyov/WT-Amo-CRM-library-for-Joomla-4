<?php
/**
 * AmoCRM contacts
 *
 * @see        https://www.amocrm.ru/developers/content/crm_platform/contacts-api
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

class Contacts implements EntityInterface
{
    use LogTrait;

    /** @var AmocrmRequest $request */
    private AmocrmRequest $request;

    /**
     * Account constructor.
     *
     * @param  AmocrmRequest  $request
     * @since  1.3.0
     */
    public function __construct(AmocrmRequest $request)
    {
        $this->request = $request;
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
     * @since   1.3.0
     */
    public function getContacts(array $data = []): object
    {
        return $this->request->getResponse('/contacts', $data, 'GET', 'application/json');
    }

    /**
     * Получение контакта по ID
     * ## Метод
     * GET /api/v4/contacts/{id}
     *
     * ## Описание
     * Метод позволяет получить данные конкретного контакта по ID.
     *
     * ## Ограничения
     * Метод доступен в соответствии с правами пользователя
     *
     * @param   int     $contact_id  AmoCRM contact id
     * @param   string  $with        Данный параметр принимает строку, в том числе из нескольких значений, указанных через запятую.
     *                               Данный метод поддерживает следующие параметры. См. ссылку
     *
     * @return  object
     *
     * @throws  AmocrmClientException
     * @link    https://www.amocrm.ru/developers/content/crm_platform/contacts-api#with-03cd15fc-1b19-487c-93c5-99f959628f45-params
     * @since   1.3.0
     */
    public function getContactById(int $contact_id, string $with = ''): object
    {
        $data = [];
        if (!empty($with)) {
            $data['with'] = $with;
        }

        return $this->request->getResponse('/contacts/'.$contact_id, $data, 'GET', 'application/json');
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
     * @throws  AmocrmClientException
     * @see     https://www.amocrm.ru/developers/content/crm_platform/contacts-api#contacts-add
     * @link    https://www.amocrm.ru/developers/content/crm_platform/custom-fields#cf-fill-examples
     * @link    https://www.amocrm.ru/developers/content/crm_platform/filters-api
     * @since   1.3.0
     */
    public function addContacts(array $data = []): object
    {
        if (empty($data)) {
            $error_message = Text::sprintf('LIB_WTAMOCRM_ERROR_METHOD_RECEIVED_EMPTY_DATA', __METHOD__);
            $this->saveToLog($error_message,'warning');
            return (object) [
                'error_code' => 500,
                'error_message' => $error_message
            ];
        }

        return $this->request->getResponse('/contacts', $data, 'POST', 'application/json');
    }

    /**
     * Редактирование контактов.
     * Метод позволяет редактировать контакты пакетно.
     *  ## Метод
     *  PATCH /api/v4/contacts
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
     * -- tags_to_add[0][id] array ID тега для добавления. Важно передать или id или name.
     * -- tags_to_add[0][name] array Название тега для добавления. Важно передать или id или name.
     * - tags_to_delete array Массив тегов для удаления.
     * -- tags_to_delete[0][id] array ID тега для удаления. Важно передать или id или name.
     * -- tags_to_delete[0][name] array Название тега для удаления. Важно передать или id или name.
     * - _embedded object Данные вложенных сущностей
     * -- _embedded[tags][0][id] int ID тега, привязанного к контакту
     * -- _embedded[tags][0][name] string Название тега, привязанного к контакту
     *
     * @param   array  $data  Массив с массивами данных пользователей.
     *
     * @return  object
     *
     * @throws  AmocrmClientException
     * @link    https://www.amocrm.ru/developers/content/crm_platform/contacts-api
     * @since   1.3.0
     */
    public function editContactsBatch(array $data): object
    {
        if (empty($data)) {
            $error_message = Text::sprintf('LIB_WTAMOCRM_ERROR_METHOD_RECEIVED_EMPTY_DATA', __METHOD__);
            $this->saveToLog($error_message,'warning');
            return (object) [
                'error_code' => 500,
                'error_message' => $error_message
            ];
        }

        return $this->request->getResponse('/contacts', $data, 'PATCH', 'application/json');
    }

    /**
     * Редактирование **единичного контакта**.
     *
     *  ## Метод
     *  PATCH /api/v4/contacts/{id}
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
     * -- tags_to_add[0][id] array ID тега для добавления. Важно передать или id или name.
     * -- tags_to_add[0][name] array Название тега для добавления. Важно передать или id или name.
     * - tags_to_delete array Массив тегов для удаления.
     * -- tags_to_delete[0][id] array ID тега для удаления. Важно передать или id или name.
     * -- tags_to_delete[0][name] array Название тега для удаления. Важно передать или id или name.
     * - _embedded object Данные вложенных сущностей
     * -- _embedded[tags][0][id] int ID тега, привязанного к контакту
     * -- _embedded[tags][0][name] string Название тега, привязанного к контакту
     *
     * @param   int    $contact_id  id контакта в AmoCRM
     * @param   array  $data        Массив с данными пользователя.
     *
     * @return  object
     *
     * @throws  AmocrmClientException
     * @link    https://www.amocrm.ru/developers/content/crm_platform/contacts-api
     * @since   1.3.0
     */
    public function editContact(int $contact_id, array $data): object
    {
        if (empty($data)) {
            $error_message = Text::sprintf('LIB_WTAMOCRM_ERROR_METHOD_RECEIVED_EMPTY_DATA', __METHOD__);
            $this->saveToLog($error_message,'warning');
            return (object) [
                'error_code' => 500,
                'error_message' => $error_message
            ];
        }

        return $this->request->getResponse('/contacts/'.$contact_id, $data, 'PATCH', 'application/json');
    }
}
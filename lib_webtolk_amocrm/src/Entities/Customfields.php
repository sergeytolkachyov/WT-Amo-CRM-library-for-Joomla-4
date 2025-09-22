<?php
/**
 * AmoCRM custom fields
 *
 * @see        https://www.amocrm.ru/developers/content/crm_platform/custom-fields
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
use Webtolk\Amocrm\Traits\DataErrorTrait;
use Webtolk\Amocrm\Traits\LogTrait;

defined('_JEXEC') or die;

class Customfields implements EntityInterface
{
    use LogTrait;
    use DataErrorTrait;

    /** @var AmocrmRequest $request */
    private AmocrmRequest $request;

    /**
     * @var array|string[] $allowed_entites
     * @since 1.3.0
     */
    protected static array $allowed_entites = ['leads', 'contacts', 'companies', 'customers'];

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
     */
    public function getCustomFields(string $entity_type = 'leads', array $data = []): object
    {
        $allowed_entites = ['leads', 'contacts', 'companies', 'customers'];
        if (!in_array($entity_type, $allowed_entites)) {

            $error_message = Text::sprintf(
                'LIB_WTAMOCRM_ERROR_GETCUSTOMFIELDS_WRONG_ENTITY_TYPE',
                $entity_type,
                implode(', ', $allowed_entites)
            );
            $this->saveToLog($error_message, 500);
            return (object) [
                'error_code' => 500,
                'error_message' => $error_message
            ];
        }

        $endpoint = '/' . $entity_type . '/custom_fields';

        return $this->request->getResponse($endpoint, $data, 'GET', 'application/json');
    }

    /**
     * Получение списка полей для **сделок**. Прокси-метод.
     * ## Ограничения
     * -    Метод возвращает до 250 полей за один запрос.
     * -    Метод доступен всем пользователям аккаунта.
     * ## Метод
     * GET /api/v4/leads/custom_fields
     *
     * @param   array  $data
     *
     * @return  object
     *
     * @throws  AmocrmClientException
     * @see     https://www.amocrm.ru/developers/content/crm_platform/custom-fields
     * @since   1.3.0
     */
    public function getLeadsCustomFields(array $data = []): object
    {
        return $this->getCustomFields('leads', $data);
    }

    /**
     * Получение списка полей для **контактов**. Прокси-метод.
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
     */
    public function getContactsCustomFields(array $data = []): object
    {
        return $this->getCustomFields('contacts', $data);
    }

    /**
     * Получение списка полей для **контактов**. Прокси-метод.
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
     */
    public function getCompaniesCustomFields(array $data = []): object
    {
        return $this->getCustomFields('companies', $data);
    }

    /**
     * Получение списка полей для **сегментов**. Прокси-метод.
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
     * @since   1.3.0
     */
    public function getSegmentsCustomFields(array $data = []): object
    {
        return $this->getCustomFields('segments', $data);
    }

    /**
     * Получение списка полей для **покупателей** (если включены "периодические покупки" в настройках AmoCRM).
     * Прокси-метод.
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
     * @since   1.3.0
     */
    public function getCustomersCustomFields(array $data = []): object
    {
        return $this->getCustomFields('customers', $data);
    }

    /**
     * Редактирование контактов.
     * Метод позволяет редактировать сущности пакетно.
     * Также вы можете добавить ID поля в метод для редактирования конкретной сущности (например /api/v4/leads/custom_fields/{id}).
     *  ## Метод
     *  PATCH /api/v4/{$entity_type}/custom_fields
     *  PATCH /api/v4/{$entity_type}/custom_fields/{$entity_id}
     *
     * ## Ограничения
     * Метод доступен только администраторам аккаунта.
     *
     * ## Параметры
     * Обязательные поля отсутствуют
     * - name string Название контакта
     * - code string Код поля, по-которому можно обновлять значение в сущности, без передачи ID поля
     * - sort int Сортировка поля в группе полей
     * - group_id bool ID группы полей
     * - is_api_only int Доступно ли поле для редактирования только через API
     * - required_statuses array|null Обязательные поля для смены этапа сделки
     * - required_statuses[0][status_id] int ID статуса, для перехода в который данное поле обязательно к заполнению
     * - required_statuses[0][pipeline_id] int ID воронки, для перехода в который данное поле обязательно к заполнению
     * - settings object Настройки поля
     * - is_visible bool Отображается ли поле в интерфейсе списка
     * - is_required bool Обязательно ли поле для заполнения при создании элемента списка
     * - remind string|null Когда напоминать о дне рождения (never – никогда, day – за день, week – за неделю, month – за месяц)
     * - enums array Доступные значения поля. Доступно для полей:
     *    -- multiselect
     *    -- radiobutton
     *    -- select
     * - enums[0][id] int ID значения необходимо передавать, если вы хотите обновить предыдущие данные без удаления актуальных ID. В случае, если вы не передадите это поле, предыдущие ID будут удалены
     * - enums[0][value] string Значение одного из доступных значений поля. Доступно для полей:
     *    -- multiselect
     *    -- radiobutton
     *    -- select
     * - enums[0][sort] int Сортировка среди других доступных значений поля. Доступно для полей:
     *   -- multiselect
     *   -- radiobutton
     *   -- select
     * - enums[0][code] string Символьный код значения. Доступно для полей:
     *    -- multiselect
     *    -- radiobutton
     *    -- select
     * - nested array Вложенные значения. Доступно для полей: category
     * -- nested[0][id] int ID вложенного значения. Для создания нового значение не нужно передавать
     * -- nested[0][parent_id] int ID родительского вложенного значения
     * -- nested[0][value] string Значение вложенного значения
     * -- nested[0][sort] int Сортировка вложенного значения
     * -- nested[0][request_id] string Временный идентификатор вложенного значения, должен быть уникальным в пределах запроса, нигде не сохраняется, используется для создания больше одного уровня вложенности за запрос
     * -- nested[0][parent_request_id] string Временный идентификатор родителя вложенного значения, используется только на момент запроса, нигде не сохраняется, определяет на какой уровень вложенности добавлять элемент, если родительский элемент еще не был создан
     * -- tracking_callback string Сallback js-функция, которая будет выполнена на странице с CRM Plugin и формой amoCRM при отправке данных. Доступно для tracking_data
     * - hidden_statuses array Настройка скрытия полей. Поля скрываются в интерфейсе в зависимости от статуса. Данный ключ возвращается только при получении списка полей сделок. Доступно для всех полей сделок, но только, если в аккаунте подключена функция Супер-поля
     *
     *
     * @param   string  $entity_type  Amo CRM entity type: `lead`, `contact`, `catalogs/{catalog_id}`, `customers/segments` etc.
     * @param   array   $data         Массив с массивами данных пользователей.
     * @param   ?int    $entity_id    id сущности (сделки, контакта etc.) для редактирования поля.
     *
     * @return  object
     *
     * @throws  AmocrmClientException
     * @link    https://www.amocrm.ru/developers/content/crm_platform/custom-fields#%D0%A0%D0%B5%D0%B4%D0%B0%D0%BA%D1%82%D0%B8%D1%80%D0%BE%D0%B2%D0%B0%D0%BD%D0%B8%D0%B5-%D0%B4%D0%BE%D0%BF%D0%BE%D0%BB%D0%BD%D0%B8%D1%82%D0%B5%D0%BB%D1%8C%D0%BD%D1%8B%D1%85-%D0%BF%D0%BE%D0%BB%D0%B5%D0%B9-%D1%81%D1%83%D1%89%D0%BD%D0%BE%D1%81%D1%82%D0%B8
     * @since   1.3.0
     */
    public function editCustomFieldsBatch(string $entity_type, array $data, ?int $entity_id = null): object
    {
        if (!$this->checkCustomFieldsEntity($entity_type)) {
            return $this->wrongEntityType(__METHOD__, $entity_type, self::$allowed_entites);
        }

        if (empty($data)) {
            return $this->receivedEmptyData(__METHOD__);
        }

        if (!empty($entity_id)) {
            $endpoint = '/' . $entity_type . '/custom_fields/' . $entity_id;
        } else {
            $endpoint = '/' . $entity_type . '/custom_fields';
        }

        return $this->request->getResponse($endpoint, $data, 'PATCH', 'application/json');
    }

    /**
     * @param   string  $entity_type
     *
     * @return  bool
     *
     * @since   1.3.0
     */
    private function checkCustomFieldsEntity(string $entity_type): bool
    {
        return in_array($entity_type, self::$allowed_entites);
    }
}
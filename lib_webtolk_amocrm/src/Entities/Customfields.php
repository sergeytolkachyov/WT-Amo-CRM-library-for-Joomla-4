<?php
/**
 * AmoCRM custom fields
 *
 * @see https://www.amocrm.ru/developers/content/crm_platform/custom-fields
 *
 * @package           WT Amocrm Library
 * @version           1.3.0-alpha2
 * @Author            Sergey Tolkachyov, https://web-tolk.ru
 * @copyright  (c)    2022 - May 2025 Sergey Tolkachyov. All rights reserved.
 * @license           GNU/GPL3 http://www.gnu.org/licenses/gpl-3.0.html
 * @since             1.3.0
 */

namespace Webtolk\Amocrm\Entities;

use Joomla\CMS\Language\Text;
use Webtolk\Amocrm\AmocrmRequest;
use Webtolk\Amocrm\Interfaces\EntityInterface;

use Webtolk\Amocrm\Traits\LogTrait;

use function defined;

defined('_JEXEC') or die;

class Customfields implements EntityInterface
{
    use LogTrait;

    /** @param AmocrmRequest $request */
    private AmocrmRequest $request;

    /**
     * Account constructor.
     * @param AmocrmRequest $request
     * @since 1.3.0
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
     * @return object
     *
     * @since 1.3.0
     */
    public function getCustomFields(string $entity_type = 'leads', array $data = []): object
    {
        $allowed_entites = ['leads', 'contacts', 'companies', 'customers'];
        if (!in_array($entity_type, $allowed_entites)) {

            $error_message = Text::sprintf(
                'LIB_WTAMOCRM_ERROR_GETCUSTOMFIELDS_WRONG_ENTITY_TYPE',
                $entity_type,
                implode(
                    ', ',
                    $allowed_entites
                )
            );
            $this->saveToLog($error_message, 500);
            return (object)[
                'error_code'    => 500,
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
     * @return object
     * @see   https://www.amocrm.ru/developers/content/crm_platform/custom-fields
     * @since 1.3.0
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
     * @return object
     * @see   https://www.amocrm.ru/developers/content/crm_platform/custom-fields
     * @since 1.0.0
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
     * @return object
     * @see   https://www.amocrm.ru/developers/content/crm_platform/custom-fields
     * @since 1.0.0
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
     * @return object
     * @see   https://www.amocrm.ru/developers/content/crm_platform/custom-fields
     * @since 1.3.0
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
     * @return object
     * @see   https://www.amocrm.ru/developers/content/crm_platform/custom-fields
     * @since 1.3.0
     */

    public function getCustomersCustomFields(array $data = []): object
    {
        return $this->getCustomFields('customers', $data);
    }
}
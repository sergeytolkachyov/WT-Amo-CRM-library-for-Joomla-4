<?php
/**
 * AmoCRM users
 *
 * @see        https://www.amocrm.ru/developers/content/crm_platform/users-api
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

class Users implements EntityInterface
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
     * Список пользователей.
     * ## Метод
     * GET /api/v4/users
     *
     * Время ответа данного метода может варьироваться в зависимости от количества пользователей,
     * подключённых к аккаунту. Увеличение числа активных пользователей может приводить
     * к увеличению времени обработки запроса. Для оптимизации работы с данным методом настоятельно
     * рекомендуем сохранять данные, полученные по этому запросу, для последующего использования,
     * и избегать частых повторных вызовов метода, чтобы снизить нагрузку на систему и
     * улучшить производительность.
     *
     * ## Параметры
     * - with string role|group|uuid|amojo_id|user_rank Данный параметр принимает строку, в том числе из нескольких значений, указанных через запятую.
     * - page int Страница выборки
     * - limit int Количество возвращаемых сущностей за один запрос (Максимум – 250)
     *
     * @param   array  $data
     *
     * @return  object
     *
     * @throws  AmocrmClientException
     * @since   1.3.0
     * @link    https://www.amocrm.ru/developers/content/crm_platform/users-api#with-3b4e201a-ba14-4f06-880e-987e2c091855-params
     */
    public function getUsers(array $data = []): object
    {
        return $this->request->getResponse('/users', $data, 'GET');
    }

    /**
     * Get Amo CRM user info by user id
     * ## Описание
     * Метод позволяет получить данные конкретного пользователя, состоящего в аккаунте, по ID.
     * ## Ограничения
     * Метод доступен только с правами администратора аккаунта.
     *
     * @param   int     $user_id  Amo CRM user id
     * @param   string  $with     Данный параметр принимает строку, в том числе из нескольких значений, указанных через запятую.
     *
     * @return  object
     *
     * @throws  AmocrmClientException
     * @link    https://www.amocrm.ru/developers/content/crm_platform/users-api#user-detail
     * @since   1.3.0
     */
    public function getUserById(int $user_id, string $with = ''): object
    {
        if (empty($user_id)) {
            return (object) [
                'error_code' => 500,
                'error_message' => Text::_('LIB_WTAMOCRM_ERROR_GETUSERBYID_EMPTY_USER_ID')
            ];
        }

        return $this->request->getResponse('/users/' . $user_id, ['with' => $with], 'GET', 'application/json');
    }

    /**
     * Добавление пользователей
     * ## Метод
     * POST /api/v4/users
     * ## Описание
     * Метод позволяет добавлять пользователей в аккаунт пакетно.
     * ## Ограничения
     * - Метод доступен только с правами администратора аккаунта.
     * - Метод позволяет добавлять не более 10 пользователей за один запрос.
     * - Метод становится недоступен, когда количество пользователей превышает 100.
     *
     * ## Параметры запроса
     * В данном методе параметры запроса имеют зависимости. Подробнее на странице документации.
     *
     * - name string Полное имя пользователя. Значение не должно содержать спец символов кроме .@-_, состоять из пробелов, содержать ссылок, а также не может быть более 50 символов. При создании нового пользователя, поле является обязательным
     * - email string E-mail пользователя. Поле является обязательным
     * - password string Пароль пользователя, должно состоять минимум из 6 символов и иметь хотя бы 1 цифру, маленькую и заглавную букву. При создании нового пользователя, поле является обязательным
     * - lang string Язык пользователя. Выбор из вариантов: en, ru, es. Поле не является обязательным, по умолчанию – язык аккаунта (ru или en)
     * - rights object Права пользователя. Поле не является обязательным, по умолчанию все доступы запрещены. Список доступных прав подробнее в документации.
     *
     * @param   array  $data  Массив с массивами данных пользователей.
     *
     * @return  object
     *
     * @throws  AmocrmClientException
     * @link    https://www.amocrm.ru/developers/content/crm_platform/users-api#with-29e6f481-152c-48d0-8523-bc4f3874753e-params
     * @since   1.3.0
     */
    public function addUsers(array $data): object
    {
        if (empty($data)) {
            return $this->receivedEmptyData(__METHOD__);
        }

        return $this->request->getResponse('/users', $data, 'POST', 'application/json');
    }

    /**
     * Список ролей пользователей.
     * Метод позволяет получить список ролей пользователей в аккаунте.
     *
     * ## Метод
     * GET /api/v4/roles
     *
     * ## Ограничения
     * Метод доступен только с правами администратора аккаунта.
     * ## Параметры
     * - with string `users` Добавляет в ответ ID пользователей, у которых установлена роль
     * - page int страница выборки
     * - limit int Количество возвращаемых сущностей за один запрос (Максимум – 250)
     *
     * @param   array  $data
     *
     * @return  object
     *
     * @throws  AmocrmClientException
     * @since   1.3.0
     * @link    https://www.amocrm.ru/developers/content/crm_platform/users-api#roles-list
     */
    public function getRoles(array $data = []): object
    {
        return $this->request->getResponse('/roles', $data, 'GET', 'application/json');
    }
}
<?php
/**
 * AmoCRM notes
 *
 * @see               https://www.amocrm.ru/developers/content/crm_platform/events-and-notes#notes-types
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

use Webtolk\Amocrm\Traits\DataErrorTrait;
use Webtolk\Amocrm\Traits\LogTrait;

use function defined;

defined('_JEXEC') or die;

class Notes implements EntityInterface
{
    use LogTrait;
    use DataErrorTrait;

    /**
     * @var array|string[]
     * @since 1.3.0
     */
    protected static array $allowed_entites = ['leads', 'contacts', 'companies', 'customers'];

    /** @param   AmocrmRequest  $request */
    private AmocrmRequest $request;

    /**
     * Account constructor.
     *
     * @param   AmocrmRequest  $request
     *
     * @since 1.3.0
     */
    public function __construct(AmocrmRequest $request)
    {
        $this->request = $request;
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
     * @return object
     * @link  https://www.amocrm.ru/developers/content/crm_platform/events-and-notes#notes-add
     * @since 1.3.0
     */

    public function addNotes(string $entity_type = 'leads', int $entity_id = 0, array $notes = []): object
    {
        if (!$this->checkNotesEntity($entity_type)) {
            return $this->wrongEntityType(__METHOD__, $entity_type, self::$allowed_entites);
        }

        if (empty($notes)) {
            return $this->receivedEmptyData(__METHOD__);
        }

        if (!empty($entity_id)) {
            $endpoint = '/' . $entity_type . '/' . $entity_id . '/notes';
        } else {
            $endpoint = '/' . $entity_type . '/notes';
        }

        return $this->request->getResponse($endpoint, $notes, 'POST', 'application/json');
    }

    /**
     * @param   string  $entity_type
     *
     * @return bool
     *
     * @since 1.3.0
     */
    private function checkNotesEntity(string $entity_type): bool
    {
        return in_array($entity_type, self::$allowed_entites);
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
     * @param   string  $entity_type  Entity type: contacts, leads etc.
     * @param   int     $entity_id    Entity id
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
     *                                - order object Сортировка результатов списка.
     *                                Доступные поля для сортировки: updated_at, id.
     *                                Доступные значения для сортировки: asc, desc.
     *                                Пример: /api/v4/leads/notes?order[updated_at]=asc
     *
     * @return object
     * @link       https://www.amocrm.ru/developers/content/crm_platform/events-and-notes#notes-list
     * @since      1.3.0
     */

    public function getNotes(string $entity_type, int $entity_id, array $params = []): object
    {
        if (!$this->checkNotesEntity($entity_type)) {
            return $this->wrongEntityType(__METHOD__, $entity_type, self::$allowed_entites);
        }

        return $this->request->getResponse(
            '/' . $entity_type . '/' . $entity_id . '/notes',
            $params,
            'GET',
            'application/json'
        );
    }


    /**
     * Редактирование примечаний.
     * Метод позволяет редактировать примечания пакетно.
     * При редактировании пакетно передается массив из объектов-примечаний.
     *  ## Метод
     *  PATCH /api/v4/{entity_type}/notes
     *
     * ## Параметры
     * Обязательные поля отсутствуют
     * - entity_id int ID сущности, в которую добавляется примечание. Обязателен при использовании метода создания примечания в сущности
     * - note_type string Тип примечания.
     * - params object Свойства примечания, зависят от типа примечания. Подробней о свойствах читайте по ссылке ниже.
     *
     * @param   string  $entity_type  Тип сущности, в которой редактируется примечание.
     * @param   array   $data         Массив с массивами примечаний.
     *
     * @link  https://www.amocrm.ru/developers/content/crm_platform/events-and-notes#notes-edit
     * @link  https://www.amocrm.ru/developers/content/crm_platform/events-and-notes#notes-types
     * @link  https://www.amocrm.ru/developers/content/crm_platform/events-and-notes#notes-params-info
     *
     * @since 1.3.0
     */
    public function editNotesBatch(string $entity_type, array $data): object
    {
        if (!$this->checkNotesEntity($entity_type)) {
            return $this->wrongEntityType(__METHOD__, $entity_type, self::$allowed_entites);
        }

        if (empty($data)) {
            return $this->receivedEmptyData(__METHOD__);
        }

        return $this->request->getResponse('/' . $entity_type . '/notes', $data, 'PATCH', 'application/json');
    }


    /**
     * Редактирование **единичного примечания** к сущности.
     *
     * ## Метод
     * PATCH /api/v4/{entity_type}/{entity_id}/notes
     * PATCH /api/v4/{entity_type}/{entity_id}/notes/{id}
     *
     * ## Параметры
     * Обязательные поля отсутствуют
     * - entity_id int ID сущности, в которую добавляется примечание. Обязателен при использовании метода создания примечания в сущности
     * - note_type string Тип примечания.
     * - params object Свойства примечания, зависят от типа примечания. Подробней о свойствах читайте по ссылке ниже.
     *
     * @param   string  $entity_type  тип сущности в AmoCRM
     * @param   int     $contact_id   id контакта в AmoCRM
     * @param   array   $data         Массив с данными пользователя.
     * @param   ?int    $note_id      Массив с данными пользователя.
     *
     * @link  https://www.amocrm.ru/developers/content/crm_platform/contacts-api
     * @since 1.3.0
     */
    public function editNote(string $entity_type, int $entity_id, array $data, ?int $note_id = null): object
    {
        if (!$this->checkNotesEntity($entity_type)) {
            return $this->wrongEntityType(__METHOD__, $entity_type, self::$allowed_entites);
        }

        if (empty($data)) {
            return $this->receivedEmptyData(__METHOD__);
        }

        if (!empty($entity_id)) {
            $endpoint = '/' . $entity_type . '/' . $entity_id . '/notes';
            if (!empty($note_id)) {
                $endpoint = '/' . $entity_type . '/' . $entity_id . '/notes/' . $note_id;
            }
        } else {
            $endpoint = '/' . $entity_type . '/notes';
        }

        return $this->request->getResponse($endpoint, $data, 'PATCH', 'application/json');
    }
}
<?php
/**
 * AmoCRM tags
 *
 * @see               https://www.amocrm.ru/developers/content/crm_platform/tags-api
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
use Webtolk\Amocrm\Interface\EntityInterface;

use Webtolk\Amocrm\Trait\LogTrait;

use function defined;

defined('_JEXEC') or die;

class Tags implements EntityInterface
{
    use LogTrait;

    /** @param AmocrmRequest $request */
    private AmocrmRequest $request;

    /**
     * @var array|string[]
     * @since 1.3.0
     */
    protected static array $allowed_entites = ['leads', 'contacts', 'companies', 'customers'];

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
     * @param   string  $entity_type
     *
     * @return bool
     *
     * @since 1.3.0
     */
    private function checkTagEntity(string $entity_type): bool
    {
        return in_array($entity_type, self::$allowed_entites);
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
     * @param   string  $entity_type
     * @param   array   $data
     *
     * @return object
     * @see   https://www.amocrm.ru/developers/content/crm_platform/tags-api
     * @since 1.3.0
     */

    public function getTags(string $entity_type = 'leads', array $data = []): object
    {

        if (!$this->checkTagEntity($entity_type)) {

            $error_message = Text::sprintf(
                'LIB_WTAMOCRM_ERROR_GETTAGS_WRONG_ENTITY_TYPE',
                $entity_type,
                implode(
                    ', ',
                    self::$allowed_entites
                )
            );
            $this->saveToLog($error_message, 'error');
            return (object)[
                'error_code'    => 500,
                'error_message' => $error_message
            ];
        }

        return $this->request->getResponse('/' . $entity_type . '/tags', $data, 'GET', 'application/json');
    }
}
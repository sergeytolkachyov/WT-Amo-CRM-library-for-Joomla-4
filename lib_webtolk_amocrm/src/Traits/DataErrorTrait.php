<?php
/**
 * @package    WT Amo CRM library package
 * @version    1.3.1
 * @Author     Sergey Tolkachyov, https://web-tolk.ru
 * @copyright  (c) 2022 - September 2025 Sergey Tolkachyov. All rights reserved.
 * @license    GNU/GPL3 http://www.gnu.org/licenses/gpl-3.0.html
 * @since      1.3.0
 */

namespace Webtolk\Amocrm\Traits;

use Joomla\CMS\Language\Text;

defined('_JEXEC') or die;

trait DataErrorTrait
{
    use LogTrait;
    /**
     * Возвращаем типовую ошибку о пустых данных для методов класса.
     * Метод возвращает объект ошибки и пишет сообщение в логи.
     *
     * @param   string  $method  Класс и метод, где возникла ошибка
     *
     * @return  object
     *
     * @since   1.3.0
     */
    private function receivedEmptyData(string $method): object
    {
        $error_message = Text::sprintf('LIB_WTAMOCRM_ERROR_METHOD_RECEIVED_EMPTY_DATA', __METHOD__);
        $this->saveToLog($error_message, 'warning');

        return (object) [
            'error_code' => 500,
            'error_message' => $error_message
        ];
    }

    /**
     * Возвращаем типовую ошибку о неверном типе сущности для методов класса.
     * Метод возвращает объект ошибки и пишет сообщение в логи.
     *
     * @param   string  $method
     * @param   string  $entity_type
     * @param   array   $allowed_entites
     *
     * @return  object
     *
     * @since   1.3.0
     */
    private function wrongEntityType(string $method, string $entity_type, array $allowed_entites): object
    {
        $error_message = Text::sprintf(
            'LIB_WTAMOCRM_ERROR_NOTES_WRONG_ENTITY_TYPE',
            __METHOD__,
            $entity_type,
            implode(', ', $allowed_entites)
        );
        $this->saveToLog($error_message, 'error');

        return (object) [
            'error_code' => 500,
            'error_message' => $error_message
        ];
    }
}
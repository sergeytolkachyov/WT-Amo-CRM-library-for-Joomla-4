<?php
/**
 * @package     Webtolk\Amocrm\Traits
 * @subpackage
 *
 * @copyright   A copyright
 * @license     A "Slug" license name e.g. GPL2
 */

namespace Webtolk\Amocrm\Traits;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;

trait DataErrorTrait
{
    use LogTrait;
    /**
     * Возвращаем типовую ошибку о пустых данных для методов класса.
     * Метод возвращает объект ошибки и пишет сообщение в логи.
     *
     * @param   string  $method Класс и метод, где возникла ошибка
     *
     * @return object
     *
     * @since 1.3.0
     */
    private function receivedEmptyData(string $method): object
    {
        $error_message = Text::sprintf('LIB_WTAMOCRM_ERROR_METHOD_RECEIVED_EMPTY_DATA', __METHOD__);
        $this->saveToLog($error_message, 'warning');

        return (object)[
            'error_code'    => 500,
            'error_message' => $error_message
        ];
    }

    /**
     * Возвращаем типовую ошибку о неверном типе сущности для методов класса.
     * Метод возвращает объект ошибки и пишет сообщение в логи.
     *
     * @param   string  $method
     * @param   string  $entity_type
     *
     * @return object
     *
     * @since 1.3.0
     */
    private function wrongEntityType(string $method, string $entity_type, array $allowed_entites): object
    {
        $error_message = Text::sprintf(
            'LIB_WTAMOCRM_ERROR_NOTES_WRONG_ENTITY_TYPE',
            __METHOD__,
            $entity_type,
            implode(
                ', ',
                $allowed_entites
            )
        );
        $this->saveToLog($error_message, 'error');

        return (object)[
            'error_code'    => 500,
            'error_message' => $error_message
        ];
    }
}
<?php
/**
 * @package        WT Amocrm Library
 * @version        1.3.0-alpha2
 * @Author         Sergey Tolkachyov, https://web-tolk.ru
 * @copyright  (c) 2022 - May 2025 Sergey Tolkachyov. All rights reserved.
 * @license        GNU/GPL3 http://www.gnu.org/licenses/gpl-3.0.html
 * @since          1.0.0
 */

namespace Joomla\Plugin\System\Wt_amocrm\Fields;

defined('_JEXEC') or die;

use Joomla\CMS\Form\FormField;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\User\UserHelper;


class TokenField extends FormField
{

    protected $type = 'Redirecturl';

    /**
     * Method to get the field input markup for a spacer.
     * The spacer does not have accept input.
     *
     * @return  string  The field input markup.
     *
     * @since   1.7.0
     */
    protected function getInput()
    {
        if (empty($this->value)) {
            $this->value = UserHelper::genRandomPassword(64);
        }
        $field_input   = [];
        $field_input[] = '<div class="input-group">';
        $field_input[] = '<input type="text" class="form-control" name="' . $this->__get('name') . '" id="' . $this->__get('id') . '" value="' . $this->value . '">';

        if (empty($this->value)) {
            $field_input[] = '<div class="invalid-feedback d-block">';
            $field_input[] = 'Токен не создан. Создайте новый токен вручную или очистите поле и сохраните настройки плагина. Токен будет сгенерирован автоматически. После этого снова сохраните параметры плагина.';
            $field_input[] = '</div>';
        } else {
            $field_input[] = '<div class="valid-feedback d-block">';
            $field_input[] = 'Токен сохранён. Для создания нового токена измените его вручную или очистите поле и дважды сохраните настройки плагина.';
            $field_input[] = '</div>';
        }

        $field_input[] = '</div>';
        return implode('', $field_input);
    }

    /**
     * @return  string  The field label markup.
     *
     * @since   1.7.0
     */
    protected function getTitle()
    {
        return $this->getLabel();
    }

    /**
     * @return  string  The field label markup.
     *
     * @since   1.7.0
     */
    protected function getLabel()
    {
        return Text::_(($this->element['label'] ? (string)$this->element['label'] : (string)$this->element['name']));
    }
}

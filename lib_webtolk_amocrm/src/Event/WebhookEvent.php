<?php
/**
 * @package    WT Amocrm Library
 * @version    1.3.0
 * @Author     Sergey Tolkachyov, https://web-tolk.ru
 * @copyright  (c) 2022 - May 2025 Sergey Tolkachyov. All rights reserved.
 * @license    GNU/GPL3 http://www.gnu.org/licenses/gpl-3.0.html
 * @since      1.3.0
 */

namespace Webtolk\Amocrm\Event;

use Joomla\CMS\Event\AbstractEvent;

defined('_JEXEC') or die;

class WebhookEvent extends AbstractEvent
{
    /**
     * Return all the webhook raw data
     *
     * @return  array
     *
     * @since   1.3.0
     */
    public function getData(): array
    {
        return $this->arguments['subject']->toArray();
    }

    /**
     * Get AmoCRM account data: id, subdomain, link
     *
     * @return  array
     *
     * @since   1.3.0
     */
    public function getAccount(): array
    {
        return $this->getData()['account'];
    }

    /**
     * Get all contacts data if exists. All contacts have a type 'company' or type 'contact'
     *
     * @return  array
     *
     * @since   1.3.0
     */
    public function getContacts(): array
    {
        return $this->getData()['contacts'] ?? [];
    }

    /**
     * Get all leads data if exists
     *
     * @return  array
     *
     * @since   1.3.0
     */
    public function getLeads(): array
    {
        return $this->getData()['leads'] ?? [];
    }

    /**
     * Get all tasks data if exists
     *
     * @return  array
     *
     * @since   1.3.0
     */
    public function getTasks(): array
    {
        return $this->getData()['task'] ?? [];
    }

    /**
     * Get all unsorted data if exists
     *
     * @return  array
     *
     * @since   1.3.0
     */
    public function getUnsorted(): array
    {
        return $this->getData()['unsorted'] ?? [];
    }

    /**
     * Get all messages data if exists
     *
     * @return  array
     *
     * @since   1.3.0
     */
    public function getMessages(): array
    {
        return $this->getData()['message'] ?? [];
    }
}

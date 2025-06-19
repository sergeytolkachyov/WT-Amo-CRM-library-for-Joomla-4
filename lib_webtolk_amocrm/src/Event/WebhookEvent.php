<?php
/**
 * @package     Webtolk\Amocrm\Event
 * @subpackage
 *
 * @copyright   A copyright
 * @license     A "Slug" license name e.g. GPL2
 */

namespace Webtolk\Amocrm\Event;

use Joomla\CMS\Event\AbstractEvent;

class WebhookEvent extends AbstractEvent
{
    /**
     * Return all the webhook raw data
     *
     * @return array
     *
     * @since 1.3.0
     */
    public function getData():array
    {
        return $this->arguments['subject']->toArray();
    }

    /**
     * Get AmoCRM account data: id, subdomain, link
     *
     * @return array
     *
     * @since 1.3.0
     */
    public function getAccount(): array
    {
        return $this->getData()['account'];
    }

    /**
     * Get all contacts data if exists. All contacts have a type 'company' or type 'contact'
     *
     * @return array
     *
     * @since 1.3.0
     */
    public function getContacts(): array
    {
        return $this->getData()['contacts'] ?? [];
    }


    /**
     * Get all leads data if exists
     *
     * @return array
     *
     * @since 1.3.0
     */
    public function getLeads(): array
    {
        return $this->getData()['leads'] ?? [];
    }

    /**
     * Get all tasks data if exists
     *
     * @return array
     *
     * @since 1.3.0
     */
    public function getTasks(): array
    {
        return $this->getData()['task'] ?? [];
    }

    /**
     * Get all unsorted data if exists
     *
     * @return array
     *
     * @since 1.3.0
     */
    public function getUnsorted(): array
    {
        return $this->getData()['unsorted'] ?? [];
    }

    /**
     * Get all messages data if exists
     *
     * @return array
     *
     * @since 1.3.0
     */
    public function getMessages(): array
    {
        return $this->getData()['message'] ?? [];
    }
}

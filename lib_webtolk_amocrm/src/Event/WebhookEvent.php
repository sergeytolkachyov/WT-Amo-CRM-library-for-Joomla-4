<?php
/**
 * @package     Webtolk\Amocrm\Event
 * @subpackage
 *
 * @copyright   A copyright
 * @license     A "Slug" license name e.g. GPL2
 */

namespace Webtolk\Amocrm\Event;

use Joomla\CMS\Event\Workflow\AbstractEvent;

class WebhookEvent extends AbstractEvent
{
    public function getAccount(): array
    {
        return $this->arguments['account'];
    }

    public function getContactsUpdated():array
    {
        return $this->arguments['contacts']['update'] ?? [];
    }
    public function getContactsDeleted():array
    {
        return $this->arguments['contacts']['delete'] ?? [];
    }
}
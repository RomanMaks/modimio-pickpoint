<?php

namespace app\services;

use app\models\PickPointRegistryItem;
use app\models\PickPointRegistryItemLog;

/**
 * Сервис для логгирования действий связанных с записями реестра
 *
 * Class RegistryItemLogService
 * @package app\services
 */
class RegistryItemLogService
{
    /**
     * @param PickPointRegistryItem $item
     * @param string $message
     * @param int $status
     *
     * @return bool
     */
    protected function log(PickPointRegistryItem $item, string $message, int $status): bool
    {
        $log = new PickPointRegistryItemLog();
        $log->registry_item_id = $item->id;
        $log->event_type = $status;
        $log->message = $message;

        return $log->save();
    }

    /**
     * @param PickPointRegistryItem $item
     * @param string $message
     *
     * @return bool
     */
    public function error(PickPointRegistryItem $item, string $message): bool
    {
        return $this->log($item, $message, PickPointRegistryItemLog::EVENT_TYPES['ERROR']);
    }

    /**
     * @param PickPointRegistryItem $item
     * @param string $message
     *
     * @return bool
     */
    public function info(PickPointRegistryItem $item, string $message): bool
    {
        return $this->log($item, $message, PickPointRegistryItemLog::EVENT_TYPES['INFO']);
    }
}
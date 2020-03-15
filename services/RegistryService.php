<?php


namespace app\services;

use app\models\Order;
use app\models\PickPointRegistry;
use app\models\PickPointRegistryItem;
use app\models\PickPointRegistryItemLog;

/**
 * Сервис для работы с реестрами
 *
 * Class RegistryService
 * @package app\services
 */
class RegistryService
{
    /**
     * Создание открытого реестра за текущий день
     *
     * @throws \Exception
     */
    public function create()
    {
        $registry = PickPointRegistry::findOne(['created_at' => date('Y-m-d')]);

        if (!empty($registry)) {
            $message = sprintf('На текущий день уже существует открытый реестр с id = %d.', $registry->id);
            \Yii::info($message);
            throw new \Exception($message);
        }

        $registry = new PickPointRegistry([
            'status' => PickPointRegistry::STATUSES['OPEN']
        ]);

        $registry->save();
        $registry->refresh();

        $orders = Order::find()
            ->joinWith('registryItem')
            ->all();

        /** @var Order $order */
        foreach ($orders as $order) {
            $registryItem = new PickPointRegistryItem([
                'status' => PickPointRegistryItem::STATUSES['CREATE'],
                'registry_id' => $registry->id,
                'order_id' => $order->id,
            ]);
            $result = $registryItem->save();
            $registryItem->refresh();

            $log = new PickPointRegistryItemLog();
            $log->registry_item_id = $registryItem->id;

            if ($result) {
                $log->event_type = PickPointRegistryItemLog::EVENT_TYPES['INFO'];
                $log->message = sprintf(
                    'Успешно создана запись реестра %d для заказа %d.',
                    $registryItem->id,
                    $order->id
                );
            } else {
                $log->event_type = PickPointRegistryItemLog::EVENT_TYPES['ERROR'];
                $log->message = sprintf(
                    'При создании записи реестра %d произошла ошибка, неудалось связать с заказом %d. Ошибка %s',
                    $registryItem->id,
                    $order->id,
                    implode(', ', $registryItem->firstErrors)
                );
            }

            $log->save();
        }
    }
}
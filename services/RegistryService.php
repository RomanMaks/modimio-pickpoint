<?php


namespace app\services;

use app\models\Order;
use app\models\OrderBox;
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
    /** @var PickPointAPIService $pickPoint */
    protected $pickPoint;

    /** @var string Путь до папки где хранятся PDF файлы для этикеток */
    protected const PATH_TO_DIRECTORY = __DIR__ . '/../data/labels/';

    /**
     * RegistryService constructor.
     * @throws \Exception
     */
    public function __construct()
    {
        $this->pickPoint = new PickPointAPIService();
    }

    /**
     * Создание или обновление открытого реестра за текущий день
     *
     * @throws \Exception
     */
    public function createOrUpdate()
    {
        $registry = PickPointRegistry::find()
            ->where(['between', 'created_at', date('Y-m-d 00:00:00'), date('Y-m-d 23:59:59')])
            ->andWhere(['status' => PickPointRegistry::STATUSES['OPEN']])
            ->one();

        if (empty($registry)) {
            $registry = new PickPointRegistry();
            $registry->status = PickPointRegistry::STATUSES['OPEN'];
        }

        $registry->save();
        $registry->refresh();

        $orders = Order::find()
            ->joinWith('registryItem')
            ->all();

        /** @var Order $order */
        foreach ($orders as $order) {
            $item = new PickPointRegistryItem([
                'status' => PickPointRegistryItem::STATUSES['CREATE'],
                'registry_id' => $registry->id,
                'order_id' => $order->id,
            ]);
            $result = $item->save();
            $item->refresh();

            $log = new PickPointRegistryItemLog();
            $log->registry_item_id = $item->id;

            if ($result) {
                $log->event_type = PickPointRegistryItemLog::EVENT_TYPES['INFO'];
                $log->message = sprintf(
                    'Успешно создана запись реестра %d для заказа %d.',
                    $item->id,
                    $order->id
                );
            } else {
                $log->event_type = PickPointRegistryItemLog::EVENT_TYPES['ERROR'];
                $log->message = sprintf(
                    'При создании записи реестра %d произошла ошибка, неудалось связать с заказом %d. Ошибка %s',
                    $item->id,
                    $order->id,
                    implode(', ', $item->firstErrors)
                );
            }

            $log->save();
        }
    }

    /**
     * Удалить выбранные записи реестра
     *
     * @param array $itemIds
     */
    public function deleteItems(array $itemIds)
    {
        $items = PickPointRegistryItem::findAll(['id' => $itemIds]);

        /** @var PickPointRegistryItem $item */
        foreach ($items as $item) {
            $log = new PickPointRegistryItemLog();
            $log->registry_item_id = $item->id;

            try {
                $item->delete();
                $log->event_type = PickPointRegistryItemLog::EVENT_TYPES['INFO'];
                $log->message = sprintf('Удаление записи реестра %d.', $item->id);
            } catch (\Throwable $exception) {
                $log->event_type = PickPointRegistryItemLog::EVENT_TYPES['ERROR'];
                $log->message = sprintf(
                    'При удалении записи реестра %d произошла ошибка. Ошибка %s',
                    $item->id,
                    $exception->getMessage()
                );
            }

            $log->save();
        }
    }

    /**
     * Регистрация реестра в PickPoint
     *
     * @param PickPointRegistry $registry
     *
     * @throws \Exception
     */
    public function registration(PickPointRegistry $registry)
    {
        // Регистрация каждой записи реестра
        $this->registrationShipments($registry->items);

        $statuses = $registry->getItems()->select('status')->column();

        // Проверяем все ли записи были успешно зарегистированы
        if (in_array(PickPointRegistryItem::STATUSES['CREATE'], $statuses) ||
            in_array(PickPointRegistryItem::STATUSES['ERROR'], $statuses)
        ) {
            throw new \Exception('Не все записи реестра удалось зарегистрировать в PickPoint');
        }

        // Формирование этикеток
        $pathToLinks = $this->labeling($registry);

        $sending = [
            'CityName' => \Yii::$app->params['pickpoint']['senderCity']['city'], // Название города передачи отправления в PickPoint
            'RegionName' => \Yii::$app->params['pickpoint']['senderCity']['region'], // Название региона передачи отправления в PickPoint
            'DeliveryPoint' => \Yii::$app->params['pickpoint']['out']['postomat'], // Пункт сдачи, номер постамата
            // 'ReestrNumber' => '<Номер документа Клиента>',
            'Invoices' => $registry->getItems()->select('departure_track_code')->column(),
        ];

        try {
            // Формирование реестра в ЛК PickPoint
            $registry->registry_number = $this->pickPoint->createRegistry($sending);
            $registry->status = PickPointRegistry::STATUSES['READY_FOR_SHIPMENT'];
            $registry->label_print_link = $pathToLinks;

            // Сохранение номера реестра и ссылки на печать этикеток
            $registry->save();
        } catch (\Throwable $exception) {
            $message = sprintf(
                'При формировании реестра %d произошла ошибка: %s',
                $registry->id,
                $exception->getMessage()
            );
            \Yii::error($message);
            throw new \Exception($message);
        }
    }

    /**
     * Регистрация отправлений в PickPoint
     *
     * @param PickPointRegistryItem[] $items
     *
     * @throws \Exception
     */
    protected function registrationShipments(array $items)
    {
        $sendings = [];

        /** @var PickPointRegistryItem $item */
        foreach ($items as $item) {
            if ($item->order->catalog_pay_id === Order::PAYMENT_METHODS['CASH_ON_DELIVERY']) {
                $postageType = 10003;
                $sum = sprintf("%01.2f", $item->order->total_price + $item->order->delivery_price);
            } else {
                $postageType = 10001;
                $sum = 0;
            }

            $sendings[] = [
                'EDTN' => $item->id, // Идентификатор запроса, используемый для ответа. Указывайте уникальное число (50 символов)
                'IKN' => \Yii::$app->params['pickpoint']['ikn'],
                'Invoice' => [
                    'SenderCode' => $item->id,
                    'Description' => 'Коллекционная модель',
                    'RecipientName' => $item->order->fullName(),
                    'PostamatNumber' => $item->order->pickupPoint->code,
                    'MobilePhone' => sprintf('+%s', $item->order->user_phone),
                    'Email' => $item->order->user_email,
                    'PostageType' => $postageType,
                    'GettingType' => 102, // Тип сдачи отправления, обязательное поле
                    'PayType' => 1,
                    'Sum' => $sum,
                    'DeliveryVat' => 20, // Ставка НДС по сервисному сбору
                    'DeliveryFee' => $item->order->delivery_price, // Сумма сервисного сбора с НДС
                    'DeliveryMode' => 1, // Режим доставки (значения : 1, если Standard и 2, если Priority)
                    'SenderCity' => [
                        'CityName' => \Yii::$app->params['pickpoint']['senderCity']['city'], // Название города сдачи отправления
                        'RegionName' => \Yii::$app->params['pickpoint']['senderCity']['region'], // Название региона сдачи отправления
                    ],
                    'Places' => array_map(
                        function (OrderBox $box) {
                            return [
                                'BarCode' => '',
                                'Width' => $box->width,
                                'Height' => $box->height,
                                'Depth' => $box->length,
                                'Weight' => $box->weight / 1000,
                            ];
                        },
                        $item->order->boxes
                    ),
                ],
            ];
        }

        $sendings = $this->pickPoint->createShipment($sendings);

        // Подтвержденные отправления
        foreach ($sendings['created'] as $sendingCreated) {
            $item = PickPointRegistryItem::findOne(['id' => $sendingCreated['SenderCode']]);
            $item->departure_track_code = $sendingCreated['InvoiceNumber'];
            $item->status = PickPointRegistryItem::STATUSES['REGISTERED'];
            $item->save();

            $log = new PickPointRegistryItemLog([
                'registry_item_id' => $item->id,
                'event_type' => PickPointRegistryItemLog::EVENT_TYPES['INFO'],
                'message' => sprintf('Успешно зарегистрированно отправление %d.', $item->id)
            ]);
            $log->save();
        }

        // Отклоненные отправления
        foreach ($sendings['rejected'] as $sendingRejected) {
            $item = PickPointRegistryItem::findOne(['id' => $sendingRejected['SenderCode']]);
            $item->status = PickPointRegistryItem::STATUSES['ERROR'];
            $item->save();

            $log = new PickPointRegistryItemLog([
                'registry_item_id' => $item->id,
                'event_type' => PickPointRegistryItemLog::EVENT_TYPES['ERROR'],
                'message' => sprintf('При регистрации отправления %d произошла ошибка: %s', $item->id, $sendingRejected['ErrorMessage'])
            ]);
            $log->save();
        }
    }

    /**
     * Формирование этикеток
     *
     * @param PickPointRegistry $registry
     *
     * @return string
     *
     * @throws \Exception
     */
    protected function labeling(PickPointRegistry $registry): string
    {
        $invoices = array_map(
            function (PickPointRegistryItem $item) { return $item->departure_track_code; },
            $registry->items
        );

        $file = $this->pickPoint->makelabel($invoices);

        $filename = str_replace(
            ['%REGISTRY_NUMBER%', '%DATETIME%'],
            [$registry->id, date('YmdHis')],
            \Yii::$app->params['pickpoint']['fileNameMask']
        );

        if (false === file_put_contents(self::PATH_TO_DIRECTORY . $filename, $file)) {
            throw new \Exception('Неудалось сохранить pdf файл с этикетками.');
        }

        return self::PATH_TO_DIRECTORY . $filename;
    }
}
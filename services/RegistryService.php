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
    protected const PATH_TO_DIRECTORY = __DIR__ . '/../data/';

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
        $registry = PickPointRegistry::findOne([
            'created_at' => date('Y-m-d'),
            'status' => PickPointRegistry::STATUSES['OPEN']
        ]);

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
        foreach ($registry->items as $item) {
            $this->shipmentRegistration($item);
        }

        // Проверяем все ли записи были успешно зарегистированы
        // if () {}

        // Формирование этикеток
        $pathToLinks = $this->labeling($registry);

        $sending = [
            'CityName' => \Yii::$app->params['pickpoint']['senderCity']['city'], // Название города передачи отправления в PickPoint
            'RegionName' => \Yii::$app->params['pickpoint']['senderCity']['region'], // Название региона передачи отправления в PickPoint
            'DeliveryPoint' => \Yii::$app->params['pickpoint']['out']['postomat'], // Пункт сдачи, номер постамата
            'ReestrNumber' => '<Номер документа Клиента>',
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
        }
    }

    /**
     * Регистрация отправления в PickPoint
     *
     * @param PickPointRegistryItem $item
     */
    protected function shipmentRegistration(PickPointRegistryItem $item)
    {
        $log = new PickPointRegistryItemLog();
        $log->registry_item_id = $item->id;
        $log->event_type = PickPointRegistryItemLog::EVENT_TYPES['INFO'];
        $log->message = sprintf(
            'Успешно зарегистрированно отправление(запись реестра) %d.',
            $item->id
        );

        if ($item->order->catalog_pay_id === Order::PAYMENT_METHODS['CASH_ON_DELIVERY']) {
            $postageType = 10003;
            $sum = sprintf("%01.2f", $item->order->total_price + $item->order->delivery_price);
        } else {
            $postageType = 10001;
            $sum = 0;
        }

        $sending = [
            'EDTN' => $item->id, // Идентификатор запроса, используемый для ответа. Указывайте уникальное число (50 символов)
            'IKN' => \Yii::$app->params['pickpoint']['ikn'],
            'Invoice' => [
                'SenderCode' => $item->order_id,
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
                            //'SubEncloses' => [ //  <Субвложимые>
                            //    [
                            //        'ProductCode' => '<Артикул товара(50 символов)>', // 1
                            //        'GoodsCode' => '<ШК товара(50 символов)>', // 1
                            //        'Name' => '<Наименование товара(200 символов)>', // 1
                            //        'Price' => '<Стоимость ед. товара с НДС>', // 1
                            //        'Quantity' => '<Кол-во ед. товара одного арт.>',
                            //        'Vat' => '<Ставка НДС по товару>',
                            //        'Description' => '<Описание товара>',
                            //        'Upi' => '<код товара>',
                            //    ],
                            //],
                        ];
                    },
                    $item->order->boxes
                ),
            ],
        ];

        try {
            $createdShipment = $this->pickPoint->createShipment($sending);
            $item->departure_track_code = $createdShipment['InvoiceNumber'];
            $item->status = PickPointRegistryItem::STATUSES['REGISTERED'];
        } catch (\Throwable $exception) {
            $item->status = PickPointRegistryItem::STATUSES['ERROR'];

            $log->event_type = PickPointRegistryItemLog::EVENT_TYPES['ERROR'];
            $log->message = sprintf(
                'При регистрации отправления(записи реестра) %d произошла ошибка: %s',
                $item->id,
                $exception->getMessage()
            );
        }

        $log->save();
        $item->save();
        $item->refresh();
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
            function (PickPointRegistryItem $item) { return $item->id; },
            $registry->items
        );

        $file = $this->pickPoint->makelabel($invoices);

        $filename = str_replace(
            ['%DOC_NUMBER%', '%DATETIME%'],
            [$registry->id, date('YmdHis')],
            \Yii::$app->params['pickpoint']['fileNameMask']
        );

        if (false === file_put_contents(self::PATH_TO_DIRECTORY . $filename, $file)) {
            throw new \Exception('Неудалось сохранить pdf файл с этикетками.');
        }

        return $filename;
    }
}
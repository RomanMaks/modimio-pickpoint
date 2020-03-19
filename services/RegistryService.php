<?php


namespace app\services;

use app\models\Order;
use app\models\OrderBox;
use app\models\PickPointRegistry;
use app\models\PickPointRegistryItem;

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

    /** @var RegistryItemLogService $log */
    protected $log;

    /** @var string Путь до папки где хранятся PDF файлы для этикеток */
    protected const PATH_TO_DIRECTORY = __DIR__ . '/../data/labels/';

    /**
     * RegistryService constructor.
     * @throws \Exception
     */
    public function __construct()
    {
        $this->pickPoint = new PickPointAPIService();
        $this->log = new RegistryItemLogService();
    }

    /**
     * Создание открытого реестра за текущий день если он еще не создан
     *
     * @return PickPointRegistry
     *
     * @throws \Exception
     */
    public function create(): PickPointRegistry
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
            ->where(['pick_point_registry_item.id' => null])
            ->all();

        /** @var Order $order */
        foreach ($orders as $order) {
            $item = new PickPointRegistryItem([
                'status' => PickPointRegistryItem::STATUSES['CREATE'],
                'registry_id' => $registry->id,
                'order_id' => $order->id,
            ]);
            $item->save();
            $item->refresh();

            $this->log->info($item, 'Новая запись реестра');
        }

        return $registry;
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
            try {
                $item->delete();
                $this->log->info($item, 'Удаление записи реестра');
            } catch (\Throwable $exception) {
                $this->log->error($item, sprintf('Не удалось удалить запись реестра. Описание: %s',
                    $exception->getMessage()
                ));
            }
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

        $statuses = $registry->getItems()
            ->select('status')
            ->column();

        // Проверяем все ли записи были успешно зарегистированы
        if (in_array(PickPointRegistryItem::STATUSES['CREATE'], $statuses) ||
            in_array(PickPointRegistryItem::STATUSES['ERROR'], $statuses)
        ) {
            throw new \Exception('Не все записи реестра удалось зарегистрировать в PickPoint');
        }

        $invoices = $registry->getItems()
            ->select('departure_track_code')
            ->column();

        // Формирование этикеток
        $pathToLinks = $this->labeling($registry->id, $invoices);

        $sending = [
            'CityName' => \Yii::$app->params['pickpoint']['senderCity']['city'], // Название города передачи отправления в PickPoint
            'RegionName' => \Yii::$app->params['pickpoint']['senderCity']['region'], // Название региона передачи отправления в PickPoint
            'DeliveryPoint' => \Yii::$app->params['pickpoint']['out']['postomat'], // Пункт сдачи, номер постамата
            'ReestrNumber' => $registry->id, // Номер документа Клиента
            'Invoices' => $invoices,
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

        foreach ($registry->items as $item) {
            $this->log->info($item, 'Реестр зарегистрирован');
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
            // Пропускаем записи реестра которые уже были зарегистрированы
            if (PickPointRegistryItem::STATUSES['REGISTERED'] === $item->status) {
                continue;
            }

            if ($item->order->catalog_pay_id === Order::PAYMENT_METHODS['CASH_ON_DELIVERY']) {
                $postageType = 10003;
                $sum = sprintf("%01.2f", $item->order->total_price + $item->order->delivery_price);
            } else {
                $postageType = 10001;
                $sum = 0;
            }

            $sendings[] = [
                'EDTN' => $item->id, // Идентификатор запроса, используемый для ответа. Указывайте уникальное число (50 символов)
                'IKN' => \Yii::$app->params['pickpoint']['ikn'], // Номер договора
                'Invoice' => [
                    'SenderCode' => $item->order->alt_number, // Номер заказа магазина (50 символов)
                    'Description' => 'Коллекционная модель', // Описание отправления, обязательное поле (200 символов)
                    'RecipientName' => $item->order->fullName(), // Имя получателя (150 символов)
                    'PostamatNumber' => $item->order->pickupPoint->code, // Номер постамата, обязательное поле (8 символов)
                    'MobilePhone' => sprintf('+%s', $item->order->user_phone), // Один номер телефона получателя, обязательное поле(100 символов)
                    'Email' => $item->order->user_email, // Адрес электронной почты получателя (256 символов)
                    'PostageType' => $postageType, // Тип услуги, обязательное поле
                    'GettingType' => 102, // Тип сдачи отправления, обязательное поле
                    'PayType' => 1, // Тип оплаты, обязательное поле
                    'Sum' => $sum, // Сумма, обязательное поле (число, два знака после запятой)
                    'DeliveryVat' => 20, // Ставка НДС по сервисному сбору
                    'DeliveryFee' => $item->order->delivery_price, // Сумма сервисного сбора с НДС
                    'DeliveryMode' => 1, // Режим доставки (значения : 1, если Standard и 2, если Priority)
                    'SenderCity' => [ // Город сдачи
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

        // Если нет записей на регистрацию то выходим

        $sendings = $this->pickPoint->createShipment($sendings);

        // Зарегистрированные записи реестра
        foreach ($sendings['created'] as $sendingCreated) {
            $item = Order::findOne(['alt_number' => $sendingCreated['SenderCode']])->registryItem;
            $item->departure_track_code = $sendingCreated['InvoiceNumber'];
            $item->status = PickPointRegistryItem::STATUSES['REGISTERED'];
            $item->save();
            $this->log->info($item, 'Запись реестра зарегистрирована');
        }

        // Отклоненные записи реестра
        foreach ($sendings['rejected'] as $sendingRejected) {
            $item = Order::findOne(['alt_number' => $sendingRejected['SenderCode']])->registryItem;
            $item->status = PickPointRegistryItem::STATUSES['ERROR'];
            $item->save();
            $this->log->error($item, sprintf('Не удалось зарегистрировать запись реестра. Описание: %s',
                $sendingRejected['ErrorMessage']
            ));
        }
    }

    /**
     * Формирование этикеток
     *
     * @param int $registryId
     * @param array $invoices
     *
     * @return string
     *
     * @throws \Exception
     */
    protected function labeling(int $registryId, array $invoices): string
    {
        $file = $this->pickPoint->makelabel($invoices);

        $filename = str_replace(
            ['%REGISTRY_NUMBER%', '%DATETIME%'],
            [$registryId, date('YmdHis')],
            \Yii::$app->params['pickpoint']['fileNameMask']
        );

        if (false === file_put_contents(self::PATH_TO_DIRECTORY . $filename, $file)) {
            throw new \Exception('Не удалось сохранить pdf файл с этикетками.');
        }

        return self::PATH_TO_DIRECTORY . $filename;
    }
}
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
            try {
                $item->delete();
            } catch (\Throwable $exception) {
                $log = new PickPointRegistryItemLog();
                $log->registry_item_id = $item->id;
                $log->event_type = PickPointRegistryItemLog::EVENT_TYPES['ERROR'];
                $log->message = sprintf(
                    'При удалении записи реестра %d произошла ошибка. Ошибка %s',
                    $item->id,
                    $exception->getMessage()
                );
                $log->save();
            }
        }
    }

    /**
     * Регистрация реестра в PickPoint
     *
     * @param PickPointRegistry $registry
     */
    public function registration(PickPointRegistry $registry)
    {
//-регистрация каждой записи реестра;
//-формирование реестра в ЛК PickPoint (если все записи реестра успешно зарегистрированы);
//-сохранение номера реестра и ссылки на печать этикеток;


        foreach ($registry->items as $item) {
            $this->shipmentRegistration($item);
        }

        $sending = [
            'CityName' => '<Название города передачи отправления в PickPoint>',
            'RegionName' => '<Название региона передачи отправления в PickPoint >',
            'DeliveryPoint' => '<Пункт сдачи, номер постамата>',
            'ReestrNumber' => '<Номер документа Клиента>',
            'Invoices' => $registry->getItems()->select('departure_track_code')->column(),
        ];

        try {
            $registry->registry_number = $this->pickPoint->createRegistry($sending);
            $registry->status = PickPointRegistry::STATUSES['READY_FOR_SHIPMENT'];
        } catch (\Throwable $exception) {
            $message = sprintf(
                'При формировании отправления %d произошла ошибка: %s',
                $registry->id,
                $exception->getMessage()
            );
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

        $places = array_map(
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
        );

        $sum = $item->order->catalog_pay_id === Order::

        $sending = [
            'EDTN' => '<Идентификатор запроса, используемый для ответа. Указывайте уникальное число (50 символов)>',
            'IKN' => \Yii::$app->params['pickpoint']['ikn'],
            'ClientNumber' => '<Номер клиента в системе агрегатора (отражается в возвратных накладных от PickPoint), ' .
                               'обязательное поле, если у ИКН проставлен флаг в CRM "Является агрегатором">',
            //'ClientName' => '<Наименование клиента в системе агрегатора, необязательное поле. Следует заполнять только' .
            //                ' при регистрации КО переданный в запросе ИКН принадлежит клиенту-агрегатору>',
            //'TittleRus' => '<Наименование на русском для отображения на сайте в мониторинге PickPoint, необязательное поле>',
            //'TittleEng' => '<Наименование на английском для отображения на сайте в мониторинге PickPoint, необязательное поле>',
            'Invoice' => [
                'SenderCode' => $item->order_id,
                'Description' => '<Описание отправления, обязательное поле (200 символов)>', // [?]
                'RecipientName' => $item->order->fullName(),
                'PostamatNumber' => $item->order->pickupPoint->code,
                'MobilePhone' => sprintf('+%s', $item->order->user_phone),
                'Email' => $item->order->user_email,
                'ConsultantNumber' => '<Номер консультанта>', // [?]
                'PostageType' => sprintf('1000%d', $item->order->catalog_pay_id),
                'GettingType' => '<Тип сдачи отправления, (см. таблицу ниже) обязательное поле>',
                'PayType' => 1,
                'Sum' => '<Сумма, обязательное поле (число, два знака после запятой)>',
                'PrepaymentSum' => '<Сумма предоплаты >',
                'DeliveryVat' => '<Ставка НДС по сервисному сбору>',
                'DeliveryFee' => '<Сумма сервисного сбора с НДС>',
                'InsuareValue' => '<Страховка (число, два знака после запятой)>',
                'DeliveryMode' => 1, // '<Режим доставки (значения : 1, если Standard и 2, если Priority)>',
                'SenderCity' => [
                    'CityName' => '<Название города сдачи отправления>',
                    'RegionName' => '<Название региона сдачи отправления>',
                ],
                //'ClientReturnAddress' => [ // Адрес клиентского возврата. Данный блок можно не передавать. Если передаете, то необходимо заполнение всех полей блока.
                //    'CityName' => '<Название города (50 символов)>',
                //    'RegionName' => '<Название региона (50 символов)>',
                //    'Address' => '<Текстовое описание адреса (150 символов)>',
                //    'FIO' => '<ФИО контактного лица (150 символов)>',
                //    'PostCode' => '<Почтовый индекс (20 символов)>',
                //    'Organisation' => '<Наименование организации (100 символов)>',
                //    'PhoneNumber' => '<Контактный телефон, обязательное поле (допускаются круглые скобки и тире)>',
                //    'Comment' => '<Комментарий (255 символов)>',
                //],
                //'UnclaimedReturnAddress' => [ // Адрес возврата невостребованного. Данный блок можно не передавать. Если передаете, то необходимо заполнение всех полей блока.
                //    'CityName' => '<Название города (50 символов)>',
                //    'RegionNa' => '<Текстовое описание адреса (150 символов)>',
                //    'FIO”' => '<ФИО контактного лица (150 символов)>',
                //    'PostCode”' => '<Почтовый индекс (20 символов)>',
                //    'Organisation' => '<Наименование организации (100 символов)>',
                //    'PhoneNumber' => '<Контактный телефон, обязательное поле (допускаются круглые скобки и тире)>',
                //    'Comment”' => '<Комментарий  (255 символов)>',
                //],
                'Places' => [
                    [
                        'BarCode' => '', // '<Штрих код от PickPoint. Отправляйте поле пустым, в ответ будет ШК (50 символов)>',
                        //'GCBarCode' => '<Клиентский штрих-код. Поле не обязательное. Можно не отправлять (255 символов)>',
                        'Width' => '<Ширина (число, два знака после запятой)>',
                        'Height' => '<Высота (число, два знака после запятой)>',
                        'Depth' => '<Глубина (число, два знака после запятой)>',
                        'Weight' => '<Вес (число, два знака после запятой)>',
                        'SubEncloses' => [ //  <Субвложимые>
                            [
                                'ProductCode' => '<Артикул товара(50 символов)>', // 1
                                'GoodsCode' => '<ШК товара(50 символов)>', // 1
                                'Name' => '<Наименование товара(200 символов)>', // 1
                                'Price' => '<Стоимость ед. товара с НДС>', // 1
                                'Quantity' => '<Кол-во ед. товара одного арт.>',
                                'Vat' => '<Ставка НДС по товару>',
                                'Description' => '<Описание товара>',
                                'Upi' => '<код товара>',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        try {
            $createdShipment = $this->pickPoint->shipmentRegistration($sending);
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
}
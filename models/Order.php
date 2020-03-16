<?php

namespace app\models;

use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * Модель "Заказ"
 *
 * Class Order
 * @package app\models
 *
 * @property integer $id               Первичный ключ
 * @property string  $alt_number       Номер заказа
 * @property integer $total_price      Стоимость заказа
 * @property integer $delivery_price   Стоимость доставки
 * @property integer $catalog_pay_id   Способ оплаты
 * @property integer $pickup_point_id  ID постомата
 * @property string  $user_name        Имя заказчика
 * @property string  $user_patronymic  Отчество заказчика
 * @property string  $user_surname     Фамилия заказчика
 * @property string  $user_phone       Телефон заказчика
 * @property string  $user_email       Email заказчика
 *
 * @property PickupPoint $pickupPoint Постомат
 * @property OrderBox[] $boxes Коробки заказа
 * @property PickPointRegistryItem $registryItem Запись в реестре
 */
class Order extends ActiveRecord
{
    /** @var array Способы оплаты */
    public const PAYMENT_METHODS = [
        'CASH_ON_DELIVERY' => 1, // Наложенный платеж
        'PREPAYMENT' => 3,       // Предоплата
    ];

    /**
     * @return string
     */
    public static function tableName(): string
    {
        return 'order';
    }

    /**
     * Правила проверки для атрибутов
     *
     * @return array
     */
    public function rules()
    {
        return [
            [
                [
                    'alt_number',
                    'user_name',
                    'user_patronymic',
                    'user_surname',
                    'user_phone',
                    'user_email',
                ],
                'string',
                'max' => 256,
            ],
            [['total_price', 'delivery_price', 'catalog_pay_id', 'pickup_point_id'], 'integer'],
            ['alt_number', 'required'],
            ['alt_number', 'unique'],
            ['user_email', 'email'],
            ['catalog_pay_id', 'in', 'range' => array_values(self::PAYMENT_METHODS)],
            [
                [
                    'total_price',
                    'delivery_price',
                    'catalog_pay_id',
                    'pickup_point_id',
                    'user_name',
                    'user_patronymic',
                    'user_surname',
                    'user_phone',
                    'user_email',
                ],
                'safe'
            ],
        ];
    }

    /**
     * Постомат
     *
     * @return ActiveQuery
     */
    public function getPickupPoint(): ActiveQuery
    {
        return $this->hasOne(PickupPoint::class, ['id' => 'pickup_point_id']);
    }

    /**
     * Коробки заказа
     *
     * @return ActiveQuery
     */
    public function getBoxes(): ActiveQuery
    {
        return $this->hasMany(OrderBox::class, ['order_id' => 'id']);
    }

    /**
     * Запись в реестре
     *
     * @return ActiveQuery
     */
    public function getRegistryItem(): ActiveQuery
    {
        return $this->hasOne(PickPointRegistryItem::class, ['order_id' => 'id']);
    }

    /**
     * ФИО
     *
     * @return string
     */
    public function fullName(): string
    {
        return implode(' ', [$this->user_surname, $this->user_name, $this->user_patronymic]);
    }
}
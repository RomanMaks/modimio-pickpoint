<?php

namespace app\models;

use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * Модель "Коробка заказа"
 *
 * Class OrderBox
 * @package app\models
 *
 * @property integer $id       Первичный ключ
 * @property integer $order_id ID заказа
 * @property float   $length   Длина (см)
 * @property float   $width    Ширина (см)
 * @property float   $height   Высота (см)
 * @property integer $weight   Вес (г)
 *
 * @property Order $order Заказ
 */
class OrderBox extends ActiveRecord
{
    /**
     * @return string
     */
    public static function tableName(): string
    {
        return 'order_box';
    }

    /**
     * Правила проверки для атрибутов
     *
     * @return array
     */
    public function rules()
    {
        return [
            [['length', 'width', 'height'], 'number'],
            [['order_id', 'weight'], 'integer'],
            [['order_id', 'length', 'width', 'height'], 'required'],
            ['weight', 'safe'],
        ];
    }

    /**
     * Заказ
     *
     * @return ActiveQuery
     */
    public function getOrder(): ActiveQuery
    {
        return $this->hasOne(Order::class, ['id' => 'order_id']);
    }
}
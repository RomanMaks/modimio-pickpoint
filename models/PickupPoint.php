<?php

namespace app\models;

use yii\db\ActiveRecord;

/**
 * Модель "Постамат PickPoint"
 *
 * Class PickupPoint
 * @package app\models
 *
 * @property integer $id      Первичный ключ
 * @property string  $code    Код постомата
 * @property string  $address Адрес постомата
 */
class PickupPoint extends ActiveRecord
{
    /**
     * @return string
     */
    public static function tableName(): string
    {
        return 'pickup_point';
    }

    /**
     * Правила проверки для атрибутов
     *
     * @return array
     */
    public function rules()
    {
        return [
            [['code', 'address'], 'string', 'max' => 256],
            [['code', 'address'], 'required'],
        ];
    }
}
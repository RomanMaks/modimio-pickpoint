<?php

namespace app\models;

use yii\db\ActiveRecord;

/**
 * Модель "Сессия"
 *
 * Class Session
 * @package app\models
 *
 * @property integer $id         Первичный ключ
 * @property string  $service    Сервис
 * @property string  $token      Уникальный идентификатор
 * @property string  $issued_at  Дата выпуска идентификатора
 */
class Session extends ActiveRecord
{
    /**
     * @return string
     */
    public static function tableName(): string
    {
        return 'sessions';
    }

    /**
     * Правила проверки для атрибутов
     *
     * @return array
     */
    public function rules()
    {
        return [
            [['service', 'token'], 'string'],
            [['service', 'token', 'issued_at'], 'required'],
            ['service', 'unique'],
            ['issued_at', 'datetime', 'format' => 'php:Y-m-d H:i:s']
        ];
    }
}
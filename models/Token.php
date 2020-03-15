<?php

namespace app\models;

use yii\db\ActiveRecord;

/**
 * Модель "Токен"
 *
 * Class Token
 * @package app\models
 *
 * @property integer $id         Первичный ключ
 * @property string  $service    Сервис
 * @property string  $session_id Уникальный идентификатор
 * @property string  $issued_at  Дата выпуска идентификатора
 */
class Token extends ActiveRecord
{
    /**
     * @return string
     */
    public static function tableName(): string
    {
        return 'tokens';
    }

    /**
     * Правила проверки для атрибутов
     *
     * @return array
     */
    public function rules()
    {
        return [
            [['service', 'session_id'], 'string'],
            [['service', 'session_id', 'issued_at'], 'required'],
            ['service', 'unique'],
            ['issued_at', 'datetime', 'format' => 'php:Y-m-d H:i:s']
        ];
    }
}
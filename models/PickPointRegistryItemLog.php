<?php

namespace app\models;

use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * Модель "Лог записи реестра"
 *
 * Class PickPointRegistryItemLog
 * @package app\models
 *
 * @property integer $id               Первичный ключ
 * @property integer $registry_item_id ID записи реестра
 * @property integer $event_type       Тип события
 * @property string  $message          Сообщение
 * @property string  $created_at       Дата создания
 *
 * @property PickPointRegistryItem $registryItem Запись реестра
 */
class PickPointRegistryItemLog extends ActiveRecord
{
    /** @var array Типы событий */
    public const EVENT_TYPES = [
        'INFO' => 1,    // Информация
        'WARNING' => 2, // Предупреждение
        'ERROR' => 3,   // Ошибка
    ];

    /**
     * @return string
     */
    public static function tableName(): string
    {
        return 'pick_point_registry_item_log';
    }

    /**
     * Правила проверки для атрибутов
     *
     * @return array
     */
    public function rules()
    {
        return [
            ['message', 'string', 'max' => 256],
            [['registry_item_id', 'event_type'], 'integer'],
            [['registry_item_id', 'event_type'], 'required'],
            ['event_type', 'in', 'range' => array_values(self::EVENT_TYPES)],
            ['created_at', 'safe'],
        ];
    }

    /**
     * Запись реестра
     *
     * @return ActiveQuery
     */
    public function getRegistryItem(): ActiveQuery
    {
        return $this->hasOne(PickPointRegistry::class, ['id' => 'registry_item_id']);
    }
}
<?php

namespace app\models;

use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * Модель "Запись реестра"
 *
 * Соответствует одному заказу. Физически это одна посылка, включающая
 * одно или несколько тарных мест (коробок). В терминологии PickPoint
 * это называется "отправление"
 *
 * Class PickPointRegistryItem
 * @package app\models
 *
 * @property integer $id                   Первичный ключ
 * @property string  $departure_track_code Трек-код отправления (получаемый от API)
 * @property integer $status               Статус
 * @property integer $registry_id          ID реестра
 * @property integer $order_id             ID заказа
 * @property string  $created_at           Дата создания
 * @property string  $updated_at           Дата последнего изменения
 *
 * @property PickPointRegistry $registry Реестр
 * @property Order $order Заказ
 * @property PickPointRegistryItemLog[] $registryItemLogs Логи записи реестра
 */
class PickPointRegistryItem extends ActiveRecord
{
    /** @var array Статусы */
    public const STATUSES = [
        'CREATE' => 1,     // Создано
        'REGISTERED' => 2, // Зарегистрировано
        'ERROR' => 3,      // Ошибка
    ];

    /**
     * @return string
     */
    public static function tableName(): string
    {
        return 'pick_point_registry_item';
    }

    /**
     * Правила проверки для атрибутов
     *
     * @return array
     */
    public function rules()
    {
        return [
            ['departure_track_code', 'string', 'max' => 256],
            [['status', 'registry_id', 'order_id'], 'integer'],
            [['departure_track_code', 'status', 'registry_id', 'order_id'], 'required'],
            ['status', 'in', 'range' => array_values(self::STATUSES)],
            [['created_at', 'updated_at'], 'safe'],
        ];
    }

    /**
     * Реестр
     *
     * @return ActiveQuery
     */
    public function getRegistry(): ActiveQuery
    {
        return $this->hasOne(PickPointRegistry::class, ['id' => 'registry_id']);
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

    /**
     * Логи записи реестра
     *
     * @return ActiveQuery
     */
    public function getRegistryItemLogs(): ActiveQuery
    {
        return $this->hasMany(PickPointRegistryItemLog::class, ['registry_item_id' => 'id']);
    }
}
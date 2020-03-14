<?php

namespace app\models;

use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * Модель "Реестр"
 *
 * Набор записей реестра. Физически это набор посылок для разовой
 * передачи в службу доставки
 *
 * Class PickPointRegistry
 * @package app\models
 *
 * @property integer $id               Первичный ключ
 * @property integer $registry_number  Номер реестра (получаемый от API)
 * @property integer $status           Статус
 * @property string  $label_print_link Ссылка на печать этикеток
 * @property string  $created_at       Дата создания
 * @property string  $updated_at       Дата последнего изменения
 *
 * @property PickPointRegistryItem[] $registryItems Записи реестра
 */
class PickPointRegistry extends ActiveRecord
{
    /** @var array Статусы */
    public const STATUSES = [
        'OPEN' => 1,
        'READY_FOR_SHIPMENT' => 2,
    ];

    /**
     * @return string
     */
    public static function tableName(): string
    {
        return 'pick_point_registry';
    }

    /**
     * Правила проверки для атрибутов
     *
     * @return array
     */
    public function rules()
    {
        return [
            ['label_print_link', 'string', 'max' => 256],
            [['registry_number', 'status'], 'integer'],
            [['registry_number', 'status'], 'required'],
            ['status', 'in', 'range' => array_values(self::STATUSES)],
            [['created_at', 'updated_at'], 'safe'],
        ];
    }

    /**
     * Записи реестра
     *
     * @return ActiveQuery
     */
    public function getRegistryItems(): ActiveQuery
    {
        return $this->hasMany(PickPointRegistryItem::class, ['registry_id' => 'id']);
    }
}
<?php

use yii\db\Migration;

/**
 * Запись реестра - соответствует одному заказу. Физически это одна посылка,
 * включающая одно или несколько тарных мест (коробок)
 *
 * Handles the creation of table `pick_point_registry_item`.
 */
class m200314_104059_create_pick_point_registry_item_table extends Migration
{
    public function up()
    {
        $this->createTable('pick_point_registry_item', [
            'id' => $this->primaryKey(),
            'departure_track_code' => $this->string(),
            'status' => $this->integer()->notNull(),
            'registry_id' => $this->integer()->notNull(),
            'order_id' => $this->integer()->notNull(),
            'created_at' => $this->timestamp()->defaultExpression('CURRENT_TIMESTAMP'),
            'updated_at' => $this->timestamp()->defaultExpression('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'),
        ]);

        $this->createIndex(
            'idx-pick_point_registry_item-status',
            'pick_point_registry_item',
            'status'
        );

        $this->createIndex(
            'idx-pick_point_registry_item-registry_id',
            'pick_point_registry_item',
            'registry_id'
        );

        $this->createIndex(
            'idx-pick_point_registry_item-order_id',
            'pick_point_registry_item',
            'order_id'
        );
    }

    public function down()
    {
        $this->dropTable('pick_point_registry_item ');
    }
}

<?php

use yii\db\Migration;

/**
 * Лог записи реестра
 *
 * Handles the creation of table `pick_point_registry_item_log`.
 */
class m200314_105946_create_pick_point_registry_item_log_table extends Migration
{
    public function safeUp()
    {
        $this->createTable('pick_point_registry_item_log', [
            'id' => $this->primaryKey(),
            'pick_point_registry_id' => $this->integer()->notNull(),
            'event_type' => $this->integer()->notNull(),
            'message' => $this->string(),
            'created_at' => $this->timestamp()->defaultExpression('CURRENT_TIMESTAMP'),
        ]);

        $this->createIndex(
            'idx-pick_point_registry_item_log-event_type',
            'pick_point_registry_item_log',
            'event_type'
        );
    }

    public function safeDown()
    {
        $this->dropTable('pick_point_registry_item_log');
    }
}

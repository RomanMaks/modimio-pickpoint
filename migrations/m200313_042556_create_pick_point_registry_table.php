<?php

use yii\db\Migration;

/**
 * Реестр - набор записей реестра. Физически это набор посылок для разовой передачи в службу доставки
 *
 * Handles the creation of table `pick_point_registry`.
 */
class m200313_042556_create_pick_point_registry_table extends Migration
{
    public function up()
    {
        $this->createTable('pick_point_registry', [
            'id' => $this->primaryKey(),
            'registry_number' => $this->integer()->notNull(),
            'status' => $this->integer()->notNull(),
            'label_print_link' => $this->string(),
            'created_at' => $this->timestamp()->defaultExpression('CURRENT_TIMESTAMP'),
            'updated_at' => $this->timestamp()->defaultExpression('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'),
        ]);

        $this->createIndex(
            'idx-pick_point_registry-status',
            'pick_point_registry',
            'status'
        );
    }

    public function down()
    {
        $this->dropTable('pick_point_registry');
    }
}

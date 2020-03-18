<?php

use yii\db\Migration;

/**
 * Токены
 *
 * Handles the creation of table `sessions`.
 */
class m200314_142524_create_sessions_table extends Migration
{
    public function up()
    {
        $this->createTable('sessions', [
            'id' => $this->primaryKey(),
            'service' => $this->string()->unique()->notNull(),
            'token' => $this->string()->notNull(),
            'issued_at' => $this->timestamp()->notNull(),
        ]);
    }

    public function down()
    {
        $this->dropTable('sessions');
    }
}

<?php

use yii\db\Migration;

/**
 * Токены
 *
 * Handles the creation of table `tokens`.
 */
class m200314_142524_create_tokens_table extends Migration
{
    public function up()
    {
        $this->createTable('tokens', [
            'id' => $this->primaryKey(),
            'service' => $this->string()->unique()->notNull(),
            'session_id' => $this->string()->notNull(),
            'issued_at' => $this->timestamp()->notNull(),
        ]);
    }

    public function down()
    {
        $this->dropTable('tokens');
    }
}

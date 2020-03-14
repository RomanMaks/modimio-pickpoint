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
            'service' => $this->string()->notNull(),
            'session_id' => $this->string()->notNull(),
            'issued_at' => $this->timestamp()->notNull(),
        ]);

        $this->createIndex(
            'uidx-tokens-service-session_id',
            'tokens',
            ['service', 'session_id'],
            true
        );
    }

    public function down()
    {
        $this->dropTable('tokens');
    }
}

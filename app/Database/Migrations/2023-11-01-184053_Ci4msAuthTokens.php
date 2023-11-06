<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class Ci4msAuthTokens extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'auto_increment' => true
            ],
            'selector' => [
                'type' => 'VARCHAR',
                'constraint' =>255
            ],
            'hashedValidator' => [
                'type' => 'VARCHAR',
                'constraint' => 255
            ],
            'user_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true
            ],
            'expires' => [
                'type' => 'DATETIME'
            ]
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('selector');
        $this->forge->addKey('hashedValidator');
        $this->forge->addForeignKey('user_id',  'users', 'id', 'CASCADE', 'CASCADE', 'ci4ms_auth_tokens_ibfk_1');
        $this->forge->createTable( 'auth_tokens');
    }

    public function down()
    {
        $this->forge->dropTable( 'auth_tokens');
    }
}

<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class Ci4msAuthEmailActivationAttemps extends Migration
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
            'created_at' => [
                'type' => 'DATETIME'
            ],
            'ip_address' => [
                'type' => 'VARCHAR',
                'constraint' => 39
            ],
            'token' => [
                'type' => 'VARCHAR',
                'constraint' => 100
            ],
            'user_agent' => [
                'type' => 'VARCHAR',
                'constraint' => 255
            ]
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('auth_email_activation_attempts');
    }

    public function down()
    {
        $this->forge->dropTable('auth_email_activation_attempts');
    }
}

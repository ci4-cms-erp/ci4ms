<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AuthResetPasswordAttempts extends Migration
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
            'email' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => true
            ],
            'ip_address' => [
                'type' => 'VARCHAR',
                'constraint' => 255
            ],
            'user_agent' => [
                'type' => 'INT',
                'constraint' => 11
            ],
            'token' => [
                'type' => 'VARCHAR',
                'constraint' => 255
            ],
            'created_at' => [
                'type' => 'DATETIME'
            ]
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('auth_reset_password_attempts');
    }

    public function down()
    {
        $this->forge->dropTable('auth_reset_password_attempts');
    }
}

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
                'constraint' => 255,
                'null' => true
            ],
            'user_agent' => [
                'type' => 'INT',
                'constraint' => 11,
                'null' => true
            ],
            'token' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => true
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true
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

<?php

namespace Modules\Auth\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateUserSessionsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'user_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'session_id' => [
                'type'       => 'VARCHAR',
                'constraint' => 128,
            ],
            'ip_address' => [
                'type'       => 'VARCHAR',
                'constraint' => 45,
            ],
            'user_agent' => [
                'type'       => 'VARCHAR',
                'constraint' => 512,
                'null'       => true,
            ],
            'browser' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
            ],
            'browser_version' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'null'       => true,
            ],
            'os' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
            ],
            'device_type' => [
                'type'       => 'ENUM',
                'constraint' => ['desktop', 'mobile', 'tablet', 'bot', 'unknown'],
                'default'    => 'unknown',
            ],
            'device_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
                'null'       => true,
            ],
            'region' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
            ],
            'country' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
            ],
            'city' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
            ],
            'last_activity' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'is_active' => [
                'type'    => 'TINYINT',
                'constraint' => 1,
                'default' => 1,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'terminated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('user_id');
        $this->forge->addKey('session_id');
        $this->forge->addKey(['user_id', 'is_active']); // composite index — liste sorgusu için

        $this->forge->createTable('user_sessions', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('user_sessions', true);
    }
}

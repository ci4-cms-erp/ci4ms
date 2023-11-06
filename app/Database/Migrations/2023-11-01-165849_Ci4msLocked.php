<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class Ci4msLocked extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true
            ],
            'type' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
            ],
            'ip_address' => [
                'type' => 'VARCHAR',
                'constraint' => 39,
            ],
            'username' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
            ],
            'locked_at' => [
                'type' => 'DATETIME',
            ],
            'expiry_date' => [
                'type' => 'DATETIME',
            ],
            'isLocked' => [
                'type' => 'BOOLEAN'
            ],
            'counter' => [
                'type' => 'TINYINT',
                'constraint' => 1,
            ]
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('ip_address');
        $this->forge->createTable('locked');
    }

    public function down()
    {
        $this->forge->dropTable('locked');
    }
}

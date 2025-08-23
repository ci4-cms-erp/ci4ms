<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

class Locked extends Migration
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
            'type' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => true
            ],
            'ip_address' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => true
            ],
            'username' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => true
            ],
            'locked_at' => [
                'type' => 'DATETIME',
                'null' => true
            ],
            'expiry_date' => [
                'type' => 'DATETIME',
                'null' => true
            ],
            'isLocked' => [
                'type' => 'TINYINT',
                'constraint' => 1,
                'null' => true
            ],
            'counter' => [
                'type' => 'TINYINT',
                'constraint' => 1,
                'null' => true
            ]
        ]);
        $this->forge->addKey('id',true);
        $this->forge->createTable('locked');
    }

    public function down()
    {
        $this->forge->dropTable('locked');
    }
}

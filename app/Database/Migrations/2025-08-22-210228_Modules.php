<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

class Modules extends Migration
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
            'create_time' => [
                'type' => 'DATETIME',
                'default' => new RawSql('CURRENT_TIMESTAMP')
            ],
            'name' => [
                'type' => 'VARCHAR',
                'constraint' => 255
            ],
            'isActive' => [
                'type' => 'TINYINT',
                'constraint' => 1,
                'default' => 1
            ],
            'icon' => [
                'type' => 'VARCHAR',
                'constraint' => 100,
                'null'=>true
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('modules');
    }

    public function down()
    {
        $this->forge->dropTable('modules');
    }
}

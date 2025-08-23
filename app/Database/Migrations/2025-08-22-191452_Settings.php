<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class Settings extends Migration
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
            'option' => [
                'type' => 'VARCHAR',
                'constraint' => 255
            ],
            'content' => [
                'type' => 'LONGTEXT',
                'null' => true
            ]
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('option');
        $this->forge->createTable('settings');
    }

    public function down()
    {
        $this->forge->dropTable('settings');
    }
}

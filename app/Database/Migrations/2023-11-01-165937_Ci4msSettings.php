<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class Ci4msSettings extends Migration
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
            'option' => [
                'type' => 'VARCHAR',
                'constraint' => 255
            ],
            'content' => [
                'type' => 'LONGTEXT',
                'null'=>true
            ]
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('option', false,true);
        $this->forge->createTable('settings');
    }

    public function down()
    {
        $this->forge->dropTable('settings');
    }
}

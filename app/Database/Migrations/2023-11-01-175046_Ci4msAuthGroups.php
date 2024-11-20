<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

class Ci4msAuthGroups extends Migration
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
            'name' => [
                'type' => 'VARCHAR',
                'constraint' => 255
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true
            ],
            'description' => [
                'type' => 'VARCHAR',
                'constraint' => 255
            ],
            'seflink' => [
                'type' => 'VARCHAR',
                'constraint' => 255
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'default' => new RawSql('CURRENT_TIMESTAMP')
            ],
            'who_created' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'null'=>true
            ]
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('name');
        $this->forge->addKey('seflink');
        $this->forge->createTable('auth_groups');
    }

    public function down()
    {
        $this->forge->dropTable('auth_groups');
    }
}

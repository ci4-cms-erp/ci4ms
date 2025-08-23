<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class LoginRules extends Migration
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
                'constraint' => 11,
                'null' => true
            ],
            'range' => [
                'type' => 'LONGTEXT',
                'null' => true
            ],
            'line' => [
                'type' => 'LONGTEXT',
                'null' => true
            ],
            'username' => [
                'type' => 'LONGTEXT',
                'null' => true
            ]
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('type');
        $this->forge->createTable('login_rules');
    }

    public function down()
    {
        $this->forge->dropTable('login_rules');
    }
}

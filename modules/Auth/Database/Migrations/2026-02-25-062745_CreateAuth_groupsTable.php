<?php

namespace Modules\Auth\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAuth_groupsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type' => 'INT',
                'constraint' => '11',
                'unsigned' => true,
                'auto_increment' => true,
                'null' => false,
            ],
            'group' => [
                'type' => 'VARCHAR',
                'constraint' => '255',
                'null' => false,
            ],
            'who_created' => [
                'type' => 'INT',
                'constraint' => '11',
                'unsigned' => true,
                'null' => true,
                'default' => null,
            ],
            'permissions' => [
                'type' => 'LONGTEXT',
                'null' => true,
                'default' => null,
            ],
            'description' => [
                'type' => 'VARCHAR',
                'constraint' => '255',
                'null' => true,
                'default' => null,
            ],
            'redirect' => [
                'type' => 'VARCHAR',
                'constraint' => '255',
                'null' => true,
                'default' => 'backend',
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('auth_groups', true);
    }

    public function down()
    {
        $this->forge->dropTable('auth_groups', true);
    }
}

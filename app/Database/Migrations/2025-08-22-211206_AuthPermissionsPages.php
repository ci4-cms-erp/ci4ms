<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AuthPermissionsPages extends Migration
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
            'pagename' => [
                'type' => 'VARCHAR',
                'constraint' => 255
            ],
            'description' => [
                'type' => 'VARCHAR',
                'constraint' => 255
            ],
            'className' => [
                'type' => 'VARCHAR',
                'constraint' => 255
            ],
            'methodName' => [
                'type' => 'VARCHAR',
                'constraint' => 255
            ],
            'sefLink' => [
                'type' => 'TEXT'
            ],
            'hasChild' => [
                'type' => 'TINYINT',
                'constraint' => 1
            ],
            'pageSort' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'null' => true
            ],
            'parent_pk' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'null' => true
            ],
            'symbol' => [
                'type' => 'VARCHAR',
                'constraint' => 255
            ],
            'inNavigation' => [
                'type' => 'TINYINT',
                'constraint' => 1
            ],
            'isBackoffice' => [
                'type' => 'TINYINT',
                'constraint' => 1
            ],
            'typeOfPermissions' => [
                'type' => 'LONGTEXT'
            ],
            'module_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true
            ],
            'isActive' => [
                'type' => 'TINYINT',
                'constraint' => 1,
                'default' => 1
            ]
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('parent_pk', 'auth_permissions_pages', 'id','CASCADE','CASCADE');
        $this->forge->addForeignKey('module_id', 'modules', 'id','CASCADE','CASCADE');
        $this->forge->createTable('auth_permissions_pages');
    }

    public function down()
    {
        $this->forge->dropTable('auth_permissions_pages');
    }
}

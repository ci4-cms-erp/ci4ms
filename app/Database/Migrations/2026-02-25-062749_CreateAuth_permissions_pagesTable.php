<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

class CreateAuth_permissions_pagesTable extends Migration
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
            'pagename' => [
                'type' => 'VARCHAR',
                'constraint' => '255',
                'null' => false,
            ],
            'description' => [
                'type' => 'VARCHAR',
                'constraint' => '255',
                'null' => false,
            ],
            'className' => [
                'type' => 'VARCHAR',
                'constraint' => '255',
                'null' => false,
            ],
            'methodName' => [
                'type' => 'VARCHAR',
                'constraint' => '255',
                'null' => false,
            ],
            'sefLink' => [
                'type' => 'TEXT',
                'null' => false,
            ],
            'hasChild' => [
                'type' => 'TINYINT',
                'constraint' => '1',
                'null' => false,
            ],
            'pageSort' => [
                'type' => 'INT',
                'constraint' => '11',
                'unsigned' => true,
                'null' => true,
                'default' => null,
            ],
            'parent_pk' => [
                'type' => 'INT',
                'constraint' => '11',
                'unsigned' => true,
                'null' => true,
                'default' => null,
            ],
            'symbol' => [
                'type' => 'VARCHAR',
                'constraint' => '255',
                'null' => false,
            ],
            'inNavigation' => [
                'type' => 'TINYINT',
                'constraint' => '1',
                'null' => false,
            ],
            'isBackoffice' => [
                'type' => 'TINYINT',
                'constraint' => '1',
                'null' => false,
            ],
            'typeOfPermissions' => [
                'type' => 'LONGTEXT',
                'null' => false,
            ],
            'module_id' => [
                'type' => 'INT',
                'constraint' => '11',
                'unsigned' => true,
                'null' => false,
            ],
            'isActive' => [
                'type' => 'TINYINT',
                'constraint' => '1',
                'null' => false,
                'default' => 1,
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('auth_permissions_pages');
    }

    public function down()
    {
        $this->forge->dropTable('auth_permissions_pages');
    }
}

<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class Ci4msAuthPermissionsPages extends Migration
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
                'type' => 'VARCHAR',
                'constraint' => 255
            ],
            'hasChild' => [
                'type' => 'BOOLEAN'
            ],
            'pageSort' => [
                'type' => 'INT',
                'constraint' => 11,
                'null'=>true
            ],
            'parent_pk' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned'=>true,
                'null'=>true
            ],
            'symbol' => [
                'type' => 'VARCHAR',
                'constraint' => 255
            ],
            'inNavigation' => [
                'type' => 'BOOLEAN'
            ],
            'isBackoffice' => [
                'type' => 'BOOLEAN'
            ],
            'typeOfPermissions' => [
                'type' => 'LONGTEXT'
            ]
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('pagename');
        $this->forge->addKey('description');
        $this->forge->addKey('className');
        $this->forge->addKey('methodName');
        $this->forge->addKey('sefLink');
        $this->forge->addKey('symbol');
        $this->forge->createTable( 'auth_permissions_pages');
    }

    public function down()
    {
        $this->forge->dropTable( 'auth_permissions_pages');
    }
}

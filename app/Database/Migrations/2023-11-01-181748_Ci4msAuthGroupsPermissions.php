<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

class Ci4msAuthGroupsPermissions extends Migration
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
            'group_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
            ],
            'page_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
            ],
            'create_r' => [
                'type' => 'BOOLEAN',
                'default'=>false,
            ],
            'update_r' => [
                'type' => 'BOOLEAN',
                'default'=>false,
            ],
            'read_r' => [
                'type' => 'BOOLEAN',
                'default'=>false,
            ],
            'delete_r' => [
                'type' => 'BOOLEAN',
                'default'=>false,
            ],
            'who_perm' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'null'=>true
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'default' => new RawSql('CURRENT_TIMESTAMP')
            ]
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('group_id', 'auth_groups', 'id', 'CASCADE', 'CASCADE', 'ci4ms_auth_groups_permissions_ibfk_1');
        $this->forge->addForeignKey('page_id', 'auth_permissions_pages', 'id', 'CASCADE', 'CASCADE', 'ci4ms_auth_groups_permissions_ibfk_2');
        $this->forge->addForeignKey('who_perm', 'users', 'id', 'SET_NULL', 'SET_NULL', 'ci4ms_auth_groups_permissions_ibfk_3');
        $this->forge->createTable( 'auth_groups_permissions');
    }

    public function down()
    {
        $this->forge->dropTable( 'auth_groups_permissions');
    }
}

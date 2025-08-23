<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

class AuthUsersPermissions extends Migration
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
            'user_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true
            ],
            'page_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true
            ],
            'create_r' => [
                'type' => 'TINYINT',
                'constraint' => 1,
                'default' => false
            ],
            'update_r' => [
                'type' => 'TINYINT',
                'constraint' => 1,
                'default' => false
            ],
            'read_r' => [
                'type' => 'TINYINT',
                'constraint' => 1,
                'default' => false
            ],
            'delete_r' => [
                'type' => 'TINYINT',
                'constraint' => 1,
                'default' => false
            ],
            'who_perm' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'null' => true
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'default' => new RawSql('CURRENT_TIMESTAMP')
            ]
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('page_id', 'auth_permissions_pages', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('who_perm', 'users', 'id', 'SET NULL', 'SET NULL');
        $this->forge->createTable('auth_users_permissions');
    }

    public function down()
    {
        $this->forge->dropTable('auth_users_permissions');
    }
}

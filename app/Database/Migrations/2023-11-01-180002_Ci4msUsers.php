<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

class Ci4msUsers extends Migration
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
            'email' => [
                'type' => 'VARCHAR',
                'constraint' => 255
            ],
            'firstname' => [
                'type' => 'VARCHAR',
                'constraint' => 255
            ],
            'sirname' => [
                'type' => 'VARCHAR',
                'constraint' => 255
            ],
            'username' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null'=>true
            ],
            'activate_hash' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null'=>true
            ],
            'password_hash' => [
                'type' => 'VARCHAR',
                'constraint' => 255
            ],
            'reset_hash' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null'=>true
            ],
            'reset_at' => [
                'type' => 'DATETIME',
                'null'=>true
            ],
            'reset_expires' => [
                'type' => 'DATETIME',
                'null'=>true
            ],
            'status' => [
                'type' => 'ENUM',
                'constraint' => ['active','deactive', 'banned', 'deleted']
            ],
            'statusMessage' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null'=>true
            ],
            'force_pass_reset' => [
                'type' => 'BOOLEAN',
                'null'=>true
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'default' => new RawSql('CURRENT_TIMESTAMP')
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null'=>true
            ],
            'deleted_at' => [
                'type' => 'DATETIME',
                'null'=>true
            ],
            'group_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true
            ],
            'who_created' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'null'=>true
            ]
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('email', false, true);
        $this->forge->addKey('username', false, true);
        $this->forge->addForeignKey('group_id', 'auth_groups', 'id', 'CASCADE', 'SET_NULL', 'users_auth_groups_id_fk');
        $this->forge->addForeignKey('who_created', 'users', 'id', 'CASCADE', 'SET_NULL', 'users_users_id_fk');
        $this->forge->createTable( 'users');
    }

    public function down()
    {
        $this->forge->dropTable( 'users');
    }
}

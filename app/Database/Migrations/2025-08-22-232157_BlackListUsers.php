<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

class BlackListUsers extends Migration
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
            'blacked_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true
            ],
            'who_blacklisted' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true
            ],
            'notes' => [
                'type' => 'TEXT',
                'null' => true
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'default' => new RawSql('CURRENT_TIMESTAMP')
            ]
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('blacked_id',  'users', 'id', 'CASCADE', 'SET_NULL', 'ci4ms_black_list_users_ci4ms_users_id_fk');
        $this->forge->addForeignKey('who_blacklisted',  'users', 'id', 'CASCADE', 'SET_NULL', 'ci4ms_black_list_users');
        $this->forge->createTable('black_list_users');
    }

    public function down()
    {
        $this->forge->dropTable('black_list_users');
    }
}

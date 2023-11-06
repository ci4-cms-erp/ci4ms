<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class Ci4msAuthLogins extends Migration
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
            'ip_address' => [
                'type' => 'VARCHAR',
                'constraint' => 39
            ],
            'email' => [
                'type' => 'VARCHAR',
                'constraint' => 255
            ],
            'trydate' => [
                'type' => 'DATETIME'
            ],
            'isSuccess' => [
                'type' => 'BOOLEAN'
            ],
            'user_agent' => [
                'type' => 'VARCHAR',
                'constraint' => 255
            ],
            'session_id' => [
                'type' => 'VARCHAR',
                'constraint' => 255
            ],
            'counter' => [
                'type' => 'TINYINT',
                'constraint' => 2,
                'null'=>true
            ],
            'username' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null'=>true
            ]
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('ip_address');
        $this->forge->addKey('email');
        $this->forge->addKey('user_agent');
        $this->forge->addKey('session_id');
        $this->forge->addKey('username');
        $this->forge->createTable( 'auth_logins');
    }

    public function down()
    {
        $this->forge->dropTable( 'auth_logins');
    }
}

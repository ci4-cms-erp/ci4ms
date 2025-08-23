<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AuthLogins extends Migration
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
            'ip_address'=>[
                'type'=>'VARCHAR',
                'constraint'=>39,
                'null'=>true
            ],
            'email'=>[
                'type'=>'VARCHAR',
                'constraint'=>255,
                'null'=>true
            ],
            'trydate'=>[
                'type'=>'DATETIME',
                'null'=>true
            ],
            'isSuccess'=>[
                'type'=>'TINYINT',
                'constraint'=>1,
                'null'=>true
            ],
            'user_agent'=>[
                'type'=>'VARCHAR',
                'constraint'=>255,
                'null'=>true
            ],
            'session_id'=>[
                'type'=>'VARCHAR',
                'constraint'=>255,
                'null'=>true
            ],
            'counter'=>[
                'type'=>'TINYINT',
                'constraint'=>2,
                'null'=>true
            ],
            'username'=>[
                'type'=>'VARCHAR',
                'constraint'=>255,
                'null'=>true
            ]
        ]);
        $this->forge->addKey('id',true);
        $this->forge->addKey(['ip_address','email']);
        $this->forge->addKey('user_agent');
        $this->forge->addKey('session_id');
        $this->forge->addKey('username');
        $this->forge->createTable('auth_logins');
    }

    public function down()
    {
        $this->forge->dropTable('auth_logins');
    }
}

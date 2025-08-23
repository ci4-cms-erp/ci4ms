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
                'constraint'=>255,
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
                'constraint'=>4,
                'null'=>true
            ],
            'username'=>[
                'type'=>'VARCHAR',
                'constraint'=>255,
                'null'=>true
            ]
        ]);
        $this->forge->addKey('id',true);
        $this->forge->createTable('auth_logins');
    }

    public function down()
    {
        $this->forge->dropTable('auth_logins');
    }
}

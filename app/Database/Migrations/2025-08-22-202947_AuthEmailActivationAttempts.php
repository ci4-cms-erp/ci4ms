<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AuthEmailActivationAttempts extends Migration
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
            'created_at'=>[
                'type'=>'DATETIME',
                'null'=>true
            ],
            'ip_address'=>[
                'type'=>'VARCHAR',
                'constraint'=>255,
                'null'=>true
            ],
            'token'=>[
                'type'=>'VARCHAR',
                'constraint'=>255,
                'null'=>true
            ],
            'user_agent'=>[
                'type'=>'VARCHAR',
                'constraint'=>255,
                'null'=>true
            ]
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('auth_email_activation_attempts');
    }

    public function down()
    {
        $this->forge->dropTable('auth_email_activation_attempts');
    }
}

<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AuthActivationAttempts extends Migration
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
            'user_id'=>[
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'null'=>true
            ],
            'ip_address'=>[
                'type'=>'VARCHAR',
                'constraint'=>255,
                'null'=>true
            ],
            'user_agent'=>[
                'type'=>'VARCHAR',
                'constraint'=>255,
                'null'=>true
            ],
            'token'=>[
                'type'=>'VARCHAR',
                'constraint'=>255,
                'null'=>true
            ],
            'created_at'=>[
                'type'=>'DATETIME',
                'null'=>true
            ],
        ]);
        $this->forge->addKey('id',true);
        $this->forge->addForeignKey('user_id','users','id','CASCADE','CASCADE');
        $this->forge->createTable('auth_activation_attempts');
    }

    public function down()
    {
        $this->forge->dropTable('auth_activation_attempts');
    }
}

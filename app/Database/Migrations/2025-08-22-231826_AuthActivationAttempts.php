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
                'unsigned' => true
            ],
            'ip_address'=>[
                'type'=>'VARCHAR',
                'constraint'=>255
            ],
            'user_agent'=>[
                'type'=>'VARCHAR',
                'constraint'=>255
            ],
            'token'=>[
                'type'=>'VARCHAR',
                'constraint'=>255
            ],
            'created_at'=>[
                'type'=>'DATETIME'
            ],
        ]);
        $this->forge->addKey('id',true);
        $this->forge->addUniqueKey('token');
        $this->forge->addForeignKey('user_id','users','id','CASCADE','CASCADE');
        $this->forge->createTable('auth_activation_attempts');
    }

    public function down()
    {
        $this->forge->dropTable('auth_activation_attempts');
    }
}

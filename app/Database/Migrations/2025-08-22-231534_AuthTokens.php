<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AuthTokens extends Migration
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
            'selector'=>[
                'type'=>'VARCHAR',
                'constraint'=>255,
                'null'=>true
            ],
            'hashedValidator'=>[
                'type'=>'VARCHAR',
                'constraint'=>255,
                'null'=>true
            ],
            'user_id'=>[
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'null'=>true
            ],
            'expires'=>[
                'type'=>'DATETIME',
                'null'=>true
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('user_id','users','id','CASCADE','CASCADE');
        $this->forge->createTable('auth_tokens');
    }

    public function down()
    {
        $this->forge->dropTable('auth_tokens');
    }
}

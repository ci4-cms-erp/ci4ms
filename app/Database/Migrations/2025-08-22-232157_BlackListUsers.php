<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

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
            'blacked_id'=>[
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'null'=>true
            ],
            'who_blacklisted'=>[
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'null'=>true
            ],
            'notes'=>[
                'type'=>'TEXT',
                'null'=>true
            ],
            'created_at'=>[
                'type'=>'DATETIME',
                'null'=>true
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('blacked_id','users','id','CASCADE','CASCADE');
        $this->forge->addForeignKey('who_blacklisted','users','id','SET NULL','SET NULL');
        $this->forge->createTable('black_list_users');
    }

    public function down()
    {
        $this->forge->dropTable('black_list_users');
    }
}

<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

class Comments extends Migration
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
            'blog_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true
            ],
            'isApproved' => [
                'type'=>'TINYINT',
                'constraint'=>1,
                'default'=>1
            ],
            'created_at' => [
                'type'=>'DATETIME',
                'default'=>new RawSql('CURRENT_TIMESTAMP')
            ],
            'comFullName' => [
                'type'=>'VARCHAR',
                'constraint'=>255
            ],
            'comEmail' => [
                'type'=>'VARCHAR',
                'constraint'=>255
            ],
            'comMessage' => [
                'type'=>'LONGTEXT'
            ],
            'isThereAnReply' => [
                'type'=>'TINYINT',
                'constraint'=>1,
                'default'=>0
            ],
            'parent_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'null'=>true
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['comFullName','comEmail']);
        $this->forge->addForeignKey('blog_id','blog','id','CASCADE','CASCADE');
        $this->forge->addForeignKey('parent_id','comments','id','CASCADE','CASCADE');
        $this->forge->createTable('comments');
    }

    public function down()
    {
        $this->forge->dropTable('comments');
    }
}

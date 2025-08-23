<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

class Blog extends Migration
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
            'isActive' => [
                'type'=>'TINYINT',
                'constraint'=>1,
                'null'=>true
            ],
            'created_at' => [
                'type'=>'DATETIME',
                'default'=>new RawSql('CURRENT_TIMESTAMP')
            ],
            'inMenu' => [
                'type'=>'TINYINT',
                'constraint'=>1,
                'null'=>true
            ],
            'author' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'null'=>true
            ],
            'title' => [
                'type'=>'TEXT',
                'null'=>true
            ],
            'seflink' => [
                'type'=>'TEXT',
                'null'=>true
            ],
            'content' => [
                'type'=>'LONGTEXT',
                'null'=>true
            ],
            'inXML' => [
                'type'=>'TINYINT',
                'constraint'=>1,
                'null'=>true
            ],
            'seo' => [
                'type'=>'LONGTEXT',
                'null'=>true
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('author','users','id');
        $this->forge->createTable('blog');
    }

    public function down()
    {
        $this->forge->dropTable('blog');
    }
}

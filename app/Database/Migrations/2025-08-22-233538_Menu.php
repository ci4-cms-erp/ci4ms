<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class Menu extends Migration
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
            'pages_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'null'=>true
            ],
            'parent' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'null'=>true
            ],
            'queue' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'null'=>true
            ],
            'urlType' => [
                'type'=>'ENUM',
                'constraint'=>['pages','blogs','url'],
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
            'target' => [
                'type'=>'ENUM',
                'constraint'=>['_blank','_self','_parent','_top'],
                'null'=>true
            ],
            'hasChildren' => [
                'type'=>'TINYINT',
                'constraint'=>1,
                'default'=>0
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('menu');
    }

    public function down()
    {
        $this->forge->dropTable('menu');
    }
}

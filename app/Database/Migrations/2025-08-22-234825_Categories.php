<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class Categories extends Migration
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
                'default'=>0
            ],
            'title' => [
                'type'=>'TEXT',
                'null'=>true
            ],
            'seflink' => [
                'type'=>'TEXT',
                'null'=>true
            ],
            'parent' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'null'=>true
            ],
            'seo' => [
                'type'=>'LONGTEXT',
                'null'=>true
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('parent','categories','id','CASCADE','SET NULL');
        $this->forge->createTable('categories');
    }

    public function down()
    {
        $this->forge->dropTable('categories');
    }
}

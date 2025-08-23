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
                'type' => 'TINYINT',
                'constraint' => 1,
                'default' => 0
            ],
            'title' => [
                'type' => 'VARCHAR',
                'constraint' => 255
            ],
            'seflink' => [
                'type' => 'VARCHAR',
                'constraint' => 255
            ],
            'parent' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'null' => true
            ],
            'seo' => [
                'type' => 'LONGTEXT',
                'null' => true
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('title');
        $this->forge->addUniqueKey('seflink');
        $this->forge->addForeignKey('parent', 'categories', 'id', 'CASCADE', 'SET NULL');
        $this->forge->createTable('categories');
    }

    public function down()
    {
        $this->forge->dropTable('categories');
    }
}

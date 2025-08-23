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
                'null' => true
            ],
            'parent' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'null' => true
            ],
            'queue' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true
            ],
            'urlType' => [
                'type' => 'ENUM',
                'constraint' => ['pages', 'blogs', 'url']
            ],
            'title' => [
                'type' => 'VARCHAR',
                'constraint' => 255
            ],
            'seflink' => [
                'type' => 'VARCHAR',
                'constraint' => 255
            ],
            'target' => [
                'type' => 'ENUM',
                'constraint' => ['_blank', '_self', '_parent', '_top']
            ],
            'hasChildren' => [
                'type' => 'TINYINT',
                'constraint' => 1,
                'default' => 0
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['title', 'seflink', 'queue']);
        $this->forge->addForeignKey('pages_id', 'pages', 'id', 'CASCADE', 'CASCADE', 'ci4ms_menu_ibfk_1');
        $this->forge->addForeignKey('parent',  'menu', 'id', 'CASCADE', 'SET_NULL', 'ci4ms_menu_ibfk_2');
        $this->forge->createTable('menu');
    }

    public function down()
    {
        $this->forge->dropTable('menu');
    }
}

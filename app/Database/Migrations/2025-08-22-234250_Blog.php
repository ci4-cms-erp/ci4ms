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
                'type' => 'TINYINT',
                'constraint' => 1
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'default' => new RawSql('CURRENT_TIMESTAMP')
            ],
            'inMenu' => [
                'type' => 'TINYINT',
                'constraint' => 1,
                'default' => 0
            ],
            'author' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true
            ],
            'title' => [
                'type' => 'VARCHAR',
                'constraint' => 255
            ],
            'seflink' => [
                'type' => 'VARCHAR',
                'constraint' => 255
            ],
            'content' => [
                'type' => 'LONGTEXT'
            ],
            'inXML' => [
                'type' => 'TINYINT',
                'constraint' => 1,
                'default' => 1
            ],
            'seo' => [
                'type' => 'LONGTEXT'
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('title');
        $this->forge->addUniqueKey('seflink');
        $this->forge->addForeignKey('author',  'users', 'id', 'CASCADE', 'SET_NULL', 'blog_users_id_fk');
        $this->forge->createTable('blog');
    }

    public function down()
    {
        $this->forge->dropTable('blog');
    }
}

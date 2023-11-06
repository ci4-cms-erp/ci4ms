<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

class Ci4msBlog extends Migration
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
                'type' => 'BOOLEAN',
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'default' => new RawSql('CURRENT_TIMESTAMP')
            ],
            'inMenu' => [
                'type' => 'BOOLEAN',
                'default' => false
            ],
            'author' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true
            ],
            'title' => [
                'type' => 'VARCHAR',
                'constraint'=>255
            ],
            'seflink' => [
                'type' => 'VARCHAR',
                'constraint'=>255
            ],
            'content' => [
                'type' => 'LONGTEXT'
            ],
            'seo' => [
                'type' => 'LONGTEXT'
            ]
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('title');
        $this->forge->addKey('seflink');
        $this->forge->addForeignKey('author',  'users', 'id', 'CASCADE', 'SET_NULL', 'blog_users_id_fk');
        $this->forge->createTable( 'blog');
    }

    public function down()
    {
        $this->forge->dropTable( 'blog');
    }
}

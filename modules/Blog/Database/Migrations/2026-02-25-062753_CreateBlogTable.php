<?php

namespace Modules\Blog\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

class CreateBlogTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type' => 'INT',
                'constraint' => '11',
                'unsigned' => true,
                'auto_increment' => true,
                'null' => false,
            ],
            'locale' => [
                'type' => 'VARCHAR',
                'constraint' => '2',
                'null' => true,
                'comment' => 'ISO 639 code, NULL = all languages'
            ],
            'isActive' => [
                'type' => 'TINYINT',
                'constraint' => '1',
                'null' => false,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => false,
                'default' => new RawSql('CURRENT_TIMESTAMP'),
            ],
            'inMenu' => [
                'type' => 'TINYINT',
                'constraint' => '1',
                'null' => false,
                'default' => 0,
            ],
            'author' => [
                'type' => 'INT',
                'constraint' => '11',
                'unsigned' => true,
                'null' => false,
            ],
            'inXML' => [
                'type' => 'TINYINT',
                'constraint' => '1',
                'null' => false,
                'default' => 1,
            ]
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('blog', true);
    }

    public function down()
    {
        $this->forge->dropTable('blog', true);
    }
}

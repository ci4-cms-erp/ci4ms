<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class BlogCategoriesPivot extends Migration
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
            'categories_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true
            ]
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('blog_id','blog','id','CASCADE','CASCADE');
        $this->forge->addForeignKey('categories_id','categories','id','CASCADE','CASCADE');
        $this->forge->createTable('blog_categories_pivot');
    }

    public function down()
    {
        $this->forge->dropTable('blog_categories_pivot');
    }
}

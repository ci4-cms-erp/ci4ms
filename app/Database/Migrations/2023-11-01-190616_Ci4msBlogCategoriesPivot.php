<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class Ci4msBlogCategoriesPivot extends Migration
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
            'categories_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true
            ],
            'blog_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true
            ]
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('blog_id', 'blog', 'id', 'CASCADE', 'CASCADE', 'blog_categories_pivot_blog_id_fk');
        $this->forge->addForeignKey('categories_id', 'categories', 'id', 'CASCADE', 'CASCADE', 'blog_categories_pivot_categories_id_fk');
        $this->forge->createTable( 'blog_categories_pivot');
    }

    public function down()
    {
        $this->forge->dropTable( 'blog_categories_pivot');
    }
}

<?php

namespace Modules\Blog\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateBlog_categories_pivotTable extends Migration
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
            'blog_id' => [
                'type' => 'INT',
                'constraint' => '11',
                'unsigned' => true,
                'null' => false,
            ],
            'categories_id' => [
                'type' => 'INT',
                'constraint' => '11',
                'unsigned' => true,
                'null' => false,
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('blog_categories_pivot', true);
    }

    public function down()
    {
        $this->forge->dropTable('blog_categories_pivot', true);
    }
}

<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class Ci4msTagsPivot extends Migration
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
            'tag_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true
            ],
            'piv_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true
            ],
            'tagType' => [
                'type' => 'VARCHAR',
                'constraint' => 255
            ]
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('tag_id', 'tags', 'id', 'CASCADE', 'CASCADE', 'ci4ms_tags_pivot_ibfk_1');
        $this->forge->addForeignKey('piv_id', 'blog', 'id', 'CASCADE', 'CASCADE', 'ci4ms_tags_pivot_ibfk_3');
        $this->forge->createTable( 'tags_pivot');
    }

    public function down()
    {
        $this->forge->dropTable( 'tags_pivot');
    }
}

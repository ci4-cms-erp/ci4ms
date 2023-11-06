<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class Ci4msTags extends Migration
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
            'tag' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
            ],
            'seflink' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
            ]
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('tag');
        $this->forge->addKey('seflink');
        $this->forge->createTable( 'tags');
    }

    public function down()
    {
        $this->forge->dropTable( 'tags');
    }
}

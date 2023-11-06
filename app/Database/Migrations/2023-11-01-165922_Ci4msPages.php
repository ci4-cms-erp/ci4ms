<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

class Ci4msPages extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true
            ],
            'title' => [
                'type' => 'VARCHAR',
                'constraint' => 255
            ],
            'content' => [
                'type' => 'LONGTEXT'
            ],
            'seflink' => [
                'type' => 'VARCHAR',
                'constraint' => 255
            ],
            'creationDate' => [
                'type' => 'DATETIME',
                'default' => new RawSql('CURRENT_TIMESTAMP')
            ],
            'isActive' => [
                'type' => 'BOOLEAN',
            ],
            'seo' => [
                'type' => 'LONGTEXT'
            ],
            'inMenu' => [
                'type' => 'BOOLEAN',
                'default' => false
            ]
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('title');
        $this->forge->addKey('seflink');
        $this->forge->createTable('pages');
    }

    public function down()
    {
        $this->forge->dropTable('pages');
    }
}

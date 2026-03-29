<?php

namespace Modules\Pages\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

class CreatePagesTable extends Migration
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
            'creationDate' => [
                'type' => 'DATETIME',
                'null' => false,
                'default' => new RawSql('CURRENT_TIMESTAMP'),
            ],
            'isActive' => [
                'type' => 'TINYINT',
                'constraint' => '1',
                'null' => false,
            ],
            'inMenu' => [
                'type' => 'TINYINT',
                'constraint' => '1',
                'null' => false,
                'default' => 0,
            ],
            'changefreq' => [
                'type' => 'VARCHAR',
                'constraint' => '16',
                'null' => true,
                'default' => 'monthly',
            ],
            'priority' => [
                'type' => 'DECIMAL',
                'constraint' => '2,1',
                'null' => true,
                'default' => 0.5,
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('pages');
    }

    public function down()
    {
        $this->forge->dropTable('pages');
    }
}

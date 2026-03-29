<?php

namespace Modules\Blog\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

class CreateCommentsTable extends Migration
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
            'isApproved' => [
                'type' => 'TINYINT',
                'constraint' => '1',
                'null' => false,
                'default' => 1,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => false,
                'default' => new RawSql('CURRENT_TIMESTAMP'),
            ],
            'comFullName' => [
                'type' => 'VARCHAR',
                'constraint' => '255',
                'null' => false,
            ],
            'comEmail' => [
                'type' => 'VARCHAR',
                'constraint' => '255',
                'null' => false,
            ],
            'comMessage' => [
                'type' => 'LONGTEXT',
                'null' => false,
            ],
            'isThereAnReply' => [
                'type' => 'TINYINT',
                'constraint' => '1',
                'null' => false,
                'default' => 0,
            ],
            'parent_id' => [
                'type' => 'INT',
                'constraint' => '11',
                'unsigned' => true,
                'null' => true,
                'default' => null,
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('comments', true);
    }

    public function down()
    {
        $this->forge->dropTable('comments', true);
    }
}

<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

class Ci4msComments extends Migration
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
            'isApproved' => [
                'type' => 'BOOLEAN',
                'default'=>false
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'default' => new RawSql('CURRENT_TIMESTAMP')
            ],
            'comFullName' => [
                'type' => 'VARCHAR',
                'constraint' => 255
            ],
            'comEmail' => [
                'type' => 'VARCHAR',
                'constraint' => 255
            ],
            'comMessage' => [
                'type' => 'LONGTEXT'
            ],
            'isThereAnReply' => [
                'type' => 'BOOLEAN',
                'default' => false
            ],
            'parent_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'null'=>true
            ]
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('comFullName');
        $this->forge->addKey('comEmail');
        $this->forge->addForeignKey('blog_id', 'blog', 'id', 'CASCADE', 'CASCADE', 'comments_blog_id_fk');
        $this->forge->addForeignKey('parent_id', 'comments', 'id', 'CASCADE', 'CASCADE', 'comments_comments_id_fk');
        $this->forge->createTable( 'comments');
    }

    public function down()
    {
        $this->forge->dropTable( 'comments');
    }
}

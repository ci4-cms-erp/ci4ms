<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

class Pages extends Migration
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
            'title'=>[
                'type'=>'VARCHAR',
                'constraint'=>255,
                'null'=>true
            ],
            'content'=>[
                'type'=>'LONGTEXT',
                'null'=>true
            ],
            'seflink'=>[
                'type'=>'VARCHAR',
                'constraint'=>255,
                'null'=>true
            ],
            'creationDate'=>[
                'type'=>'DATETIME',
                'default'=>new RawSql('CURRENT_TIMESTAMP')
            ],
            'isActive'=>[
                'type'=>'TINYINT',
                'constraint'=>1,
                'null'=>true
            ],
            'seo'=>[
                'type'=>'LONGTEXT',
                'null'=>true
            ],
            'inMenu'=>[
                'type'=>'TINYINT',
                'constraint'=>1,
                'default'=>0
            ]
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('pages');
    }

    public function down()
    {
        $this->forge->dropTable('pages');
    }
}

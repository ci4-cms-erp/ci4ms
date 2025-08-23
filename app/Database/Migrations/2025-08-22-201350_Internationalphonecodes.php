<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class Internationalphonecodes extends Migration
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
            'code' => [
                'type' => 'VARCHAR',
                'constraint'=>255,
                'null'=>true
            ],
            'dial_code' => [
                'type' => 'VARCHAR',
                'constraint'=>255,
                'null'=>true
            ],
            'name' => [
                'type' => 'VARCHAR',
                'constraint'=>255,
                'null'=>true
            ]
        ]);
        $this->forge->addKey('id',true);
        $this->forge->createTable('international_phone_codes');
    }

    public function down()
    {
        $this->forge->dropTable('international_phone_codes');
    }
}

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
                'constraint'=>255
            ],
            'dial_code' => [
                'type' => 'VARCHAR',
                'constraint'=>255
            ],
            'name' => [
                'type' => 'VARCHAR',
                'constraint'=>255
            ]
        ]);
        $this->forge->addKey('id',true);
        $this->forge->addKey(['code','dial_code','name']);
        $this->forge->createTable('international_phone_codes');
    }

    public function down()
    {
        $this->forge->dropTable('international_phone_codes');
    }
}

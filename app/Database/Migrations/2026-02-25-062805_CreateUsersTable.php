<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

class CreateUsersTable extends Migration
{
    public function up()
    {
        $this->forge->addColumn('users',[
            'firstname' => [
                'type' => 'VARCHAR',
                'constraint' => '255',
                'null' => false,
            ],
            'surname' => [
                'type' => 'VARCHAR',
                'constraint' => '255',
                'null' => false,
            ],
            'profileIMG' => [
                'type' => 'TEXT',
                'null' => false,
                'default' => 'https://dummyimage.com/50x50/ced4da/6c757d.jpg',
            ],
            'who_created' => [
                'type' => 'INT',
                'constraint' => '11',
                'unsigned' => true,
                'null' => true,
                'default' => null,
            ],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('users', ['firstname', 'surname', 'profileIMG', 'who_created']);
    }
}

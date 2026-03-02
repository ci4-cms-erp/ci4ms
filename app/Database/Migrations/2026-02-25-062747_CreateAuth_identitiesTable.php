<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAuth_identitiesTable extends Migration
{
    public function up()
    {
        $this->forge->addColumn('auth_identities', [
            'who_banned' => [
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
        $this->forge->dropColumn('auth_identities', 'who_banned');
    }
}

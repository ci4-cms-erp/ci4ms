<?php

namespace Modules\Auth\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAuth_identitiesTable extends Migration
{
    public function up()
    {
        if (!$this->db->fieldExists('who_banned', 'auth_identities')) {
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
    }

    public function down()
    {
        if ($this->db->fieldExists('who_banned', 'auth_identities')) {
            $this->forge->dropColumn('auth_identities', 'who_banned');
        }
    }
}

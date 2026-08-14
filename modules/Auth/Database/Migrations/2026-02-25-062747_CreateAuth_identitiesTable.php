<?php

namespace Modules\Auth\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAuth_identitiesTable extends Migration
{
    public function up()
    {
        // fieldExists() results are cached on the connection and go stale if
        // the schema changes within this process (migrate:refresh /
        // rollback+migrate). Reset first so the guard doesn't lie.
        $this->db->resetDataCache();

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
        $this->db->resetDataCache();

        if ($this->db->fieldExists('who_banned', 'auth_identities')) {
            $this->forge->dropColumn('auth_identities', 'who_banned');
        }
    }
}

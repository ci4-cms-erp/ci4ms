<?php

namespace Modules\Auth\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAuth_identitiesTable extends Migration
{
    public function up()
    {
        // fieldExists() sonucu bağlantıda önbelleklenir ve şema bu süreç içinde
        // değişince bayat kalır (migrate:refresh / rollback+migrate). Guard yalan
        // söylemesin diye önce sıfırlanır.
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

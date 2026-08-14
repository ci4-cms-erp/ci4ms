<?php

namespace Modules\Users\Database\Migrations;

use CodeIgniter\Database\Migration;

class UsersAddColumns extends Migration
{
    public function up()
    {
        // The fieldExists() result is cached on the connection and goes stale
        // if the schema changes within this process (migrate:refresh / rollback+migrate).
        // It is reset first so the guard doesn't lie.
        $this->db->resetDataCache();

        if ($this->db->fieldExists('own_language', 'users')) {
            return;
        }

        $this->forge->addColumn(
            'users',
            [
                'own_language'=>[
                    'type'=>'VARCHAR',
                    'constraint' => 5,
                    'default' => 'en'
                ]
            ],
        );
    }

    public function down()
    {
        $this->db->resetDataCache();

        $this->forge->dropColumn('users',[
            'own_language'
        ]);
    }
}

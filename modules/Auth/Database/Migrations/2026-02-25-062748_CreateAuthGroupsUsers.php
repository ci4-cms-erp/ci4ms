<?php

namespace Modules\Auth\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAuthGroupsUsers extends Migration
{
    public function up()
    {
        // fieldExists() sonucu bağlantıda önbelleklenir ve şema bu süreç içinde
        // değişince bayat kalır (migrate:refresh / rollback+migrate). Guard yalan
        // söylemesin diye önce sıfırlanır.
        $this->db->resetDataCache();

        $columns = [
            'description' => [
                'type' => 'VARCHAR',
                'constraint' => '255',
                'null' => true,
                'default' => null,
            ],
            'redirect' => [
                'type' => 'VARCHAR',
                'constraint' => '255',
                'null' => true,
                'default' => null,
            ],
            'who_created' => [
                'type' => 'INT',
                'constraint' => '11',
                'unsigned' => true,
                'null' => true,
                'default' => null,
            ]
        ];

        foreach ($columns as $name => $def) {
            if (!$this->db->fieldExists($name, 'auth_groups_users')) {
                $this->forge->addColumn('auth_groups_users', [$name => $def]);
            }
        }
    }

    public function down()
    {
        $this->db->resetDataCache();

        foreach (['description', 'redirect', 'who_created'] as $col) {
            if ($this->db->fieldExists($col, 'auth_groups_users')) {
                $this->forge->dropColumn('auth_groups_users', $col);
            }
        }
    }
}

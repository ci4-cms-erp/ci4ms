<?php

namespace Modules\Auth\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateUsersTable extends Migration
{
    public function up()
    {
        // fieldExists() sonucu bağlantıda önbelleklenir ve şema bu süreç içinde
        // değişince bayat kalır (migrate:refresh / rollback+migrate). Guard yalan
        // söylemesin diye önce sıfırlanır.
        $this->db->resetDataCache();

        $columns = [
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
                'type' => 'VARCHAR',
                'constraint' => '255',
                'null' => true,
                'default' => 'https://dummyimage.com/50x50/ced4da/6c757d.jpg',
            ],
            'who_created' => [
                'type' => 'INT',
                'constraint' => '11',
                'unsigned' => true,
                'null' => true,
                'default' => null,
            ],
        ];

        foreach ($columns as $name => $def) {
            if (!$this->db->fieldExists($name, 'users')) {
                $this->forge->addColumn('users', [$name => $def]);
            }
        }
    }

    public function down()
    {
        $this->db->resetDataCache();

        foreach (['firstname', 'surname', 'profileIMG', 'who_created'] as $col) {
            try {
                if ($this->db->fieldExists($col, 'users')) {
                    $this->forge->dropColumn('users', $col);
                }
            } catch (\Exception $e) {
                // Ignore drop errors during tests
            }
        }
    }
}

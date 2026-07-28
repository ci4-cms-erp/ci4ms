<?php

namespace Modules\Users\Database\Migrations;

use CodeIgniter\Database\Migration;

class UsersAddColumns extends Migration
{
    public function up()
    {
        // fieldExists() sonucu bağlantıda önbelleklenir ve şema bu süreç içinde
        // değişince bayat kalır (migrate:refresh / rollback+migrate). Guard yalan
        // söylemesin diye önce sıfırlanır.
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

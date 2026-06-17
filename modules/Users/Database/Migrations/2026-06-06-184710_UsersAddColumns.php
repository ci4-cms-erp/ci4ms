<?php

namespace Modules\Users\Database\Migrations;

use CodeIgniter\Database\Migration;

class UsersAddColumns extends Migration
{
    public function up()
    {
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
        $this->forge->dropColumn('users',[
            'own_language'
        ]);
    }
}

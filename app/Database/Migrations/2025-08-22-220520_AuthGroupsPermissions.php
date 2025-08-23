<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

class AuthGroupsPermissions extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'auto_increment' => true
            ],
            'group_id'=>[
                'type'=>'INT',
                'constraint'=>11,
                'unsigned'=>true
            ],
            'page_id'=>[
                'type'=>'INT',
                'constraint'=>11,
                'unsigned'=>true
            ],
            'create_r'=>[
                'type'=>'TINYINT',
                'constraint'=>1
            ],
            'update_r'=>[
                'type'=>'TINYINT',
                'constraint'=>1
            ],
            'read_r'=>[
                'type'=>'TINYINT',
                'constraint'=>1
            ],
            'delete_r'=>[
                'type'=>'TINYINT',
                'constraint'=>1
            ],
            'who_perm'=>[
                'type'=>'INT',
                'constraint'=>11,
                'unsigned'=>true,
                'null'=>true
            ],
            'created_at'=>[
                'type'=>'DATETIME',
                'default'=>new RawSql('CURRENT_TIMESTAMP')
            ]
        ]);
        $this->forge->addKey('id',true);
        $this->forge->addForeignKey('group_id','ci4ms_auth_groups','id','CASCADE','CASCADE');
        $this->forge->addForeignKey('page_id','ci4ms_auth_permissions_pages','id','CASCADE','CASCADE');
        $this->forge->addForeignKey('who_perm','users','id','SET NULL','SET NULL');
        $this->forge->createTable('auth_groups_permissions');
    }

    public function down()
    {
        $this->forge->dropTable('auth_groups_permissions');
    }
}

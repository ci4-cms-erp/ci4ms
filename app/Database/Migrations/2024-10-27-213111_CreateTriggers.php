<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateTriggers extends Migration
{
    public function up()
    {
        $prefix='';
        if(!empty(getenv('database.default.DBPrefix'))) $prefix=getenv('database.default.DBPrefix');
        $this->db->query("CREATE TRIGGER superuser_perms_add AFTER INSERT ON {$prefix}auth_permissions_pages FOR EACH ROW INSERT INTO  {$prefix}auth_groups_permissions (group_id,page_id, create_r,update_r,delete_r,read_r,who_perm) VALUES (1,NEW.id,1,1,1,1,1)");
    }

    public function down()
    {
        $this->db->query("DROP TRIGGER IF EXISTS superuser_perms_add");
    }
}

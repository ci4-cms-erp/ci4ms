<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateSuperUserTrigger extends Migration
{
    public function up()
    {
        $this->db->query('CREATE TRIGGER superuser_perms_add AFTER INSERT ON '.getenv('database.default.DBPrefix').'auth_permissions_pages FOR EACH ROW INSERT INTO  '.getenv('database.default.DBPrefix').'auth_groups_permissions (group_id,page_id, create_r,update_r,delete_r,read_r,who_perm) VALUES (1,NEW.id,1,1,1,1,1)');
    }

    public function down()
    {
        $this->db->query('DROP TRIGGER IF EXISTS superuser_permissions_add');
    }
}

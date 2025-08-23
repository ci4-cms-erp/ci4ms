<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class FkColumns extends Migration
{
    public function up()
    {
        $this->db->query('ALTER TABLE '.getenv('database.default.DBPrefix').'auth_groups ADD CONSTRAINT '.getenv('database.default.DBPrefix').'auth_groups_ibfk_1 FOREIGN KEY (who_created) REFERENCES '.getenv('database.default.DBPrefix').'users(id) ON DELETE SET NULL ON UPDATE CASCADE');
    }

    public function down()
    {
        $this->db->query('ALTER TABLE '.getenv('database.default.DBPrefix').'auth_groups DROP FOREIGN KEY '.getenv('database.default.DBPrefix').'auth_groups_ibfk_1');
    }
}

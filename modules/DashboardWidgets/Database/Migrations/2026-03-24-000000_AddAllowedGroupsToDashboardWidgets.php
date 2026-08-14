<?php

namespace Modules\DashboardWidgets\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddAllowedGroupsToDashboardWidgets extends Migration
{
    public function up()
    {
        // fieldExists() caches its result on the connection and goes stale if
        // the schema changes within this process (migrate:refresh /
        // rollback+migrate). Reset it first so the guard doesn't lie.
        $this->db->resetDataCache();

        if ($this->db->fieldExists('allowed_groups', 'dashboard_widgets')) {
            return;
        }

        $this->forge->addColumn('dashboard_widgets', [
            'allowed_groups' => [
                'type' => 'TEXT',
                'null' => true,
                'comment' => 'JSON array of group names allowed to see this widget. Empty means everyone.',
            ],
        ]);
    }

    public function down()
    {
        $this->db->resetDataCache();

        $this->forge->dropColumn('dashboard_widgets', 'allowed_groups');
    }
}

<?php

namespace Modules\DashboardWidgets\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddAllowedGroupsToDashboardWidgets extends Migration
{
    public function up()
    {
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
        $this->forge->dropColumn('dashboard_widgets', 'allowed_groups');
    }
}

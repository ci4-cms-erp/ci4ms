<?php

namespace Modules\DashboardWidgets\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddAllowedGroupsToDashboardWidgets extends Migration
{
    public function up()
    {
        // fieldExists() sonucu bağlantıda önbelleklenir ve şema bu süreç içinde
        // değişince bayat kalır (migrate:refresh / rollback+migrate). Guard yalan
        // söylemesin diye önce sıfırlanır.
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

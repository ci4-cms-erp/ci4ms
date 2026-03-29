<?php

namespace Modules\DashboardWidgets\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

class CreateDashboardWidgetsTables extends Migration
{
    public function up()
    {
        // ── Widget definitions ──
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'slug' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'unique'     => true,
            ],
            'title' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
            ],
            'description' => [
                'type'       => 'VARCHAR',
                'constraint' => 500,
                'null'       => true,
            ],
            'type' => [
                'type'       => 'VARCHAR',
                'constraint' => 30,
                'default'    => 'stat',
                'comment'    => 'stat, chart, table, list, html',
            ],
            'icon' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'default'    => 'fas fa-chart-bar',
            ],
            'color' => [
                'type'       => 'VARCHAR',
                'constraint' => 30,
                'default'    => 'primary',
            ],
            'data_source' => [
                'type'       => 'VARCHAR',
                'constraint' => 500,
                'null'       => true,
                'comment'    => 'Class::method or SQL query identifier',
            ],
            'default_size' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'default'    => 'col-lg-3',
            ],
            'refresh_seconds' => [
                'type'     => 'INT',
                'unsigned' => true,
                'default'  => 0,
                'comment'  => '0 = no auto-refresh',
            ],
            'is_system' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 0,
                'comment'    => '1 = built-in, cannot delete',
            ],
            'is_active' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 1,
            ],
            'created_at' => [
                'type'    => 'DATETIME',
                'default' => new RawSql('CURRENT_TIMESTAMP'),
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('dashboard_widgets', true);

        // ── User widget preferences ──
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'user_id' => [
                'type'     => 'INT',
                'unsigned' => true,
            ],
            'widget_id' => [
                'type'     => 'INT',
                'unsigned' => true,
            ],
            'position' => [
                'type'     => 'INT',
                'unsigned' => true,
                'default'  => 0,
            ],
            'size' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'default'    => 'col-lg-3',
            ],
            'is_visible' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 1,
            ],
            'config_json' => [
                'type' => 'TEXT',
                'null' => true,
                'comment' => 'User-specific widget config (colors, date range, etc.)',
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['user_id', 'widget_id'], false, true);
        $this->forge->addForeignKey('widget_id', 'dashboard_widgets', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('user_widget_preferences', true);
    }

    public function down()
    {
        $this->forge->dropTable('user_widget_preferences', true);
        $this->forge->dropTable('dashboard_widgets', true);
    }
}

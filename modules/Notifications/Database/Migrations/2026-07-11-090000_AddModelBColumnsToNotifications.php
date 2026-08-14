<?php

namespace Modules\Notifications\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Extends the `notifications` table additively (without DROP) for Model B (global
 * record + per-user read status): severity/target_type/target_value/channel columns,
 * making legacy user_id nullable, and the (target_type,target_value) index.
 *
 * up() is guarded by fieldExists/index checks at every step (protects existing data and
 * re-running). down() exists only for file completeness; rollback is NOT RUN MANUALLY.
 */
class AddModelBColumnsToNotifications extends Migration
{
    public function up()
    {
        // The fieldExists() result is cached on the connection and goes stale if the
        // schema changes within this process (migrate:refresh / rollback+migrate). Reset
        // first so the guard doesn't lie.
        $this->db->resetDataCache();

        $table = 'notifications';

        if (! $this->db->fieldExists('severity', $table)) {
            $this->forge->addColumn($table, [
                'severity' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 16,
                    'null'       => false,
                    'default'    => 'info',
                    'after'      => 'type',
                ],
            ]);
        }

        if (! $this->db->fieldExists('target_type', $table)) {
            $this->forge->addColumn($table, [
                'target_type' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 16,
                    'null'       => false,
                    'default'    => 'broadcast',
                    'after'      => 'severity',
                ],
            ]);
        }

        if (! $this->db->fieldExists('target_value', $table)) {
            $this->forge->addColumn($table, [
                'target_value' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 255,
                    'null'       => true,
                    'default'    => null,
                    'after'      => 'target_type',
                ],
            ]);
        }

        if (! $this->db->fieldExists('channel', $table)) {
            $this->forge->addColumn($table, [
                'channel' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 32,
                    'null'       => false,
                    'default'    => 'inapp',
                    'after'      => 'url',
                ],
            ]);
        }

        // Legacy user_id is no longer required (Model B rows write user_id=null).
        $this->forge->modifyColumn($table, [
            'user_id' => [
                'name'       => 'user_id',
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
                'default'    => null,
            ],
        ]);

        // (target_type, target_value) index — added via a raw ALTER since Forge's
        // addColumn doesn't add an index; guarded against re-running with a SHOW INDEX check.
        $prefixed = $this->db->prefixTable($table);
        $existing = $this->db->query("SHOW INDEX FROM `{$prefixed}` WHERE Key_name = 'notif_target'")->getResultArray();
        if ($existing === []) {
            $this->db->query("ALTER TABLE `{$prefixed}` ADD INDEX notif_target (target_type, target_value)");
        }
    }

    public function down()
    {
        $this->db->resetDataCache();

        $table = 'notifications';

        foreach (['severity', 'target_type', 'target_value', 'channel'] as $column) {
            if ($this->db->fieldExists($column, $table)) {
                $this->forge->dropColumn($table, $column);
            }
        }
    }
}

<?php

namespace Modules\Notifications\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Adds an additive (no-DROP) `created_by` column to the `notifications` table
 * for accountability tracing.
 *
 * MEANING: the identity of the user who PRODUCED the row — not its recipient.
 * Model B rows are global (`user_id` is always NULL, the target is carried via
 * `target_type`/`target_value`), so a separate column is needed to answer "who
 * sent it". It is only populated for human-authored broadcasts (PHASE 3
 * composer); for event/CLI-originated notifications it stays NULL, and this
 * distinction is deliberate: NULL = "produced by the system".
 * The ONLY place that writes it: InAppChannel::buildRow(); the value ALWAYS
 * comes from `auth()->id()` on the server, NEVER read from the client.
 *
 * NO FOREIGN KEY — deliberately: this is an audit trail. A CASCADE FK to
 * `users` would delete a user's sent notifications along with their account,
 * while SET NULL would destroy the trail itself; either would defeat the
 * purpose of having a trail. After a user is deleted, the remaining identity
 * is preserved as an unresolved reference (the read path never looks at this
 * column, so there is no JOIN cost either).
 *
 * up() is fieldExists-guarded (safe to re-run), and `after` is only given once
 * the anchor column's PRESENCE is confirmed: the module folder may also be
 * dropped onto a database where the PHASE 2 migration has not run yet. down()
 * exists only for file integrity: the column carries data, so an automatic
 * DROP would be data loss.
 */
class AddCreatedByToNotifications extends Migration
{
    public function up()
    {
        // The fieldExists() result is cached on the connection and goes stale if
        // the schema changes within this process (migrate:refresh / rollback+migrate).
        // Reset it first so the guard doesn't lie.
        $this->db->resetDataCache();

        $table = 'notifications';

        if ($this->db->fieldExists('created_by', $table)) {
            return;
        }

        $definition = [
            'type'       => 'INT',
            'constraint' => 11,
            'unsigned'   => true,
            'null'       => true,
            'default'    => null,
        ];

        // `after` is only given if the anchor column actually exists; otherwise
        // MySQL rejects the ALTER entirely with "Unknown column in 'notifications'".
        if ($this->db->fieldExists('exclude_users', $table)) {
            $definition['after'] = 'exclude_users';
        }

        $this->forge->addColumn($table, ['created_by' => $definition]);
    }

    public function down()
    {
        $this->db->resetDataCache();

        // Rollback is done MANUALLY — this column carries audit data, so an automatic DROP would be data loss.
        // Manual step (if needed):
        //   ALTER TABLE `{prefix}notifications` DROP COLUMN `created_by`;
    }
}

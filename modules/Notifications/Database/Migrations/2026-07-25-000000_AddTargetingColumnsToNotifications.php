<?php

namespace Modules\Notifications\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Adds the `exclude_users` column to the `notifications` table additively (without
 * DROP) for rich targeting.
 *
 * FORMAT — SENTINEL-WRAPPED CSV: the value is always comma-wrapped at both ends
 * (`,5,12,`), or NULL if there are no excluded users. Thanks to the wrapping commas,
 * the `exclude_users NOT LIKE '%,{userId},%'` check on the read path NEVER confuses
 * `,1,` with `,12,`. A JSON function is NOT USED: plain text + LIKE was deliberately
 * chosen to stay independent of MySQL/MariaDB version differences.
 * Written by: InAppChannel::buildRow(); the ONLY reader: Notifier::applyRelevance().
 *
 * up() is guarded by fieldExists (can be re-run). down() exists only for file
 * completeness: the column carries data, an automatic DROP would be data loss.
 */
class AddTargetingColumnsToNotifications extends Migration
{
    public function up()
    {
        // The fieldExists() result is cached on the connection and goes stale if the
        // schema changes within this process (migrate:refresh / rollback+migrate). Reset
        // first so the guard doesn't lie.
        $this->db->resetDataCache();

        $table = 'notifications';

        if (! $this->db->fieldExists('exclude_users', $table)) {
            $this->forge->addColumn($table, [
                'exclude_users' => [
                    'type'    => 'TEXT',
                    'null'    => true,
                    'default' => null,
                    'after'   => 'target_value',
                ],
            ]);
        }
    }

    public function down()
    {
        $this->db->resetDataCache();

        // Rollback is done MANUALLY — this column carries data, an automatic DROP would be data loss.
        // Manual step (if needed):
        //   ALTER TABLE `{prefix}notifications` DROP COLUMN `exclude_users`;
    }
}

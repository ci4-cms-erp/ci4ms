<?php

namespace Modules\Notifications\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * User notification preferences (opt-out) table: one row per (user_id, type, channel).
 *
 * Because Model B keeps a global row (the same row belongs to everyone), the
 * preference cannot be applied at SEND time; the filter is applied at READ time
 * via the LEFT JOIN + `p.id IS NULL` anti-join inside `Notifier::applyRelevance()`.
 * That is why only MUTE (enabled = 0) rows are meaningful here; the absence of a
 * row means "notifications on".
 *
 * `type` is a type or a type PREFIX: an 'audit' row mutes both 'audit' and
 * 'audit.login' records (in the JOIN: `n.type LIKE CONCAT(p.type, '.%')`).
 * `channel = '*'` covers all channels. `severity = 'critical'` rows never enter
 * the JOIN at all, meaning critical notifications cannot be muted.
 *
 * The INDEXes exist for the read path: the JOIN narrows first by `user_id`
 * (notif_pref_lookup), while the UNIQUE index prevents duplicate writes of the
 * same triple. The FK is CASCADE: deleting a user deletes their preferences too.
 *
 * up() is tableExists-guarded (safe to re-run). down() exists only for file
 * integrity: the table carries user data, so an automatic DROP would be data loss.
 */
class CreateNotificationPreferencesTable extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('notification_preferences')) {
            return;
        }

        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
                'null'           => false,
            ],
            'user_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => false,
            ],
            // Full type ('audit.login') or a type prefix ('audit').
            'type' => [
                'type'       => 'VARCHAR',
                'constraint' => 64,
                'null'       => false,
            ],
            // '*' = all channels; otherwise matches notifications.channel exactly.
            'channel' => [
                'type'       => 'VARCHAR',
                'constraint' => 32,
                'null'       => false,
                'default'    => '*',
            ],
            // 0 = muted (filtered out on the read path). 1 = default, the row exists purely informationally.
            'enabled' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'null'       => false,
                'default'    => 1,
            ],
            'created_at' => [
                'type'    => 'DATETIME',
                'null'    => true,
                'default' => null,
            ],
            'updated_at' => [
                'type'    => 'DATETIME',
                'null'    => true,
                'default' => null,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey(['user_id', 'type', 'channel'], false, true, 'notif_pref_unique');
        $this->forge->addKey(['user_id', 'enabled'], false, false, 'notif_pref_lookup');

        // If the Shield `users` table doesn't exist (module standalone, Shield not
        // migrated), create without the FK: the table still works, cleanup stays on the app side.
        if ($this->db->tableExists('users')) {
            $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        } else {
            log_message('warning', 'notification_preferences: `users` tablosu yok, FK olmadan oluşturuldu.');
        }

        $this->forge->createTable('notification_preferences', true, ['ENGINE' => 'InnoDB']);
    }

    public function down()
    {
        // Rollback is done MANUALLY — the table carries user preferences, so an automatic DROP would be data loss.
        // Manual step (if needed):
        //   DROP TABLE `{prefix}notification_preferences`;
    }
}

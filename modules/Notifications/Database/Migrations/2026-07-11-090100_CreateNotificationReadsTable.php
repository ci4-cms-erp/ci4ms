<?php

namespace Modules\Notifications\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Model B read-status table: at most one row per (notification_id, user_id).
 *
 * A row is written here when a notification is read by a relevant user; the absence
 * of a row means "unread". UNIQUE(notification_id,user_id) prevents a duplicate read
 * record (markAll is idempotent via INSERT IGNORE). FKs are CASCADE: when a
 * notification or user is deleted, the related read records are deleted too.
 */
class CreateNotificationReadsTable extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('notification_reads')) {
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
            'notification_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => false,
            ],
            'user_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => false,
            ],
            'read_at' => [
                'type' => 'DATETIME',
                'null' => false,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey(['notification_id', 'user_id'], false, true); // UNIQUE
        $this->forge->addKey('user_id');
        $this->forge->addForeignKey('notification_id', 'notifications', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('notification_reads', true, ['ENGINE' => 'InnoDB']);
    }

    public function down()
    {
        $this->forge->dropTable('notification_reads', true);
    }
}

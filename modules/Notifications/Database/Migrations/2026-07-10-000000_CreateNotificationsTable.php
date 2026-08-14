<?php

namespace Modules\Notifications\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateNotificationsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
                'null'           => false,
            ],
            // Recipient user. Role targets are expanded into these rows at send time.
            'user_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => false,
            ],
            // Machine-readable event type: 'update.available', 'auth.failed_login', 'comment.new' ...
            'type' => [
                'type'       => 'VARCHAR',
                'constraint' => 64,
                'null'       => false,
            ],
            'title' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => false,
            ],
            'body' => [
                'type'    => 'TEXT',
                'null'    => true,
                'default' => null,
            ],
            // Target navigated to on click. Validated on the Notifier side when written
            // (site-internal '/...' or http(s) only); escaped with esc() when displayed.
            'url' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
                'default'    => null,
            ],
            // NULL = unread. No separate flag is kept; the date carries both the status and the time.
            'read_at' => [
                'type'    => 'DATETIME',
                'null'    => true,
                'default' => null,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => false,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey(['user_id', 'read_at']); // for the unread badge + user list
        $this->forge->addKey('created_at');
        $this->forge->createTable('notifications', true);
    }

    public function down()
    {
        $this->forge->dropTable('notifications', true);
    }
}

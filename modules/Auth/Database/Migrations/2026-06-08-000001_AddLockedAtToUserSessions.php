<?php

declare(strict_types=1);

namespace Modules\Auth\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddLockedAtToUserSessions extends Migration
{
    public function up(): void
    {
        // fieldExists() results are cached on the connection and go stale if
        // the schema changes within this process (migrate:refresh /
        // rollback+migrate). Reset first so the guard doesn't lie.
        $this->db->resetDataCache();

        if ($this->db->fieldExists('locked_at', 'user_sessions')) {
            return;
        }

        // locked_at NULL means the session is open; if set, it's locked as of that timestamp.
        $this->forge->addColumn('user_sessions', [
            'locked_at' => [
                'type'    => 'DATETIME',
                'null'    => true,
                'default' => null,
                'after'   => 'last_activity',
            ],
        ]);
    }

    public function down(): void
    {
        $this->db->resetDataCache();

        $this->forge->dropColumn('user_sessions', 'locked_at');
    }
}

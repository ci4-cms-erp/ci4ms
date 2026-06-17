<?php

declare(strict_types=1);

namespace Modules\Auth\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddLockedAtToUserSessions extends Migration
{
    public function up(): void
    {
        // locked_at NULL ise oturum açık; dolu ise o timestamp'ten itibaren kilitli.
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
        $this->forge->dropColumn('user_sessions', 'locked_at');
    }
}

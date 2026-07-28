<?php

declare(strict_types=1);

namespace Modules\Auth\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddLockedAtToUserSessions extends Migration
{
    public function up(): void
    {
        // fieldExists() sonucu bağlantıda önbelleklenir ve şema bu süreç içinde
        // değişince bayat kalır (migrate:refresh / rollback+migrate). Guard yalan
        // söylemesin diye önce sıfırlanır.
        $this->db->resetDataCache();

        if ($this->db->fieldExists('locked_at', 'user_sessions')) {
            return;
        }

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
        $this->db->resetDataCache();

        $this->forge->dropColumn('user_sessions', 'locked_at');
    }
}

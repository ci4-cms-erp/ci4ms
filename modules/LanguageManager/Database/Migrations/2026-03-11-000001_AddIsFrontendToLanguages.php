<?php

declare(strict_types=1);

namespace Modules\LanguageManager\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Adds `is_frontend` column to the `languages` table.
 * This flag controls whether a language is available on the public frontend.
 */
class AddIsFrontendToLanguages extends Migration
{
    public function up(): void
    {
        // fieldExists() sonucu bağlantıda önbelleklenir ve şema bu süreç içinde
        // değişince bayat kalır (migrate:refresh / rollback+migrate). Guard yalan
        // söylemesin diye önce sıfırlanır.
        $this->db->resetDataCache();

        if ($this->db->fieldExists('is_frontend', 'languages')) {
            return;
        }

        $this->forge->addColumn('languages', [
            'is_frontend' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 1,
                'null'       => false,
                'after'      => 'is_active',
                'comment'    => '1 = available on frontend for visitors',
            ],
        ]);
    }

    public function down(): void
    {
        $this->db->resetDataCache();

        $this->forge->dropColumn('languages', 'is_frontend');
    }
}

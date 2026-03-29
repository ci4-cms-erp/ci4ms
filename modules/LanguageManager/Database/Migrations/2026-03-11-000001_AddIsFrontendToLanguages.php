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
        $this->forge->dropColumn('languages', 'is_frontend');
    }
}

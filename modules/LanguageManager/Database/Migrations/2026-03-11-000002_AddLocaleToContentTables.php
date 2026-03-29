<?php

declare(strict_types=1);

namespace Modules\LanguageManager\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Adds `locale` column to content tables (`blog`, `pages`).
 * NULL = show in all languages (backward compatible with single-language mode).
 */
class AddLocaleToContentTables extends Migration
{
    private array $tables = ['blog', 'pages'];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if ($this->db->tableExists($table) && !$this->db->fieldExists('locale', $table)) {
                $this->forge->addColumn($table, [
                    'locale' => [
                        'type'       => 'VARCHAR',
                        'constraint' => 10,
                        'null'       => true,
                        'default'    => null,
                        'after'      => 'id',
                        'comment'    => 'ISO 639 code, NULL = all languages',
                    ],
                ]);

                // Add index for faster locale-based queries
                $this->db->query("ALTER TABLE `{$this->db->DBPrefix}{$table}` ADD INDEX `idx_{$table}_locale` (`locale`)");
            }
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if ($this->db->tableExists($table) && $this->db->fieldExists('locale', $table)) {
                $this->forge->dropColumn($table, 'locale');
            }
        }
    }
}

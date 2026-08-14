<?php

declare(strict_types=1);

namespace Modules\MigrationManager\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * `migration_runs.run_source` — distinguishes whether a run was started
 * from the CLI or from the web panel (PHASE 2 — "CRITICAL CONSTRAINT"
 * decision, for the audit row that the `ci4ms:migrate` command will write
 * to `migration_runs`).
 *
 * PROBLEM: `run_by` is a NULLABLE FK
 * (`2026-08-02-151717_CreateMigrationRunsTable.php:73-79`) and today the UI
 * ALWAYS renders a `run_by IS NULL` row as "Deleted user"
 * (`Views/list.php:225` server-side row, `:517` DataTables JS render; lang
 * key `MigrationManager.deletedUser`,
 * `Language/en/MigrationManager.php:52`, `Language/tr/MigrationManager.php:52`).
 * When `php spark ci4ms:migrate` runs from the CLI there's no session, so
 * `run_by` would also be null — but that means "run from the command line",
 * NOT "deleted user". `run_by IS NULL` alone CANNOT DISTINGUISH these two
 * cases; hence a separate column is added.
 *
 * `DEFAULT 'web'` IS DELIBERATE: the existing
 * `Modules\MigrationManager\Controllers\MigrationManager::recordRun()`
 * (`Controllers/MigrationManager.php:432-445`) keeps calling
 * `$this->commonModel->create('migration_runs', [...])` with no knowledge
 * of this column — since `run_source` isn't supplied on INSERT, MySQL
 * applies its default and the row automatically gets `'web'`. The web flow
 * keeps behaving correctly with ZERO code changes. The new CLI audit write
 * (out of scope, `modules/Backend/Commands/Ci4msMigrate.php`) will pass
 * `run_source => 'cli'` explicitly.
 *
 * The existing `2026-08-02-151717_CreateMigrationRunsTable.php` was NOT
 * MODIFIED — that migration may already be applied in other environments;
 * that's why the ALTER is done in a separate migration. Style/guard
 * reference:
 * `modules/Auth/Database/Migrations/2026-06-08-000001_AddLockedAtToUserSessions.php`
 * (fieldExists guard + resetDataCache + `after` positioning).
 */
class AddRunSourceToMigrationRuns extends Migration
{
    public function up(): void
    {
        // fieldExists() results are cached on the connection and go stale if
        // the schema changes within this process (migrate:refresh /
        // rollback+migrate). Reset first so the guard doesn't lie.
        $this->db->resetDataCache();

        if ($this->db->fieldExists('run_source', 'migration_runs')) {
            return;
        }

        $this->forge->addColumn('migration_runs', [
            'run_source' => [
                'type'       => 'ENUM',
                'constraint' => ['web', 'cli'],
                'null'       => false,
                'default'    => 'web',
                'after'      => 'run_by',
            ],
        ]);
    }

    public function down(): void
    {
        $this->db->resetDataCache();

        $this->forge->dropColumn('migration_runs', 'run_source');
    }
}

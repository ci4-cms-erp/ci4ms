<?php

namespace Modules\MigrationManager\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

/**
 * Audit table: 1 row per migration/seed run ATTEMPT (not per migration
 * file, but per run action — see DECISION-1,
 * .ci4ms/plans/migration-manager/context.md:588-604).
 *
 * FK `run_by` → `users.id` is guarded by `tableExists('users')` BUT is NOT
 * SWALLOWED by a try/catch: the try/catch-swallowing anti-pattern in
 * `modules/Backend/Database/Migrations/2026-02-25-062806_AddForeignKeys.php`
 * is DELIBERATELY not repeated here (none of that file's 14 FKs actually
 * got created on the live DB, see DECISION-1-ADDENDUM).
 */
class CreateMigrationRunsTable extends Migration
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
            'kind' => [
                'type'       => 'ENUM',
                'constraint' => ['migration', 'seed'],
                'null'       => false,
            ],
            // migration: a namespace like 'Modules\Blog'; seed: FQCN.
            'target' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => false,
            ],
            'status' => [
                'type'       => 'ENUM',
                'constraint' => ['success', 'failed'],
                'null'       => false,
            ],
            'applied_count' => [
                'type'       => 'SMALLINT',
                'constraint' => 5,
                'unsigned'   => true,
                'null'       => false,
                'default'    => 0,
            ],
            'batch' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
                'default'    => null,
            ],
            // JSON: the list of applied version+class pairs OR an error message.
            'message' => [
                'type'    => 'TEXT',
                'null'    => true,
                'default' => null,
            ],
            'duration_ms' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => false,
                'default'    => 0,
            ],
            'run_by' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
                'default'    => null,
            ],
            'ip' => [
                'type'       => 'VARCHAR',
                'constraint' => 45,
                'null'       => true,
                'default'    => null,
            ],
            'created_at' => [
                'type'    => 'DATETIME',
                'null'    => false,
                'default' => new RawSql('CURRENT_TIMESTAMP'),
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey(['target', 'kind', 'created_at'], false, false, 'idx_target_kind_created');
        $this->forge->addKey(['kind', 'status', 'created_at'], false, false, 'idx_kind_status_created');

        // If the `users` table doesn't exist (module standalone, Shield not migrated), create without an FK.
        if ($this->db->tableExists('users')) {
            $this->forge->addForeignKey('run_by', 'users', 'id', 'CASCADE', 'SET NULL');
        } else {
            log_message('warning', 'migration_runs: `users` tablosu yok, FK olmadan oluşturuldu.');
        }

        $this->forge->createTable('migration_runs', true);
    }

    public function down()
    {
        $this->forge->dropTable('migration_runs', true);
    }
}

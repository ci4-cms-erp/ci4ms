<?php

declare(strict_types=1);

namespace Modules\FixtureModule\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Test-only migration fixture for ModuleInstallerNamespaceTest.
 *
 * Lives under tests/_support/ (never modules/) and is wired to the
 * `Modules\FixtureModule` PSR-4 prefix at test runtime via
 * Config\Services::autoloader()->addNamespace(), so it is discoverable by
 * MigrationRunner::findNamespaceMigrations() without touching the real
 * modules/ directory.
 */
class CreateFixtureTable extends Migration
{
    protected $DBGroup = 'tests';

    public function up(): void
    {
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('module_installer_fixture', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('module_installer_fixture', true);
    }
}

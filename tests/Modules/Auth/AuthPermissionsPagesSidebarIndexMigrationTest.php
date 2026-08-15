<?php

declare(strict_types=1);

namespace Tests\Modules\Auth;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Modules\Auth\Database\Migrations\AddSidebarIndexToAuthPermissionsPages;

/**
 * Tests AddSidebarIndexToAuthPermissionsPages (phase-4 DB item): the composite
 * index for BaseController::generateSidebar()'s query. Purely additive, so the
 * only behaviours to lock are: up() creates it, down() removes it, and both
 * are idempotent.
 *
 * Same $migrate=false / direct up()-down() pattern and ci4ms_test safety guard
 * as AuthPermissionsPagesUniqueKeyMigrationTest. tearDown() drops the index so
 * this marginal, cache-fronted index never lingers in the shared test schema.
 *
 * @internal
 */
final class AuthPermissionsPagesSidebarIndexMigrationTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $refresh   = false;
    protected $migrate   = false;
    protected $namespace = null;

    private const INDEX = 'idx_perm_pages_sidebar';

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertStringContainsString(
            'ci4ms_test',
            (string) \Config\Database::connect()->database,
            'Refusing to run: the active connection is not pointed at the test database.',
        );

        // Migration files carry a timestamp prefix, so the class is not PSR-4
        // autoloadable by name; require it (discovered via glob) before use.
        if (! class_exists(AddSidebarIndexToAuthPermissionsPages::class, false)) {
            $matches = glob(ROOTPATH . 'modules/Auth/Database/Migrations/*_AddSidebarIndexToAuthPermissionsPages.php');
            $this->assertNotEmpty($matches, 'Sidebar index migration file not found.');
            require_once $matches[0];
        }
    }

    protected function tearDown(): void
    {
        (new AddSidebarIndexToAuthPermissionsPages())->down();
        parent::tearDown();
    }

    private function indexExists(): bool
    {
        foreach ($this->db->getIndexData('auth_permissions_pages') as $index) {
            if ($index->name === self::INDEX) {
                return true;
            }
        }

        return false;
    }

    public function testUpAddsIndexAndDownRemovesIt(): void
    {
        $migration = new AddSidebarIndexToAuthPermissionsPages();

        $migration->down();
        $this->assertFalse($this->indexExists(), 'precondition: index should not exist at the start');

        $migration->up();
        $this->assertTrue($this->indexExists(), 'up() must create the sidebar index');

        $migration->down();
        $this->assertFalse($this->indexExists(), 'down() must remove the sidebar index');
    }

    public function testUpAndDownAreIdempotent(): void
    {
        $migration = new AddSidebarIndexToAuthPermissionsPages();

        $migration->up();
        $migration->up(); // second up() is a no-op, not a duplicate-key error
        $this->assertTrue($this->indexExists());

        $migration->down();
        $migration->down(); // second down() is a no-op
        $this->assertFalse($this->indexExists());
    }
}

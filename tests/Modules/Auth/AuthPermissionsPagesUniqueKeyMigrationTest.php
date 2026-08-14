<?php

declare(strict_types=1);

namespace Tests\Modules\Auth;

use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Modules\Auth\Database\Migrations\AddUniqueKeyToAuthPermissionsPages;
use RuntimeException;

/**
 * Tests for `AddUniqueKeyToAuthPermissionsPages`
 * (`modules/Auth/Database/Migrations/2026-08-14-170018_AddUniqueKeyToAuthPermissionsPages.php`),
 * Round 2 / B2(a), closing the TOCTOU race left open by Round 1's
 * application-level `Methods::classNameMethodNameCollides()` guard.
 *
 * `$migrate = false`: same rationale as
 * `tests/Modules/Auth/AuthGroupsUniqueKeyMigrationTest.php` — the migration
 * class is instantiated directly and only `up()`/`down()` are called,
 * DatabaseTestTrait's own automatic `latest('tests')` (triggered when
 * `$namespace = null`, `vendor/codeigniter4/framework/system/Test/DatabaseTestTrait.php:165-187`)
 * is never invoked from this class, so a failed `MigrationRunner::latest()`
 * never triggers its automatic `regress(-1)` against the rest of the shared
 * `ci4ms_test` schema.
 *
 * DECISION — leave the migration permanently APPLIED in `ci4ms_test`:
 * `context-round2.md` documents that B2(b)'s own regression test (dispatched
 * right after this task) relies on the DB-level UNIQUE constraint being
 * active in `ci4ms_test` as a second, defense-in-depth layer behind the
 * application-level guard. Every test method in this class is therefore
 * designed to leave the constraint applied when it finishes — including via
 * `tearDown()`, which unconditionally re-applies `up()` (idempotent, no-op
 * if already applied). This makes the end state deterministic regardless of
 * PHPUnit's test execution order (this project's own
 * `tests/database/ExampleDatabaseTest.php` incident, documented in
 * `.ci4ms/knowledge/pitfalls.md`, is why order is not assumed to be
 * declaration order only).
 *
 * @internal
 */
final class AuthPermissionsPagesUniqueKeyMigrationTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $refresh = false;

    protected $migrate = false;

    protected $namespace = null;

    private const TABLE            = 'auth_permissions_pages';
    private const GENERATED_COLUMN = 'class_method_key';
    private const UNIQUE_KEY       = 'auth_permissions_pages_class_method_unique';

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertStringContainsString(
            'ci4ms_test',
            (string) \Config\Database::connect()->database,
            'Refusing to run: the active connection is not pointed at the test database.',
        );

        $this->requireMigrationFile();
    }

    /**
     * Always leaves the constraint applied — see class docblock "DECISION".
     */
    protected function tearDown(): void
    {
        (new AddUniqueKeyToAuthPermissionsPages())->up();

        parent::tearDown();
    }

    /**
     * (a) Happy path: `up()` adds the generated column and the UNIQUE index
     * on it, with the right column and uniqueness flag.
     */
    public function testUpAddsUniqueConstraintOnGeneratedClassMethodKey(): void
    {
        (new AddUniqueKeyToAuthPermissionsPages())->down();
        $this->assertNull($this->findIndex(self::UNIQUE_KEY), 'Ön koşul: constraint zaten var, temiz başlangıç durumu kurulamadı.');

        (new AddUniqueKeyToAuthPermissionsPages())->up();

        $index = $this->findIndex(self::UNIQUE_KEY);
        $this->assertNotNull($index, self::UNIQUE_KEY . ' index bulunamadı.');
        $this->assertSame('0', (string) $index->Non_unique, self::UNIQUE_KEY . ' UNIQUE olmalı (Non_unique=0).');
        $this->assertSame(self::GENERATED_COLUMN, $index->Column_name);
        $this->assertTrue($this->db->fieldExists(self::GENERATED_COLUMN, self::TABLE));
    }

    /**
     * The 3 pre-existing `className=''`/`methodName=''` virtual-parent rows
     * (`ModuleScanner::createVirtualParents()`) must survive `up()` intact.
     */
    public function testUpPreservesExistingVirtualParentRows(): void
    {
        (new AddUniqueKeyToAuthPermissionsPages())->down();

        $before = $this->countVirtualParentRows();
        $this->assertGreaterThanOrEqual(
            1,
            $before,
            'ci4ms_test\'te beklenen className=\'\'/methodName=\'\' virtual-parent satırları yok - ön koşul karşılanmadı.',
        );

        (new AddUniqueKeyToAuthPermissionsPages())->up();

        $after = $this->countVirtualParentRows();
        $this->assertSame($before, $after, 'up() sonrası mevcut virtual-parent satır sayısı değişti - veri kaybı riski.');
    }

    /**
     * Once applied, the DB itself (not just the application-level guard)
     * rejects a second row with the same (className, methodName) pair.
     */
    public function testConstraintRejectsCollidingRealPairAtDbLevel(): void
    {
        (new AddUniqueKeyToAuthPermissionsPages())->up();

        $suffix     = bin2hex(random_bytes(4));
        $className  = 'DbaTaskB2aClass_' . $suffix;
        $methodName = 'method_' . $suffix;

        $firstId = null;

        try {
            $firstId = $this->insertPermissionPageRow($className, $methodName, 'first_' . $suffix);

            $caught = null;

            try {
                $this->insertPermissionPageRow($className, $methodName, 'second_' . $suffix);
            } catch (DatabaseException $e) {
                $caught = $e;
            }

            $this->assertNotNull($caught, 'Aynı (className, methodName) çiftiyle ikinci INSERT DB seviyesinde reddedilmedi.');
            $this->assertStringContainsString('uplicate', $caught->getMessage());
        } finally {
            if ($firstId !== null) {
                $this->db->table(self::TABLE)->where('id', $firstId)->delete();
            }
        }
    }

    /**
     * Functional-regression guard: `ModuleScanner::createVirtualParents()`
     * must still be able to insert a brand-new `('', '')` row after the
     * constraint is active.
     */
    public function testConstraintAllowsNewVirtualParentRow(): void
    {
        (new AddUniqueKeyToAuthPermissionsPages())->up();

        $before = $this->countVirtualParentRows();

        $suffix = bin2hex(random_bytes(4));
        $newId  = null;

        try {
            $newId = $this->insertPermissionPageRow('', '', 'virtual_' . $suffix);
            $this->assertGreaterThan(0, $newId, 'Yeni virtual-parent satırı eklenemedi (regresyon).');

            $after = $this->countVirtualParentRows();
            $this->assertSame($before + 1, $after, 'Yeni virtual-parent satırı eklendikten sonra sayaç beklenen şekilde artmadı.');
        } finally {
            if ($newId !== null) {
                $this->db->table(self::TABLE)->where('id', $newId)->delete();
            }
        }
    }

    /**
     * (b) `down()` removes the UNIQUE index and the generated column
     * without touching any data row.
     */
    public function testDownRemovesConstraintAndPreservesAllRows(): void
    {
        (new AddUniqueKeyToAuthPermissionsPages())->up();
        $totalBefore = $this->db->table(self::TABLE)->countAllResults();

        (new AddUniqueKeyToAuthPermissionsPages())->down();

        $this->assertNull($this->findIndex(self::UNIQUE_KEY), 'down() sonrası ' . self::UNIQUE_KEY . ' hâlâ var.');
        $this->assertFalse(
            $this->db->fieldExists(self::GENERATED_COLUMN, self::TABLE),
            'down() sonrası generated kolon hâlâ var.',
        );

        $totalAfter = $this->db->table(self::TABLE)->countAllResults();
        $this->assertSame($totalBefore, $totalAfter, 'down() veri satırı kaybına/kazanımına yol açtı - yalnızca şema değişmeliydi.');

        // tearDown() migration'ı tekrar uygulayacak (bkz. sınıf docblock'u "DECISION").
    }

    /**
     * (c) Defense branch: a real (non-empty) duplicate (className,
     * methodName) pair makes `up()` fail with a meaningful `RuntimeException`
     * instead of a raw DB error, and no ALTER runs (constraint stays absent).
     */
    public function testUpThrowsMeaningfulExceptionOnUnexpectedRealDuplicate(): void
    {
        (new AddUniqueKeyToAuthPermissionsPages())->down();

        $suffix     = bin2hex(random_bytes(4));
        $className  = 'DbaTaskB2aDup_' . $suffix;
        $methodName = 'dupmethod_' . $suffix;

        $firstId  = $this->insertPermissionPageRow($className, $methodName, 'dup1_' . $suffix);
        $secondId = $this->insertPermissionPageRow($className, $methodName, 'dup2_' . $suffix);

        try {
            $caught = null;

            try {
                (new AddUniqueKeyToAuthPermissionsPages())->up();
            } catch (RuntimeException $e) {
                $caught = $e;
            }

            $this->assertNotNull($caught, 'up() gerçek duplicate varken RuntimeException fırlatmadı.');
            $this->assertStringContainsString(self::TABLE, $caught->getMessage());
            $this->assertStringContainsString($className, $caught->getMessage());
            $this->assertStringContainsString($methodName, $caught->getMessage());

            $this->assertNull($this->findIndex(self::UNIQUE_KEY), 'Guard tetiklendiğinde constraint yine de eklenmiş.');
        } finally {
            $this->db->table(self::TABLE)->whereIn('id', [$firstId, $secondId])->delete();
        }

        // tearDown() migration'ı tekrar uygulayacak (bkz. sınıf docblock'u "DECISION").
    }

    private function countVirtualParentRows(): int
    {
        return $this->db->table(self::TABLE)->where(['className' => '', 'methodName' => ''])->countAllResults();
    }

    /**
     * Inserts a real `auth_permissions_pages` row satisfying every NOT NULL
     * column (including `module_id`, which has a real FK to `modules.id` —
     * see `modules/Backend/Database/Migrations/2026-02-25-062806_AddForeignKeys.php:15`).
     * `module_id` is resolved from an existing `modules` row rather than
     * hardcoded, since the exact module IDs seeded into `ci4ms_test` are not
     * this test's concern.
     */
    private function insertPermissionPageRow(string $className, string $methodName, string $pagenameSuffix): int
    {
        $moduleId = (int) ($this->db->table('modules')->select('id')->get()->getRow()->id ?? 0);
        $this->assertGreaterThan(0, $moduleId, 'ci4ms_test.modules tablosunda hiç satır yok - test ön koşulu karşılanmadı.');

        $this->db->table(self::TABLE)->insert([
            'pagename'          => 'dba_task_b2a_' . $pagenameSuffix,
            'description'       => 'dba_task_b2a_' . $pagenameSuffix,
            'className'         => $className,
            'methodName'        => $methodName,
            'sefLink'           => '#',
            'hasChild'          => 0,
            'pageSort'          => null,
            'parent_pk'         => null,
            'symbol'            => 'fas fa-folder',
            'inNavigation'      => 0,
            'isBackoffice'      => 1,
            'typeOfPermissions' => '{"read_r":true}',
            'module_id'         => $moduleId,
            'isActive'          => 1,
        ]);

        return (int) $this->db->insertID();
    }

    /**
     * Requires the migration file directly. `AddUniqueKeyToAuthPermissionsPages`
     * is not resolvable through standard PSR-4 autoloading — CI4 migration
     * filenames (`{timestamp}_{ClassName}.php`) do not match the class name
     * 1:1, so `MigrationRunner` loads the file itself via
     * `include_once $migration->path`
     * (`vendor/codeigniter4/framework/system/Database/MigrationRunner.php:966`).
     * Same technique as `AuthGroupsUniqueKeyMigrationTest::requireMigrationFile()`;
     * the timestamp is discovered via `glob()` rather than hardcoded so a
     * future rename of the migration file does not break this test.
     */
    private function requireMigrationFile(): void
    {
        if (class_exists(AddUniqueKeyToAuthPermissionsPages::class, false)) {
            return;
        }

        $matches = glob(ROOTPATH . 'modules/Auth/Database/Migrations/*_AddUniqueKeyToAuthPermissionsPages.php');
        $this->assertNotEmpty(
            $matches,
            'Migration dosyası bulunamadı: modules/Auth/Database/Migrations/*_AddUniqueKeyToAuthPermissionsPages.php',
        );

        require_once $matches[0];
    }

    private function findIndex(string $keyName): ?object
    {
        $prefixed = $this->db->DBPrefix . self::TABLE;

        $rows = $this->db->query('SHOW INDEX FROM `' . $prefixed . '` WHERE Key_name = ?', [$keyName])->getResult();

        return $rows[0] ?? null;
    }
}

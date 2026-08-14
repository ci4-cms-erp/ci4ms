<?php

declare(strict_types=1);

namespace Tests\Modules\Methods;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Services;
use Modules\Methods\Libraries\ModuleInstaller;

// Must stay at file scope, NOT in setUp(): PHP caches the resolution of an
// unqualified is_dir() call per call-site on its first execution, so the shim
// has to be declared before any earlier test can reach ModuleInstaller's call
// site. PHPUnit require_once's every test file while building the suite
// (Runner/TestSuiteLoader.php:115), i.e. before the first test runs.
require_once SUPPORTPATH . 'Methods/is_dir_shim.php';

/**
 * Regression test for ModuleInstaller::runModuleMigrations() namespace bug.
 *
 * `runModuleMigrations()` builds the migration namespace as
 * `Modules\{Name}\Database\Migrations` (ModuleInstaller.php:40), but the
 * PSR-4 prefix actually registered for every module is `Modules\{Name}`
 * (app/Config/Autoload.php:106). Because FileLocator::listNamespaceFiles()
 * does an exact-key lookup against the registered prefixes
 * (Autoloader.php:254, FileLocator.php:355), the extra `\Database\Migrations`
 * suffix never matches anything and zero migrations are ever found — while
 * the method still reports `migrated` as the on-disk file count
 * (ModuleInstaller.php:46-47) instead of what was actually applied, and
 * `success` stays true. This test proves the fix's target behaviour (the
 * migration is genuinely applied and the report reflects reality) and is
 * expected to fail against today's buggy code.
 *
 * A fixture module is wired in under tests/_support/ (never modules/,
 * see dispatch notes: other PHPUnit processes may be scanning modules/
 * concurrently) via Config\Services::autoloader()->addNamespace(), and the
 * on-disk is_dir() gate in ModuleInstaller (:31) is bypassed with a
 * namespace-scoped function shadow (see is_dir_shim.php) rather than by
 * creating any real directory under modules/.
 *
 * Order-dependence audit (ci4ms-qa, C3 in context.md): unlike
 * FileeditorWriteAllowlistTest/PermgroupPrivilegeEscalationTest, this class
 * never calls Controller::validate(), so it does not share those two files'
 * exposure to the process-shared `validation` service leak closed in
 * SecurityXSSCSRFTest::tearDown() (Factories::resetSingle('validation')) —
 * measured empirically, no such reset is added here because none applies.
 *
 * A SEPARATE, unrelated order-dependence was found, root-caused and fixed:
 * when any earlier test in the same PHPUnit process had already triggered
 * Modules\Methods\Libraries\ModuleInstaller::runModuleMigrations() for a
 * real module (e.g. any test that dispatches a real backend request, such
 * as SecurityXSSCSRFTest), PHP's namespace-fallback resolution for the
 * unqualified is_dir() call inside runModuleMigrations() got cached to the
 * global \is_dir() on that FIRST call — per call-site, not per-namespace-
 * state, so requiring the shim afterwards in this class' setUp() could no
 * longer take effect (a general PHP behaviour, reproduced outside this
 * codebase/framework, not a defect in ModuleInstaller.php). The require was
 * therefore hoisted out of setUp() to this file's top level, which PHPUnit
 * executes while it require_once's every test file to build the suite
 * (Runner/TestSuiteLoader.php:115) — i.e. before the first test of the run,
 * so the shim always wins the first resolution of that call site. See
 * context.md's "[ci4ms-qa] C3 kök nedeni" and "[ci4ms-debugger] C3 kapandı"
 * entries for the full hypothesis/experiment/evidence chain.
 *
 * @internal
 */
final class ModuleInstallerNamespaceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $refresh = false;
    protected $migrateOnce = true;

    private const FIXTURE_NAMESPACE = 'Modules\\FixtureModule';
    private const FIXTURE_TABLE = 'module_installer_fixture';

    protected function setUp(): void
    {
        parent::setUp();

        // Canary: never allowed to run against anything but the throwaway
        // test schema. See .ci4ms/knowledge (tests-need-dedicated-db).
        $this->assertStringContainsString(
            'ci4ms_test',
            (string) \Config\Database::connect()->database,
            'Refusing to run: DB connection does not point at ci4ms_test.',
        );

        Services::autoloader()->addNamespace(
            self::FIXTURE_NAMESPACE,
            SUPPORTPATH . 'Methods/FixtureModule',
        );
    }

    protected function tearDown(): void
    {
        \Config\Database::forge()->dropTable(self::FIXTURE_TABLE, true);

        \Config\Database::connect()
            ->table('migrations')
            ->where('namespace', self::FIXTURE_NAMESPACE)
            ->delete();

        Services::autoloader()->removeNamespace(self::FIXTURE_NAMESPACE);

        parent::tearDown();
    }

    /**
     * Fixed runModuleMigrations() must genuinely apply the fixture migration
     * (real namespace resolution) and report the true applied count instead
     * of an on-disk file count. Against today's code the namespace is wrong,
     * so nothing is actually migrated and this assertion set fails.
     *
     * @return void
     */
    public function testRunModuleMigrationsAppliesFixtureAndReportsRealCount(): void
    {
        $result = (new ModuleInstaller())->runModuleMigrations('FixtureModule');

        $this->assertTrue($result['success'], 'runModuleMigrations() reported failure: ' . (string) ($result['error'] ?? ''));
        $this->assertSame(1, $result['migrated'], 'migrated count must equal the number of migrations actually applied, not files on disk.');

        $this->seeNumRecords(1, 'migrations', ['namespace' => self::FIXTURE_NAMESPACE]);

        $this->assertTrue(
            \Config\Database::connect()->tableExists(self::FIXTURE_TABLE),
            'Fixture table was never created — migration namespace did not resolve.',
        );
    }
}

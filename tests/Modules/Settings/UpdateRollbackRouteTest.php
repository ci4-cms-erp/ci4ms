<?php

declare(strict_types=1);

namespace Tests\Modules\Settings;

use CodeIgniter\Shield\Test\AuthenticationTesting;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Modules\Auth\Models\UserModel;
use Modules\Install\Services\InstallService;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Router and filter chain in front of Settings::rollbackUpdate().
 *
 * UpdateRollbackPathTest covers the resolution primitive and
 * UpdateRollbackControllerTest the method's own contract; both bypass routing.
 * This class closes the last gap: that the endpoint is actually unreachable
 * without authentication and without the `update` permission, which is what
 * makes the restore-source guard the *second* line of defence rather than the
 * only one.
 *
 * Requires a real MySQL `tests` database group — the project's migrations use
 * MySQL-only DDL and will not run on SQLite. Point database.tests.* at a
 * throwaway schema (never the live one) in .env or phpunit.xml.dist; with the
 * shipped in-memory SQLite default this class is skipped rather than run
 * against a half-built schema.
 *
 * Safety: the real UpdateService is used, so a *successful* rollback would copy
 * files into the project. Only paths that can never reach rollback() are
 * exercised — no name given, unknown name, traversal payloads — and
 * testNoRequestTouchesTheProjectBackupTree() pins that the backup tree is
 * untouched. The success path stays with the spied controller test.
 *
 * @internal
 */
final class UpdateRollbackRouteTest extends CIUnitTestCase
{
    use FeatureTestTrait;
    use DatabaseTestTrait;
    use AuthenticationTesting;

    // $refresh=true makes DatabaseTestTrait::regressDatabase() run on every
    // test method; MigrationRunner::regress() ignores setNamespace() and
    // internally forces $this->namespace=null regardless of what is passed
    // (vendor/codeigniter4/framework/system/Database/MigrationRunner.php:
    // 289-291), so it drops EVERY namespace's tables in the shared
    // ci4ms_test schema, not just this class's fixtures. Same root cause as
    // tests/database/ExampleDatabaseTest.php; same fix shape as
    // tests/Modules/Users/PermgroupPrivilegeEscalationTest.php and
    // tests/Modules/MigrationManager/*Test.php.
    protected $refresh = false;

    protected $migrateOnce = true;

    protected $namespace = null;

    private const ROUTE = 'backend/settings/rollbackUpdate';

    protected function setUp(): void
    {
        if (config('Database')->tests['DBDriver'] !== 'MySQLi') {
            $this->markTestSkipped('Needs a MySQL tests database group; the project migrations are MySQL-only.');
        }

        parent::setUp();

        \Config\Services::migrations()->setNamespace(null)->latest();

        // Migrations only build the schema. auth_permissions_pages is filled by the
        // Methods scanner and languages/settings/identity by the installer; without
        // them Ci4MsAuthFilter refuses every backend route and the test would pass
        // for the wrong reason.
        //
        // Unconditional call is safe: InstallService::createDefaultData() now checks
        // each table (auth_groups, auth_groups_users, languages, pages, blog, menu,
        // settings) independently and only writes what's missing -- a second call is
        // a no-op. The previous single `languages===0` outer guard predated that
        // per-block idempotency and, once `languages` was non-empty from an earlier
        // test in the shared schema, permanently skipped this class's own identity
        // creation even when auth_groups/auth_groups_users were empty. See
        // superadminUser() below for why the admin lookup is by group membership,
        // not by this call's own 'seedadmin' username.
        (new InstallService())->createDefaultData([
            'fname'    => 'Seed',
            'sname'    => 'Admin',
            'username' => 'seedadmin',
            'email'    => 'seedadmin@example.com',
            'password' => 'SuperSecret123!',
            'siteName' => 'CI4MS Test System',
        ]);

        // This class is about the auth/permission chain, not CSRF. In the testing
        // environment Config\Security::$redirect is false, so a token mismatch
        // throws before any filter under test runs. The module's own opt-out API
        // is used rather than stripping the filter, and CSRF enforcement itself
        // stays covered by tests/Feature/SecurityXSSCSRFTest.
        $filters = config(\Config\Filters::class);
        $filters->mergeCsrfExcept([self::ROUTE]);
        \CodeIgniter\Config\Factories::injectMock('config', 'Filters', $filters);

        cache()->clean();
    }

    protected function tearDown(): void
    {
        // Modules\Auth\Filters\SessionTracker (part of backendGuard) runs on
        // every authenticated request in this class and inserts a row into
        // user_sessions whenever it does not find its own tracker id in the
        // session. FeatureTestTrait::withSession() replaces $_SESSION
        // wholesale on each post() call (request() below always passes only
        // the CSRF pair), so the tracker id never survives between requests
        // and every authenticated request looks like a new device. With
        // $refresh=false the schema is no longer dropped between test
        // methods, so those rows would otherwise accumulate indefinitely
        // across runs instead of being wiped by the next regress().
        $admin = $this->superadminUser();

        if ($admin !== null) {
            $this->db->table('user_sessions')->where('user_id', $admin->id)->delete();
        }

        \CodeIgniter\Config\Factories::reset('config');

        // request() calls actingAs($admin) on every test that exercises the
        // controller path. Shield's Auth facade caches its Session
        // authenticator instance for the whole PHPUnit process (see the
        // detailed explanation in tests/Feature/SecurityXSSCSRFTest.php's own
        // tearDown()); login() flips that instance's private $userState to
        // STATE_LOGGED_IN, which is not derived from $_SESSION, so wiping
        // $_SESSION alone does not undo it. Without this reset,
        // testAnonymousRequestNeverReachesTheEndpoint() fails whenever
        // --order-by=random happens to run it after one of this class's own
        // authenticated tests (reproduced with --order-by=reverse on this
        // class alone) — resetSingle('auth') discards the cached instance so
        // the next service('auth') call derives login state fresh.
        \Config\Services::resetSingle('auth');

        parent::tearDown();
    }

    public function testAnonymousRequestNeverReachesTheEndpoint(): void
    {
        $result = $this->withHeaders($this->ajaxHeaders())->post(self::ROUTE, $this->payload('anything'));

        $this->assertTrue($result->isRedirect(), 'An unauthenticated rollback must not be served');
        $this->assertStringNotContainsString(
            'rollbackSuccess',
            (string) $result->response()->getBody(),
        );
    }

    public function testSuperadminReachesTheControllerButAMissingNameIsRefused(): void
    {
        $result = $this->request([]);

        // 400 proves the request travelled the whole filter chain and the
        // controller answered; a filter refusal would redirect instead.
        $this->assertSame(400, $result->response()->getStatusCode());
        $this->assertStringContainsString(lang('Settings.backupNameRequired'), (string) $result->response()->getBody());
    }

    public function testAnUnknownBackupNameIsRefusedWithNotFound(): void
    {
        $result = $this->request($this->payload('v9.9.9.9_to_v9.9.9.9_nope'));

        $this->assertSame(404, $result->response()->getStatusCode());
        $this->assertStringContainsString(lang('Settings.invalidBackupName'), (string) $result->response()->getBody());
    }

    /**
     * @param string $payload Value submitted as backup_name
     */
    #[DataProvider('traversalPayloads')]
    public function testTraversalPayloadsAreRefusedThroughTheFullStack(string $payload): void
    {
        $result = $this->request($this->payload($payload));

        $this->assertContains($result->response()->getStatusCode(), [400, 404]);
        $this->assertStringNotContainsString(lang('Settings.rollbackSuccess'), (string) $result->response()->getBody());
    }

    /**
     * @return list<array{string}>
     */
    public static function traversalPayloads(): array
    {
        return [
            ['../uploads'],
            ['../../public'],
            ['../../app'],
            ['./../tmp'],
            ['..'],
            ['.'],
            ['/etc'],
        ];
    }

    /**
     * No request may add, remove or resize anything under writable/backups.
     */
    public function testNoRequestTouchesTheProjectBackupTree(): void
    {
        $before = $this->backupSnapshot();

        $this->request([]);
        $this->request($this->payload('v9.9.9.9_to_v9.9.9.9_nope'));

        foreach (self::traversalPayloads() as [$payload]) {
            $this->request($this->payload($payload));
        }

        $this->assertSame($before, $this->backupSnapshot());
    }

    /**
     * The identity created above is not guaranteed to be *this* class's own
     * 'seedadmin' user: `InstallService::createDefaultData()` is idempotent per
     * table now, so whichever test class's args first populated
     * `auth_groups_users` in this shared, `$refresh=false` `ci4ms_test` schema
     * keeps that identity for the rest of the process. Resolving by group
     * membership instead of by a hardcoded username works regardless of which
     * class won that race.
     */
    private function superadminUser(): ?\CodeIgniter\Shield\Entities\User
    {
        $link = (new \ci4commonmodel\CommonModel())->selectOne('auth_groups_users', ['group' => 'superadmin']);

        if ($link === null) {
            return null;
        }

        return (new UserModel())->find($link->user_id);
    }

    /**
     * Performs the request as a superadmin with a valid CSRF token.
     *
     * @param array<string, string> $payload
     */
    private function request(array $payload): \CodeIgniter\Test\TestResponse
    {
        $admin = $this->superadminUser();
        $this->assertNotNull($admin, 'Installer did not create any superadmin identity');

        $security  = \Config\Services::security();
        $tokenName = $security->getTokenName();
        $hash      = $security->generateHash();

        // CSRF is session based here; the hash must be readable both by the
        // Security instance already built in this process and by the one the
        // request rebuilds, hence both the superglobal and the request session.
        $_SESSION[$tokenName] = $hash;

        return $this->actingAs($admin)
            ->withSession([$tokenName => $hash])
            ->withHeaders($this->ajaxHeaders())
            ->post(self::ROUTE, $payload + [$tokenName => $hash]);
    }

    /**
     * @return array<string, string>
     */
    private function ajaxHeaders(): array
    {
        return ['X-Requested-With' => 'XMLHttpRequest'];
    }

    /**
     * @return array<string, string>
     */
    private function payload(string $backupName): array
    {
        return ['backup_name' => $backupName];
    }

    /**
     * @return list<string> path:size pairs under writable/backups, sorted
     */
    private function backupSnapshot(): array
    {
        $paths = [];

        foreach ((array) glob(WRITEPATH . 'backups/*') as $path) {
            $path    = (string) $path;
            $paths[] = $path . ':' . (is_file($path) ? (string) filesize($path) : 'dir');
        }

        sort($paths);

        return $paths;
    }
}

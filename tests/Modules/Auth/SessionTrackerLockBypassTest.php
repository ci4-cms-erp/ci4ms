<?php

declare(strict_types=1);

namespace Tests\Modules\Auth;

use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Test\AuthenticationTesting;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Modules\Auth\Models\UserSessionModel;
use Modules\Methods\Libraries\ModuleScanner;

/**
 * Regression lock for GHSA-2f22-3xhp-xp4j: `Modules\Auth\Filters\SessionTracker`
 * used to read the raw `$_COOKIE['remember_token']` superglobal and skip its
 * `locked_at` enforcement whenever that cookie was present — with any value,
 * forged or not, because the app never issues a cookie by that name (Shield's
 * remember-me cookie is named `remember`, see `modules/Auth/Config/Auth.php`
 * `rememberCookieName`). A locked session could therefore be bypassed simply
 * by sending `Cookie: remember_token=anything`.
 *
 * The fix removed the `$hasRememberToken` read and the `! $hasRememberToken &&`
 * clause from the `locked_at` branch (`modules/Auth/Filters/SessionTracker.php`,
 * currently around line 68). This class exercises the ONLY branch of
 * `before()` that runs the `locked_at` check: `SessionTracker::before()` looks
 * up the row by `session_id` and only enters the `else` branch (where the lock
 * check lives) when a `user_sessions` row already exists for that id — on a
 * brand-new session id it takes the `recordLogin()` branch instead and never
 * evaluates `locked_at` at all. Every test method here therefore pre-creates
 * a `user_sessions` row through `UserSessionModel` with a known `session_id`
 * and injects that same id via `FeatureTestTrait::withSession()` so the
 * request actually lands in the branch under test.
 *
 * `FeatureTestTrait` has no `withCookies()` helper (verified against
 * `vendor/codeigniter4/framework/system/Test/FeatureTestTrait.php`: only
 * `withRoutes()/withSession()/withHeaders()/withBodyFormat()/withBody()`
 * exist) and `populateGlobals()`/`setupRequest()` never touch `$_COOKIE`. The
 * filter under test reads the raw superglobal directly (that IS the bug being
 * regression-tested), so the forged-cookie scenario writes
 * `$_COOKIE['remember_token']` directly and removes it in a `finally` block
 * plus a `tearDown()` safety net so it can never leak into another test.
 *
 * Route choice: `backend/migration-manager` is a `backendGuard`-protected
 * route (`app/Config/Filters.php:181-190`) whose filter chain includes
 * `SessionTracker` (`:187`) and which `MigrationManagerViewRenderTest`
 * already proves returns a real 200 for an in-memory superadmin — reused here
 * rather than inventing a new protected route.
 *
 * @internal
 */
final class SessionTrackerLockBypassTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;
    use AuthenticationTesting;

    protected $refresh = false;

    protected $migrateOnce = true;

    protected $namespace = null;

    private const ROUTE = 'backend/migration-manager';

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertStringContainsString(
            'ci4ms_test',
            (string) \Config\Database::connect()->database,
            'Refusing to run: the active connection is not pointed at the test database.',
        );

        service('routes')->loadRoutes();
        cache()->clean();

        // Fail-closed permission layer: Ci4MsAuthFilter needs an
        // auth_permissions_pages row for backend/migration-manager, same
        // precondition as MigrationManagerViewRenderTest.
        (new ModuleScanner())->runScan();

        $this->db->transStart();
    }

    protected function tearDown(): void
    {
        // Safety net: a fatal/assertion failure between setting and unsetting
        // $_COOKIE['remember_token'] inside a test method must never leak the
        // forged cookie into the next test in this process.
        unset($_COOKIE['remember_token']);

        $this->db->transRollback();

        // actingAs() logs in on the shared Session authenticator singleton;
        // that state survives into whichever test runs next in this process
        // (same class of bug documented in
        // tests/Modules/MigrationManager/MigrationManagerViewRenderTest.php's
        // tearDown()).
        \Config\Services::resetSingle('auth');

        // Ci4MsAuthFilter::before() writes the acting user's own_language onto
        // the shared `language` service singleton (same rationale as
        // MigrationManagerViewRenderTest's tearDown()).
        \Config\Services::resetSingle('language');

        parent::tearDown();
    }

    /**
     * The bug's exact trigger: a locked session plus a forged
     * `remember_token` cookie must still be redirected to the lock screen,
     * never allowed through to the controller.
     */
    public function testLockedSessionWithForgedRememberTokenCookieStillRedirectsToLockScreen(): void
    {
        $admin     = $this->createSuperadmin('lock_bypass_sa1');
        $sessionId = bin2hex(random_bytes(16));
        $this->seedUserSession((int) $admin->id, $sessionId, locked: true);

        $_COOKIE['remember_token'] = bin2hex(random_bytes(8));

        try {
            $result = $this->actingAs($admin)
                ->withSession(['ci4ms_session_tracker_id' => $sessionId])
                ->get(self::ROUTE);
        } finally {
            unset($_COOKIE['remember_token']);
        }

        $this->assertSame(302, $result->response()->getStatusCode());

        $redirectUrl = (string) $result->getRedirectUrl();
        $this->assertStringContainsString(
            '/backend/lock',
            $redirectUrl,
            "A locked session with a forged remember_token cookie must redirect to the lock screen; got '{$redirectUrl}'.",
        );
    }

    /**
     * Regression guard for the pre-existing (correct) behaviour: a locked
     * session with NO cookie at all must still redirect to the lock screen.
     * This proves the fix did not change the no-cookie path.
     */
    public function testLockedSessionWithoutCookieStillRedirectsToLockScreen(): void
    {
        $admin     = $this->createSuperadmin('lock_bypass_sa2');
        $sessionId = bin2hex(random_bytes(16));
        $this->seedUserSession((int) $admin->id, $sessionId, locked: true);

        $this->assertArrayNotHasKey('remember_token', $_COOKIE);

        $result = $this->actingAs($admin)
            ->withSession(['ci4ms_session_tracker_id' => $sessionId])
            ->get(self::ROUTE);

        $this->assertSame(302, $result->response()->getStatusCode());

        $redirectUrl = (string) $result->getRedirectUrl();
        $this->assertStringContainsString(
            '/backend/lock',
            $redirectUrl,
            "A locked session without any cookie must redirect to the lock screen; got '{$redirectUrl}'.",
        );
    }

    /**
     * Negative control: an unlocked session (locked_at NULL) must reach the
     * controller normally (200), proving the fix did not over-lock.
     */
    public function testUnlockedSessionReachesTheController(): void
    {
        $admin     = $this->createSuperadmin('lock_bypass_sa3');
        $sessionId = bin2hex(random_bytes(16));
        $this->seedUserSession((int) $admin->id, $sessionId, locked: false);

        $result = $this->actingAs($admin)
            ->withSession(['ci4ms_session_tracker_id' => $sessionId])
            ->get(self::ROUTE);

        $result->assertStatus(200);
    }

    /**
     * Inserts an active `user_sessions` row for the given session id so that
     * `SessionTracker::before()` finds `$exists` and evaluates its
     * `locked_at` branch instead of taking the `recordLogin()` branch.
     *
     * @param int    $userId    Owning Shield user id
     * @param string $sessionId Tracker id to inject via withSession()
     * @param bool   $locked    Whether to also call UserSessionModel::lockSession()
     */
    private function seedUserSession(int $userId, string $sessionId, bool $locked): void
    {
        $model = new UserSessionModel();
        $model->insert([
            'user_id'         => $userId,
            'session_id'      => $sessionId,
            'ip_address'      => '127.0.0.1',
            'user_agent'      => 'phpunit',
            'browser'         => 'phpunit',
            'browser_version' => '1.0',
            'os'              => 'test',
            // device_type is a DB-level ENUM('desktop','mobile','tablet','bot','unknown')
            // (modules/Auth/Database/Migrations/2026-04-03-034658_CreateUserSessionsTable.php);
            // any other value truncates and the insert silently fails.
            'device_type'     => 'desktop',
            'device_name'     => 'test',
            'last_activity'   => date('Y-m-d H:i:s'),
            'is_active'       => 1,
            'created_at'      => date('Y-m-d H:i:s'),
        ]);

        if ($locked) {
            $model->lockSession($sessionId);
        }
    }

    /**
     * Creates an in-memory superadmin the same way
     * MigrationManagerViewRenderTest::createSuperadmin() does: no
     * auth_groups_users write, group membership set directly on the fetched
     * entity via setGroupsCache().
     *
     * @param string $label Unique-ish label used to build username/email
     */
    private function createSuperadmin(string $label): User
    {
        $suffix = bin2hex(random_bytes(4));

        $users = auth()->getProvider();
        $user  = new User([
            'firstname'    => 'Test',
            'surname'      => 'User',
            'username'     => $label . '_' . $suffix,
            'email'        => $label . '_' . $suffix . '@example.test',
            'password'     => 'SuperSecret123!',
            'active'       => 1,
            'own_language' => 'en',
        ]);
        $users->save($user);

        // Model::save() does not back-fill $user->id on the entity passed in;
        // a fresh, fully-hydrated fetch is required before actingAs()/auth()->id()
        // (same requirement as MigrationManagerViewRenderTest::createSuperadmin()).
        $fresh = $users->findById($users->getInsertID());
        $this->assertNotNull($fresh, 'Newly created user could not be re-fetched.');
        $fresh->setGroupsCache(['superadmin']);

        return $fresh;
    }
}

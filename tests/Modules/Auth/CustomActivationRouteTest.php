<?php

declare(strict_types=1);

namespace Tests\Modules\Auth;

use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserIdentityModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * Router/filter-chain regression lock for `GET backend/login/verify-account`
 * (`CustomActivationController::verify()`), the public email-activation link.
 *
 * The bug this pins: `Modules\Auth\Controllers\BaseController::$helpers` used
 * to be `[]`, so the global `showError()` helper (defined only in
 * `modules/Backend/Helpers/ci4ms_helper.php`, NOT in `app/Config/Autoload.php`'s
 * global `$helpers` list) was never loaded for this controller family.
 * `verify()` calls bare `showError()` on all three of its error paths — with
 * the helper unloaded, every one of those paths fatals with
 * "Call to undefined function ...showError()" instead of returning a normal
 * error response. The fix (`modules/Auth/Controllers/BaseController.php:41`,
 * `$helpers = ['Modules\Backend\Helpers\ci4ms']`) was applied by hand, not by
 * this session — nothing enforced it staying in place, so this class exists to
 * make regressing it loud.
 *
 * The controller is deliberately driven through real routing/filters
 * (`FeatureTestTrait::get()`), not instantiated directly: bypassing
 * `initController()` would also bypass the exact mechanism the bug lived in
 * (`$helpers` autoloading), and the test would stop proving anything about the
 * fix. See the control-experiment evidence in the dispatching QA report.
 *
 * DB group note: every fixture here goes through Shield's own models
 * (`UserIdentityModel`, `auth()->getProvider()`), which — like
 * `DatabaseTestTrait`'s own `$this->db` (`CIUnitTestCase::$DBGroup = 'tests'`)
 * — resolve `$DBGroup === null` to the literal group `'tests'`
 * (`vendor/codeigniter4/framework/system/Database/Config.php:63-64`, hardcoded
 * for `ENVIRONMENT === 'testing'` regardless of `Config\Database::$defaultGroup`).
 * All of it shares one connection/session, so `$this->db->transStart()`/
 * `transRollback()` wraps every write this class makes, including the ones
 * made *inside* the HTTP request dispatched by `get()`. `ci4commonmodel\CommonModel`
 * is deliberately never used here — its hardcoded `'default'` group resolves to
 * a *different* cached connection under the same alias-but-different-key trap
 * documented in `tests/Modules/Users/PermgroupPrivilegeEscalationTest.php`.
 *
 * Throttle note: the route runs through `auth-rates`
 * (`Modules\Auth\Filters\AuthThrottleFilter`, profile `auth` = 10 req/60s per
 * IP, `app/Config/Throttle.php`), backed by the `file` cache handler — which
 * persists across separate PHPUnit process invocations, not just within one.
 * `cache()->clean()` in `setUp()` resets that bucket before every test method
 * (same technique as `tests/Modules/Settings/UpdateRollbackRouteTest.php`), so
 * repeated runs of this file (e.g. the red/green control experiment) never
 * trip a false 429.
 *
 * @internal
 */
final class CustomActivationRouteTest extends CIUnitTestCase
{
    use FeatureTestTrait;
    use DatabaseTestTrait;

    protected $refresh = false;

    protected $migrateOnce = true;

    protected $namespace = null;

    private const ROUTE = 'backend/login/verify-account';

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

        $this->db->transStart();
    }

    protected function tearDown(): void
    {
        $this->db->transRollback();

        parent::tearDown();
    }

    /**
     * (1) No `token` query param at all: `verify()` line 11-12 must answer
     * with `showError()`'s normal 404 page, not a fatal.
     */
    public function testMissingTokenReturnsErrorPageInsteadOfFataling(): void
    {
        $result = $this->get(self::ROUTE);

        $this->assertSame(404, $result->response()->getStatusCode());
        $this->assertStringNotContainsString(
            'Call to undefined function',
            (string) $result->response()->getBody(),
        );
    }

    /**
     * (2) A well-formed but unknown token: `verify()` line 18-19 must answer
     * with `showError()`'s normal 404 page, not a fatal.
     */
    public function testUnknownTokenReturnsErrorPageInsteadOfFataling(): void
    {
        $result = $this->get(self::ROUTE, ['token' => bin2hex(random_bytes(20))]);

        $this->assertSame(404, $result->response()->getStatusCode());
        $this->assertStringNotContainsString(
            'Call to undefined function',
            (string) $result->response()->getBody(),
        );
    }

    /**
     * (3) The token resolves to a real `auth_identities` row, but that row's
     * `user_id` points at no existing user (`verify()` line 37, the fallback
     * after `findById()` returns null): must answer with `showError()`'s
     * normal 404 page, not a fatal.
     *
     * `auth_identities.user_id` carries a real
     * `ON DELETE CASCADE` foreign key to `users.id`
     * (`ci4ms_auth_identities_user_id_foreign`, verified against
     * `ci4ms_test`'s live schema — deleting a real user after inserting its
     * identity would just cascade-delete the identity too, not produce an
     * orphan). The orphan row is built directly instead: FK checks are
     * dropped for the single insert with a `user_id` that cannot belong to
     * any real user, then restored immediately (`SET FOREIGN_KEY_CHECKS` is
     * session state, not transactional — the transaction wrapping this test
     * only protects the row's data, not that setting, hence the immediate
     * re-enable rather than relying on tearDown()).
     */
    public function testOrphanIdentityReturnsErrorPageInsteadOfFataling(): void
    {
        $token = bin2hex(random_bytes(20));

        $this->db->disableForeignKeyChecks();

        try {
            (new UserIdentityModel())->insert([
                'user_id' => 999_000_000 + random_int(1, 900_000),
                'type'    => 'email_activate',
                'name'    => 'register',
                'secret'  => $token,
            ]);
        } finally {
            $this->db->enableForeignKeyChecks();
        }

        $result = $this->get(self::ROUTE, ['token' => $token]);

        $this->assertSame(404, $result->response()->getStatusCode());
        $this->assertStringNotContainsString(
            'Call to undefined function',
            (string) $result->response()->getBody(),
        );
    }

    /**
     * (4) Green-path guard: a valid `email_activate` token must still
     * activate the user, delete the single-use identity and redirect to the
     * post-login page — proving the other three tests aren't passing by
     * having been rewritten into "always error".
     */
    public function testValidTokenActivatesUserDeletesIdentityAndRedirects(): void
    {
        $token  = bin2hex(random_bytes(20));
        $userId = $this->createInactiveUser();

        (new UserIdentityModel())->insert([
            'user_id' => $userId,
            'type'    => 'email_activate',
            'name'    => 'register',
            'secret'  => $token,
        ]);

        $result = $this->get(self::ROUTE, ['token' => $token]);

        $this->assertTrue($result->isRedirect(), 'A valid activation token must redirect, not error.');

        $user = auth()->getProvider()->findById($userId);
        $this->assertNotNull($user);
        $this->assertSame(1, (int) $user->active, 'User must be active=1 after a successful activation.');

        $identity = (new UserIdentityModel())
            ->where('type', 'email_activate')
            ->where('secret', $token)
            ->first();
        $this->assertNull($identity, 'The single-use activation identity must be deleted after success.');
    }

    /**
     * Creates a real, inactive Shield user via the same provider the
     * controller itself uses (`auth()->getProvider()`), matching the pattern
     * in `tests/Modules/Auth/AuthGroupsUniqueKeyMigrationTest.php`.
     */
    private function createInactiveUser(): int
    {
        $provider = auth()->getProvider();
        $suffix   = bin2hex(random_bytes(6));

        $provider->save(new User([
            'firstname' => 'Activation',
            'surname'   => 'QA',
            'username'  => 'activation_qa_' . $suffix,
            'email'     => 'activation_qa_' . $suffix . '@example.test',
            'password'  => 'SuperSecret123!',
            'active'    => 0,
        ]));

        return (int) $provider->getInsertID();
    }
}

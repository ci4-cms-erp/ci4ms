<?php

declare(strict_types=1);

namespace Tests\Modules\Users;

use ci4commonmodel\CommonModel;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\HTTP\SiteURI;
use CodeIgniter\HTTP\UserAgent;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Test\AuthenticationTesting;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\App;
use Modules\Users\Controllers\PermgroupController;
use ReflectionProperty;

/**
 * Regression suite for F1 (PermgroupController::user_perms() missing
 * self-target and delegation-ceiling guards).
 *
 * Before the fix, the only guard on this endpoint was on the *target* being
 * superadmin -- any authenticated actor reaching this method could POST to
 * their own id (self-escalation, outside the subset guard which always
 * compares against the actor's *current* permissions) or grant a peer more
 * than the actor's own effective permission set. Both guards, plus the
 * empty-perms[] wipe path staying behind the self-target guard, are locked
 * here.
 *
 * Same technique as tests/Modules/Users/PermgroupPrivilegeEscalationTest.php:
 * the controller is instantiated directly and driven through
 * ReflectionProperty, bypassing routing/filters -- the bug lives entirely
 * inside the controller body.
 *
 * @internal
 */
final class PermgroupUserPermsAuthzTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use AuthenticationTesting;

    protected $refresh = false;

    protected $migrateOnce = true;

    /**
     * null migrates every registered namespace, not just Modules\Users --
     * fixtures need Modules\Auth's auth_groups/auth_groups_users tables and
     * Shield's own users/identities tables (same rationale as
     * PermgroupPrivilegeEscalationTest).
     */
    protected $namespace = null;

    private CommonModel $commonModel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertStringContainsString(
            'ci4ms_test',
            (string) \Config\Database::connect()->database,
            'Refusing to run: the active connection is not pointed at the test database.',
        );

        // Same order-independence guards as PermgroupPrivilegeEscalationTest
        // (shared 'validation' and 'routes' services) -- see that file's
        // setUp() docblock for the full empirical write-up.
        \Config\Services::resetSingle('validation');
        service('routes')->loadRoutes();

        cache()->clean();

        // group 'tests' explicitly: this resolves to the SAME cached
        // connection object DatabaseTestTrait's own $this->db uses (both
        // null-coalesce to 'tests' under ENVIRONMENT === 'testing'), so
        // writes made through this instance participate in this test's
        // transaction and roll back in tearDown(). A bare `new CommonModel()`
        // (group 'default') would NOT share that connection -- see
        // PermgroupPrivilegeEscalationTest::setUp() for the full rationale.
        $this->commonModel = new CommonModel('tests');

        $this->db->transStart();
    }

    protected function tearDown(): void
    {
        $this->db->transRollback();

        parent::tearDown();
    }

    /**
     * An actor may not POST to user_perms() targeting their own id -- doing
     * so would let them self-escalate outside the subset guard, which only
     * compares against the actor's *current* permissions.
     */
    public function testSelfTargetIsForbidden(): void
    {
        [$ownedPageId] = $this->createPermissionPages('userperms_self_t1');

        $ownGroup = 'userperms_owngroup_t1';
        $this->createGroup($ownGroup);
        $actorId = $this->createUser('userperms_actor_t1');
        $this->addUserToGroup($actorId, $ownGroup);
        $this->primeAuthGroupsMatrix($ownGroup, [$this->pagePermission($ownedPageId, 'read')]);

        $before = auth()->getProvider()->findById($actorId)->getPermissions();

        $response = $this->dispatchUserPerms($actorId, $actorId, [
            'perms' => [$ownedPageId => ['roles' => 'read_r']],
        ]);

        $this->assertSame(403, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertSame(lang('Users.cannotEditOwnPermissions'), $body['messages']['error'] ?? null);

        $after = auth()->getProvider()->findById($actorId)->getPermissions();
        $this->assertSame($before, $after, 'user_perms() must not change the actor\'s own permissions via self-targeting.');
    }

    /**
     * An actor may not grant a target user a permission the actor does not
     * hold themselves (delegation ceiling).
     */
    public function testForeignPermissionGrantIsForbidden(): void
    {
        [$ownedPageId] = $this->createPermissionPages('userperms_foreign_t2');
        [$foreignPageId] = $this->createPermissionPages('userperms_foreign_t2b');

        $ownGroup = 'userperms_owngroup_t2';
        $this->createGroup($ownGroup);
        $actorId = $this->createUser('userperms_actor_t2');
        $this->addUserToGroup($actorId, $ownGroup);
        $this->primeAuthGroupsMatrix($ownGroup, [$this->pagePermission($ownedPageId, 'read')]);

        $targetId = $this->createUser('userperms_target_t2');
        $before = auth()->getProvider()->findById($targetId)->getPermissions();

        $response = $this->dispatchUserPerms($actorId, $targetId, [
            'perms' => [$foreignPageId => ['roles' => 'read_r']],
        ]);

        $this->assertSame(403, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertSame(lang('Users.permsExceedOwnGrant'), $body['messages']['error'] ?? null);

        $after = auth()->getProvider()->findById($targetId)->getPermissions();
        $this->assertSame($before, $after, 'user_perms() must not persist a permission the actor does not hold themselves.');
    }

    /**
     * Permanent regression guard (must stay green): a legitimate grant of a
     * subset of the actor's own permissions to a different, non-superadmin
     * user must keep working.
     */
    public function testLegitimateSubsetGrantSucceeds(): void
    {
        [$ownedPageId, $ownedPagename] = $this->createPermissionPages('userperms_subset_t3');

        $ownGroup = 'userperms_owngroup_t3';
        $this->createGroup($ownGroup);
        $actorId = $this->createUser('userperms_actor_t3');
        $this->addUserToGroup($actorId, $ownGroup);
        $this->primeAuthGroupsMatrix($ownGroup, [$this->pagePermission($ownedPageId, 'read')]);

        $targetId = $this->createUser('userperms_target_t3');

        $response = $this->dispatchUserPerms($actorId, $targetId, [
            'perms' => [$ownedPageId => ['roles' => 'read_r']],
        ]);

        $this->assertNotSame(403, $response->getStatusCode(), 'A legitimate own-subset grant must not be rejected.');

        $after = auth()->getProvider()->findById($targetId)->getPermissions();
        $this->assertContains(
            strtolower($ownedPagename) . '.read',
            $after,
            'user_perms() must persist a permission the actor holds themselves.',
        );
    }

    /**
     * The empty-perms[] path (wipes all of the target's direct permissions)
     * must stay behind the self-target guard -- an actor posting an empty
     * perms[] to their own id must still be rejected with
     * cannotEditOwnPermissions, not silently wipe their own permissions via
     * the early-return syncPermissions() call.
     */
    public function testEmptyPermsSelfWipeIsForbidden(): void
    {
        [$ownedPageId, $ownedPagename] = $this->createPermissionPages('userperms_wipe_t4');

        $ownGroup = 'userperms_owngroup_t4';
        $this->createGroup($ownGroup);
        $actorId = $this->createUser('userperms_actor_t4');
        $this->addUserToGroup($actorId, $ownGroup);
        $this->primeAuthGroupsMatrix($ownGroup, [$this->pagePermission($ownedPageId, 'read')]);

        // Give the actor a direct (user-level) permission so a wipe would be
        // observable if it were ever allowed to run.
        auth()->getProvider()->findById($actorId)->syncPermissions(strtolower($ownedPagename) . '.read');

        $response = $this->dispatchUserPerms($actorId, $actorId, [
            'perms' => [],
        ]);

        $this->assertSame(403, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertSame(lang('Users.cannotEditOwnPermissions'), $body['messages']['error'] ?? null);

        $after = auth()->getProvider()->findById($actorId)->getPermissions();
        $this->assertContains(
            strtolower($ownedPagename) . '.read',
            $after,
            'An empty perms[] self-POST must not wipe the actor\'s own direct permissions -- it must be rejected by the self-target guard first.',
        );
    }

    /**
     * Builds a 'pagename.action' permission string the way
     * Modules\Auth\Config\AuthGroups::loadFromDatabase() does.
     */
    private function pagePermission(int $pageId, string $action): string
    {
        $page = $this->commonModel->selectOne('auth_permissions_pages', ['id' => $pageId]);
        $this->assertNotNull($page, "fixture page {$pageId} disappeared.");

        return strtolower($page->pagename) . '.' . $action;
    }

    /**
     * Creates two auth_permissions_pages rows (an "owned" one and a
     * "foreign" one, sharing $labelPrefix) and returns
     * [ownedPageId, ownedPagename].
     *
     * @return array{0: int, 1: string} [insertId, pagename]
     */
    private function createPermissionPages(string $labelPrefix): array
    {
        // The suffix is also folded into className (not just pagename) so
        // that two calls in the same test never collide on
        // auth_permissions_pages_class_method_unique
        // (modules/Auth/Database/Migrations/2026-08-14-170018_AddUniqueKeyToAuthPermissionsPages.php)
        // -- see PermgroupPrivilegeEscalationTest::createPermissionPages()
        // for the full rationale (identical fixture, copy-pasted).
        $suffix   = bin2hex(random_bytes(4));
        $pagename = $labelPrefix . '_' . $suffix;

        $id = (int) $this->commonModel->create('auth_permissions_pages', [
            'pagename'          => $pagename,
            'description'       => $pagename,
            'className'         => '-Modules-Fixture-Controllers-Fixture-' . $suffix,
            'methodName'        => 'index',
            'sefLink'           => $pagename,
            'hasChild'          => 0,
            'symbol'            => 'fa fa-circle',
            'inNavigation'      => 0,
            'isBackoffice'      => 1,
            'typeOfPermissions' => json_encode(['read_r' => 1], JSON_UNESCAPED_UNICODE),
            'module_id'         => 1,
        ]);

        return [$id, $pagename];
    }

    /**
     * Seeds Modules\Auth\Config\AuthGroups' 24h-cached group/permission
     * matrix directly (see PermgroupPrivilegeEscalationTest for the full
     * rationale on why loadFromDatabase()'s own connection cannot see this
     * test's fixture writes before commit).
     *
     * Unlike PermgroupPrivilegeEscalationTest's identically-named helper,
     * this one also populates the 'permissions' key: user_perms() (and this
     * test's own fixture setup) calls Shield's syncPermissions(), which
     * validates every permission string against
     * array_keys(setting('AuthGroups.permissions')) --
     * Authorizable::syncPermissions() at vendor/codeigniter4/shield/src/
     * Authorization/Traits/Authorizable.php:208-218 -- a completely
     * separate check from the matrix-based can() used by group_update()/
     * group_create(), which is all the original helper needed to satisfy.
     *
     * @param array<string> $permissions
     */
    private function primeAuthGroupsMatrix(string $group, array $permissions): void
    {
        cache()->save('shield_auth_dynamic_config', [
            'groups'      => [],
            'permissions' => array_fill_keys($permissions, 'fixture permission'),
            'matrix'      => [$group => $permissions],
        ], 86400);
    }

    private function createGroup(string $name): int
    {
        return (int) $this->commonModel->create('auth_groups', [
            'group'       => $name,
            'description' => $name,
        ]);
    }

    private function createUser(string $label): int
    {
        $suffix = bin2hex(random_bytes(4));

        $users = auth()->getProvider();
        $user  = new User([
            'firstname' => 'Test',
            'surname'   => 'User',
            'username'  => $label . '_' . $suffix,
            'email'     => $label . '_' . $suffix . '@example.test',
            'password'  => 'SuperSecret123!',
            'active'    => 1,
        ]);
        $users->save($user);

        return (int) $users->getInsertID();
    }

    private function addUserToGroup(int $userId, string $group): void
    {
        $this->commonModel->create('auth_groups_users', [
            'user_id'    => $userId,
            'group'      => $group,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Logs $actorId in and runs PermgroupController::user_perms($targetId)
     * with $post as the request body, bypassing routing and filters.
     *
     * @param array<string, mixed> $post
     */
    private function dispatchUserPerms(int $actorId, int $targetId, array $post): ResponseInterface
    {
        $controller = new PermgroupController();
        $this->primeRequest($actorId, $post, $controller);

        return $controller->user_perms($targetId);
    }

    private function primeRequest(int $actorId, array $post, PermgroupController $controller): void
    {
        $actor = auth()->getProvider()->findById($actorId);
        $this->actingAs($actor);

        $config  = config(App::class);
        $request = new IncomingRequest($config, new SiteURI($config), null, new UserAgent());
        $request->setMethod('POST');
        $request->setGlobal('post', $post);
        $request->setGlobal('request', $post);

        $controller->commonModel = $this->commonModel;

        (new ReflectionProperty(\CodeIgniter\Controller::class, 'request'))->setValue($controller, $request);
        (new ReflectionProperty(\CodeIgniter\Controller::class, 'response'))->setValue($controller, service('response', $config, false));
    }
}

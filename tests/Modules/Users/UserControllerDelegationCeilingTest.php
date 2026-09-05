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
use Modules\Users\Controllers\UserController;
use ReflectionProperty;

/**
 * Regression suite for F4 (UserController::update_user()/create_user()
 * missing self-target and delegation-ceiling guards).
 *
 * Before the fix, update_user() let an actor target their own account id
 * (self-escalation via group change, bypassing the safer profile() path)
 * and Shield's Authorizable::syncGroups() only verified the posted group
 * names *exist* -- it never compared a target group's permission level to
 * the actor's own, so a non-superadmin holding only
 * users.create_user.create / users.update_user.update could promote a peer
 * (or themselves, via create_user) into a group more powerful than their
 * own. actorMayAssignGroup() closes that gap in both methods.
 *
 * Same technique as PermgroupPrivilegeEscalationTest/
 * PermgroupUserPermsAuthzTest: the controller is instantiated directly and
 * driven through ReflectionProperty, bypassing routing/filters.
 *
 * @internal
 */
final class UserControllerDelegationCeilingTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use AuthenticationTesting;

    protected $refresh = false;

    protected $migrateOnce = true;

    protected $namespace = null;

    private CommonModel $commonModel;

    protected function setUp(): void
    {
        parent::setUp();
        helper('Modules\Backend\Helpers\ci4ms');

        $this->assertStringContainsString(
            'ci4ms_test',
            (string) \Config\Database::connect()->database,
            'Refusing to run: the active connection is not pointed at the test database.',
        );

        \Config\Services::resetSingle('validation');
        service('routes')->loadRoutes();

        cache()->clean();

        // group 'tests' explicitly -- shares DatabaseTestTrait's own
        // connection object so writes roll back in tearDown(). See
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
     * An actor may not POST to update_user() targeting their own account id
     * -- self-service editing has a dedicated, safer path (profile()).
     */
    public function testUpdateUserSelfTargetIsForbidden(): void
    {
        $ownGroup = 'delegation_owngroup_t1';
        $ownGroupId = $this->createGroup($ownGroup);
        $actorId = $this->createUser('delegation_actor_t1');
        $this->addUserToGroup($actorId, $ownGroup);

        $response = $this->dispatchUpdateUser($actorId, $actorId, [
            'username'     => 'delegft1' . bin2hex(random_bytes(3)),
            'firstname'    => 'Self',
            'surname'      => 'Target',
            'email'        => 'delegft1_' . bin2hex(random_bytes(3)) . '@example.test',
            'group'        => [$ownGroupId],
            'own_language' => 'en',
        ]);

        $this->assertSame(403, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertSame(lang('Users.cannotEditOwnAccount'), $body['messages']['error'] ?? null);
    }

    /**
     * An actor may not assign a target user to a group whose permission
     * matrix exceeds the actor's own effective permissions (delegation
     * ceiling), via update_user().
     */
    public function testUpdateUserDelegationCeilingRejectsMorePowerfulGroup(): void
    {
        [$ownedPageId] = $this->createPermissionPages('delegation_update_t2');
        [$foreignPageId] = $this->createPermissionPages('delegation_update_t2b');

        $ownGroup = 'delegation_owngroup_t2';
        $this->createGroup($ownGroup);
        $actorId = $this->createUser('delegation_actor_t2');
        $this->addUserToGroup($actorId, $ownGroup);
        $this->primeAuthGroupsMatrix($ownGroup, [$this->pagePermission($ownedPageId, 'read')]);

        $powerfulGroupId = $this->createGroupWithPermissions('delegation_powerful_t2', [
            ['page_id' => $foreignPageId, 'read_r' => true],
        ]);

        $targetId = $this->createUser('delegation_target_t2');
        $before = $this->commonModel->selectOne('auth_groups_users', ['user_id' => $targetId]);

        $response = $this->dispatchUpdateUser($actorId, $targetId, [
            'username'     => 'delegft2' . bin2hex(random_bytes(3)),
            'firstname'    => 'Target',
            'surname'      => 'User',
            'email'        => 'delegft2_' . bin2hex(random_bytes(3)) . '@example.test',
            'group'        => [$powerfulGroupId],
            'own_language' => 'en',
        ]);

        $this->assertSame(403, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertSame(lang('Users.groupExceedsOwnGrant'), $body['messages']['error'] ?? null);

        $after = $this->commonModel->selectOne('auth_groups_users', ['user_id' => $targetId]);
        $this->assertSame(
            $before,
            $after,
            'update_user() must not assign a target user to a group exceeding the actor\'s own permissions.',
        );
    }

    /**
     * Same delegation ceiling via create_user(): the guard must run before
     * the new user row is created, so a rejected request never leaves an
     * orphaned (groupless, or worse, over-privileged) account behind.
     */
    public function testCreateUserDelegationCeilingRejectsMorePowerfulGroupAndLeavesNoOrphan(): void
    {
        [$ownedPageId] = $this->createPermissionPages('delegation_create_t3');
        [$foreignPageId] = $this->createPermissionPages('delegation_create_t3b');

        $ownGroup = 'delegation_owngroup_t3';
        $this->createGroup($ownGroup);
        $actorId = $this->createUser('delegation_actor_t3');
        $this->addUserToGroup($actorId, $ownGroup);
        $this->primeAuthGroupsMatrix($ownGroup, [$this->pagePermission($ownedPageId, 'read')]);

        $powerfulGroupId = $this->createGroupWithPermissions('delegation_powerful_t3', [
            ['page_id' => $foreignPageId, 'read_r' => true],
        ]);

        $newEmail = 'delegft3_' . bin2hex(random_bytes(4)) . '@example.test';
        $beforeUserCount = $this->commonModel->count('users');
        $beforeIdentityCount = $this->commonModel->count('auth_identities', ['secret' => $newEmail]);

        $response = $this->dispatchCreateUser($actorId, [
            'username'     => 'delegft3' . bin2hex(random_bytes(3)),
            'firstname'    => 'New',
            'surname'      => 'Account',
            'email'        => $newEmail,
            'group'        => [$powerfulGroupId],
            'password'     => 'SuperSecret123!',
            'own_language' => 'en',
        ]);

        $this->assertSame(403, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertSame(lang('Users.groupExceedsOwnGrant'), $body['messages']['error'] ?? null);

        $this->assertSame(
            $beforeUserCount,
            $this->commonModel->count('users'),
            'create_user() must not leave an orphaned user row behind when the delegation ceiling rejects the request.',
        );
        $this->assertSame(
            $beforeIdentityCount,
            $this->commonModel->count('auth_identities', ['secret' => $newEmail]),
            'create_user() must not leave an orphaned auth_identities row behind when the delegation ceiling rejects the request.',
        );
    }

    /**
     * Permanent regression guard (must stay green): assigning a target user
     * to a group that is a genuine subset of the actor's own permissions
     * must keep working via update_user().
     */
    public function testUpdateUserLegitimateSubsetGroupAssignmentSucceeds(): void
    {
        [$ownedPageId, $ownedPagename] = $this->createPermissionPages('delegation_legit_t4');

        $ownGroup = 'delegation_owngroup_t4';
        $this->createGroup($ownGroup);
        $actorId = $this->createUser('delegation_actor_t4');
        $this->addUserToGroup($actorId, $ownGroup);

        $safeGroupName = 'delegation_safe_t4';
        $safeGroupId = $this->createGroupWithPermissions($safeGroupName, [
            ['page_id' => $ownedPageId, 'read_r' => true],
        ]);

        // syncGroups() (reached on the success path) validates the target
        // group name via GroupModel::isValidGroup(), which reads
        // array_keys(setting('AuthGroups.groups')) -- a separate check from
        // the matrix-based can() the delegation-ceiling guard itself uses,
        // so $safeGroupName must also be listed as a valid group here.
        $this->primeAuthGroupsMatrix($ownGroup, [$this->pagePermission($ownedPageId, 'read')], [$safeGroupName]);

        $targetId = $this->createUser('delegation_target_t4');

        $response = $this->dispatchUpdateUser($actorId, $targetId, [
            'username'     => 'delegft4' . bin2hex(random_bytes(3)),
            'firstname'    => 'Target',
            'surname'      => 'User',
            'email'        => 'delegft4_' . bin2hex(random_bytes(3)) . '@example.test',
            'group'        => [$safeGroupId],
            'own_language' => 'en',
        ]);

        $this->assertNotSame(403, $response->getStatusCode(), 'A legitimate own-subset group assignment must not be rejected.');

        $after = $this->commonModel->selectOne('auth_groups_users', ['user_id' => $targetId]);
        $this->assertNotNull($after, 'target user must have been assigned to a group.');
        $this->assertSame($safeGroupName, $after->group);
    }

    private function pagePermission(int $pageId, string $action): string
    {
        $page = $this->commonModel->selectOne('auth_permissions_pages', ['id' => $pageId]);
        $this->assertNotNull($page, "fixture page {$pageId} disappeared.");

        return strtolower($page->pagename) . '.' . $action;
    }

    /**
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
     * $extraValidGroups additionally marks group names as valid for
     * GroupModel::isValidGroup() (vendor/codeigniter4/shield/src/Models/
     * GroupModel.php:79-84, which reads
     * array_keys(setting('AuthGroups.groups'))) -- syncGroups(), reached on
     * the legitimate-assignment success path, validates every target group
     * name against it, a separate check from the matrix-based can() the
     * delegation-ceiling guard itself uses.
     *
     * @param array<string> $permissions
     * @param array<string> $extraValidGroups
     */
    private function primeAuthGroupsMatrix(string $group, array $permissions, array $extraValidGroups = []): void
    {
        $validGroups = array_unique(array_merge([$group], $extraValidGroups));

        cache()->save('shield_auth_dynamic_config', [
            'groups'      => array_fill_keys($validGroups, ['title' => 'fixture', 'description' => 'fixture']),
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

    /**
     * @param array<int, array<string, mixed>> $permissions auth_groups.permissions payload (page_id + role flags)
     */
    private function createGroupWithPermissions(string $name, array $permissions): int
    {
        return (int) $this->commonModel->create('auth_groups', [
            'group'       => $name,
            'description' => $name,
            'permissions' => json_encode($permissions, JSON_UNESCAPED_UNICODE),
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
     * @param array<string, mixed> $post
     */
    private function dispatchUpdateUser(int $actorId, int $targetId, array $post): ResponseInterface
    {
        $controller = new UserController();
        $this->primeRequest($actorId, $post, $controller);

        return $controller->update_user($targetId);
    }

    /**
     * @param array<string, mixed> $post
     */
    private function dispatchCreateUser(int $actorId, array $post): ResponseInterface
    {
        $controller = new UserController();
        $this->primeRequest($actorId, $post, $controller);

        return $controller->create_user();
    }

    private function primeRequest(int $actorId, array $post, UserController $controller): void
    {
        $actor = auth()->getProvider()->findById($actorId);
        $this->actingAs($actor);

        $config  = config(App::class);
        $request = new IncomingRequest($config, new SiteURI($config), null, new UserAgent());
        $request = $request->withMethod('POST');
        $request->setGlobal('post', $post);
        $request->setGlobal('request', $post);

        $controller->commonModel = $this->commonModel;

        (new ReflectionProperty(\CodeIgniter\Controller::class, 'request'))->setValue($controller, $request);
        (new ReflectionProperty(\CodeIgniter\Controller::class, 'response'))->setValue($controller, service('response', $config, false));
    }
}

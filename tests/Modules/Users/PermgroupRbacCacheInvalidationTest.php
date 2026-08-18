<?php

declare(strict_types=1);

namespace Tests\Modules\Users;

use ci4commonmodel\CommonModel;
use CodeIgniter\Config\Services;
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
use Tests\Support\Notifications\CapturingChannel;
use Tests\Support\Notifications\FakeDispatchNotifier;

/**
 * Behavioural lock for PermgroupController's two rbac_cache_flush() call
 * sites: group_update()'s `$editResult && transStatus()` success branch
 * (:234) and group_create()'s insert success branch (:112).
 *
 * Editing a group's permission matrix is the single most direct way to change
 * what Ci4MsAuthFilter will authorise, so the two caches it reads from
 * ('shield_auth_dynamic_config' and 'backend_page_info_*') must be dropped
 * before the redirect. Without this test, deleting the flush at :234 changes
 * no test result at all.
 *
 * A negative control (a request the delegation-ceiling guard rejects) proves
 * the assertion is tied to the success branch, not to entering the method.
 *
 * The controller is driven directly through ReflectionProperty, the same
 * technique as PermgroupPrivilegeEscalationTest, whose T4 fixture recipe
 * (non-member actor + primed matrix) is reused below because it is the one
 * combination already proven to reach group_update()'s success branch.
 *
 * @internal
 */
final class PermgroupRbacCacheInvalidationTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use AuthenticationTesting;

    protected $refresh = false;

    protected $migrateOnce = true;

    /**
     * null migrates every registered namespace: the fixtures need
     * Modules\Auth's auth_groups/auth_groups_users plus Shield's users table.
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

        // Order-independence guards, see PermgroupPrivilegeEscalationTest's
        // setUp() docblock for the full empirical write-up of both.
        Services::resetSingle('validation');
        service('routes')->loadRoutes();

        cache()->clean();

        $this->commonModel = new CommonModel('tests');

        // group_update()'s success branch also fires `ci4ms.audit`, whose
        // app/Config/Events.php listener dispatches through the real
        // Notifier -- which builds `new CommonModel()` on the AUTOCOMMITTING
        // 'default' group (Notifier.php:43) and would leave notification rows
        // in ci4ms_test that this test's transaction cannot roll back.
        Services::injectMock('notifier', new FakeDispatchNotifier(new CapturingChannel()));

        $this->db->transStart();
    }

    protected function tearDown(): void
    {
        $this->db->transRollback();

        Services::resetSingle('notifier');

        parent::tearDown();
    }

    /**
     * PermgroupController.php:234 -- group_update()'s success branch.
     *
     * @return void
     */
    public function testGroupUpdateFlushesRbacCachesOnSuccess(): void
    {
        [$ownedPageId] = $this->createPermissionPages('b3pg_ok');

        $ownGroup = 'b3pg_owngroup_ok';
        $this->createGroup($ownGroup);
        $actorId = $this->createUser('b3pg_actor_ok');
        $this->addUserToGroup($actorId, $ownGroup);

        // Writes 'shield_auth_dynamic_config' -- both the matrix the subset
        // guard reads through can() AND the first of the two keys the flush
        // must remove.
        $this->primeAuthGroupsMatrix($ownGroup, [$this->pagePermission($ownedPageId, 'read')]);
        $pageKey = $this->primeBackendPageInfo();

        $targetGroupId = $this->createGroup('b3pg_target_ok');

        $this->dispatchGroupUpdate($actorId, $targetGroupId, [
            'groupName'   => 'B3 Permgroup Target Ok',
            'description' => 'Legitimate update',
            'seflink'     => 'b3-permgroup-target-ok',
            'perms'       => [$ownedPageId => ['roles' => 'read_r']],
        ]);

        $after = $this->commonModel->selectOne('auth_groups', ['id' => $targetGroupId]);
        $this->assertNotNull($after, 'precondition: target group row must still exist.');
        $this->assertSame(
            'Legitimate update',
            $after->description,
            'precondition: group_update() must have reached its success branch, otherwise the cache assertions prove nothing.',
        );

        $this->assertNull(
            cache()->get('shield_auth_dynamic_config'),
            'group_update() must invalidate shield_auth_dynamic_config (rbac_cache_flush(), PermgroupController.php:234).',
        );
        $this->assertNull(
            cache()->get($pageKey),
            'group_update() must invalidate backend_page_info_* (rbac_cache_flush(), PermgroupController.php:234).',
        );
    }

    /**
     * Negative control: a request the delegation-ceiling guard rejects
     * (PermgroupController.php:182-186) writes nothing, so it must leave both
     * caches alone. Without this, an unconditional flush placed anywhere
     * before the guard would still satisfy the test above.
     *
     * @return void
     */
    public function testRejectedGroupUpdateDoesNotFlushRbacCaches(): void
    {
        [$ownedPageId]   = $this->createPermissionPages('b3pg_rej_own');
        [$foreignPageId] = $this->createPermissionPages('b3pg_rej_foreign');

        $ownGroup = 'b3pg_owngroup_rej';
        $this->createGroup($ownGroup);
        $actorId = $this->createUser('b3pg_actor_rej');
        $this->addUserToGroup($actorId, $ownGroup);

        $this->primeAuthGroupsMatrix($ownGroup, [$this->pagePermission($ownedPageId, 'read')]);
        $pageKey = $this->primeBackendPageInfo();

        $targetGroupId = $this->createGroup('b3pg_target_rej');

        $response = $this->dispatchGroupUpdate($actorId, $targetGroupId, [
            'groupName'   => 'B3 Permgroup Target Rej',
            'description' => 'Escalation attempt',
            'seflink'     => 'b3-permgroup-target-rej',
            'perms'       => [$foreignPageId => ['roles' => 'read_r']],
        ]);

        $this->assertSame(403, $response->getStatusCode(), 'precondition: the delegation ceiling must have rejected this request.');

        $this->assertNotNull(
            cache()->get('shield_auth_dynamic_config'),
            'a rejected group_update() must not invalidate shield_auth_dynamic_config -- nothing was written.',
        );
        $this->assertNotNull(
            cache()->get($pageKey),
            'a rejected group_update() must not invalidate backend_page_info_* -- nothing was written.',
        );
    }

    /**
     * group_create()'s success branch (PermgroupController.php:112).
     *
     * A new auth_groups row changes AuthGroups::$groups, and that array is
     * exactly what Shield's GroupModel::isValidGroup() reads through
     * setting('AuthGroups.groups') -- served from the 24h-cached
     * 'shield_auth_dynamic_config' (AuthGroups.php:90-107). Leaving it stale
     * makes the group that was just created invisible to syncGroups(), while
     * the user-edit dropdown lists it straight from the table
     * (UserController.php:368): the group is selectable but assigning it
     * throws AuthorizationException::forUnknownGroup out of
     * UserController.php:351 -- after $user->save() has already committed the
     * profile fields, so the write is left half-applied.
     *
     * @return void
     */
    public function testGroupCreateFlushesRbacCachesOnSuccess(): void
    {
        [$ownedPageId] = $this->createPermissionPages('b3pg_cr_ok');

        $ownGroup = 'b3pg_owngroup_cr';
        $this->createGroup($ownGroup);
        $actorId = $this->createUser('b3pg_actor_cr');
        $this->addUserToGroup($actorId, $ownGroup);

        $this->primeAuthGroupsMatrix($ownGroup, [$this->pagePermission($ownedPageId, 'read')]);
        $pageKey = $this->primeBackendPageInfo();

        $this->dispatchGroupCreate($actorId, [
            'groupName'   => 'B3 Permgroup Created ' . bin2hex(random_bytes(3)),
            'description' => 'Legitimate create',
            'seflink'     => 'b3-permgroup-created',
            'perms'       => [$ownedPageId => ['roles' => 'read_r']],
        ]);

        $this->assertNotNull(
            $this->commonModel->selectOne('auth_groups', ['description' => 'Legitimate create']),
            'precondition: group_create() must have reached its success branch, otherwise the cache assertions prove nothing.',
        );

        $this->assertNull(
            cache()->get('shield_auth_dynamic_config'),
            'group_create() must invalidate shield_auth_dynamic_config -- the new group is absent from the cached AuthGroups::$groups until it does, and syncGroups() then rejects it as unknown.',
        );
        $this->assertNull(
            cache()->get($pageKey),
            'group_create() must invalidate backend_page_info_* -- rbac_cache_flush() clears the pair together.',
        );
    }

    /**
     * Negative control for the test above: a request the delegation-ceiling
     * guard rejects (PermgroupController.php:82-86) writes no auth_groups
     * row, so it must leave both caches alone. Without this, an
     * unconditional flush placed at the top of group_create() would still
     * satisfy the success-branch assertion.
     *
     * Unlike group_update(), the rejected path here redirects rather than
     * returning 403, so the absence of the row is the precondition.
     *
     * @return void
     */
    public function testRejectedGroupCreateDoesNotFlushRbacCaches(): void
    {
        [$ownedPageId]   = $this->createPermissionPages('b3pg_crj_own');
        [$foreignPageId] = $this->createPermissionPages('b3pg_crj_for');

        $ownGroup = 'b3pg_owngroup_crj';
        $this->createGroup($ownGroup);
        $actorId = $this->createUser('b3pg_actor_crj');
        $this->addUserToGroup($actorId, $ownGroup);

        $this->primeAuthGroupsMatrix($ownGroup, [$this->pagePermission($ownedPageId, 'read')]);
        $pageKey = $this->primeBackendPageInfo();

        $this->dispatchGroupCreate($actorId, [
            'groupName'   => 'B3 Permgroup Rejected ' . bin2hex(random_bytes(3)),
            'description' => 'Escalation attempt create',
            'seflink'     => 'b3-permgroup-rejected',
            'perms'       => [$foreignPageId => ['roles' => 'read_r']],
        ]);

        $this->assertNull(
            $this->commonModel->selectOne('auth_groups', ['description' => 'Escalation attempt create']),
            'precondition: the delegation ceiling must have rejected this request before the insert.',
        );

        $this->assertNotNull(
            cache()->get('shield_auth_dynamic_config'),
            'a rejected group_create() must not invalidate shield_auth_dynamic_config -- nothing was written.',
        );
        $this->assertNotNull(
            cache()->get($pageKey),
            'a rejected group_create() must not invalidate backend_page_info_* -- nothing was written.',
        );
    }

    /**
     * Writes one 'backend_page_info_*' entry (the wildcard-matched half of
     * rbac_cache_flush()) and returns its key.
     *
     * @return string
     */
    private function primeBackendPageInfo(): string
    {
        $pageKey = 'backend_page_info_b3_' . bin2hex(random_bytes(4));

        cache()->save($pageKey, ['id' => 4242, 'pagename' => 'qa.page'], 300);

        $this->assertNotNull(cache()->get('shield_auth_dynamic_config'), 'precondition: shield_auth_dynamic_config must be primed.');
        $this->assertNotNull(cache()->get($pageKey), 'precondition: ' . $pageKey . ' must be primed.');

        return $pageKey;
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
     * Creates an auth_permissions_pages row and returns [id, pagename].
     *
     * The random suffix is folded into className as well as pagename so two
     * calls in one test can never collide on
     * auth_permissions_pages_class_method_unique.
     *
     * @return array{0: int, 1: string}
     */
    private function createPermissionPages(string $labelPrefix): array
    {
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
     * Seeds the 24h-cached group/permission matrix directly, bypassing
     * AuthGroups::loadFromDatabase()'s default-group connection, which cannot
     * see this test's uncommitted fixture writes.
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

    /**
     * Creates a Shield user with a random-suffixed username/email.
     *
     * The length guard is not decoration: users.username is varchar(30), and
     * Shield surfaces an overflow as an unrelated-looking
     * "Attempt to read property \"id\" on null" from
     * UserModel::saveEmailIdentity() (or a bare "Query error: 0" with the
     * identity UPDATE quoted), because insert() fails, insertID() stays
     * stale and find() then returns null. $label must therefore stay <= 21
     * characters.
     *
     * @param string $label Human-readable prefix, made unique with a random suffix.
     *
     * @return int users.id
     */
    private function createUser(string $label): int
    {
        $suffix   = bin2hex(random_bytes(4));
        $username = $label . '_' . $suffix;

        $this->assertLessThanOrEqual(
            30,
            strlen($username),
            'fixture username "' . $username . '" overflows users.username varchar(30).',
        );

        $users = auth()->getProvider();
        $user  = new User([
            'firstname' => 'Test',
            'surname'   => 'User',
            'username'  => $username,
            'email'     => $username . '@example.test',
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
    private function dispatchGroupCreate(int $actorId, array $post): ResponseInterface
    {
        $controller = new PermgroupController();
        $this->primeRequest($actorId, $post, $controller);

        return $controller->group_create();
    }

    /**
     * @param array<string, mixed> $post
     */
    private function dispatchGroupUpdate(int $actorId, int $groupId, array $post): ResponseInterface
    {
        $controller = new PermgroupController();
        $this->primeRequest($actorId, $post, $controller);

        return $controller->group_update($groupId);
    }

    /**
     * @param array<string, mixed> $post
     */
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

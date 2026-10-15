<?php

declare(strict_types=1);

namespace Tests\Modules\Methods;

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
use Modules\Backend\Config\BackendConfig;
use Modules\Methods\Controllers\Methods;
use ReflectionClassConstant;
use ReflectionProperty;
use Tests\Support\Notifications\CapturingChannel;
use Tests\Support\Notifications\FakeDispatchNotifier;

/**
 * Behavioural lock for the permission-row editor's superadmin/PROTECTED_MODULES
 * gates: create()/update()'s superadmin-only gate (Methods.php:116-118,
 * :193-195), update()'s PROTECTED_MODULES enforcement (Methods.php:197-203),
 * and index()'s POST+AJAX toggle branch superadmin gate + independent
 * module_id/page_id PROTECTED_MODULES enforcement (Methods.php:54-75).
 *
 * `auth_permissions_pages.pagename`/`className`/`methodName`/`typeOfPermissions`
 * are exactly the fields Ci4MsAuthFilter reads to authorize every backend
 * request, so a non-superadmin actor holding only the endpoint's own,
 * delegable route permission must never be able to write or toggle a row --
 * doing so would let them remap any route's authorization to themselves.
 *
 * Nine scenarios:
 *   testUpdateRejectsNonSuperadminWithRealPermission
 *   testCreateRejectsNonSuperadminWithRealPermission
 *   testIndexPostRejectsNonSuperadminWithRealPermission
 *   testUpdateClearsGateForSuperadminOnNonProtectedRow
 *   testCreateClearsGateForSuperadminOnNonProtectedModule
 *   testIndexPostClearsGateForSuperadminOnNonProtectedModule
 *   testUpdateRejectsProtectedModuleRowEvenForSuperadmin
 *   testIndexPostRejectsBothProtectedModuleIdAndProtectedPageIdEvenForSuperadmin
 *   testIndexGetRendersListForNonSuperadminWithRealReadPermission
 *
 * The ninth scenario closes a gap none of the first eight cover: they all
 * dispatch index() via dispatchIndexPost(), which primeRequest() always
 * binds to a POST+AJAX request -- so nothing in this file previously drove
 * a REAL GET request through index(). The GET branch (page list render) is
 * intentionally left open to `read` permission and sits AFTER the
 * POST+AJAX superadmin gate, not before it (Methods.php:41-97) -- if that
 * gate were ever hoisted to the top of the method, all eight scenarios
 * above would stay green (they only ever send POST+AJAX) while every
 * `read`-permission, non-superadmin actor lost the list screen outright.
 * testIndexGetRendersListForNonSuperadminWithRealReadPermission is the only
 * test in the suite that would catch that regression.
 *
 * The three non-superadmin scenarios grant the actor the REAL, `methods.`
 * prefixed route permission -- methods.methodupdate.update /
 * methods.methodcreate.create / methods.methodlist.read -- read back from the
 * actual `auth_permissions_pages` row ModuleScanner registered for each route
 * (ModuleScanner.php:51: `pagename = {Module}.{route "as" name}`) and run
 * through the same transform production uses (AuthGroups::loadFromDatabase(),
 * Ci4MsAuthFilter::run() -- both call permission_string(strtolower($page->pagename),
 * $action)), instead of being typed by hand (see pagePermission()). Same
 * technique as MethodsModuleManagementSuperadminGateTest::pagePermission()
 * and its fixture helpers, copied/adapted verbatim.
 *
 * The three "clears gate" scenarios do not drive a full CRUD success path
 * beyond what proves the gate did not fire -- they assert the response is not
 * the gate's 403 AND that the request actually reached the next real branch
 * (the DB row/module was mutated), so a superadmin cleared both the
 * superadmin gate and, where applicable, a false-positive PROTECTED_MODULES
 * rejection.
 *
 * testUpdateRejectsProtectedModuleRowEvenForSuperadmin and
 * testIndexPostRejectsBothProtectedModuleIdAndProtectedPageIdEvenForSuperadmin
 * prove the PROTECTED_MODULES lock holds even for superadmin -- mirroring the
 * moduleDelete()/moduleInfo() "this module is never touched" rule -- and that
 * index()'s two independent POST paths (module_id, page_id) are BOTH closed,
 * not just one.
 *
 * The controller is instantiated directly and driven through
 * ReflectionProperty, the same technique the sibling Methods test suites use:
 * the gate logic lives entirely inside the controller body, so the
 * routing/filter layer would add noise without adding coverage.
 *
 * @internal
 */
final class MethodsPermissionRowSuperadminGateTest extends CIUnitTestCase
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

        Services::resetSingle('validation');
        service('routes')->loadRoutes();

        cache()->clean();

        $this->commonModel = new CommonModel('tests');

        // create()/update()'s success branch fires auditMethodEvent() ->
        // 'ci4ms.audit' -> service('notifier'), whose real implementation
        // builds a CommonModel on the AUTOCOMMITTING 'default' group
        // (Notifier.php:43) -- letting it run would write real notification
        // rows into ci4ms_test that this test's transaction can never roll
        // back. Same double MethodsRbacCacheInvalidationTest uses.
        Services::injectMock('notifier', new FakeDispatchNotifier(new CapturingChannel()));

        $this->db->transStart();
    }

    protected function tearDown(): void
    {
        $this->db->transRollback();

        Services::resetSingle('notifier');

        parent::tearDown();
    }

    // ------------------------------------------------------------------
    // Non-superadmin, real route permission -> 403
    // ------------------------------------------------------------------

    /**
     * A non-superadmin actor holding the real methodupdate.update permission
     * must still be rejected by the superadmin gate, and the target row must
     * be left untouched.
     *
     * @return void
     */
    public function testUpdateRejectsNonSuperadminWithRealPermission(): void
    {
        $permission = $this->pagePermission('Methods.methodUpdate', 'update', 'methods.methodupdate.update');
        $actorId    = $this->createPrivilegedActor($permission);

        $moduleId = $this->createNonProtectedModule();
        $row      = $this->createPermissionPageRow($moduleId, 'Before Update');

        $response = $this->dispatchUpdate($actorId, $row['id'], $this->updatePost($row['className'], $row['methodName'], 'After Update'));

        $this->assertSame(403, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertSame(lang('Methods.permissionManagementForbidden'), $body['messages']['error'] ?? null);

        $after = $this->commonModel->selectOne('auth_permissions_pages', ['id' => $row['id']]);
        $this->assertSame('Before Update', $after->pagename ?? null, 'a rejected request must not have modified the row.');
    }

    /**
     * A non-superadmin actor holding the real methodcreate.create permission
     * must still be rejected by the superadmin gate, and no row must be
     * written.
     *
     * @return void
     */
    public function testCreateRejectsNonSuperadminWithRealPermission(): void
    {
        $permission = $this->pagePermission('Methods.methodCreate', 'create', 'methods.methodcreate.create');
        $actorId    = $this->createPrivilegedActor($permission);

        $pagename = 'permrow_gate_create_reject_' . bin2hex(random_bytes(4));
        $before   = $this->commonModel->count('auth_permissions_pages');

        $response = $this->dispatchCreate($actorId, $this->createPost($pagename));

        $this->assertSame(403, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertSame(lang('Methods.permissionManagementForbidden'), $body['messages']['error'] ?? null);

        $this->assertSame(
            $before,
            $this->commonModel->count('auth_permissions_pages'),
            'a rejected request must not write a row.',
        );
        $this->assertNull($this->commonModel->selectOne('auth_permissions_pages', ['pagename' => $pagename]));
    }

    /**
     * A non-superadmin actor holding the real methodlist.read permission --
     * i.e. index()'s own GET route role, sufficient to VIEW the list -- must
     * still be rejected by the POST+AJAX toggle branch's superadmin gate, and
     * the target module must not be toggled.
     *
     * @return void
     */
    public function testIndexPostRejectsNonSuperadminWithRealPermission(): void
    {
        $permission = $this->pagePermission('Methods.methodList', 'read', 'methods.methodlist.read');
        $actorId    = $this->createPrivilegedActor($permission);

        $moduleId = $this->createNonProtectedModule();
        $before   = $this->commonModel->selectOne('modules', ['id' => $moduleId]);

        $response = $this->dispatchIndexPost($actorId, ['status' => 'inactive', 'module_id' => $moduleId]);

        $this->assertSame(403, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertSame(lang('Methods.permissionManagementForbidden'), $body['messages']['error'] ?? null);

        $after = $this->commonModel->selectOne('modules', ['id' => $moduleId]);
        $this->assertSame((int) $before->isActive, (int) $after->isActive, 'a rejected toggle must not flip modules.isActive.');
    }

    /**
     * The GET branch (page list render) must stay open to a non-superadmin
     * actor holding only the route's own real `read` permission --
     * index()'s superadmin gate lives INSIDE the POST+AJAX toggle branch
     * (Methods.php:43-63), not at the top of the method, and the two must
     * never be conflated: this proves the response is the actually
     * rendered list view (200, containing the `modulesContainer` DOM
     * marker from list.php), not the POST+AJAX branch's
     * failForbidden()/JSON body.
     *
     * @return void
     */
    public function testIndexGetRendersListForNonSuperadminWithRealReadPermission(): void
    {
        $permission = $this->pagePermission('Methods.methodList', 'read', 'methods.methodlist.read');
        $actorId    = $this->createPrivilegedActor($permission);

        $response = $this->dispatchIndexGet($actorId);

        $this->assertNotSame(403, $response->getStatusCode(), 'a read-permission, non-superadmin actor must not be blocked from viewing the list.');
        $this->assertSame(200, $response->getStatusCode());

        $body = (string) $response->getBody();
        $this->assertStringContainsString(
            'id="modulesContainer"',
            $body,
            'the response must be the rendered list view (list.php\'s modulesContainer marker), not a JSON forbidden body.',
        );
    }

    // ------------------------------------------------------------------
    // Superadmin clears the gate (non-protected target)
    // ------------------------------------------------------------------

    /**
     * A superadmin must clear the gate on a non-PROTECTED_MODULES row and
     * reach update()'s success branch.
     *
     * @return void
     */
    public function testUpdateClearsGateForSuperadminOnNonProtectedRow(): void
    {
        $actorId  = $this->superadminActorId();
        $moduleId = $this->createNonProtectedModule();
        $row      = $this->createPermissionPageRow($moduleId, 'Before Update');

        $response = $this->dispatchUpdate($actorId, $row['id'], $this->updatePost($row['className'], $row['methodName'], 'After Update'));

        $this->assertNotSame(403, $response->getStatusCode(), 'a superadmin must not be blocked by the permission-row gate.');

        $after = $this->commonModel->selectOne('auth_permissions_pages', ['id' => $row['id']]);
        $this->assertSame(
            'After Update',
            $after->pagename ?? null,
            'precondition: the gate must have cleared -- update() must have reached its success branch.',
        );
    }

    /**
     * A superadmin must clear the gate on a non-PROTECTED_MODULES module and
     * reach create()'s success branch.
     *
     * @return void
     */
    public function testCreateClearsGateForSuperadminOnNonProtectedModule(): void
    {
        $actorId  = $this->superadminActorId();
        $moduleId = $this->createNonProtectedModule();
        $pagename = 'permrow_gate_create_ok_' . bin2hex(random_bytes(4));

        $response = $this->dispatchCreate($actorId, $this->createPost($pagename, $moduleId));

        $this->assertNotSame(403, $response->getStatusCode(), 'a superadmin must not be blocked by the permission-row gate.');
        $this->assertNotNull(
            $this->commonModel->selectOne('auth_permissions_pages', ['pagename' => $pagename]),
            'precondition: the gate must have cleared -- create() must have reached its success branch.',
        );
    }

    /**
     * A superadmin must clear the gate on a non-PROTECTED_MODULES module and
     * reach index()'s toggle branch.
     *
     * @return void
     */
    public function testIndexPostClearsGateForSuperadminOnNonProtectedModule(): void
    {
        $actorId  = $this->superadminActorId();
        $moduleId = $this->createNonProtectedModule();

        $response = $this->dispatchIndexPost($actorId, ['status' => 'inactive', 'module_id' => $moduleId]);

        $this->assertNotSame(403, $response->getStatusCode(), 'a superadmin must not be blocked by the permission-row gate.');

        $after = $this->commonModel->selectOne('modules', ['id' => $moduleId]);
        $this->assertSame(
            0,
            (int) $after->isActive,
            'precondition: the gate must have cleared -- index() must have reached the toggle logic.',
        );
    }

    // ------------------------------------------------------------------
    // PROTECTED_MODULES holds even for superadmin
    // ------------------------------------------------------------------

    /**
     * update() must refuse a row belonging to a PROTECTED_MODULES module even
     * for a superadmin actor, and leave the row untouched.
     *
     * @return void
     */
    public function testUpdateRejectsProtectedModuleRowEvenForSuperadmin(): void
    {
        $actorId            = $this->superadminActorId();
        $protectedModuleId  = $this->protectedModuleId();
        $row                = $this->createPermissionPageRow($protectedModuleId, 'Before Update');

        $response = $this->dispatchUpdate($actorId, $row['id'], $this->updatePost($row['className'], $row['methodName'], 'After Update'));

        $this->assertSame(403, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertSame(lang('Methods.permissionPageProtected'), $body['messages']['error'] ?? null);

        $after = $this->commonModel->selectOne('auth_permissions_pages', ['id' => $row['id']]);
        $this->assertSame(
            'Before Update',
            $after->pagename ?? null,
            'a rejected request must not have modified the protected row -- even for superadmin.',
        );
    }

    /**
     * index()'s POST+AJAX toggle branch must refuse BOTH the module_id path
     * and the page_id path when they resolve to a PROTECTED_MODULES module,
     * even for a superadmin actor -- neither path may be left open just
     * because the other is closed.
     *
     * @return void
     */
    public function testIndexPostRejectsBothProtectedModuleIdAndProtectedPageIdEvenForSuperadmin(): void
    {
        $actorId           = $this->superadminActorId();
        $protectedModuleId = $this->protectedModuleId();
        $moduleBefore      = $this->commonModel->selectOne('modules', ['id' => $protectedModuleId]);

        $moduleResponse = $this->dispatchIndexPost($actorId, ['status' => 'inactive', 'module_id' => $protectedModuleId]);

        $this->assertSame(
            403,
            $moduleResponse->getStatusCode(),
            'module_id path: a PROTECTED_MODULES module must be refused even for superadmin.',
        );
        $moduleBody = json_decode((string) $moduleResponse->getBody(), true);
        $this->assertSame(lang('Methods.permissionPageProtected'), $moduleBody['messages']['error'] ?? null);

        $row    = $this->createPermissionPageRow($protectedModuleId, 'Protected Page');
        $pageId = $row['id'];

        $pageResponse = $this->dispatchIndexPost($actorId, ['status' => 'inactive', 'page_id' => $pageId]);

        $this->assertSame(
            403,
            $pageResponse->getStatusCode(),
            'page_id path: a page belonging to a PROTECTED_MODULES module must be refused even for superadmin.',
        );
        $pageBody = json_decode((string) $pageResponse->getBody(), true);
        $this->assertSame(lang('Methods.permissionPageProtected'), $pageBody['messages']['error'] ?? null);

        $moduleAfter = $this->commonModel->selectOne('modules', ['id' => $protectedModuleId]);
        $this->assertSame(
            (int) $moduleBefore->isActive,
            (int) $moduleAfter->isActive,
            'the protected module row must not have been toggled by either path.',
        );
        $pageAfter = $this->commonModel->selectOne('auth_permissions_pages', ['id' => $pageId]);
        $this->assertSame(1, (int) $pageAfter->isActive, 'the protected page row must not have been toggled.');
    }

    // ------------------------------------------------------------------
    // Fixtures
    // ------------------------------------------------------------------

    /**
     * Reads the REAL `auth_permissions_pages` row ModuleScanner registered
     * for $pagename (ModuleScanner.php:51) and runs it through the exact
     * transform production uses to build a permission string
     * (AuthGroups::loadFromDatabase(), Ci4MsAuthFilter::run() -- both call
     * permission_string(strtolower($page->pagename), $action)), instead of
     * typing the result by hand. Verbatim copy of
     * MethodsModuleManagementSuperadminGateTest::pagePermission().
     *
     * @return string
     */
    private function pagePermission(string $pagename, string $action, string $expected): string
    {
        $page = $this->commonModel->selectOne('auth_permissions_pages', ['pagename' => $pagename]);
        $this->assertNotNull(
            $page,
            "precondition: auth_permissions_pages has no row for '{$pagename}' -- the route was renamed, or moduleScan has not run against this schema; update this test to match.",
        );

        $permission = permission_string($page->pagename, $action);
        $this->assertSame(
            $expected,
            $permission,
            "the permission string derived from auth_permissions_pages must equal {$expected}; if this fails, either the pagename/action or permission_string()'s format changed -- update \$expected to match, do not delete this check.",
        );

        return $permission;
    }

    /**
     * Creates a non-superadmin actor and grants them the real route
     * permission via the shield_auth_dynamic_config matrix. Verbatim copy of
     * MethodsModuleManagementSuperadminGateTest::createPrivilegedActor().
     *
     * @return int users.id
     */
    private function createPrivilegedActor(string $permission): int
    {
        $suffix = bin2hex(random_bytes(4));
        $group  = 'qa_permrow_gate_' . $suffix;

        cache()->save('shield_auth_dynamic_config', [
            'groups'      => [$group => ['title' => 'fixture', 'description' => 'fixture']],
            'permissions' => [$permission => 'fixture permission'],
            'matrix'      => [$group => [$permission]],
        ], 300);

        // Label kept short on purpose: users.username is VARCHAR(30)
        // (vendor/codeigniter4/shield/src/Database/Migrations/2020-12-28-223112_create_auth_tables.php:49)
        // and createUser() appends its OWN 9-char suffix on top of this
        // label -- a longer label pushes the final username past 30 chars,
        // which MariaDB truncates/rejects silently inside a transaction
        // (BaseConnection::query() swallows the failure without throwing
        // when $transDepth !== 0 and $transException is false), leaving
        // $this->db->insertID() at 0 and the subsequent find() at null.
        $userId = $this->createUser('prow_actor_' . $suffix);
        $this->addUserToGroup($userId, $group);

        $actor = auth()->getProvider()->findById($userId);
        $this->assertTrue(
            $actor->can($permission),
            "precondition: the fixture actor must really hold {$permission}, otherwise a 403 downstream would be a false positive for the wrong reason.",
        );
        $this->assertFalse($actor->inGroup('superadmin'), 'precondition: the fixture actor must not be a superadmin.');

        return $userId;
    }

    /**
     * Creates (or reuses) the real "superadmin" auth_groups row and a new
     * user who is a member of it. Find-or-create mirrors
     * MethodsModuleManagementSuperadminGateTest::superadminActorId():
     * auth_groups.group carries a UNIQUE index, so a blind create() would
     * collide with a "superadmin" row already present on the shared,
     * non-refreshed ci4ms_test schema.
     *
     * @return int users.id
     */
    private function superadminActorId(): int
    {
        $existingSuperadmin = $this->commonModel->selectOne('auth_groups', ['group' => 'superadmin']);
        if ($existingSuperadmin === null) {
            $this->commonModel->create('auth_groups', [
                'group'       => 'superadmin',
                'description' => 'superadmin',
            ]);
        }

        // Short label, same VARCHAR(30) rationale as createPrivilegedActor().
        $userId = $this->createUser('prow_superadmin');
        $this->addUserToGroup($userId, 'superadmin');

        return $userId;
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
     * Resolves the id of a real `modules` row whose name is in the
     * controller's own PROTECTED_MODULES constant, read via reflection so
     * this fixture can never drift from the production list. Prefers
     * `ci4ms_test` fixture data documented in context.md (Backend/Menu/
     * Methods/Pages/Settings/Users) but does not hardcode an id.
     *
     * @return int modules.id
     */
    private function protectedModuleId(): int
    {
        $protected = (new ReflectionClassConstant(Methods::class, 'PROTECTED_MODULES'))->getValue();

        $row = $this->db->table('modules')->whereIn('name', $protected)->get()->getFirstRow();
        $this->assertNotNull(
            $row,
            'precondition: ci4ms_test must have at least one modules row whose name is in PROTECTED_MODULES.',
        );

        return (int) $row->id;
    }

    /**
     * Creates a fresh `modules` row whose name is guaranteed not to be in
     * PROTECTED_MODULES (random suffix, not a plausible core module name).
     *
     * @return int modules.id
     */
    private function createNonProtectedModule(): int
    {
        return (int) $this->commonModel->create('modules', [
            'name'     => 'PermRowFixtureModule' . bin2hex(random_bytes(4)),
            'icon'     => 'fas fa-cogs',
            'isActive' => true,
        ]);
    }

    /**
     * Creates an auth_permissions_pages row with a unique (className,
     * methodName) pair (unique-key safe, see
     * AddUniqueKeyToAuthPermissionsPages) belonging to $moduleId.
     *
     * @return array{id: int, className: string, methodName: string}
     */
    private function createPermissionPageRow(int $moduleId, string $pagename): array
    {
        $className  = '-Modules-Fixture-Controllers-PermRowGate' . bin2hex(random_bytes(4));
        $methodName = 'show';

        $id = (int) $this->commonModel->create('auth_permissions_pages', [
            'pagename'          => $pagename,
            'description'       => $pagename,
            'className'         => $className,
            'methodName'        => $methodName,
            'sefLink'           => 'permrow-gate-' . bin2hex(random_bytes(4)),
            'hasChild'          => 0,
            'symbol'            => 'fa fa-circle',
            'inNavigation'      => 0,
            'isBackoffice'      => 1,
            'isActive'          => true,
            'typeOfPermissions' => json_encode(['read_r' => true], JSON_UNESCAPED_UNICODE),
            'module_id'         => $moduleId,
        ]);

        return ['id' => $id, 'className' => $className, 'methodName' => $methodName];
    }

    /**
     * @return array<string, mixed>
     */
    private function updatePost(string $className, string $methodName, string $pagename): array
    {
        return [
            'pagename'          => $pagename,
            'description'       => 'desc',
            'className'         => $className,
            'methodName'        => $methodName,
            'sefLink'           => 'permrow-gate-update',
            'symbol'            => 'fa fa-circle',
            'typeOfPermissions' => ['read'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function createPost(string $pagename, ?int $moduleId = null): array
    {
        $post = [
            'pagename'          => $pagename,
            'description'       => 'desc',
            'className'         => '-Modules-Fixture-Controllers-PermRowGateCreate' . bin2hex(random_bytes(4)),
            'methodName'        => 'index',
            'sefLink'           => 'permrow-gate-create-' . bin2hex(random_bytes(4)),
            'symbol'            => 'fa fa-circle',
            'typeOfPermissions' => ['read'],
        ];
        if ($moduleId !== null) {
            $post['moduleName'] = (string) $moduleId;
        }

        return $post;
    }

    /**
     * @param array<string, mixed> $post
     */
    private function dispatchUpdate(int $actorId, int $pk, array $post): ResponseInterface
    {
        $controller = new Methods();
        $this->primeRequest($actorId, $post, $controller);

        return $controller->update($pk);
    }

    /**
     * @param array<string, mixed> $post
     */
    private function dispatchCreate(int $actorId, array $post): ResponseInterface
    {
        $controller = new Methods();
        $this->primeRequest($actorId, $post, $controller);

        return $controller->create();
    }

    /**
     * @param array<string, mixed> $post
     */
    private function dispatchIndexPost(int $actorId, array $post): ResponseInterface
    {
        $controller = new Methods();
        $this->primeRequest($actorId, $post, $controller, true);

        return $controller->index();
    }

    /**
     * Drives a REAL GET request through index() -- unlike dispatchIndexPost(),
     * which primeRequest() always binds to a POST+AJAX request, this
     * exercises the unconditional view-render branch at the bottom of
     * index() (Methods.php:94-97).
     *
     * The controller is instantiated directly, same bypass technique as
     * every other dispatchXxx() in this file, but list.php (extended
     * through Modules\Backend\Views\base) reads several
     * BaseController::initController() keys -- backConfig, logged_in_user,
     * navigation, title, uri -- that a bare `new Methods()` never
     * populates, because initController() itself is never invoked by the
     * bypass pattern. Left unset, $this->defData stays null and the very
     * first line of list.php (`$backConfig->viewLayout`) dereferences an
     * undefined variable; this environment runs with error_reporting(E_ALL)
     * (app/Config/Boot/testing.php:17) and
     * Exceptions::errorHandler() promotes E_WARNING to a thrown
     * ErrorException whenever `error_reporting() & $severity` is non-zero
     * (vendor/codeigniter4/framework/system/Debug/Exceptions.php:211-213),
     * so an incomplete defData does not degrade the render -- it throws.
     * The keys below are seeded with the same values initController()
     * would produce for this route (title.pagename mirrors the real
     * auth_permissions_pages.pagename read back by pagePermission() /
     * Ci4MsAuthFilter for this exact route), except navigation, which is
     * left empty: render_sidebar_menu() (Views/base.php) no-ops on an
     * empty array, and neither list.php nor base.php dereferences
     * $navigation's contents outside that loop, so the render is unaffected.
     * Going through the full HTTP/filter/routing pipeline instead (as
     * MigrationManagerViewRenderTest.php does) was considered and rejected
     * for this file specifically: it would be the only test in this class
     * exercising Ci4MsAuthFilter/session/language singletons that the other
     * eight tests never touch, and mixing that pipeline into a file whose
     * own class docblock justifies the bypass pattern ("the routing/filter
     * layer would add noise without adding coverage") would be inconsistent
     * with the rest of the suite for no benefit here: the guard under test
     * lives entirely inside index()'s own body, not in the filter.
     *
     * @return ResponseInterface
     */
    private function dispatchIndexGet(int $actorId): ResponseInterface
    {
        $controller = new Methods();
        $this->primeRequest($actorId, [], $controller, false, 'GET');

        $controller->defData = [
            'backConfig'     => new BackendConfig(),
            'logged_in_user' => auth()->getProvider()->findById($actorId),
            'navigation'     => [],
            'title'          => (object) ['pagename' => 'Methods.methodList'],
            'uri'            => 'backend/methods',
        ];

        // list.php extends Modules\Backend\Views\base, whose top bar renders
        // Notifications\Cells\BellCell::render() unconditionally
        // (Views/base.php:37) -- BellCell calls
        // $notifier->unreadCount()/listFor(), neither of which setUp()'s
        // FakeDispatchNotifier implements: that double exists only to stub
        // dispatch()'s DB-writing path for create()/update()'s success
        // branch (see its own class docblock -- "The parent constructor is
        // deliberately NOT called: it builds a CommonModel ... this double
        // never needs"), so calling the two read methods on it fatals with
        // "Typed property Notifier::$model must not be accessed before
        // initialization". unreadCount()/listFor() are read-only, so
        // swapping back to the real Notifier for the duration of this one
        // render carries none of the autocommit-write risk the fake was
        // introduced to avoid.
        Services::resetSingle('notifier');

        $result = $controller->index();

        Services::injectMock('notifier', new FakeDispatchNotifier(new CapturingChannel()));

        // index()'s GET branch returns view()'s raw HTML string, not a
        // ResponseInterface (Methods.php:41: @return
        // ResponseInterface|string) -- unlike its POST+AJAX branch, which
        // always answers through failForbidden()/respond(). CodeIgniter::run()
        // normally wraps a plain string return into the response body
        // itself; that step is skipped here because the controller is
        // driven directly, bypassing the framework's own request/response
        // lifecycle (same reason primeRequest() has to build the response
        // object by hand below), so it is reproduced explicitly to keep
        // this helper's return type uniform with the other dispatchXxx()
        // helpers in this file -- and so the mutation drill in Madde 1
        // (moving the superadmin gate to the top of index()) still yields a
        // real ResponseInterface with a real 403 status code to assert on.
        if (is_string($result)) {
            $response = (new ReflectionProperty(\CodeIgniter\Controller::class, 'response'))->getValue($controller);

            return $response->setBody($result);
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $post
     */
    private function primeRequest(int $actorId, array $post, Methods $controller, bool $ajax = false, string $method = 'POST'): void
    {
        $actor = auth()->getProvider()->findById($actorId);
        // testIndexPostRejectsBothProtectedModuleIdAndProtectedPageIdEvenForSuperadmin
        // dispatches twice with the same actor -- Session::login() throws
        // LogicException on a second login attempt while a session is
        // already active, so log out first (a no-op the first time).
        auth('session')->getAuthenticator()->logout();
        $this->actingAs($actor);

        $config  = config(App::class);
        $request = new IncomingRequest($config, new SiteURI($config), null, new UserAgent());
        $request = $request->withMethod($method);
        $request->setGlobal('post', $post);
        $request->setGlobal('request', $post);

        // index()'s toggle branch answers failForbidden() to anything that is
        // not an XHR (Methods.php:43); create()/update() do not require it.
        if ($ajax) {
            $request->setHeader('X-Requested-With', 'XMLHttpRequest');
        }

        $controller->commonModel = $this->commonModel;

        (new ReflectionProperty(\CodeIgniter\Controller::class, 'request'))->setValue($controller, $request);
        (new ReflectionProperty(\CodeIgniter\Controller::class, 'response'))->setValue($controller, service('response', $config, false));
    }
}

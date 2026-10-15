<?php

declare(strict_types=1);

namespace Tests\Modules\Methods;

use ci4commonmodel\CommonModel;
use CodeIgniter\HTTP\Files\FileCollection;
use CodeIgniter\HTTP\Files\UploadedFile;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\HTTP\SiteURI;
use CodeIgniter\HTTP\UserAgent;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Test\AuthenticationTesting;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\App;
use Modules\Methods\Controllers\Methods;
use ReflectionProperty;

/**
 * Behavioural lock for B-4: moduleUpload()/moduleCreate()/moduleDelete()
 * (Methods.php:210, :361, :453) must stay behind the third-layer
 * `auth()->user()->inGroup('superadmin')` gate (Methods.php:223-225,
 * :372-374, :465-467) even for an actor holding the endpoint's own,
 * delegable route permission -- a route filter/permission-matrix bypass
 * alone must never be enough to reach these actions, because an uploaded
 * or created module's Config/Routes.php is `require`d unconditionally on
 * the next request (app/Config/Routes.php module require loop) --
 * i.e. one delegated permission = remote code execution.
 *
 * Six scenarios, one per (endpoint x actor) pair:
 *   testModuleUploadRejectsNonSuperadminWithRealPermission
 *   testModuleUploadClearsGateForSuperadmin
 *   testModuleCreateRejectsNonSuperadminWithRealPermission
 *   testModuleCreateClearsGateForSuperadmin
 *   testModuleDeleteRejectsNonSuperadminWithRealPermission
 *   testModuleDeleteClearsGateForSuperadmin
 *
 * The three non-superadmin scenarios grant the actor the REAL, `methods.`
 * prefixed route permission -- methods.moduleupload.create /
 * methods.modulecreate.create / methods.moduledelete.delete -- read back
 * from the actual `auth_permissions_pages` row ModuleScanner registered for
 * each route (ModuleScanner.php:51: `pagename = {Module}.{route "as"
 * name}`) and run through the same transform production uses
 * (AuthGroups::loadFromDatabase():129-136, Ci4MsAuthFilter::run():63 --
 * both call permission_string(strtolower($page->pagename), $action)),
 * instead of being typed by hand (see pagePermission()). It is granted via
 * the same shield_auth_dynamic_config matrix fixture
 * UserControllerDelegationCeilingTest::primeAuthGroupsMatrix() uses, and
 * assert $actor->can($permission) is true as a precondition. That
 * precondition is the difference between this suite proving the
 * superadmin gate works and it accidentally proving the permission
 * filter works for the wrong reason (a false-positive green if the
 * permission string were wrong) -- deriving it from auth_permissions_pages
 * instead of hardcoding it means a renamed route or changed pagename
 * breaks this test loudly instead of leaving the fixture silently out of
 * sync with production.
 *
 * The two "clears gate" scenarios do not have to drive the full
 * upload/create success path -- only that the response is not the
 * gate's 403. moduleUpload() is proven past the gate with a file that
 * exists on disk but was never registered as an HTTP upload by PHP, so
 * UploadedFile::isValid() (which calls is_uploaded_file()) fails
 * naturally and the controller falls through to invalidZipFile --
 * proof the gate did not fire, without ever touching ZipSecurityValidator
 * or extracting anything. moduleCreate() is proven past the gate by
 * posting the name of a module that already exists on disk (`Methods`
 * itself), so moduleAlreadyExists() short-circuits before make:module is
 * ever invoked -- no file is written. moduleDelete()'s "clears gate"
 * scenario reuses the exact HARD SAFETY GATE fixture
 * MethodsRbacCacheInvalidationTest::testModuleDeleteFlushesRbacCaches()
 * already relies on: a `modules` row pointing at a name with no
 * directory under modules/, so removeModuleFiles() short-circuits to
 * success (ModuleInstaller.php:247) without ever deleting real files.
 *
 * The controller is instantiated directly and driven through
 * ReflectionProperty, the same technique the sibling
 * MethodsRbacCacheInvalidationTest / MethodsCreateModuleIdTest suites use.
 *
 * @internal
 */
final class MethodsModuleManagementSuperadminGateTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use AuthenticationTesting;

    protected $refresh = false;

    protected $migrateOnce = true;

    protected $namespace = null;

    /**
     * Verbatim copy of Language/en/Methods.php's `moduleManagementForbidden`
     * text. Asserted literally instead of via a second lang() call, so a
     * key deleted or renamed in every locale breaks this test loudly -- CI4
     * returns the raw key string when a key is missing, so comparing
     * lang() against lang() would stay green in that case.
     */
    private const EXPECTED_FORBIDDEN_MESSAGE = 'You do not have permission to perform module management operations.';

    /**
     * Fixed module name the moduleCreate non-superadmin scenario posts.
     * Cleaned up in tearDown() regardless of whether the gate held, because
     * a regressed gate makes moduleCreate() really run `make:module` and
     * write this directory to disk.
     */
    private const QA_CREATE_MODULE_NAME = 'QaShouldNeverExist';

    private CommonModel $commonModel;

    /**
     * Temp files created by makeUnusableUpload(), deleted in tearDown()
     * regardless of whether the controller ever reads them.
     *
     * @var list<string>
     */
    private array $scratchPaths = [];

    protected function setUp(): void
    {
        parent::setUp();
        helper(['Modules\Backend\Helpers\ci4ms', 'filesystem']);

        $this->assertStringContainsString(
            'ci4ms_test',
            (string) \Config\Database::connect()->database,
            'Refusing to run: the active connection is not pointed at the test database.',
        );

        \Config\Services::resetSingle('validation');
        service('routes')->loadRoutes();

        cache()->clean();

        $this->commonModel = new CommonModel('tests');

        $this->db->transStart();
    }

    protected function tearDown(): void
    {
        $this->db->transRollback();

        foreach ($this->scratchPaths as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }
        $this->scratchPaths = [];

        // Defense-in-depth: if the superadmin gate did not hold (e.g. the
        // control experiment that proves this suite actually exercises the
        // gate), moduleCreate() really ran `make:module` and wrote this
        // directory to disk -- outside the DB transaction, so
        // transRollback() above cannot undo it. Clean it up so a broken
        // gate does not leave a permanent module directory that
        // app/Config/Routes.php's module require loop then `require`s on
        // every subsequent request.
        $qaModulePath = ROOTPATH . 'modules/' . self::QA_CREATE_MODULE_NAME;
        if (is_dir($qaModulePath)) {
            delete_files($qaModulePath, true);
            @rmdir($qaModulePath);
        }

        parent::tearDown();
    }

    // ------------------------------------------------------------------
    // moduleUpload()
    // ------------------------------------------------------------------

    /**
     * A non-superadmin actor holding the real moduleupload.create
     * permission must still be rejected by the third-layer gate.
     *
     * @return void
     */
    public function testModuleUploadRejectsNonSuperadminWithRealPermission(): void
    {
        $permission = $this->pagePermission('Methods.moduleUpload', 'create', 'methods.moduleupload.create');
        $actorId    = $this->createPrivilegedActor($permission);

        $response = $this->dispatchModuleUpload($actorId, null);

        $this->assertSame(403, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertSame(self::EXPECTED_FORBIDDEN_MESSAGE, $body['messages']['error'] ?? null);
    }

    /**
     * A superadmin must clear the gate; the fixture upload is deliberately
     * unusable so the assertion is that the NEXT real check (isValid())
     * is what rejects it, not the gate.
     *
     * @return void
     */
    public function testModuleUploadClearsGateForSuperadmin(): void
    {
        $actorId = $this->superadminActorId();

        $response = $this->dispatchModuleUpload($actorId, $this->makeUnusableUpload());

        $this->assertNotSame(
            403,
            $response->getStatusCode(),
            'a superadmin must not be blocked by the module-management gate.',
        );
        $body = json_decode((string) $response->getBody(), true);
        $this->assertSame(
            lang('Methods.invalidZipFile'),
            $body['message'] ?? null,
            'precondition: the gate must have been cleared -- rejection must come from isValid(), which the fixture upload deliberately fails (never registered as an HTTP upload), not from the superadmin gate.',
        );
    }

    // ------------------------------------------------------------------
    // moduleCreate()
    // ------------------------------------------------------------------

    /**
     * A non-superadmin actor holding the real modulecreate.create
     * permission must still be rejected by the third-layer gate.
     *
     * @return void
     */
    public function testModuleCreateRejectsNonSuperadminWithRealPermission(): void
    {
        $permission = $this->pagePermission('Methods.moduleCreate', 'create', 'methods.modulecreate.create');
        $actorId    = $this->createPrivilegedActor($permission);
        $modulePath = ROOTPATH . 'modules/' . self::QA_CREATE_MODULE_NAME;

        // HARD SAFETY GATE, same pattern as the moduleDelete scenarios
        // below: moduleCreate() really runs `make:module` and writes a new
        // module's files to disk when the gate does not hold. This test
        // expects the gate to reject the request before that ever happens,
        // but the assertion is kept as a non-negotiable backstop
        // regardless -- and BEFORE dispatch, so a regressed gate is caught
        // before it can write a real module directory that
        // app/Config/Routes.php's module require loop then `require`s on
        // every subsequent request.
        $this->assertDirectoryDoesNotExist(
            $modulePath,
            'Refusing to run: moduleCreate() would write real files under ' . $modulePath,
        );

        $response = $this->dispatchModuleCreate($actorId, ['module_name' => self::QA_CREATE_MODULE_NAME]);

        $this->assertSame(403, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertSame(self::EXPECTED_FORBIDDEN_MESSAGE, $body['messages']['error'] ?? null);
        $this->assertDirectoryDoesNotExist(
            $modulePath,
            'a rejected request must never reach make:module.',
        );
    }

    /**
     * A superadmin must clear the gate. Posting the name of a module that
     * already exists on disk (`Methods` itself) makes moduleAlreadyExists()
     * short-circuit before make:module is ever invoked, so this proves the
     * gate cleared without writing a single file.
     *
     * @return void
     */
    public function testModuleCreateClearsGateForSuperadmin(): void
    {
        $actorId = $this->superadminActorId();

        $this->assertDirectoryExists(ROOTPATH . 'modules/Methods', 'test setup: the Methods module itself must exist on disk.');

        $response = $this->dispatchModuleCreate($actorId, ['module_name' => 'methods']);

        $this->assertNotSame(
            403,
            $response->getStatusCode(),
            'a superadmin must not be blocked by the module-management gate.',
        );
        $body = json_decode((string) $response->getBody(), true);
        $this->assertSame(
            lang('Methods.moduleAlreadyExists'),
            $body['message'] ?? null,
            'precondition: the gate must have been cleared -- rejection must come from the already-exists check, not the superadmin gate.',
        );
    }

    // ------------------------------------------------------------------
    // moduleDelete()
    // ------------------------------------------------------------------

    /**
     * A non-superadmin actor holding the real moduledelete.delete
     * permission must still be rejected by the third-layer gate.
     *
     * @return void
     */
    public function testModuleDeleteRejectsNonSuperadminWithRealPermission(): void
    {
        $permission = $this->pagePermission('Methods.moduleDelete', 'delete', 'methods.moduledelete.delete');
        $actorId    = $this->createPrivilegedActor($permission);
        $moduleName = 'QaGateNonAdmin' . bin2hex(random_bytes(4));
        $modulePath = ROOTPATH . 'modules/' . $moduleName;

        // HARD SAFETY GATE, copied from
        // MethodsRbacCacheInvalidationTest::testModuleDeleteFlushesRbacCaches:
        // moduleDelete() really deletes modules/<name>/ recursively when the
        // directory exists. This test expects the gate to reject the request
        // before that logic is ever reached, but the assertion is kept as a
        // non-negotiable backstop regardless.
        $this->assertDirectoryDoesNotExist(
            $modulePath,
            'Refusing to run: moduleDelete() would delete real files under ' . $modulePath,
        );

        $moduleId = (int) $this->commonModel->create('modules', [
            'name'     => $moduleName,
            'icon'     => 'fas fa-cogs',
            'isActive' => true,
        ]);

        $response = $this->dispatchModuleDelete($actorId, [
            'module_id'    => $moduleId,
            'confirm_name' => $moduleName,
        ]);

        $this->assertSame(403, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertSame(self::EXPECTED_FORBIDDEN_MESSAGE, $body['messages']['error'] ?? null);
        $this->assertNotNull(
            $this->commonModel->selectOne('modules', ['id' => $moduleId]),
            'precondition: a rejected request must not have deleted the modules row.',
        );
        $this->assertDirectoryDoesNotExist($modulePath, 'the fixture module directory must still not exist.');
    }

    /**
     * A superadmin must clear the gate and reach moduleDelete()'s success
     * branch. Same HARD SAFETY GATE fixture as
     * MethodsRbacCacheInvalidationTest::testModuleDeleteFlushesRbacCaches:
     * a `modules` row with no directory on disk, so removeModuleFiles()
     * short-circuits to success (ModuleInstaller.php:247) and no real file
     * is ever deleted.
     *
     * @return void
     */
    public function testModuleDeleteClearsGateForSuperadmin(): void
    {
        $actorId    = $this->superadminActorId();
        $moduleName = 'QaGateSuperadmin' . bin2hex(random_bytes(4));
        $modulePath = ROOTPATH . 'modules/' . $moduleName;

        $this->assertDirectoryDoesNotExist(
            $modulePath,
            'Refusing to run: moduleDelete() would delete real files under ' . $modulePath,
        );

        $moduleId = (int) $this->commonModel->create('modules', [
            'name'     => $moduleName,
            'icon'     => 'fas fa-cogs',
            'isActive' => true,
        ]);

        $response = $this->dispatchModuleDelete($actorId, [
            'module_id'    => $moduleId,
            'confirm_name' => $moduleName,
        ]);

        $body = json_decode((string) $response->getBody(), true);
        $this->assertSame(
            'success',
            $body['status'] ?? null,
            'a superadmin must clear the module-management gate and reach moduleDelete()\'s success branch.',
        );
        $this->assertNull($this->commonModel->selectOne('modules', ['id' => $moduleId]));
        $this->assertDirectoryDoesNotExist($modulePath, 'the fixture module directory must still not exist afterwards.');
    }

    // ------------------------------------------------------------------
    // Fixtures
    // ------------------------------------------------------------------

    /**
     * Reads the REAL `auth_permissions_pages` row ModuleScanner registered
     * for $pagename (ModuleScanner.php:51) and runs it through the exact
     * transform production uses to build a permission string
     * (AuthGroups::loadFromDatabase():129-136, Ci4MsAuthFilter::run():63 --
     * both call permission_string(strtolower($page->pagename), $action)),
     * instead of typing the result by hand.
     *
     * Deriving from auth_permissions_pages, on its own, only protects
     * against a renamed route/pagename (the row lookup fails loudly instead
     * of silently keeping a stale hand-typed string). It does NOT protect
     * against a corrupted transform -- e.g. a `methods.` prefix dropped by
     * a future edit to this fixture -- because nothing else in these tests
     * exercises $actor->can() with this exact string (the controller gate
     * under test checks inGroup('superadmin'), not can()). The $expected
     * equality check closes that gap.
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
     * permission via the shield_auth_dynamic_config matrix -- the exact
     * mechanism Ci4MsAuthFilter/Authorizable::can() read in production
     * (Modules\Auth\Config\AuthGroups::loadFromDatabase(),
     * permission_string() = strtolower($pagename) . '.' . $action). Same
     * technique as UserControllerDelegationCeilingTest::primeAuthGroupsMatrix().
     * $permission must come from pagePermission(), never be typed by hand
     * -- see that method's docblock for why.
     *
     * Asserts $actor->can($permission) as a precondition, so a caller that
     * later reads 403 from the controller cannot blame a missing or
     * misspelled permission string instead of the gate.
     *
     * @return int users.id
     */
    private function createPrivilegedActor(string $permission): int
    {
        $suffix = bin2hex(random_bytes(4));
        $group  = 'qa_gate_' . $suffix;

        cache()->save('shield_auth_dynamic_config', [
            'groups'      => [$group => ['title' => 'fixture', 'description' => 'fixture']],
            'permissions' => [$permission => 'fixture permission'],
            'matrix'      => [$group => [$permission]],
        ], 300);

        $userId = $this->createUser('gate_actor_' . $suffix);
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
     * PermgroupMalformedPermsPayloadTest::superadminActorId(): auth_groups.group
     * carries a UNIQUE index, so a blind create() would collide with a
     * "superadmin" row already present on the shared, non-refreshed
     * ci4ms_test schema.
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

        $userId = $this->createUser('gate_superadmin');
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
     * A real file on disk, wrapped in a real UploadedFile, that was never
     * registered as an HTTP upload by PHP -- so UploadedFile::isValid()
     * (which calls is_uploaded_file()) fails naturally, without needing to
     * override any UploadedFile method.
     */
    private function makeUnusableUpload(): UploadedFile
    {
        $marker = bin2hex(random_bytes(4));
        $path   = sys_get_temp_dir() . '/qa_gate_upload_' . $marker . '.zip';
        file_put_contents($path, "not a real zip\n");
        $this->scratchPaths[] = $path;

        return new UploadedFile($path, 'qa_gate_' . $marker . '.zip', 'application/zip', (int) filesize($path), UPLOAD_ERR_OK);
    }

    private function dispatchModuleUpload(int $actorId, ?UploadedFile $file): ResponseInterface
    {
        $controller = new Methods();
        $this->primeRequest($actorId, [], $controller, $file);

        return $controller->moduleUpload();
    }

    /**
     * @param array<string, mixed> $post
     */
    private function dispatchModuleCreate(int $actorId, array $post): ResponseInterface
    {
        $controller = new Methods();
        $this->primeRequest($actorId, $post, $controller);

        return $controller->moduleCreate();
    }

    /**
     * @param array<string, mixed> $post
     */
    private function dispatchModuleDelete(int $actorId, array $post): ResponseInterface
    {
        $controller = new Methods();
        $this->primeRequest($actorId, $post, $controller);

        return $controller->moduleDelete();
    }

    /**
     * @param array<string, mixed> $post
     */
    private function primeRequest(int $actorId, array $post, Methods $controller, ?UploadedFile $file = null): void
    {
        $actor = auth()->getProvider()->findById($actorId);
        $this->actingAs($actor);

        $config  = config(App::class);
        $request = new IncomingRequest($config, new SiteURI($config), null, new UserAgent());
        $request = $request->withMethod('POST');
        $request->setGlobal('post', $post);
        $request->setGlobal('request', $post);

        // All three endpoints under test answer failForbidden() to anything
        // that is not an XHR (Methods.php:212, :363, :455).
        $request->setHeader('X-Requested-With', 'XMLHttpRequest');

        if ($file !== null) {
            $collection = new FileCollection();
            (new ReflectionProperty(FileCollection::class, 'files'))->setValue($collection, ['modules' => $file]);
            (new ReflectionProperty(IncomingRequest::class, 'files'))->setValue($request, $collection);
        }

        $controller->commonModel = $this->commonModel;

        (new ReflectionProperty(\CodeIgniter\Controller::class, 'request'))->setValue($controller, $request);
        (new ReflectionProperty(\CodeIgniter\Controller::class, 'response'))->setValue($controller, service('response', $config, false));
    }
}

<?php

declare(strict_types=1);

namespace Tests\Modules\Methods;

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
use Modules\Methods\Controllers\Methods;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClassConstant;
use ReflectionProperty;

/**
 * Regression suite for GHSA-5j8q (typeOfPermissions write-side type contract).
 *
 * `Methods::create():152` / `update():240` read
 * `$this->request->getPost('typeOfPermissions')` and pass it straight into
 * `in_array('create', $roles)` (and update()/create() variants for
 * update/read/delete). Before `validationRules()` gained the
 * `permissionActionList` rule, the only rule on this field was `required`,
 * which accepts any non-empty scalar -- a POST of `typeOfPermissions=5` or
 * `typeOfPermissions=create` reached `in_array()` unchanged and PHP 8 raises
 * a `TypeError` there because `in_array()`'s second argument must be an
 * array. This suite locks the fix
 * (`modules/Backend/Validation/CustomRules.php::permissionActionList()` +
 * `Methods::validationRules():653`): a scalar, an empty array, or an array
 * containing a value outside {create, read, update, delete} must now be
 * rejected by `validate()` with a normal validation-error redirect, never a
 * TypeError, and must never reach the database write. A last test confirms
 * the ordinary array flow (already covered by
 * `MethodsCollisionGuardTest`) still works, so this file cannot regress into
 * "rejects everything".
 *
 * Same driving technique as `MethodsCollisionGuardTest`: the controller is
 * instantiated directly and driven through ReflectionProperty, bypassing
 * routing/filters. Because there is no HTTP kernel in the way, an
 * uncaught TypeError surfaces to PHPUnit as an ERROR, not a translated
 * HTTP 500 -- which is exactly what the mutation-testing control experiment
 * in this task's evidence log relies on to prove these tests are red
 * without the fix.
 *
 * @internal
 */
final class MethodsTypeOfPermissionsValidationTest extends CIUnitTestCase
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

        $this->commonModel = new CommonModel('tests');

        // Same fixture precondition as MethodsCollisionGuardTest::setUp() --
        // update() tests below create their row with module_id=2 and rely on
        // that id resolving to "Backup", a module NOT in
        // Methods::PROTECTED_MODULES. If either drifts, update()'s
        // PROTECTED_MODULES gate would 403 every update() test in this file
        // silently, instead of exercising the typeOfPermissions validation
        // path under test.
        $fixtureModule = $this->commonModel->selectOne('modules', ['id' => 2]);
        $this->assertNotNull($fixtureModule, 'Fixture precondition: modules.id=2 must exist in ci4ms_test.');
        $this->assertSame('Backup', $fixtureModule->name, 'Fixture precondition: modules.id=2 must be the non-protected "Backup" module.');

        $protectedModules = (new ReflectionClassConstant(Methods::class, 'PROTECTED_MODULES'))->getValue();
        $this->assertNotContains(
            $fixtureModule->name,
            $protectedModules,
            "Fixture precondition: '{$fixtureModule->name}' (modules.id=2) must NOT be in Methods::PROTECTED_MODULES.",
        );

        $this->db->transStart();
    }

    protected function tearDown(): void
    {
        $this->db->transRollback();

        parent::tearDown();
    }

    /**
     * A scalar `typeOfPermissions` POST (e.g. `5` or `create`) must be
     * turned away by validate() -- not reach `in_array()` and TypeError.
     *
     * `moduleName` is deliberately supplied and valid: create() resolves
     * `moduleName` to `module_id` (resolveModuleId()) and redirects with a
     * *different* error before ever reading `typeOfPermissions` if that
     * resolution fails. Without a valid `moduleName` here, the mutation
     * control experiment for this test would stay green even with the fix
     * reverted -- the request would be rejected for the wrong reason
     * (missing module) before ever reaching the vulnerable line, masking
     * the very regression this test exists to catch.
     *
     * @param string $scalarValue Scalar value posted as typeOfPermissions.
     */
    #[DataProvider('scalarTypeOfPermissionsValues')]
    public function testCreateRejectsScalarTypeOfPermissionsWithoutThrowing(string $scalarValue): void
    {
        $pagename = 'qa_typeofperms_create_scalar_' . bin2hex(random_bytes(4));
        $before   = $this->commonModel->count('auth_permissions_pages');

        $response = $this->dispatchCreate($this->createActor(), [
            'pagename'          => $pagename,
            'sefLink'           => 'qa-typeofperms-create-scalar-' . bin2hex(random_bytes(2)),
            'className'         => '',
            'methodName'        => '',
            'moduleName'        => 1,
            'typeOfPermissions' => $scalarValue,
        ]);

        $this->assertInstanceOf(ResponseInterface::class, $response, 'create() must return a response, not throw.');
        $this->assertGreaterThanOrEqual(300, $response->getStatusCode(), 'a validation failure must redirect, not render 200.');
        $this->assertLessThan(400, $response->getStatusCode());
        $this->assertSame(
            $before,
            $this->commonModel->count('auth_permissions_pages'),
            'create() must not write a row when typeOfPermissions fails validation.',
        );
        $this->assertNull($this->commonModel->selectOne('auth_permissions_pages', ['pagename' => $pagename]));
    }

    /**
     * Same contract as the create() case above, for update() -- update()
     * reads `typeOfPermissions` at line 240, immediately after the
     * className/methodName collision guard, with no moduleName involved.
     *
     * @param string $scalarValue Scalar value posted as typeOfPermissions.
     */
    #[DataProvider('scalarTypeOfPermissionsValues')]
    public function testUpdateRejectsScalarTypeOfPermissionsWithoutThrowing(string $scalarValue): void
    {
        $className  = '-Modules-Fixture-Controllers-QAScalar' . bin2hex(random_bytes(3));
        $methodName = 'index';
        $pk         = $this->createPermissionPageRow($className, $methodName, 'QA Scalar Old Name');

        $response = $this->dispatchUpdate($this->createActor(), $pk, [
            'pagename'          => 'QA Scalar New Name',
            'sefLink'           => 'qa-typeofperms-update-scalar',
            'className'         => $className,
            'methodName'        => $methodName,
            'typeOfPermissions' => $scalarValue,
        ]);

        $this->assertInstanceOf(ResponseInterface::class, $response, 'update() must return a response, not throw.');
        $this->assertGreaterThanOrEqual(300, $response->getStatusCode());
        $this->assertLessThan(400, $response->getStatusCode());

        $after = $this->commonModel->selectOne('auth_permissions_pages', ['id' => $pk]);
        $this->assertNotNull($after, 'row must not disappear.');
        $this->assertSame('QA Scalar Old Name', $after->pagename, 'update() must not persist any change when typeOfPermissions fails validation.');
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function scalarTypeOfPermissionsValues(): array
    {
        return [
            'numeric string' => ['5'],
            'plain word string' => ['create'],
        ];
    }

    /**
     * An empty array carries no valid action and must be rejected the same
     * way a scalar is -- Ci4MsAuthFilter::neededAction() would otherwise
     * fall back to its 'read' default for it, same as for a non-array
     * value.
     *
     * Note: this is actually exercising CI4's built-in `required` rule, not
     * `permissionActionList`'s own `$value === []` branch --
     * `Rules::required()` (vendor/codeigniter4/framework/system/Validation/
     * Rules.php:319-333) already treats an array via `$str !== []`, so an
     * empty array fails `required` before `permissionActionList` ever runs.
     * This test would stay green even if `permissionActionList`'s own
     * empty-array check were deleted entirely -- it locks the end-to-end
     * "empty array is rejected" contract, not the specific rule that
     * currently enforces it.
     */
    public function testCreateRejectsEmptyArrayTypeOfPermissions(): void
    {
        $pagename = 'qa_typeofperms_create_empty_' . bin2hex(random_bytes(4));
        $before   = $this->commonModel->count('auth_permissions_pages');

        $response = $this->dispatchCreate($this->createActor(), [
            'pagename'          => $pagename,
            'sefLink'           => 'qa-typeofperms-create-empty',
            'className'         => '',
            'methodName'        => '',
            'moduleName'        => 1,
            'typeOfPermissions' => [],
        ]);

        $this->assertNotSame(200, $response->getStatusCode(), 'a validation failure must redirect, not render 200.');
        $this->assertSame($before, $this->commonModel->count('auth_permissions_pages'));
        $this->assertNull($this->commonModel->selectOne('auth_permissions_pages', ['pagename' => $pagename]));
    }

    /**
     * Same empty-array contract for update().
     *
     * Note: like the create() case above, this exercises CI4's built-in
     * `required` rule (`Rules::required()` already rejects `[]` via
     * `$str !== []`), not `permissionActionList`'s own empty-array branch --
     * it would stay green even if that branch were removed from the rule.
     */
    public function testUpdateRejectsEmptyArrayTypeOfPermissions(): void
    {
        $className  = '-Modules-Fixture-Controllers-QAEmpty' . bin2hex(random_bytes(3));
        $methodName = 'index';
        $pk         = $this->createPermissionPageRow($className, $methodName, 'QA Empty Old Name');

        $response = $this->dispatchUpdate($this->createActor(), $pk, [
            'pagename'          => 'QA Empty New Name',
            'sefLink'           => 'qa-typeofperms-update-empty',
            'className'         => $className,
            'methodName'        => $methodName,
            'typeOfPermissions' => [],
        ]);

        $this->assertNotSame(200, $response->getStatusCode());
        $after = $this->commonModel->selectOne('auth_permissions_pages', ['id' => $pk]);
        $this->assertNotNull($after);
        $this->assertSame('QA Empty Old Name', $after->pagename, 'update() must not persist any change when typeOfPermissions is an empty array.');
    }

    /**
     * A value outside the {create, read, update, delete} whitelist must be
     * rejected, even when mixed in with otherwise-valid keywords -- the old
     * code only ever checked whether specific keywords were *present* via
     * in_array(), it never rejected extraneous garbage.
     */
    public function testCreateRejectsDisallowedActionKeyword(): void
    {
        $pagename = 'qa_typeofperms_create_bad_' . bin2hex(random_bytes(4));
        $before   = $this->commonModel->count('auth_permissions_pages');

        $response = $this->dispatchCreate($this->createActor(), [
            'pagename'          => $pagename,
            'sefLink'           => 'qa-typeofperms-create-bad',
            'className'         => '',
            'methodName'        => '',
            'moduleName'        => 1,
            'symbol'            => 'fa fa-circle',
            'typeOfPermissions' => ['create', 'DROP TABLE'],
        ]);

        $this->assertNotSame(200, $response->getStatusCode());
        $this->assertSame($before, $this->commonModel->count('auth_permissions_pages'));
        $this->assertNull($this->commonModel->selectOne('auth_permissions_pages', ['pagename' => $pagename]));
    }

    /**
     * Same disallowed-keyword contract for update().
     */
    public function testUpdateRejectsDisallowedActionKeyword(): void
    {
        $className  = '-Modules-Fixture-Controllers-QABad' . bin2hex(random_bytes(3));
        $methodName = 'index';
        $pk         = $this->createPermissionPageRow($className, $methodName, 'QA Bad Old Name');

        $response = $this->dispatchUpdate($this->createActor(), $pk, [
            'pagename'          => 'QA Bad New Name',
            'sefLink'           => 'qa-typeofperms-update-bad',
            'className'         => $className,
            'methodName'        => $methodName,
            'symbol'            => 'fa fa-circle',
            'typeOfPermissions' => ['create', 'DROP TABLE'],
        ]);

        $this->assertNotSame(200, $response->getStatusCode());
        $after = $this->commonModel->selectOne('auth_permissions_pages', ['id' => $pk]);
        $this->assertNotNull($after);
        $this->assertSame('QA Bad Old Name', $after->pagename, 'update() must not persist any change when typeOfPermissions contains a disallowed keyword.');
    }

    /**
     * Regression guard: a normal, fully-valid array must still be accepted
     * by create() -- this file must not turn into a "rejects everything"
     * suite that would mask a rule that is too strict. Mirrors
     * MethodsCollisionGuardTest::testCreatePersistsTypeOfPermissionsAsValidJson().
     */
    public function testCreateAcceptsOrdinaryArrayTypeOfPermissions(): void
    {
        $pagename = 'qa_typeofperms_create_ok_' . bin2hex(random_bytes(4));

        $response = $this->dispatchCreate($this->createActor(), [
            'pagename'          => $pagename,
            'sefLink'           => 'qa-typeofperms-create-ok',
            'className'         => '-Modules-Fixture-Controllers-QAOk' . bin2hex(random_bytes(3)),
            'methodName'        => 'index',
            'moduleName'        => 1,
            'symbol'            => 'fa fa-circle',
            'typeOfPermissions' => ['create', 'read'],
        ]);

        $this->assertSame(302, $response->getStatusCode(), 'a successful create() redirects to methodList.');

        $created = $this->commonModel->selectOne('auth_permissions_pages', ['pagename' => $pagename]);
        $this->assertNotNull($created, 'create() must have written the row when typeOfPermissions is a valid array.');

        $decoded = json_decode($created->typeOfPermissions, true);
        $this->assertSame(
            [
                'create_r' => true,
                'update_r' => false,
                'read_r'   => true,
                'delete_r' => false,
            ],
            $decoded,
        );
    }

    /**
     * Same acceptance contract for update().
     */
    public function testUpdateAcceptsOrdinaryArrayTypeOfPermissions(): void
    {
        $className  = '-Modules-Fixture-Controllers-QAOkUpd' . bin2hex(random_bytes(3));
        $methodName = 'index';
        $pk         = $this->createPermissionPageRow($className, $methodName, 'QA Ok Old Name');

        $response = $this->dispatchUpdate($this->createActor(), $pk, [
            'pagename'          => 'QA Ok New Name',
            'sefLink'           => 'qa-typeofperms-update-ok',
            'className'         => $className,
            'methodName'        => $methodName,
            'symbol'            => 'fa fa-circle',
            'typeOfPermissions' => ['delete'],
        ]);

        $this->assertSame(302, $response->getStatusCode(), 'a successful update() redirects to methodList.');

        $after = $this->commonModel->selectOne('auth_permissions_pages', ['id' => $pk]);
        $this->assertNotNull($after);
        $this->assertSame('QA Ok New Name', $after->pagename);

        $decoded = json_decode($after->typeOfPermissions, true);
        $this->assertSame(
            [
                'create_r' => false,
                'update_r' => false,
                'read_r'   => false,
                'delete_r' => true,
            ],
            $decoded,
        );
    }

    /**
     * Regression guard for the write/consume source mismatch fixed by
     * changing create()'s `$this->validate($this->validationRules())` to
     * `$this->validateData($this->request->getPost(), $this->validationRules())`.
     *
     * Before that fix, validate() sourced its data from $_REQUEST (query
     * string + POST -- see Validation::withRequest() ->
     * IncomingRequest::getVar() -> fetchGlobal('request')), while the
     * field's consumption (Methods.php:152,
     * `$this->request->getPost('typeOfPermissions')`) only ever reads
     * $_POST. A request carrying `typeOfPermissions` solely in the query
     * string -- absent from the POST body -- would pass validate() (the
     * `required` rule saw the query-string value) and then reach
     * `in_array('create', $roles)` with `$roles === null`, a TypeError.
     * This test locks the fix: a POST body missing `typeOfPermissions`
     * must be rejected the same way regardless of what a query string
     * carries for the same key, and must never throw.
     */
    public function testCreateRejectsTypeOfPermissionsSuppliedOnlyViaQueryString(): void
    {
        $pagename = 'qa_typeofperms_create_qsonly_' . bin2hex(random_bytes(4));
        $before   = $this->commonModel->count('auth_permissions_pages');

        $response = $this->dispatchCreateWithQueryStringOnlyTypeOfPermissions(
            $this->createActor(),
            [
                'pagename'   => $pagename,
                'sefLink'    => 'qa-typeofperms-create-qsonly-' . bin2hex(random_bytes(2)),
                'className'  => '',
                'methodName' => '',
                'moduleName' => 1,
                // typeOfPermissions deliberately absent from $_POST.
            ],
            ['typeOfPermissions' => ['read']],
        );

        $this->assertInstanceOf(ResponseInterface::class, $response, 'create() must return a response, not throw.');
        $this->assertGreaterThanOrEqual(300, $response->getStatusCode(), 'a validation failure must redirect, not render 200.');
        $this->assertLessThan(400, $response->getStatusCode());
        $this->assertSame(
            $before,
            $this->commonModel->count('auth_permissions_pages'),
            'create() must not write a row when typeOfPermissions is missing from the POST body.',
        );
        $this->assertNull($this->commonModel->selectOne('auth_permissions_pages', ['pagename' => $pagename]));
    }

    /**
     * Same write/consume source-mismatch contract for update() -- mirrors
     * testCreateRejectsTypeOfPermissionsSuppliedOnlyViaQueryString() above.
     * `symbol` is deliberately supplied and valid: without the fix,
     * validate() would have passed on the query-string value and execution
     * would throw a TypeError at `in_array('create', $roles)` (line 242)
     * before ever reaching the `symbol` column write -- so `symbol`'s
     * presence has no bearing on whether the fix is exercised here, but it
     * is included anyway so this test cannot coincidentally pass for the
     * unrelated reason documented in Task A (a NULL `symbol` write failing
     * silently) if the code path under test ever changes.
     */
    public function testUpdateRejectsTypeOfPermissionsSuppliedOnlyViaQueryString(): void
    {
        $className  = '-Modules-Fixture-Controllers-QAQsOnly' . bin2hex(random_bytes(3));
        $methodName = 'index';
        $pk         = $this->createPermissionPageRow($className, $methodName, 'QA QsOnly Old Name');

        $response = $this->dispatchUpdateWithQueryStringOnlyTypeOfPermissions(
            $this->createActor(),
            $pk,
            [
                'pagename'   => 'QA QsOnly New Name',
                'sefLink'    => 'qa-typeofperms-update-qsonly',
                'className'  => $className,
                'methodName' => $methodName,
                'symbol'     => 'fa fa-circle',
                // typeOfPermissions deliberately absent from $_POST.
            ],
            ['typeOfPermissions' => ['read']],
        );

        $this->assertInstanceOf(ResponseInterface::class, $response, 'update() must return a response, not throw.');
        $this->assertGreaterThanOrEqual(300, $response->getStatusCode());
        $this->assertLessThan(400, $response->getStatusCode());

        $after = $this->commonModel->selectOne('auth_permissions_pages', ['id' => $pk]);
        $this->assertNotNull($after, 'row must not disappear.');
        $this->assertSame('QA QsOnly Old Name', $after->pagename, 'update() must not persist any change when typeOfPermissions is missing from the POST body.');
    }

    /**
     * Creates an actor with superadmin membership -- create()/update() gate
     * on inGroup('superadmin') before reaching validate(). Mirrors
     * MethodsCollisionGuardTest::createActor().
     */
    private function createActor(): int
    {
        $suffix = bin2hex(random_bytes(4));

        $users = auth()->getProvider();
        $user  = new User([
            'firstname' => 'Test',
            'surname'   => 'User',
            'username'  => 'qa_typeofperms_actor_' . $suffix,
            'email'     => 'qa_typeofperms_actor_' . $suffix . '@example.test',
            'password'  => 'SuperSecret123!',
            'active'    => 1,
        ]);
        $users->save($user);

        $userId = (int) $users->getInsertID();

        $existingSuperadmin = $this->commonModel->selectOne('auth_groups', ['group' => 'superadmin']);
        if ($existingSuperadmin === null) {
            $this->commonModel->create('auth_groups', [
                'group'       => 'superadmin',
                'description' => 'superadmin',
            ]);
        }

        $this->commonModel->create('auth_groups_users', [
            'user_id'    => $userId,
            'group'      => 'superadmin',
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return $userId;
    }

    private function createPermissionPageRow(string $className, string $methodName, string $pagename): int
    {
        return (int) $this->commonModel->create('auth_permissions_pages', [
            'pagename'          => $pagename,
            'description'       => $pagename,
            'className'         => $className,
            'methodName'        => $methodName,
            'sefLink'           => strtolower(str_replace([' ', '_'], '-', $pagename)),
            'hasChild'          => 0,
            'symbol'            => 'fa fa-circle',
            'inNavigation'      => 0,
            'isBackoffice'      => 1,
            'typeOfPermissions' => json_encode(['read_r' => true], JSON_UNESCAPED_UNICODE),
            // 2 = "Backup" in ci4ms_test, not in Methods::PROTECTED_MODULES
            // -- see the setUp() precondition assert above.
            'module_id'         => 2,
        ]);
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
    private function dispatchUpdate(int $actorId, int $pk, array $post): ResponseInterface
    {
        $controller = new Methods();
        $this->primeRequest($actorId, $post, $controller);

        return $controller->update($pk);
    }

    /**
     * Same as dispatchCreate(), except the request's `post` and `request`
     * (query-string-plus-POST) globals are seeded independently, to
     * simulate `typeOfPermissions` arriving only via the query string --
     * see primeRequestWithQueryStringOnlyOverride().
     *
     * @param array<string, mixed> $post
     * @param array<string, mixed> $extraRequestOnly
     */
    private function dispatchCreateWithQueryStringOnlyTypeOfPermissions(int $actorId, array $post, array $extraRequestOnly): ResponseInterface
    {
        $controller = new Methods();
        $this->primeRequestWithQueryStringOnlyOverride($actorId, $post, $extraRequestOnly, $controller);

        return $controller->create();
    }

    /**
     * Same as dispatchUpdate(), except the request's `post` and `request`
     * globals are seeded independently -- see
     * primeRequestWithQueryStringOnlyOverride().
     *
     * @param array<string, mixed> $post
     * @param array<string, mixed> $extraRequestOnly
     */
    private function dispatchUpdateWithQueryStringOnlyTypeOfPermissions(int $actorId, int $pk, array $post, array $extraRequestOnly): ResponseInterface
    {
        $controller = new Methods();
        $this->primeRequestWithQueryStringOnlyOverride($actorId, $post, $extraRequestOnly, $controller);

        return $controller->update($pk);
    }

    private function primeRequest(int $actorId, array $post, Methods $controller): void
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

    /**
     * Same as primeRequest(), except the `post` and `request`
     * (query-string-plus-POST, per IncomingRequest::getVar() ->
     * fetchGlobal('request')) globals are seeded independently instead of
     * from the same array. `$post` is what `$this->request->getPost(...)`
     * reads; `$post + $extraRequestOnly` is what `Controller::validate()`
     * (via Validation::withRequest() -> IncomingRequest::getVar()) would
     * have read before the create()/update() fix -- a key present only in
     * `$extraRequestOnly` simulates a value supplied solely via the query
     * string, never in the POST body.
     *
     * @param array<string, mixed> $post
     * @param array<string, mixed> $extraRequestOnly
     */
    private function primeRequestWithQueryStringOnlyOverride(int $actorId, array $post, array $extraRequestOnly, Methods $controller): void
    {
        $actor = auth()->getProvider()->findById($actorId);
        $this->actingAs($actor);

        $config  = config(App::class);
        $request = new IncomingRequest($config, new SiteURI($config), null, new UserAgent());
        $request = $request->withMethod('POST');
        $request->setGlobal('post', $post);
        $request->setGlobal('request', $post + $extraRequestOnly);

        $controller->commonModel = $this->commonModel;

        (new ReflectionProperty(\CodeIgniter\Controller::class, 'request'))->setValue($controller, $request);
        (new ReflectionProperty(\CodeIgniter\Controller::class, 'response'))->setValue($controller, service('response', $config, false));
    }
}

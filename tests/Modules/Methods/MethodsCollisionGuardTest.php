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
use ReflectionProperty;

/**
 * Regression suite for F3 (Methods::create()/update() authorization gap).
 *
 * className/methodName are the exact keys Ci4MsAuthFilter uses to resolve
 * the authorization row for an incoming request (see
 * modules/Auth/Filters/Ci4MsAuthFilter.php:38-41). Before the fix, neither
 * field was validated at all, and both create() and update() wrote the raw
 * getPost() value straight to the row -- an attacker could squat an
 * existing controller/method pair (className+methodName collision) or
 * inject characters with no charset restriction. This locks the two-part
 * fix actually shipped (see context.md "F3 -- Methods.php create()/update()
 * authz açığı", Group C): (1) a strict className/methodName regex and (2)
 * classNameMethodNameCollides(), an application-level duplicate-key guard.
 *
 * Also covers BUG2: create()'s typeOfPermissions write used to persist the
 * raw POST array (implicit-array-to-string PHP coercion into a longtext
 * column); it must now be JSON-encoded the same way update() already does.
 *
 * Same technique as the other F1-F4 regression suites: the controller is
 * instantiated directly and driven through ReflectionProperty, bypassing
 * routing/filters -- classNameMethodNameCollides() lives entirely inside
 * the controller body.
 *
 * @internal
 */
final class MethodsCollisionGuardTest extends CIUnitTestCase
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

        $this->db->transStart();
    }

    protected function tearDown(): void
    {
        $this->db->transRollback();

        parent::tearDown();
    }

    /**
     * create() must reject className/methodName values containing
     * characters outside [A-Za-z0-9_-] -- e.g. an XSS payload or a path
     * traversal attempt -- before any row is written.
     */
    public function testCreateRejectsInvalidCharactersInClassNameAndMethodName(): void
    {
        $pagename = 'f3_invalidchars_t1_' . bin2hex(random_bytes(4));
        $before   = $this->commonModel->count('auth_permissions_pages');

        $response = $this->dispatchCreate($this->createActor(), [
            'pagename'          => $pagename,
            'description'       => 'desc',
            'className'         => '<script>alert(1)</script>',
            'methodName'        => '../../../etc/passwd',
            'sefLink'           => 'f3-invalidchars-t1',
            'symbol'            => 'fa fa-circle',
            'typeOfPermissions' => ['read'],
        ]);

        $this->assertNotSame(200, $response->getStatusCode(), 'a validation failure must redirect, not render 200.');
        $this->assertSame(
            $before,
            $this->commonModel->count('auth_permissions_pages'),
            'create() must not write a row when className/methodName fail validation.',
        );
        $this->assertNull($this->commonModel->selectOne('auth_permissions_pages', ['pagename' => $pagename]));
    }

    /**
     * create() must reject a className/methodName pair that already belongs
     * to another row.
     */
    public function testCreateRejectsCollisionWithAnotherRowsClassNameMethodName(): void
    {
        $existingClassName = '-Modules-Fixture-Controllers-F3Collide' . bin2hex(random_bytes(3));
        $this->createPermissionPageRow($existingClassName, 'index', 'F3 Existing Page');

        $attemptedPagename = 'f3_collide_new_t2_' . bin2hex(random_bytes(4));

        $response = $this->dispatchCreate($this->createActor(), [
            'pagename'          => $attemptedPagename,
            'description'       => 'desc',
            'className'         => $existingClassName,
            'methodName'        => 'index',
            'sefLink'           => 'f3-collide-new-t2',
            'symbol'            => 'fa fa-circle',
            'typeOfPermissions' => ['read'],
        ]);

        $this->assertNotNull($response);
        $this->assertNull(
            $this->commonModel->selectOne('auth_permissions_pages', ['pagename' => $attemptedPagename]),
            'create() must not create a new row when className/methodName collide with an existing row.',
        );
        $this->assertSame(
            1,
            $this->commonModel->count('auth_permissions_pages', ['className' => $existingClassName, 'methodName' => 'index']),
            'The className/methodName pair must still resolve to exactly the original row.',
        );
    }

    /**
     * update() must allow submitting the form unchanged (or changing only
     * unrelated fields like pagename) without tripping the collision guard
     * against its own row -- no false positive.
     */
    public function testUpdateAllowsUnchangedOwnClassNameMethodName(): void
    {
        $className  = '-Modules-Fixture-Controllers-F3OwnRow' . bin2hex(random_bytes(3));
        $methodName = 'show';
        $pk         = $this->createPermissionPageRow($className, $methodName, 'F3 Old Name');

        $response = $this->dispatchUpdate($this->createActor(), $pk, [
            'pagename'          => 'F3 New Name',
            'description'       => 'desc',
            'className'         => $className,
            'methodName'        => $methodName,
            'sefLink'           => 'f3-ownrow-new',
            'symbol'            => 'fa fa-circle',
            'typeOfPermissions' => ['read'],
        ]);

        $this->assertNotNull($response);
        $after = $this->commonModel->selectOne('auth_permissions_pages', ['id' => $pk]);
        $this->assertNotNull($after, 'row must not disappear.');
        $this->assertSame('F3 New Name', $after->pagename, 'update() must persist the unrelated field change.');
        $this->assertSame($className, $after->className, 'update() must not touch className when it was not changed.');
        $this->assertSame($methodName, $after->methodName, 'update() must not touch methodName when it was not changed.');
    }

    /**
     * update() must reject repointing a row's className/methodName at a
     * DIFFERENT row's pair (squatting), leaving the victim row untouched.
     */
    public function testUpdateRejectsCollisionWithAnotherRowsClassNameMethodName(): void
    {
        $classNameA = '-Modules-Fixture-Controllers-F3ColA' . bin2hex(random_bytes(3));
        $classNameB = '-Modules-Fixture-Controllers-F3ColB' . bin2hex(random_bytes(3));
        $this->createPermissionPageRow($classNameA, 'index', 'F3 Row A');
        $pkB = $this->createPermissionPageRow($classNameB, 'index', 'F3 Row B');

        $response = $this->dispatchUpdate($this->createActor(), $pkB, [
            'pagename'          => 'F3 Row B Renamed',
            'description'       => 'desc',
            'className'         => $classNameA,
            'methodName'        => 'index',
            'sefLink'           => 'f3-rowb-renamed',
            'symbol'            => 'fa fa-circle',
            'typeOfPermissions' => ['read'],
        ]);

        $this->assertNotNull($response);
        $after = $this->commonModel->selectOne('auth_permissions_pages', ['id' => $pkB]);
        $this->assertNotNull($after, 'row B must not disappear.');
        $this->assertSame('F3 Row B', $after->pagename, 'update() must not persist any change when the collision guard rejects the request.');
        $this->assertSame($classNameB, $after->className, 'row B className must remain its own, not row A\'s.');
    }

    /**
     * BUG2: create()'s typeOfPermissions write must be valid JSON matching
     * update()'s own encoding shape, not the raw POST array coerced to the
     * string "Array".
     *
     * UPDATED (Round 2 / B2b): create() used to omit `module_id` from its
     * INSERT entirely, which only failed loudly under this test
     * environment's strict sql_mode (see context-round2.md, "[ci4ms-qa /
     * Round 2 fixup]") -- that required a session-local STRICT_ALL_TABLES
     * relaxation here to observe create() as it behaved in production.
     * ci4ms-developer's B2b fix now resolves `module_id` from the POST
     * field `moduleName` (which, despite its name, carries a `modules.id`
     * integer -- see modules/Methods/Views/form.php) via
     * Methods::resolveModuleId(), so a valid `moduleName` is now required
     * for create() to reach the INSERT at all. The sql_mode relaxation is
     * gone; module id 1 ("Backend") is reused here because it is the same
     * fixture value createPermissionPageRow() below already relies on for
     * every other test in this file.
     */
    public function testCreatePersistsTypeOfPermissionsAsValidJson(): void
    {
        $pagename = 'f3_json_t5_' . bin2hex(random_bytes(4));

        $this->dispatchCreate($this->createActor(), [
            'pagename'          => $pagename,
            'description'       => 'desc',
            'className'         => '-Modules-Fixture-Controllers-F3Json' . bin2hex(random_bytes(3)),
            'methodName'        => 'index',
            'sefLink'           => 'f3-json-t5',
            'symbol'            => 'fa fa-circle',
            'typeOfPermissions' => ['create', 'read'],
            'moduleName'        => 1,
        ]);

        $created = $this->commonModel->selectOne('auth_permissions_pages', ['pagename' => $pagename]);
        $this->assertNotNull($created, 'create() must have written the row for this assertion to be meaningful.');
        $this->assertSame(1, (int) $created->module_id, 'create() must persist the resolved module_id from moduleName.');

        $decoded = json_decode($created->typeOfPermissions, true);
        $this->assertNotNull($decoded, 'typeOfPermissions must be valid JSON, not the raw POST array coerced to a string.');
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
     * Creates an actor with no group membership -- classNameMethodNameCollides()
     * and the validation rules under test do not depend on the actor's own
     * permissions (unlike F1/F4), so a bare authenticated user is enough.
     */
    private function createActor(): int
    {
        $suffix = bin2hex(random_bytes(4));

        $users = auth()->getProvider();
        $user  = new User([
            'firstname' => 'Test',
            'surname'   => 'User',
            'username'  => 'f3_actor_' . $suffix,
            'email'     => 'f3_actor_' . $suffix . '@example.test',
            'password'  => 'SuperSecret123!',
            'active'    => 1,
        ]);
        $users->save($user);

        return (int) $users->getInsertID();
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
            'module_id'         => 1,
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
}

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
 * Regression suite for the Round 2 / B2(b) fix: Methods::create()'s INSERT
 * into auth_permissions_pages never included `module_id`
 * (NOT NULL, no default, FK to modules(id) -- see
 * modules/Backend/Database/Migrations/2026-02-25-062806_AddForeignKeys.php:15).
 * The Methods form's module <select> is named `moduleName` but its option
 * values are actually modules.id (modules/Methods/Views/form.php:146-154).
 * This locks: (1) a valid `moduleName` post resolves to the real modules.id
 * and is written to the row, (2) a missing/non-numeric/non-existent module
 * reference is rejected with no row written.
 *
 * Same direct-controller-driving technique as
 * tests/Modules/Methods/MethodsCollisionGuardTest.php.
 *
 * @internal
 */
final class MethodsCreateModuleIdTest extends CIUnitTestCase
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
     * create() must resolve a valid `moduleName` post to the referenced
     * module's real modules.id and persist it as auth_permissions_pages.module_id
     * -- checked via a before/after DB read, not an assumption.
     */
    public function testCreateWritesResolvedModuleIdMatchingSelectedModule(): void
    {
        $moduleId = $this->createModuleRow();
        $pagename = 'b2b_moduleid_t1_' . bin2hex(random_bytes(4));
        $before   = $this->commonModel->count('auth_permissions_pages');

        $this->dispatchCreate($this->createActor(), [
            'pagename'          => $pagename,
            'description'       => 'desc',
            'className'         => '-Modules-Fixture-Controllers-B2bModuleId' . bin2hex(random_bytes(3)),
            'methodName'        => 'index',
            'sefLink'           => 'b2b-moduleid-t1',
            'symbol'            => 'fa fa-circle',
            'typeOfPermissions' => ['read'],
            'moduleName'        => (string) $moduleId,
        ]);

        $after = $this->commonModel->count('auth_permissions_pages');
        $this->assertSame($before + 1, $after, 'create() must write exactly one new row when the module reference is valid.');

        $created = $this->commonModel->selectOne('auth_permissions_pages', ['pagename' => $pagename]);
        $this->assertNotNull($created, 'create() must have written the row.');
        $this->assertSame($moduleId, (int) $created->module_id, "module_id must match the selected module's real id, not 0 or null.");
    }

    /**
     * create() must reject the request and write no row when `moduleName`
     * is absent from the POST body entirely.
     */
    public function testCreateRejectsMissingModuleAndWritesNoRow(): void
    {
        $pagename = 'b2b_moduleid_t2_' . bin2hex(random_bytes(4));
        $before   = $this->commonModel->count('auth_permissions_pages');

        $response = $this->dispatchCreate($this->createActor(), [
            'pagename'          => $pagename,
            'description'       => 'desc',
            'className'         => '-Modules-Fixture-Controllers-B2bMissing' . bin2hex(random_bytes(3)),
            'methodName'        => 'index',
            'sefLink'           => 'b2b-moduleid-t2',
            'symbol'            => 'fa fa-circle',
            'typeOfPermissions' => ['read'],
        ]);

        $this->assertNotSame(200, $response->getStatusCode(), 'a rejected module reference must redirect, not render 200.');
        $this->assertSame(
            $before,
            $this->commonModel->count('auth_permissions_pages'),
            'create() must not write a row when moduleName is missing.',
        );
        $this->assertNull($this->commonModel->selectOne('auth_permissions_pages', ['pagename' => $pagename]));
    }

    /**
     * create() must reject the request and write no row when `moduleName`
     * does not resolve to any existing modules row.
     */
    public function testCreateRejectsNonExistentModuleAndWritesNoRow(): void
    {
        $pagename = 'b2b_moduleid_t3_' . bin2hex(random_bytes(4));
        $before   = $this->commonModel->count('auth_permissions_pages');

        $this->dispatchCreate($this->createActor(), [
            'pagename'          => $pagename,
            'description'       => 'desc',
            'className'         => '-Modules-Fixture-Controllers-B2bGhost' . bin2hex(random_bytes(3)),
            'methodName'        => 'index',
            'sefLink'           => 'b2b-moduleid-t3',
            'symbol'            => 'fa fa-circle',
            'typeOfPermissions' => ['read'],
            'moduleName'        => '999999999',
        ]);

        $this->assertSame(
            $before,
            $this->commonModel->count('auth_permissions_pages'),
            'create() must not write a row when moduleName references a non-existent module.',
        );
        $this->assertNull($this->commonModel->selectOne('auth_permissions_pages', ['pagename' => $pagename]));
    }

    /**
     * create() must reject the request and write no row when `moduleName`
     * is not a positive integer (e.g. tampered with a non-numeric value).
     */
    public function testCreateRejectsNonNumericModuleAndWritesNoRow(): void
    {
        $pagename = 'b2b_moduleid_t4_' . bin2hex(random_bytes(4));
        $before   = $this->commonModel->count('auth_permissions_pages');

        $this->dispatchCreate($this->createActor(), [
            'pagename'          => $pagename,
            'description'       => 'desc',
            'className'         => '-Modules-Fixture-Controllers-B2bNonNumeric' . bin2hex(random_bytes(3)),
            'methodName'        => 'index',
            'sefLink'           => 'b2b-moduleid-t4',
            'symbol'            => 'fa fa-circle',
            'typeOfPermissions' => ['read'],
            'moduleName'        => 'not-a-number',
        ]);

        $this->assertSame(
            $before,
            $this->commonModel->count('auth_permissions_pages'),
            'create() must not write a row when moduleName is not numeric.',
        );
        $this->assertNull($this->commonModel->selectOne('auth_permissions_pages', ['pagename' => $pagename]));
    }

    private function createModuleRow(): int
    {
        return $this->commonModel->create('modules', [
            'name'     => 'B2bFixtureModule' . bin2hex(random_bytes(4)),
            'icon'     => 'fas fa-cogs',
            'isActive' => true,
        ]);
    }

    /**
     * Creates an actor with no group membership -- resolveModuleId() and the
     * validation rules under test do not depend on the actor's own
     * permissions.
     */
    private function createActor(): int
    {
        $suffix = bin2hex(random_bytes(4));

        $users = auth()->getProvider();
        $user  = new User([
            'firstname' => 'Test',
            'surname'   => 'User',
            'username'  => 'b2b_actor_' . $suffix,
            'email'     => 'b2b_actor_' . $suffix . '@example.test',
            'password'  => 'SuperSecret123!',
            'active'    => 1,
        ]);
        $users->save($user);

        return (int) $users->getInsertID();
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

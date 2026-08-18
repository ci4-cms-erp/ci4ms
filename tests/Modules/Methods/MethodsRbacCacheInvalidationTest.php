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
use Modules\Methods\Controllers\Methods;
use ReflectionProperty;
use Tests\Support\Notifications\CapturingChannel;
use Tests\Support\Notifications\FakeDispatchNotifier;

/**
 * Behavioural locks for B-3: every auth_permissions_pages mutation in
 * Modules\Methods\Controllers\Methods must invalidate BOTH RBAC caches
 * Ci4MsAuthFilter reads from, via rbac_cache_flush() (app/Common.php:289-293).
 *
 * Four call sites are covered, one test each:
 *   Methods.php:114 create()       -> testCreateFlushesRbacCachesOnSuccess
 *   Methods.php:175 update()       -> testUpdateFlushesRbacCachesOnSuccess
 *   Methods.php:197 moduleScan()   -> testModuleScanFlushesRbacCachesWhenTheScanChangedSomething
 *   Methods.php:481 moduleDelete() -> testModuleDeleteFlushesRbacCaches
 *
 * Each test primes 'shield_auth_dynamic_config' AND a 'backend_page_info_*'
 * key, drives the SUCCESS branch of the action for real, and asserts both
 * keys are gone -- so deleting the rbac_cache_flush() line at any one of the
 * four call sites turns exactly one of these tests red.
 *
 * Two negative controls (create's validation-failure branch, moduleScan's
 * no-change branch) prove the assertion is tied to the success branch rather
 * than to merely entering the method.
 *
 * MEASURED, not assumed -- moduleScan(): ModuleScanner::runScan() returns
 * false against a ci4ms_test whose auth_permissions_pages already matches the
 * routes registered in the PHPUnit process, and the flush is gated on that
 * return value (Methods.php:196), so a naive "call moduleScan() and assert
 * the caches are gone" test would be permanently mutation-BLIND. Probe
 * output (see context.md "Kanıt kütüğü"): pages before=139, runScan#1 =>
 * false, 0 removed / 0 added. The trigger used below is therefore the
 * cheapest branch of runScan() that flips $isChanged: a `modules` row whose
 * folder does not exist on disk is dropped by the module-cleaning loop
 * (ModuleScanner.php:189-195), which sets $isChanged = true and removes only
 * that row. Measured blast radius: pages 139 -> 139, modules 16 -> 16, probe
 * row self-deleted.
 *
 * MEASURED, not assumed -- moduleDelete(): $installer->removeModuleFiles()
 * (ModuleInstaller.php:243-249) returns success immediately when the module
 * directory does not exist, before any delete_files() call. The fixture below
 * is therefore a `modules` DB row pointing at a name with NO directory under
 * modules/, asserted with assertDirectoryDoesNotExist() before dispatch. No
 * real module is ever passed to moduleDelete() from this file and no file is
 * ever deleted -- PROTECTED_MODULES (Methods.php:14-27) is a backstop, not
 * the safety mechanism relied on here.
 *
 * The controller is instantiated directly and driven through
 * ReflectionProperty, the technique the sibling Methods/Users suites already
 * use: the invalidation lives entirely inside the controller body, so the
 * routing/filter layer would add noise without adding coverage.
 *
 * @internal
 */
final class MethodsRbacCacheInvalidationTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use AuthenticationTesting;

    protected $refresh = false;

    protected $migrateOnce = true;

    /**
     * null migrates every registered namespace, not just Modules\Methods --
     * the fixtures need Shield's users table and Modules\Auth's
     * auth_permissions_pages (same rationale as MethodsCollisionGuardTest).
     */
    protected $namespace = null;

    private CommonModel $commonModel;

    /**
     * Autocommitting connection ('default' group, aliased to ci4ms_test by
     * Config\Database::__construct() under ENVIRONMENT === 'testing').
     * Only the moduleScan() test uses it: ModuleScanner::runScan() builds its
     * own `new CommonModel()` on that same group, and a row written through
     * this test's transactional 'tests' connection would be invisible to it.
     */
    private CommonModel $autocommitModel;

    /**
     * Stale `modules` row name inserted by the moduleScan() test, cleaned up
     * unconditionally in tearDown() in case the scanner did not remove it.
     */
    private ?string $staleModuleName = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertStringContainsString(
            'ci4ms_test',
            (string) \Config\Database::connect()->database,
            'Refusing to run: the active connection is not pointed at the test database.',
        );

        // Same order-independence guards as MethodsCollisionGuardTest: the
        // shared 'validation' singleton keeps stale errors across tests, and
        // the shared 'routes' collection is dropped by any earlier
        // Services::reset() -- this class drives the controller directly, so
        // nothing else would rebuild it before redirect()->route() runs.
        Services::resetSingle('validation');
        service('routes')->loadRoutes();

        cache()->clean();

        $this->commonModel     = new CommonModel('tests');
        $this->autocommitModel = new CommonModel();

        $this->assertStringContainsString(
            'ci4ms_test',
            (string) \Config\Database::connect('default')->database,
            'Refusing to run: the autocommitting connection is not pointed at the test database.',
        );

        // Every success branch under test also fires `ci4ms.audit`, whose
        // app/Config/Events.php listener dispatches through service('notifier').
        // The real Notifier builds `new CommonModel()` on the AUTOCOMMITTING
        // 'default' group (Notifier.php:43), so letting it run would write
        // notification rows into ci4ms_test that this test's transaction can
        // never roll back. FakeDispatchNotifier + CapturingChannel is the
        // project's existing DB-free double for exactly this listener
        // (tests/Modules/Notifications/AuditListenerSeverityTest.php).
        Services::injectMock('notifier', new FakeDispatchNotifier(new CapturingChannel()));

        $this->db->transStart();
    }

    protected function tearDown(): void
    {
        $this->db->transRollback();

        // Outside the transaction on purpose -- see $autocommitModel.
        if ($this->staleModuleName !== null) {
            $this->autocommitModel->remove('modules', ['name' => $this->staleModuleName]);
            $this->staleModuleName = null;
        }

        Services::resetSingle('notifier');

        parent::tearDown();
    }

    /**
     * Methods.php:114 -- create()'s successful-insert branch.
     *
     * @return void
     */
    public function testCreateFlushesRbacCachesOnSuccess(): void
    {
        $pageKey  = $this->primeRbacCaches();
        $pagename = 'b3_create_' . bin2hex(random_bytes(4));

        $this->dispatchCreate($this->createActor(), [
            'pagename'          => $pagename,
            'description'       => 'desc',
            'className'         => '-Modules-Fixture-Controllers-B3Create' . bin2hex(random_bytes(3)),
            'methodName'        => 'index',
            'sefLink'           => 'b3-create',
            'symbol'            => 'fa fa-circle',
            'typeOfPermissions' => ['read'],
            'moduleName'        => 1,
        ]);

        $this->assertNotNull(
            $this->commonModel->selectOne('auth_permissions_pages', ['pagename' => $pagename]),
            'precondition: create() must have reached its success branch, otherwise the cache assertions prove nothing.',
        );

        $this->assertRbacCachesFlushed($pageKey, 'Methods::create()');
    }

    /**
     * Negative control for Methods.php:114: a request rejected by validation
     * writes nothing, so it must NOT flush either. Without this, an
     * unconditional flush at the top of create() would still satisfy the test
     * above.
     *
     * @return void
     */
    public function testCreateDoesNotFlushWhenValidationRejectsTheRequest(): void
    {
        $pageKey  = $this->primeRbacCaches();
        $pagename = 'b3_create_invalid_' . bin2hex(random_bytes(4));

        $this->dispatchCreate($this->createActor(), [
            'pagename'          => $pagename,
            'description'       => 'desc',
            'className'         => '<script>alert(1)</script>',
            'methodName'        => '../../etc/passwd',
            'sefLink'           => 'b3-create-invalid',
            'symbol'            => 'fa fa-circle',
            'typeOfPermissions' => ['read'],
            'moduleName'        => 1,
        ]);

        $this->assertNull(
            $this->commonModel->selectOne('auth_permissions_pages', ['pagename' => $pagename]),
            'precondition: the request must have been rejected for this control to mean anything.',
        );

        $this->assertRbacCachesStillPrimed($pageKey, 'a rejected Methods::create()');
    }

    /**
     * Methods.php:175 -- update()'s successful-edit branch.
     *
     * @return void
     */
    public function testUpdateFlushesRbacCachesOnSuccess(): void
    {
        $className = '-Modules-Fixture-Controllers-B3Update' . bin2hex(random_bytes(3));
        $pk        = $this->createPermissionPageRow($className, 'show', 'B3 Update Before');

        $pageKey = $this->primeRbacCaches();

        $this->dispatchUpdate($this->createActor(), $pk, [
            'pagename'          => 'B3 Update After',
            'description'       => 'desc',
            'className'         => $className,
            'methodName'        => 'show',
            'sefLink'           => 'b3-update-after',
            'symbol'            => 'fa fa-circle',
            'typeOfPermissions' => ['read', 'update'],
        ]);

        $after = $this->commonModel->selectOne('auth_permissions_pages', ['id' => $pk]);
        $this->assertNotNull($after, 'precondition: the row must still exist.');
        $this->assertSame(
            'B3 Update After',
            $after->pagename,
            'precondition: update() must have reached its success branch, otherwise the cache assertions prove nothing.',
        );

        $this->assertRbacCachesFlushed($pageKey, 'Methods::update()');
    }

    /**
     * Methods.php:197 -- moduleScan()'s $isChanged === true branch.
     *
     * @return void
     */
    public function testModuleScanFlushesRbacCachesWhenTheScanChangedSomething(): void
    {
        $this->insertStaleModuleRow();

        $pageKey  = $this->primeRbacCaches();
        $response = $this->dispatchModuleScan($this->createActor());

        $this->assertSame(
            201,
            $response->getStatusCode(),
            'precondition: respondCreated() means runScan() returned true; a 200 here means the flush branch was never entered and this test is blind.',
        );
        $this->assertNull(
            $this->autocommitModel->selectOne('modules', ['name' => $this->staleModuleName]),
            'precondition: the scanner must have dropped the folder-less module row -- that is what set $isChanged.',
        );

        $this->assertRbacCachesFlushed($pageKey, 'Methods::moduleScan()');
    }

    /**
     * Negative control for Methods.php:197: with nothing to synchronise,
     * runScan() reports false and the caches must survive.
     *
     * This also pins the measurement the flushing test depends on -- if
     * ci4ms_test ever drifts out of sync with the routes registered in the
     * PHPUnit process, this control goes red and tells the next reader that
     * the sibling test's trigger is no longer the only thing flipping
     * $isChanged.
     *
     * @return void
     */
    public function testModuleScanDoesNotFlushWhenNothingChanged(): void
    {
        $pageKey  = $this->primeRbacCaches();
        $response = $this->dispatchModuleScan($this->createActor());

        $this->assertSame(200, $response->getStatusCode(), 'precondition: an unchanged scan must respond 200, not 201.');
        $this->assertSame(
            ['result' => false],
            json_decode((string) $response->getBody(), true),
            'precondition: ci4ms_test is expected to be in sync with the registered routes.',
        );

        $this->assertRbacCachesStillPrimed($pageKey, 'a no-change Methods::moduleScan()');
    }

    /**
     * Methods.php:481 -- moduleDelete()'s success branch.
     *
     * @return void
     */
    public function testModuleDeleteFlushesRbacCaches(): void
    {
        $moduleName = 'QaGhostModule' . bin2hex(random_bytes(4));
        $modulePath = ROOTPATH . 'modules/' . $moduleName;

        // HARD SAFETY GATE: moduleDelete() calls removeModuleFiles(), which
        // really deletes modules/<name>/ recursively. It short-circuits to
        // success when the directory does not exist (ModuleInstaller.php:247),
        // and that is the ONLY path this test is allowed to take.
        $this->assertDirectoryDoesNotExist(
            $modulePath,
            'Refusing to run: moduleDelete() would delete real files under ' . $modulePath,
        );

        $moduleId = (int) $this->commonModel->create('modules', [
            'name'     => $moduleName,
            'icon'     => 'fas fa-cogs',
            'isActive' => true,
        ]);
        $pageId = $this->createPermissionPageRow(
            '-Modules-Fixture-Controllers-B3Delete' . bin2hex(random_bytes(3)),
            'index',
            'B3 Delete Page',
            $moduleId,
        );

        $pageKey = $this->primeRbacCaches();

        $response = $this->dispatchModuleDelete($this->createActor(), [
            'module_id'    => $moduleId,
            'confirm_name' => $moduleName,
        ]);

        $body = json_decode((string) $response->getBody(), true);
        $this->assertSame(
            'success',
            $body['status'] ?? null,
            'precondition: moduleDelete() must have reached its success branch, otherwise the cache assertions prove nothing.',
        );
        $this->assertNull($this->commonModel->selectOne('modules', ['id' => $moduleId]), 'precondition: the modules row must be gone.');
        $this->assertNull($this->commonModel->selectOne('auth_permissions_pages', ['id' => $pageId]), 'precondition: the permission page row must be gone.');
        $this->assertDirectoryDoesNotExist($modulePath, 'the fixture module directory must still not exist afterwards.');

        $this->assertRbacCachesFlushed($pageKey, 'Methods::moduleDelete()');
    }

    /**
     * Writes both RBAC cache keys and returns the generated
     * 'backend_page_info_*' key name.
     *
     * The two keys mirror what production actually stores: the Shield
     * group/permission matrix under a fixed name, and one per-route page
     * lookup under a wildcard-matched name.
     *
     * @return string The primed backend_page_info_* key.
     */
    private function primeRbacCaches(): string
    {
        $pageKey = 'backend_page_info_b3_' . bin2hex(random_bytes(4));

        cache()->save('shield_auth_dynamic_config', ['matrix' => ['qa_group' => ['qa.read']]], 300);
        cache()->save($pageKey, ['id' => 4242, 'pagename' => 'qa.page'], 300);

        $this->assertNotNull(cache()->get('shield_auth_dynamic_config'), 'precondition: shield_auth_dynamic_config must be primed.');
        $this->assertNotNull(cache()->get($pageKey), 'precondition: ' . $pageKey . ' must be primed.');

        return $pageKey;
    }

    /**
     * @param string $pageKey Key returned by primeRbacCaches().
     * @param string $what    Human label for the action under test.
     *
     * @return void
     */
    private function assertRbacCachesFlushed(string $pageKey, string $what): void
    {
        $this->assertNull(
            cache()->get('shield_auth_dynamic_config'),
            $what . ' must invalidate shield_auth_dynamic_config (rbac_cache_flush()).',
        );
        $this->assertNull(
            cache()->get($pageKey),
            $what . ' must invalidate backend_page_info_* (rbac_cache_flush()).',
        );
    }

    /**
     * @param string $pageKey Key returned by primeRbacCaches().
     * @param string $what    Human label for the action under test.
     *
     * @return void
     */
    private function assertRbacCachesStillPrimed(string $pageKey, string $what): void
    {
        $this->assertNotNull(
            cache()->get('shield_auth_dynamic_config'),
            $what . ' must not invalidate shield_auth_dynamic_config -- nothing was written.',
        );
        $this->assertNotNull(
            cache()->get($pageKey),
            $what . ' must not invalidate backend_page_info_* -- nothing was written.',
        );
    }

    /**
     * Inserts a `modules` row whose folder does not exist under modules/, so
     * ModuleScanner's module-cleaning loop drops it and reports a change.
     *
     * Written through the autocommitting 'default' connection because the
     * scanner reads through its own instance of that same group; a row
     * written inside this test's 'tests' transaction would be invisible to
     * it. tearDown() removes the row if the scanner did not.
     *
     * @return void
     */
    private function insertStaleModuleRow(): void
    {
        $name = 'QaScanProbe' . bin2hex(random_bytes(4));
        $this->assertDirectoryDoesNotExist(
            ROOTPATH . 'modules/' . $name,
            'the stale module fixture must not correspond to a real module directory.',
        );

        $this->autocommitModel->create('modules', ['name' => $name, 'icon' => 'fas fa-cogs', 'isActive' => true]);
        $this->staleModuleName = $name;
    }

    /**
     * Creates an actor with no group membership -- none of the branches under
     * test consult the actor's own permissions.
     *
     * @return int users.id
     */
    private function createActor(): int
    {
        $suffix = bin2hex(random_bytes(4));

        $users = auth()->getProvider();
        $user  = new User([
            'firstname' => 'Test',
            'surname'   => 'User',
            'username'  => 'b3_actor_' . $suffix,
            'email'     => 'b3_actor_' . $suffix . '@example.test',
            'password'  => 'SuperSecret123!',
            'active'    => 1,
        ]);
        $users->save($user);

        return (int) $users->getInsertID();
    }

    /**
     * @param string $className  Unique className half of the (className, methodName) unique key.
     * @param string $methodName methodName half of that key.
     * @param string $pagename   Row's pagename.
     * @param int    $moduleId   modules.id the row belongs to.
     *
     * @return int auth_permissions_pages.id
     */
    private function createPermissionPageRow(string $className, string $methodName, string $pagename, int $moduleId = 1): int
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
            'module_id'         => $moduleId,
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

    private function dispatchModuleScan(int $actorId): ResponseInterface
    {
        $controller = new Methods();
        $this->primeRequest($actorId, [], $controller, true);

        return $controller->moduleScan();
    }

    /**
     * @param array<string, mixed> $post
     */
    private function dispatchModuleDelete(int $actorId, array $post): ResponseInterface
    {
        $controller = new Methods();
        $this->primeRequest($actorId, $post, $controller, true);

        return $controller->moduleDelete();
    }

    /**
     * @param array<string, mixed> $post
     */
    private function primeRequest(int $actorId, array $post, Methods $controller, bool $ajax = false): void
    {
        $actor = auth()->getProvider()->findById($actorId);
        $this->actingAs($actor);

        $config  = config(App::class);
        $request = new IncomingRequest($config, new SiteURI($config), null, new UserAgent());
        $request->setMethod('POST');
        $request->setGlobal('post', $post);
        $request->setGlobal('request', $post);

        // moduleScan()/moduleDelete() answer failForbidden() to anything that
        // is not an XHR (Methods.php:190, :430).
        if ($ajax) {
            $request->setHeader('X-Requested-With', 'XMLHttpRequest');
        }

        $controller->commonModel = $this->commonModel;

        (new ReflectionProperty(\CodeIgniter\Controller::class, 'request'))->setValue($controller, $request);
        (new ReflectionProperty(\CodeIgniter\Controller::class, 'response'))->setValue($controller, service('response', $config, false));
    }
}

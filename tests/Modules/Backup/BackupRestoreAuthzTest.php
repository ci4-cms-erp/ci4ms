<?php

declare(strict_types=1);

namespace Tests\Modules\Backup;

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
use Modules\Backup\Controllers\Backup;
use ReflectionProperty;

/**
 * Regression suite for F2 (Backup::restore() missing a superadmin-only
 * guard).
 *
 * Before the fix, restore() relied solely on the route's Modules\Methods
 * permission entry and the backendGuard filter -- if either was
 * misconfigured or bypassed, any authenticated non-superadmin user reaching
 * this action could restore an arbitrary SQL dump. This locks the
 * controller-level guard added as a third layer of defense (same rationale
 * as Modules\MigrationManager\Controllers\MigrationManager).
 *
 * The guard is the very first statement in restore(), before any file
 * handling, so this test never needs to supply a real upload -- an empty
 * POST is enough to prove the request never reaches the file-processing
 * logic.
 *
 * @internal
 */
final class BackupRestoreAuthzTest extends CIUnitTestCase
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
     * A non-superadmin actor POSTing to restore() must be rejected outright,
     * before any file is touched or db_backups row count changes.
     */
    public function testNonSuperadminActorIsForbiddenFromRestoring(): void
    {
        $ownGroup = 'backup_restore_owngroup_t1';
        $this->createGroup($ownGroup);
        $actorId = $this->createUser('backup_restore_t1');
        $this->addUserToGroup($actorId, $ownGroup);

        $beforeCount = $this->commonModel->count('db_backups');

        $response = $this->dispatchRestore($actorId, []);

        $this->assertSame(403, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertSame(lang('Backup.restoreForbidden'), $body['messages']['error'] ?? null);

        $this->assertSame(
            $beforeCount,
            $this->commonModel->count('db_backups'),
            'A rejected restore() request must never reach the file-processing/db_backups logic.',
        );
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
     * @param array<string, mixed> $post
     */
    private function dispatchRestore(int $actorId, array $post): ResponseInterface
    {
        $controller = new Backup();
        $this->primeRequest($actorId, $post, $controller);

        return $controller->restore();
    }

    private function primeRequest(int $actorId, array $post, Backup $controller): void
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

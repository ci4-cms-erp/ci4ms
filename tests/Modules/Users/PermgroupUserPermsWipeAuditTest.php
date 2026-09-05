<?php

declare(strict_types=1);

namespace Tests\Modules\Users;

use ci4commonmodel\CommonModel;
use CodeIgniter\Config\Services;
use CodeIgniter\Events\Events;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\HTTP\SiteURI;
use CodeIgniter\HTTP\UserAgent;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Test\AuthenticationTesting;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Closure;
use Config\App;
use Modules\Users\Controllers\PermgroupController;
use ReflectionProperty;
use Tests\Support\Notifications\CapturingChannel;
use Tests\Support\Notifications\FakeDispatchNotifier;

/**
 * Behavioural lock for the audit producer on user_perms()' empty-perms[] wipe
 * path (PermgroupController.php:424-430).
 *
 * That branch is the LEGITIMATE "clear all of this user's direct permissions"
 * action: an empty perms[] is a deliberate wipe, not a malformed payload
 * (is_array([]) is true and the delegation-ceiling loop trivially passes on an
 * empty set). Before this file, PermgroupController.php:427 -- the
 * auditRbacEvent() call on that branch -- was never executed by any test, so
 * an RBAC wipe could silently stop notifying superadmins with nothing turning
 * red.
 *
 * WHY A LOCAL Events::on LISTENER RATHER THAN CapturingChannel: the contract
 * under test belongs to the PRODUCER (severity 'warning', action
 * 'rbac.userPermsUpdated'), and the app/Config/Events.php consumer rewrites
 * both on the way out -- action becomes 'audit.' . $action as the notification
 * type, and the severity only survives because that listener forwards it. A
 * channel-level assertion would therefore be an assertion about the listener,
 * which tests/Modules/Notifications/AuditListenerSeverityTest.php already
 * owns. The listener is removed with Events::removeListener() in tearDown();
 * Events::removeAllListeners('ci4ms.audit') is deliberately NOT used, because
 * it would also drop the application's own listener for the remainder of the
 * PHPUnit process.
 *
 * A FakeDispatchNotifier is still injected: the application's listener runs
 * too, and the real Notifier builds `new CommonModel()` on the autocommitting
 * 'default' group (Notifier.php:43), which would leave notification rows in
 * ci4ms_test that this test's transaction cannot roll back.
 *
 * @internal
 */
final class PermgroupUserPermsWipeAuditTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use AuthenticationTesting;

    protected $refresh = false;

    protected $migrateOnce = true;

    /**
     * null migrates every registered namespace: the fixtures need
     * Modules\Auth's auth_permissions_pages plus Shield's users/identities/
     * permissions tables.
     */
    protected $namespace = null;

    private CommonModel $commonModel;

    /**
     * Every `ci4ms.audit` payload seen during the test, in trigger order.
     *
     * @var list<array<string, mixed>>
     */
    private array $auditEvents = [];

    /**
     * The exact closure registered on `ci4ms.audit`, kept so tearDown() can
     * pass it to Events::removeListener(), which matches listeners by
     * identity (Events.php:210).
     */
    private ?Closure $auditListener = null;

    protected function setUp(): void
    {
        parent::setUp();
        helper('Modules\Backend\Helpers\ci4ms');

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

        Services::injectMock('notifier', new FakeDispatchNotifier(new CapturingChannel()));

        $this->auditEvents   = [];
        $this->auditListener = function (array $event): void {
            $this->auditEvents[] = $event;
        };
        Events::on('ci4ms.audit', $this->auditListener);

        $this->db->transStart();
    }

    protected function tearDown(): void
    {
        $this->db->transRollback();

        if ($this->auditListener !== null) {
            Events::removeListener('ci4ms.audit', $this->auditListener);
            $this->auditListener = null;
        }

        Services::resetSingle('notifier');

        parent::tearDown();
    }

    /**
     * The legitimate wipe must keep working: an empty perms[] posted against
     * ANOTHER user clears that user's direct permissions.
     *
     * This is the guard rail for the two audit assertions below -- a "fix"
     * that made the empty payload rejected would satisfy neither, and this
     * test says which of the two failure modes happened.
     *
     * @return void
     */
    public function testEmptyPermsPayloadStillWipesTheTargetsDirectPermissions(): void
    {
        [$actorId, $targetId, $permission] = $this->seedWipeScenario('wipe_keep');

        $response = $this->dispatchUserPerms($actorId, $targetId, ['perms' => []]);

        $this->assertNotSame(403, $response->getStatusCode(), 'an empty perms[] against another user is a legitimate wipe, not a rejection.');
        $this->assertNotContains(
            $permission,
            auth()->getProvider()->findById($targetId)->getPermissions(),
            'the empty-perms[] path must still clear the target\'s direct permissions.',
        );
    }

    /**
     * PermgroupController.php:427 -- the wipe must fire `ci4ms.audit` with
     * action 'rbac.userPermsUpdated'.
     *
     * @return void
     */
    public function testEmptyPermsWipeEmitsTheUserPermsUpdatedAuditAction(): void
    {
        [$actorId, $targetId] = $this->seedWipeScenario('wipe_action');

        $this->dispatchUserPerms($actorId, $targetId, ['perms' => []]);

        $actions = array_column($this->auditEvents, 'action');
        $this->assertContains(
            'rbac.userPermsUpdated',
            $actions,
            'the empty-perms[] wipe must emit a ci4ms.audit event with action "rbac.userPermsUpdated"; saw: '
                . json_encode($actions, JSON_UNESCAPED_UNICODE),
        );
    }

    /**
     * PermgroupController.php:427 -- the wipe's audit event must carry
     * severity 'warning'.
     *
     * 'warning' and not 'critical': a wipe by an authorised actor is a
     * routine administrative action, and Notifier::applyPreferences() makes
     * 'critical' notifications impossible to mute -- that level is reserved
     * for the rejected-delegation producers (:418, :182, :83). Asserted in
     * its own test method so that changing only the severity reds exactly
     * this one and leaves the action test above green.
     *
     * @return void
     */
    public function testEmptyPermsWipeAuditEventCarriesWarningSeverity(): void
    {
        [$actorId, $targetId] = $this->seedWipeScenario('wipe_sev');

        $this->dispatchUserPerms($actorId, $targetId, ['perms' => []]);

        $event = $this->auditEventFor('rbac.userPermsUpdated');

        $this->assertSame(
            'warning',
            $event['severity'] ?? null,
            'the empty-perms[] wipe audit event must be severity "warning".',
        );
        $this->assertIsString($event['message'] ?? null, 'the audit event must carry a message for the notification title.');
        $this->assertNotSame('', $event['message'], 'the audit event message must not be empty.');
    }

    /**
     * The first captured `ci4ms.audit` payload with the given action.
     *
     * @param string $action Action key to look for.
     *
     * @return array<string, mixed>
     */
    private function auditEventFor(string $action): array
    {
        foreach ($this->auditEvents as $event) {
            if (($event['action'] ?? null) === $action) {
                return $event;
            }
        }

        $this->fail(
            'no ci4ms.audit event with action "' . $action . '" was emitted; saw: '
                . json_encode(array_column($this->auditEvents, 'action'), JSON_UNESCAPED_UNICODE),
        );
    }

    /**
     * Builds the fixture the three tests share: a non-superadmin actor, a
     * separate non-superadmin target that already holds one direct
     * permission, and the primed AuthGroups config both Shield's
     * syncPermissions() validation and the controller's can() calls read.
     *
     * The actor deliberately belongs to NO group: with an empty perms[] the
     * delegation-ceiling loop iterates zero times and returns true, so the
     * actor needs no permissions of their own to reach the wipe branch --
     * priming a matrix for them would only obscure that.
     *
     * @param string $label Short fixture label (kept <= 21 chars, see createUser()).
     *
     * @return array{0: int, 1: int, 2: string} [actorId, targetId, the target's seeded permission string]
     */
    private function seedWipeScenario(string $label): array
    {
        [$pageId, $pagename] = $this->createPermissionPage($label);
        $permission          = strtolower($pagename) . '.read';

        // syncPermissions() validates every string against
        // array_keys(setting('AuthGroups.permissions')) -- Shield's
        // Authorizable::syncPermissions() -- which resolves through the
        // 'shield_auth_dynamic_config' cache, and AuthGroups::
        // loadFromDatabase()'s own connection cannot see this test's
        // uncommitted fixture row.
        cache()->save('shield_auth_dynamic_config', [
            'groups'      => [],
            'permissions' => [$permission => 'fixture permission'],
            'matrix'      => [],
        ], 86400);

        $actorId  = $this->createUser($label . '_a');
        $targetId = $this->createUser($label . '_t');

        auth()->getProvider()->findById($targetId)->syncPermissions($permission);
        $this->assertContains(
            $permission,
            auth()->getProvider()->findById($targetId)->getPermissions(),
            'precondition: the target must start with a direct permission, or a wipe would be unobservable.',
        );
        $this->assertNotSame($actorId, $targetId, 'precondition: actor and target must differ, or the self-target guard fires first.');
        $this->assertGreaterThan(0, $pageId);

        return [$actorId, $targetId, $permission];
    }

    /**
     * Creates one auth_permissions_pages row and returns [id, pagename].
     *
     * @return array{0: int, 1: string}
     */
    private function createPermissionPage(string $labelPrefix): array
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
     * Creates a Shield user with a random-suffixed username/email.
     *
     * The length guard is not decoration: users.username is varchar(30), and
     * Shield reports an overflow as an unrelated-looking
     * "Attempt to read property \"id\" on null" from
     * UserModel::saveEmailIdentity(). $label must stay <= 21 characters.
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

    /**
     * @param array<string, mixed> $post
     */
    private function dispatchUserPerms(int $actorId, int $targetId, array $post): ResponseInterface
    {
        $controller = new PermgroupController();
        $this->primeRequest($actorId, $post, $controller);

        return $controller->user_perms($targetId);
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
        $request = $request->withMethod('POST');
        $request->setGlobal('post', $post);
        $request->setGlobal('request', $post);

        $controller->commonModel = $this->commonModel;

        (new ReflectionProperty(\CodeIgniter\Controller::class, 'request'))->setValue($controller, $request);
        (new ReflectionProperty(\CodeIgniter\Controller::class, 'response'))->setValue($controller, service('response', $config, false));
    }
}

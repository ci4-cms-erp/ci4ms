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
 * Regression suite for Round 2 / B1: UserController::update_user() let a
 * POSTed password overwrite a peer's password_hash with no proof the actor
 * knew the target's current password.
 *
 * update_user() is protected only by the delegable granular users.update
 * permission (see modules/Users/Config/Routes.php + Modules\Methods), not
 * superadmin-only -- unlike profile(), which requires and verifies
 * current_password before accepting a new one. A non-superadmin actor
 * holding users.update could therefore reset any peer's password and take
 * over the account. See context-round2.md "[build-lead] B1 için not".
 *
 * Same technique as UserControllerDelegationCeilingTest: the controller is
 * instantiated directly and driven through ReflectionProperty, bypassing
 * routing/filters. Every dispatch posts 'group' => [self::NONEXISTENT_GROUP_ID],
 * a group id that matches no auth_groups row, so
 * UserController::update_user() resolves $groupNames to an empty array --
 * this sidesteps both actorMayAssignGroup() (trivially true for []) and
 * Shield's Authorizable::syncGroups() -> GroupModel::isValidGroup() (never
 * called for []), which otherwise checks setting('AuthGroups.groups'), a
 * separate dynamically-cached config surface unrelated to what this test
 * suite is verifying (password authorization, not group delegation --
 * already covered by UserControllerDelegationCeilingTest).
 *
 * @internal
 */
final class UserControllerPeerPasswordResetAuthzTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use AuthenticationTesting;

    protected $refresh = false;

    protected $migrateOnce = true;

    protected $namespace = null;

    private const NONEXISTENT_GROUP_ID = 999999999;

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
     * A non-superadmin actor's POSTed password must never reach a peer's
     * password_hash: the request is rejected with the dedicated forbidden
     * message and the hash is left byte-for-byte unchanged.
     */
    public function testNonSuperadminPasswordPostToPeerIsRejectedAndHashUnchanged(): void
    {
        $ownGroup = 'peerpw_owngroup_t1';
        $this->createGroup($ownGroup);
        $actorId = $this->createUser('peerpw_actor_t1');
        $this->addUserToGroup($actorId, $ownGroup);

        $targetId   = $this->createUser('peerpw_target_t1');
        $beforeHash = $this->passwordHash($targetId);
        $this->assertNotNull($beforeHash, 'fixture target user must have a password_hash before the attack attempt.');

        $response = $this->dispatchUpdateUser($actorId, $targetId, [
            'username'     => 'peerpwt1' . bin2hex(random_bytes(3)),
            'firstname'    => 'Target',
            'surname'      => 'User',
            'email'        => 'peerpwt1_' . bin2hex(random_bytes(3)) . '@example.test',
            'group'        => [self::NONEXISTENT_GROUP_ID],
            'password'     => 'AttackerChosen123!',
            'own_language' => 'en',
        ]);

        $this->assertSame(403, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertSame(lang('Users.cannotResetPeerPassword'), $body['messages']['error'] ?? null);

        $afterHash = $this->passwordHash($targetId);
        $this->assertSame(
            $beforeHash,
            $afterHash,
            'A non-superadmin actor must never be able to change a peer\'s password_hash via update_user().',
        );
    }

    /**
     * Permanent regression guard (false-positive lock): a superadmin actor
     * resetting a peer's password via update_user() must still succeed --
     * the new guard must not block the legitimate case.
     */
    public function testSuperadminPasswordPostToPeerSucceedsAndHashChanges(): void
    {
        $actorId = $this->createSuperadminActor('peerpw_actor_t2');

        $targetId   = $this->createUser('peerpw_target_t2');
        $beforeHash = $this->passwordHash($targetId);
        $this->assertNotNull($beforeHash, 'fixture target user must have a password_hash before the reset.');

        $response = $this->dispatchUpdateUser($actorId, $targetId, [
            'username'     => 'peerpwt2' . bin2hex(random_bytes(3)),
            'firstname'    => 'Target',
            'surname'      => 'User',
            'email'        => 'peerpwt2_' . bin2hex(random_bytes(3)) . '@example.test',
            'group'        => [self::NONEXISTENT_GROUP_ID],
            'password'     => 'SuperadminChosen123!',
            'own_language' => 'en',
        ]);

        $this->assertNotSame(
            403,
            $response->getStatusCode(),
            'A superadmin actor must still be able to reset a peer\'s password via update_user().',
        );

        $afterHash = $this->passwordHash($targetId);
        $this->assertNotNull($afterHash);
        $this->assertNotSame(
            $beforeHash,
            $afterHash,
            'A superadmin actor\'s password reset must actually change the target\'s password_hash.',
        );
    }

    /**
     * Permanent regression guard for Round 1 / F4: a non-superadmin actor
     * updating a peer's non-password fields (no password field POSTed at
     * all) must keep working -- the new guard must be scoped to the
     * password field only, not to editing a peer's account in general.
     */
    public function testNonSuperadminFieldOnlyUpdateWithoutPasswordStillSucceeds(): void
    {
        $ownGroup = 'peerpw_owngroup_t3';
        $this->createGroup($ownGroup);
        $actorId = $this->createUser('peerpw_actor_t3');
        $this->addUserToGroup($actorId, $ownGroup);

        $targetId     = $this->createUser('peerpw_target_t3');
        $beforeHash   = $this->passwordHash($targetId);
        $newFirstname = 'Renamed' . bin2hex(random_bytes(3));

        $response = $this->dispatchUpdateUser($actorId, $targetId, [
            'username'     => 'peerpwt3' . bin2hex(random_bytes(3)),
            'firstname'    => $newFirstname,
            'surname'      => 'User',
            'email'        => 'peerpwt3_' . bin2hex(random_bytes(3)) . '@example.test',
            'group'        => [self::NONEXISTENT_GROUP_ID],
            'own_language' => 'en',
        ]);

        $this->assertNotSame(
            403,
            $response->getStatusCode(),
            'A non-superadmin actor updating non-password fields must not be blocked by the password guard.',
        );

        $after = $this->commonModel->selectOne('users', ['id' => $targetId]);
        $this->assertNotNull($after);
        $this->assertSame($newFirstname, $after->firstname);

        $afterHash = $this->passwordHash($targetId);
        $this->assertSame(
            $beforeHash,
            $afterHash,
            'No password field was POSTed, so password_hash must remain untouched.',
        );
    }

    private function passwordHash(int $userId): ?string
    {
        $identity = $this->commonModel->selectOne('auth_identities', [
            'user_id' => $userId,
            'type'    => 'email_password',
        ]);

        return $identity->secret2 ?? null;
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
     * Finds or creates the real "superadmin" auth_groups row (auth_groups.group
     * carries a UNIQUE index -- modules/Auth/Database/Migrations/
     * 2026-08-02-225933_AddUniqueKeyToAuthGroupsAndUsers.php -- so a blind
     * create() collides whenever a "superadmin" row already exists on the
     * shared, non-refreshed ci4ms_test schema), then adds a fresh user to
     * it. Identical fixture to
     * PermgroupPrivilegeEscalationTest::testIdorLocksOutTheRealSuperadminGroup()
     * (find-or-create, copy-pasted).
     */
    private function createSuperadminActor(string $label): int
    {
        $existingSuperadmin = $this->commonModel->selectOne('auth_groups', ['group' => 'superadmin']);
        if ($existingSuperadmin === null) {
            $this->commonModel->create('auth_groups', [
                'group'       => 'superadmin',
                'description' => 'superadmin',
            ]);
        }

        $actorId = $this->createUser($label);
        $this->addUserToGroup($actorId, 'superadmin');

        return $actorId;
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

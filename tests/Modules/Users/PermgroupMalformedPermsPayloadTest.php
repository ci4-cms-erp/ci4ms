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
use Modules\Users\Controllers\PermgroupController;
use ReflectionProperty;
use Throwable;

/**
 * Reproduction suite for B-4 (context.md "B-4 (DÜŞÜK) --
 * actorGrantsSubsetOfOwnPermissions() bozuk POST yükünde 500", scope widened
 * to fail-closed-everywhere by ci4ms-lead's "LEAD NİHAİ KARARLARI").
 *
 * Three malformed shapes of the perms[] POST field are exercised against all
 * three call sites that consume it (group_create():73/:77-81,
 * group_update():154/:158-162, user_perms():322/:332-336), each with both a
 * non-superadmin actor (reaches actorGrantsSubsetOfOwnPermissions()) and a
 * superadmin actor (the `!auth()->user()->inGroup('superadmin') && ...`
 * short-circuit at :73/:154/:322 skips the guard call entirely and falls
 * straight into the write loop):
 *
 * - P1: perms[<pageId>]['roles'] is an ARRAY instead of a string.
 *   explode('|', $perm['roles']) throws TypeError (confirmed empirically,
 *   this session: "explode(): Argument #2 ($string) must be of type string,
 *   array given").
 * - P2: perms itself is a SCALAR (e.g. 'abc') instead of an array.
 *   For a non-superadmin actor, actorGrantsSubsetOfOwnPermissions(array
 *   $pageMap, array $postedPerms) throws TypeError at the call boundary
 *   (confirmed empirically: PHP does not coerce a scalar into an
 *   array-typed parameter, declare(strict_types=1) or not -- grep confirms
 *   this file has no such declaration). For a superadmin actor the guard
 *   call never happens, so that TypeError never fires: `foreach ($scalar
 *   as ...)` in the unconditional write loop is what runs instead. A raw
 *   `php -r` probe (no framework bootstrap) shows this degrading to a
 *   0-iteration E_WARNING with no throw -- this class's tests originally
 *   assumed that meant a silent, uncaught, corrupting write. Running the
 *   actual controller under PHPUnit disproved that: this project's test
 *   environment converts the E_WARNING into an **ErrorException**.
 *   group_create()/group_update() have no try/catch around their write
 *   loops, so it propagates uncaught (PHPUnit reports a test Error, same
 *   practical outcome as P1/P3's TypeError, no data is written either
 *   way). user_perms()'s write loop sits inside its own
 *   `try { ... } catch (\Exception $e)`, and ErrorException -- unlike
 *   TypeError -- extends \Exception, so this specific case IS caught:
 *   execution never reaches syncPermissions(), so nothing is actually
 *   corrupted, but the response is a generic redirect-with-error rather
 *   than a deliberate fail-closed rejection (see that test's own docblock
 *   for the full detail). The lesson generalises: this class's docblocks
 *   describe what was actually observed running under PHPUnit, not what a
 *   standalone PHP CLI probe would predict -- the two differ here.
 * - P3: an individual perms[<pageId>] ROW is itself a scalar (e.g. 'abc')
 *   instead of ['roles' => ...]. $perm['roles'] is then a string-offset
 *   access with a non-numeric key, which throws TypeError in PHP 8
 *   (confirmed empirically: "Cannot access offset of type string on
 *   string") -- PHP 7's "Illegal string offset" E_WARNING behaviour no
 *   longer applies on the PHP 8.4.20 CLI this session ran on.
 *
 * TypeError extends \Error, not \Exception, so user_perms()'s
 * `try { ... } catch (\Exception $e)` (PermgroupController.php:314,:348)
 * does NOT catch any of P1/P2/P3's TypeErrors -- they propagate exactly the
 * same as they do from group_create()/group_update(), which have no
 * try/catch at all around these lines. Every "Throws" test below therefore
 * expects the SAME outcome (an uncaught PHP Error, today) regardless of
 * entry point.
 *
 * Every test in this class encodes ci4ms-lead's BINDING fail-closed target
 * (context.md "LEAD NİHAİ KARARLARI" / B-4), not today's behaviour -- they
 * are expected to FAIL (red) against today's controller. The assertion
 * order in each test is: (1) no PHP Error/Warning-driven data corruption
 * escapes uncaught / unnoticed, (2) no partial or silently-wiped permission
 * data was persisted, (3) where ci4ms-lead named a specific reject idiom
 * ("Üç çağrı sınırında ... dizi değilse istek fail-closed reddedilir": P2
 * only), the response uses that idiom. P1/P3 (the per-row shape check,
 * decision item (c)) deliberately do NOT assert a specific idiom -- the
 * lead left the reject *mechanism* there to the implementer.
 *
 * Same technique as tests/Modules/Users/PermgroupPrivilegeEscalationTest.php
 * and tests/Modules/Users/PermgroupUserPermsAuthzTest.php: the controller is
 * instantiated directly and driven through ReflectionProperty, bypassing
 * routing/filters -- this bug lives entirely inside the controller body.
 *
 * @internal
 */
final class PermgroupMalformedPermsPayloadTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use AuthenticationTesting;

    protected $refresh = false;

    protected $migrateOnce = true;

    /**
     * null migrates every registered namespace, not just Modules\Users --
     * fixtures need Modules\Auth's auth_groups/auth_groups_users tables and
     * Shield's own users/identities tables (same rationale as the sibling
     * suites in this directory).
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

        // Same order-independence guards as the sibling suites in this
        // directory (shared 'validation' and 'routes' services) -- see
        // PermgroupPrivilegeEscalationTest::setUp() for the full empirical
        // write-up.
        \Config\Services::resetSingle('validation');
        service('routes')->loadRoutes();

        cache()->clean();

        // group 'tests' explicitly: resolves to the SAME cached connection
        // object DatabaseTestTrait's own $this->db uses, so writes made
        // through this instance participate in this test's transaction and
        // roll back in tearDown(). See PermgroupPrivilegeEscalationTest::
        // setUp() for the full rationale.
        $this->commonModel = new CommonModel('tests');

        $this->db->transStart();
    }

    protected function tearDown(): void
    {
        $this->db->transRollback();

        parent::tearDown();
    }

    // ------------------------------------------------------------------
    // P1: perms[<pageId>]['roles'] is an ARRAY instead of a string.
    // ------------------------------------------------------------------

    /**
     * today: group_create()'s actorGrantsSubsetOfOwnPermissions() call
     * (:73) reaches explode('|', $perm['roles']) at :273 with an array
     * `roles` value and throws an uncaught TypeError -- no HTTP response is
     * produced at all, the request 500s.
     * target: rejected via group_create()'s existing reject idiom, no group
     * row persisted.
     */
    public function testGroupCreateWithRolesArrayThrowsUncaughtTypeErrorForPrivilegedActor(): void
    {
        [$pageId] = $this->createPermissionPages('mp_gc_p1_priv_pg');

        $ownGroup = 'mp-gc-p1-priv-own';
        $this->createGroup($ownGroup);
        $actorId = $this->createUser('mp_gc_p1_priv');
        $this->addUserToGroup($actorId, $ownGroup);

        $targetGroupName = 'mp-gc-p1-priv-new';

        $thrown = null;

        try {
            $this->dispatchGroupCreate($actorId, [
                'groupName'   => $targetGroupName,
                'description' => 'malformed roles array, privileged actor',
                'seflink'     => $targetGroupName,
                'perms'       => [$pageId => ['roles' => ['create_r', 'read_r']]],
            ]);
        } catch (Throwable $e) {
            $thrown = $e;
        }

        $this->assertNull($thrown, $this->crashMessage($thrown, 'group_create() (privileged actor, roles is an array)'));

        $created = $this->commonModel->selectOne('auth_groups', ['group' => $targetGroupName]);
        $this->assertNull($created, 'group_create() must not persist a group when a perms[] row\'s "roles" is not a string (fail-closed).');
    }

    /**
     * today: same as the privileged-actor variant -- the
     * `!auth()->user()->inGroup('superadmin') && ...` short-circuit at :73
     * skips the guard call for a superadmin actor, but the unconditional
     * write loop at :77-81 hits the identical unguarded explode() and
     * throws the same uncaught TypeError.
     * target: rejected, superadmin included, no group row persisted.
     */
    public function testGroupCreateWithRolesArrayThrowsUncaughtTypeErrorForSuperadmin(): void
    {
        [$pageId] = $this->createPermissionPages('mp_gc_p1_sa_pg');
        $superadminId = $this->superadminActorId('mp_gc_p1_sa');

        $targetGroupName = 'mp-gc-p1-sa-new';

        $thrown = null;

        try {
            $this->dispatchGroupCreate($superadminId, [
                'groupName'   => $targetGroupName,
                'description' => 'malformed roles array, superadmin actor',
                'seflink'     => $targetGroupName,
                'perms'       => [$pageId => ['roles' => ['create_r', 'read_r']]],
            ]);
        } catch (Throwable $e) {
            $thrown = $e;
        }

        $this->assertNull($thrown, $this->crashMessage($thrown, 'group_create() (superadmin actor, roles is an array)'));

        $created = $this->commonModel->selectOne('auth_groups', ['group' => $targetGroupName]);
        $this->assertNull($created, 'group_create() must not persist a group when a perms[] row\'s "roles" is not a string, even for a superadmin actor (fail-closed).');
    }

    /**
     * today: group_update()'s guard call (:154) reaches the same
     * unguarded explode() at :273 and throws an uncaught TypeError.
     * target: rejected, target group's stored permissions unchanged.
     */
    public function testGroupUpdateWithRolesArrayThrowsUncaughtTypeErrorForPrivilegedActor(): void
    {
        [$pageId] = $this->createPermissionPages('mp_gu_p1_priv_pg');

        $ownGroup = 'mp-gu-p1-priv-own';
        $this->createGroup($ownGroup);
        $actorId = $this->createUser('mp_gu_p1_priv');
        $this->addUserToGroup($actorId, $ownGroup);

        $targetGroupName = 'mp-gu-p1-priv-target';
        $targetGroupId   = $this->createGroupWithPermissions($targetGroupName);
        $before          = $this->commonModel->selectOne('auth_groups', ['id' => $targetGroupId]);

        $thrown = null;

        try {
            $this->dispatchGroupUpdate($actorId, $targetGroupId, [
                'groupName'   => $targetGroupName,
                'description' => $targetGroupName,
                'seflink'     => $targetGroupName,
                'perms'       => [$pageId => ['roles' => ['create_r', 'read_r']]],
            ]);
        } catch (Throwable $e) {
            $thrown = $e;
        }

        $this->assertNull($thrown, $this->crashMessage($thrown, 'group_update() (privileged actor, roles is an array)'));

        $after = $this->commonModel->selectOne('auth_groups', ['id' => $targetGroupId]);
        $this->assertNotNull($after, 'target group row disappeared.');
        $this->assertSame($before->permissions, $after->permissions, 'group_update() must not change the target group\'s permissions when a perms[] row\'s "roles" is not a string (fail-closed).');
    }

    /**
     * today: same crash, superadmin actor -- the guard call is skipped by
     * the short-circuit but the unconditional write loop at :158-162 hits
     * the same unguarded explode().
     * target: rejected, superadmin included, target group's stored
     * permissions unchanged.
     */
    public function testGroupUpdateWithRolesArrayThrowsUncaughtTypeErrorForSuperadmin(): void
    {
        [$pageId] = $this->createPermissionPages('mp_gu_p1_sa_pg');
        $superadminId = $this->superadminActorId('mp_gu_p1_sa');

        $targetGroupName = 'mp-gu-p1-sa-target';
        $targetGroupId   = $this->createGroupWithPermissions($targetGroupName);
        $before          = $this->commonModel->selectOne('auth_groups', ['id' => $targetGroupId]);

        $thrown = null;

        try {
            $this->dispatchGroupUpdate($superadminId, $targetGroupId, [
                'groupName'   => $targetGroupName,
                'description' => $targetGroupName,
                'seflink'     => $targetGroupName,
                'perms'       => [$pageId => ['roles' => ['create_r', 'read_r']]],
            ]);
        } catch (Throwable $e) {
            $thrown = $e;
        }

        $this->assertNull($thrown, $this->crashMessage($thrown, 'group_update() (superadmin actor, roles is an array)'));

        $after = $this->commonModel->selectOne('auth_groups', ['id' => $targetGroupId]);
        $this->assertNotNull($after, 'target group row disappeared.');
        $this->assertSame($before->permissions, $after->permissions, 'group_update() must not change the target group\'s permissions when a perms[] row\'s "roles" is not a string, even for a superadmin actor (fail-closed).');
    }

    /**
     * today: user_perms()'s guard call (:322) reaches the same unguarded
     * explode() at :273 inside the `try { ... } catch (\Exception $e)`
     * block (:314-350) -- since TypeError extends \Error, not \Exception,
     * the catch does not match and the TypeError still escapes uncaught.
     * target: rejected, target user's permissions unchanged.
     */
    public function testUserPermsWithRolesArrayThrowsUncaughtTypeErrorForPrivilegedActor(): void
    {
        [$ownedPageId, $ownedPagename] = $this->createPermissionPages('mp_up_p1_priv_pg');

        $ownGroup = 'mp-up-p1-priv-own';
        $this->createGroup($ownGroup);
        $actorId = $this->createUser('mp_up_p1_priv');
        $this->addUserToGroup($actorId, $ownGroup);
        $this->primeAuthGroupsMatrix($ownGroup, [$this->pagePermission($ownedPageId, 'read')]);

        $targetId = $this->createUser('mp_up_p1_priv_tgt');
        $before   = auth()->getProvider()->findById($targetId)->getPermissions();

        $thrown = null;

        try {
            $this->dispatchUserPerms($actorId, $targetId, [
                'perms' => [$ownedPageId => ['roles' => ['create_r', 'read_r']]],
            ]);
        } catch (Throwable $e) {
            $thrown = $e;
        }

        $this->assertNull($thrown, $this->crashMessage($thrown, 'user_perms() (privileged actor, roles is an array)'));

        $after = auth()->getProvider()->findById($targetId)->getPermissions();
        $this->assertSame($before, $after, 'user_perms() must not change the target\'s permissions when a perms[] row\'s "roles" is not a string (fail-closed).');
    }

    /**
     * today: same crash, superadmin actor -- the guard call is skipped by
     * the short-circuit but the write loop's explode() at :336 is reached
     * unconditionally and still throws, still uncaught by :348's
     * catch (\Exception $e).
     * target: rejected, superadmin included, target user's permissions
     * unchanged.
     */
    public function testUserPermsWithRolesArrayThrowsUncaughtTypeErrorForSuperadmin(): void
    {
        [$ownedPageId] = $this->createPermissionPages('mp_up_p1_sa_pg');
        $superadminId = $this->superadminActorId('mp_up_p1_sa');

        $targetId = $this->createUser('mp_up_p1_sa_tgt');
        $before   = auth()->getProvider()->findById($targetId)->getPermissions();

        $thrown = null;

        try {
            $this->dispatchUserPerms($superadminId, $targetId, [
                'perms' => [$ownedPageId => ['roles' => ['create_r', 'read_r']]],
            ]);
        } catch (Throwable $e) {
            $thrown = $e;
        }

        $this->assertNull($thrown, $this->crashMessage($thrown, 'user_perms() (superadmin actor, roles is an array)'));

        $after = auth()->getProvider()->findById($targetId)->getPermissions();
        $this->assertSame($before, $after, 'user_perms() must not change the target\'s permissions when a perms[] row\'s "roles" is not a string, even for a superadmin actor (fail-closed).');
    }

    // ------------------------------------------------------------------
    // P2: perms itself is a SCALAR instead of an array.
    // ------------------------------------------------------------------

    /**
     * today: group_create()'s guard call (:73) passes the raw scalar
     * straight into actorGrantsSubsetOfOwnPermissions(array $pageMap,
     * array $postedPerms) -- PHP throws TypeError at the call boundary
     * (argument type mismatch) before the method body ever runs.
     * target: rejected via group_create()'s named idiom
     * (redirect()->route('group_create')->withInput()->with('errors', ...),
     * per ci4ms-lead's decision item (b)), no group row persisted.
     */
    public function testGroupCreateWithScalarPermsThrowsUncaughtTypeErrorForPrivilegedActor(): void
    {
        $ownGroup = 'mp-gc-p2-priv-own';
        $this->createGroup($ownGroup);
        $actorId = $this->createUser('mp_gc_p2_priv');
        $this->addUserToGroup($actorId, $ownGroup);

        $targetGroupName = 'mp-gc-p2-priv-new';

        $thrown   = null;
        $response = null;

        try {
            $response = $this->dispatchGroupCreate($actorId, [
                'groupName'   => $targetGroupName,
                'description' => 'perms is a scalar, privileged actor',
                'seflink'     => $targetGroupName,
                'perms'       => 'abc',
            ]);
        } catch (Throwable $e) {
            $thrown = $e;
        }

        $this->assertNull($thrown, $this->crashMessage($thrown, 'group_create() (privileged actor, perms is a scalar)'));

        $created = $this->commonModel->selectOne('auth_groups', ['group' => $targetGroupName]);
        $this->assertNull($created, 'group_create() must not persist a group when perms is not an array (fail-closed).');

        $this->assertSame(302, $response->getStatusCode(), "group_create() must reject a non-array perms via its existing redirect()->route('group_create')->withInput()->with('errors', ...) idiom.");
    }

    /**
     * today (MEASURED, corrects this class's own original prediction --
     * see git history / QA report for the raw `php -r` probe that got this
     * wrong before running it through PHPUnit): the
     * `!auth()->user()->inGroup('superadmin') && ...` short-circuit at :73
     * skips the guard call entirely for a superadmin actor, so the guard's
     * own TypeError never fires. The unconditional write loop's
     * `foreach ($this->request->getPost('perms') as ...)` at :77 then
     * iterates over a plain string. A raw PHP CLI probe (no framework
     * bootstrap) shows this degrading to a 0-iteration E_WARNING with no
     * throw -- but running the actual controller under PHPUnit shows this
     * project's environment converts that E_WARNING into an
     * **ErrorException**, which propagates UNCAUGHT out of group_create()
     * (no try/catch there at all), exactly like the TypeError cases above.
     * PHPUnit reports this as a test Error, not a Failure. No group row
     * ends up persisted either way, but there is no controlled reject
     * response -- the request simply 500s.
     * target: rejected, no group row persisted (superadmin included, per
     * ci4ms-lead's binding "Superadmin de dahil reddedilir").
     */
    public function testGroupCreateWithScalarPermsThrowsUncaughtErrorForSuperadmin(): void
    {
        $superadminId = $this->superadminActorId('mp_gc_p2_sa');

        $targetGroupName = 'mp-gc-p2-sa-new';

        $thrown   = null;
        $response = null;

        try {
            $response = $this->dispatchGroupCreate($superadminId, [
                'groupName'   => $targetGroupName,
                'description' => 'perms is a scalar, superadmin actor',
                'seflink'     => $targetGroupName,
                'perms'       => 'abc',
            ]);
        } catch (Throwable $e) {
            $thrown = $e;
        }

        $this->assertNull($thrown, $this->crashMessage($thrown, 'group_create() (superadmin actor, perms is a scalar)'));

        $created = $this->commonModel->selectOne('auth_groups', ['group' => $targetGroupName]);
        $this->assertNull($created, 'group_create() must not persist a group when perms is not an array, even for a superadmin actor (fail-closed).');

        $this->assertSame(302, $response->getStatusCode(), "group_create() must reject a non-array perms via its existing redirect()->route('group_create')->withInput()->with('errors', ...) idiom, superadmin included.");
    }

    /**
     * today: group_update()'s guard call (:154) passes the raw scalar into
     * the same array-typed parameter and throws TypeError at the call
     * boundary, before the method body runs.
     * target: rejected via group_update()'s named idiom (failForbidden(),
     * per ci4ms-lead's decision item (b)), target group's stored
     * permissions unchanged.
     */
    public function testGroupUpdateWithScalarPermsThrowsUncaughtTypeErrorForPrivilegedActor(): void
    {
        $ownGroup = 'mp-gu-p2-priv-own';
        $this->createGroup($ownGroup);
        $actorId = $this->createUser('mp_gu_p2_priv');
        $this->addUserToGroup($actorId, $ownGroup);

        $targetGroupName = 'mp-gu-p2-priv-target';
        $targetGroupId   = $this->createGroupWithPermissions($targetGroupName);
        $before          = $this->commonModel->selectOne('auth_groups', ['id' => $targetGroupId]);

        $thrown   = null;
        $response = null;

        try {
            $response = $this->dispatchGroupUpdate($actorId, $targetGroupId, [
                'groupName'   => $targetGroupName,
                'description' => $targetGroupName,
                'seflink'     => $targetGroupName,
                'perms'       => 'abc',
            ]);
        } catch (Throwable $e) {
            $thrown = $e;
        }

        $this->assertNull($thrown, $this->crashMessage($thrown, 'group_update() (privileged actor, perms is a scalar)'));

        $after = $this->commonModel->selectOne('auth_groups', ['id' => $targetGroupId]);
        $this->assertNotNull($after, 'target group row disappeared.');
        $this->assertSame($before->permissions, $after->permissions, 'group_update() must not change the target group\'s permissions when perms is not an array (fail-closed).');

        $this->assertSame(403, $response->getStatusCode(), 'group_update() must reject a non-array perms via its existing failForbidden() idiom.');
    }

    /**
     * today (MEASURED under PHPUnit, corrects this class's own initial
     * raw-CLI-probe prediction -- see the analogous group_create() test's
     * docblock for the full explanation): the guard call is skipped by the
     * superadmin short-circuit at :154, so the write loop's foreach over a
     * scalar at :158 runs unconditionally. This project's environment
     * converts the resulting E_WARNING into an uncaught ErrorException
     * that propagates straight out of group_update() (no try/catch there
     * either) -- a 500, not a silent wipe. PHPUnit reports this as a test
     * Error.
     * target: rejected, target group's stored permissions unchanged
     * (superadmin included).
     */
    public function testGroupUpdateWithScalarPermsThrowsUncaughtErrorForSuperadmin(): void
    {
        $superadminId = $this->superadminActorId('mp_gu_p2_sa');

        $targetGroupName = 'mp-gu-p2-sa-target';
        $targetGroupId   = $this->createGroupWithPermissions($targetGroupName);
        $before          = $this->commonModel->selectOne('auth_groups', ['id' => $targetGroupId]);

        $thrown   = null;
        $response = null;

        try {
            $response = $this->dispatchGroupUpdate($superadminId, $targetGroupId, [
                'groupName'   => $targetGroupName,
                'description' => $targetGroupName,
                'seflink'     => $targetGroupName,
                'perms'       => 'abc',
            ]);
        } catch (Throwable $e) {
            $thrown = $e;
        }

        $this->assertNull($thrown, $this->crashMessage($thrown, 'group_update() (superadmin actor, perms is a scalar)'));

        $after = $this->commonModel->selectOne('auth_groups', ['id' => $targetGroupId]);
        $this->assertNotNull($after, 'target group row disappeared.');
        $this->assertSame($before->permissions, $after->permissions, 'group_update() must not change the target group\'s permissions when perms is not an array, even for a superadmin actor (fail-closed).');

        $this->assertSame(403, $response->getStatusCode(), 'group_update() must reject a non-array perms via its existing failForbidden() idiom, superadmin included.');
    }

    /**
     * today: user_perms()'s guard call (:322) passes the raw scalar into
     * the same array-typed parameter and throws TypeError at the call
     * boundary, inside the try block (:314) but -- as above -- TypeError
     * is not caught by catch (\Exception $e) at :348, so it still escapes
     * uncaught.
     * target: rejected via user_perms()'s own existing reject pattern
     * (failForbidden(lang('Users.permsExceedOwnGrant')), the same idiom
     * the delegation-ceiling guard already uses at :323), target user's
     * permissions unchanged.
     */
    public function testUserPermsWithScalarPermsThrowsUncaughtTypeErrorForPrivilegedActor(): void
    {
        [$ownedPageId] = $this->createPermissionPages('mp_up_p2_priv_pg');

        $ownGroup = 'mp-up-p2-priv-own';
        $this->createGroup($ownGroup);
        $actorId = $this->createUser('mp_up_p2_priv');
        $this->addUserToGroup($actorId, $ownGroup);
        $this->primeAuthGroupsMatrix($ownGroup, [$this->pagePermission($ownedPageId, 'read')]);

        $targetId = $this->createUser('mp_up_p2_priv_tgt');
        $before   = auth()->getProvider()->findById($targetId)->getPermissions();

        $thrown   = null;
        $response = null;

        try {
            $response = $this->dispatchUserPerms($actorId, $targetId, ['perms' => 'abc']);
        } catch (Throwable $e) {
            $thrown = $e;
        }

        $this->assertNull($thrown, $this->crashMessage($thrown, 'user_perms() (privileged actor, perms is a scalar)'));

        $after = auth()->getProvider()->findById($targetId)->getPermissions();
        $this->assertSame($before, $after, 'user_perms() must not change the target\'s permissions when perms is not an array (fail-closed).');

        $this->assertSame(403, $response->getStatusCode(), "user_perms() must reject a non-array perms via its existing failForbidden(lang('Users.permsExceedOwnGrant')) pattern.");
    }

    /**
     * today (MEASURED under PHPUnit -- this one genuinely differs from
     * group_create()/group_update()'s superadmin+P2 tests, not just a
     * corrected prediction): the same E_WARNING-turned-ErrorException from
     * `foreach ('abc' as ...)` (:332) happens here too, but this call site
     * sits inside user_perms()'s own `try { ... } catch (\Exception $e)`
     * block (:314-350) -- and ErrorException DOES extend \Exception, so
     * *this* one IS caught (unlike the P1/P3 TypeErrors above, which are
     * \Error and never caught by this same block). The catch fires its
     * generic reject path: redirect()->route('user_perms',[$id])
     * ->withInput()->with('error', lang('Backend.notUpdated',
     * [$e->getMessage()])) -- a 302, not the specific
     * failForbidden(lang('Users.permsExceedOwnGrant')) 403 the
     * delegation-ceiling guard one line above (:322-323) already uses for
     * a *recognized* foreign-permission grant. Because the exception fires
     * before $user->syncPermissions(...$perms) is ever reached, the
     * target's permissions are NOT actually corrupted today (this class's
     * own first prediction, made from a raw `php -r` probe with no
     * framework bootstrap, wrongly assumed a silent 0-iteration foreach
     * and a real wipe -- corrected here after actually running it). The
     * real gap is narrower than that: a malformed payload is not being
     * deliberately fail-closed rejected, it is falling through to a
     * generic catch-all that also leaks $e->getMessage() (an internal PHP
     * exception string) into a user-facing flash message.
     * target: rejected via user_perms()'s own delegation-ceiling idiom
     * (failForbidden(lang('Users.permsExceedOwnGrant'))), target user's
     * permissions unchanged (superadmin included).
     */
    public function testUserPermsWithScalarPermsIsCaughtButNotDeliberatelyRejectedForSuperadmin(): void
    {
        [$ownedPageId, $ownedPagename] = $this->createPermissionPages('mp_up_p2_sa_pg');
        $this->primeAuthGroupsMatrix('mp-up-p2-sa-irrelevant', [$this->pagePermission($ownedPageId, 'read')]);

        $targetId = $this->createUser('mp_up_p2_sa_tgt');
        auth()->getProvider()->findById($targetId)->syncPermissions(strtolower($ownedPagename) . '.read');
        $before = auth()->getProvider()->findById($targetId)->getPermissions();
        $this->assertNotEmpty($before, 'fixture assumption violated: target must start with a direct permission.');

        $superadminId = $this->superadminActorId('mp_up_p2_sa');

        $response = $this->dispatchUserPerms($superadminId, $targetId, ['perms' => 'abc']);

        $after = auth()->getProvider()->findById($targetId)->getPermissions();
        $this->assertSame(
            $before,
            $after,
            'sanity check: user_perms() must not change the target\'s permissions when perms is not an array. Today this happens to hold -- the ErrorException from foreach() over a scalar is thrown, and caught by :348, before $user->syncPermissions() is ever reached -- but only incidentally, not via a deliberate fail-closed check; see the status-code assertion below for the actual gap.',
        );

        $this->assertSame(403, $response->getStatusCode(), "user_perms() must reject a non-array perms via its existing failForbidden(lang('Users.permsExceedOwnGrant')) pattern, superadmin included.");
    }

    // ------------------------------------------------------------------
    // P3: an individual perms[<pageId>] row is itself a SCALAR instead of
    // ['roles' => ...].
    // ------------------------------------------------------------------

    /**
     * today: group_create()'s guard call (:73) reaches
     * `$perm['roles']` at :273 with $perm === 'abc' (a string) -- PHP 8
     * throws TypeError on a non-numeric string-offset access ("Cannot
     * access offset of type string on string", confirmed empirically; this
     * is NOT the PHP 7 "Illegal string offset" E_WARNING).
     * target: rejected, no group row persisted.
     */
    public function testGroupCreateWithScalarRowThrowsUncaughtTypeErrorForPrivilegedActor(): void
    {
        [$pageId] = $this->createPermissionPages('mp_gc_p3_priv_pg');

        $ownGroup = 'mp-gc-p3-priv-own';
        $this->createGroup($ownGroup);
        $actorId = $this->createUser('mp_gc_p3_priv');
        $this->addUserToGroup($actorId, $ownGroup);

        $targetGroupName = 'mp-gc-p3-priv-new';

        $thrown = null;

        try {
            $this->dispatchGroupCreate($actorId, [
                'groupName'   => $targetGroupName,
                'description' => 'perms row is a scalar, privileged actor',
                'seflink'     => $targetGroupName,
                'perms'       => [$pageId => 'abc'],
            ]);
        } catch (Throwable $e) {
            $thrown = $e;
        }

        $this->assertNull($thrown, $this->crashMessage($thrown, 'group_create() (privileged actor, perms row is a scalar)'));

        $created = $this->commonModel->selectOne('auth_groups', ['group' => $targetGroupName]);
        $this->assertNull($created, 'group_create() must not persist a group when a perms[] row is itself a scalar instead of an array (fail-closed).');
    }

    /**
     * today: same crash, superadmin actor -- the guard call is skipped by
     * the short-circuit, but the write loop's foreach at :77 iterates a
     * genuine one-element array fine (unlike P2's top-level scalar) and
     * hits the identical string-offset TypeError at :81.
     * target: rejected, superadmin included, no group row persisted.
     */
    public function testGroupCreateWithScalarRowThrowsUncaughtTypeErrorForSuperadmin(): void
    {
        [$pageId] = $this->createPermissionPages('mp_gc_p3_sa_pg');
        $superadminId = $this->superadminActorId('mp_gc_p3_sa');

        $targetGroupName = 'mp-gc-p3-sa-new';

        $thrown = null;

        try {
            $this->dispatchGroupCreate($superadminId, [
                'groupName'   => $targetGroupName,
                'description' => 'perms row is a scalar, superadmin actor',
                'seflink'     => $targetGroupName,
                'perms'       => [$pageId => 'abc'],
            ]);
        } catch (Throwable $e) {
            $thrown = $e;
        }

        $this->assertNull($thrown, $this->crashMessage($thrown, 'group_create() (superadmin actor, perms row is a scalar)'));

        $created = $this->commonModel->selectOne('auth_groups', ['group' => $targetGroupName]);
        $this->assertNull($created, 'group_create() must not persist a group when a perms[] row is itself a scalar, even for a superadmin actor (fail-closed).');
    }

    /**
     * today: group_update()'s guard call (:154) hits the same string-offset
     * TypeError at :273.
     * target: rejected, target group's stored permissions unchanged.
     */
    public function testGroupUpdateWithScalarRowThrowsUncaughtTypeErrorForPrivilegedActor(): void
    {
        [$pageId] = $this->createPermissionPages('mp_gu_p3_priv_pg');

        $ownGroup = 'mp-gu-p3-priv-own';
        $this->createGroup($ownGroup);
        $actorId = $this->createUser('mp_gu_p3_priv');
        $this->addUserToGroup($actorId, $ownGroup);

        $targetGroupName = 'mp-gu-p3-priv-target';
        $targetGroupId   = $this->createGroupWithPermissions($targetGroupName);
        $before          = $this->commonModel->selectOne('auth_groups', ['id' => $targetGroupId]);

        $thrown = null;

        try {
            $this->dispatchGroupUpdate($actorId, $targetGroupId, [
                'groupName'   => $targetGroupName,
                'description' => $targetGroupName,
                'seflink'     => $targetGroupName,
                'perms'       => [$pageId => 'abc'],
            ]);
        } catch (Throwable $e) {
            $thrown = $e;
        }

        $this->assertNull($thrown, $this->crashMessage($thrown, 'group_update() (privileged actor, perms row is a scalar)'));

        $after = $this->commonModel->selectOne('auth_groups', ['id' => $targetGroupId]);
        $this->assertNotNull($after, 'target group row disappeared.');
        $this->assertSame($before->permissions, $after->permissions, 'group_update() must not change the target group\'s permissions when a perms[] row is itself a scalar (fail-closed).');
    }

    /**
     * today: same crash, superadmin actor.
     * target: rejected, superadmin included, target group's stored
     * permissions unchanged.
     */
    public function testGroupUpdateWithScalarRowThrowsUncaughtTypeErrorForSuperadmin(): void
    {
        [$pageId] = $this->createPermissionPages('mp_gu_p3_sa_pg');
        $superadminId = $this->superadminActorId('mp_gu_p3_sa');

        $targetGroupName = 'mp-gu-p3-sa-target';
        $targetGroupId   = $this->createGroupWithPermissions($targetGroupName);
        $before          = $this->commonModel->selectOne('auth_groups', ['id' => $targetGroupId]);

        $thrown = null;

        try {
            $this->dispatchGroupUpdate($superadminId, $targetGroupId, [
                'groupName'   => $targetGroupName,
                'description' => $targetGroupName,
                'seflink'     => $targetGroupName,
                'perms'       => [$pageId => 'abc'],
            ]);
        } catch (Throwable $e) {
            $thrown = $e;
        }

        $this->assertNull($thrown, $this->crashMessage($thrown, 'group_update() (superadmin actor, perms row is a scalar)'));

        $after = $this->commonModel->selectOne('auth_groups', ['id' => $targetGroupId]);
        $this->assertNotNull($after, 'target group row disappeared.');
        $this->assertSame($before->permissions, $after->permissions, 'group_update() must not change the target group\'s permissions when a perms[] row is itself a scalar, even for a superadmin actor (fail-closed).');
    }

    /**
     * today: user_perms()'s guard call (:322) hits the same string-offset
     * TypeError at :273, inside the try block but uncaught by :348's
     * catch (\Exception $e) for the same reason as P1/P2.
     * target: rejected, target user's permissions unchanged.
     */
    public function testUserPermsWithScalarRowThrowsUncaughtTypeErrorForPrivilegedActor(): void
    {
        [$ownedPageId] = $this->createPermissionPages('mp_up_p3_priv_pg');

        $ownGroup = 'mp-up-p3-priv-own';
        $this->createGroup($ownGroup);
        $actorId = $this->createUser('mp_up_p3_priv');
        $this->addUserToGroup($actorId, $ownGroup);
        $this->primeAuthGroupsMatrix($ownGroup, [$this->pagePermission($ownedPageId, 'read')]);

        $targetId = $this->createUser('mp_up_p3_priv_tgt');
        $before   = auth()->getProvider()->findById($targetId)->getPermissions();

        $thrown = null;

        try {
            $this->dispatchUserPerms($actorId, $targetId, ['perms' => [$ownedPageId => 'abc']]);
        } catch (Throwable $e) {
            $thrown = $e;
        }

        $this->assertNull($thrown, $this->crashMessage($thrown, 'user_perms() (privileged actor, perms row is a scalar)'));

        $after = auth()->getProvider()->findById($targetId)->getPermissions();
        $this->assertSame($before, $after, 'user_perms() must not change the target\'s permissions when a perms[] row is itself a scalar (fail-closed).');
    }

    /**
     * today: same crash, superadmin actor -- the write loop's foreach at
     * :332 iterates a genuine one-element array fine, then hits the same
     * string-offset TypeError at :336.
     * target: rejected, superadmin included, target user's permissions
     * unchanged.
     */
    public function testUserPermsWithScalarRowThrowsUncaughtTypeErrorForSuperadmin(): void
    {
        [$ownedPageId] = $this->createPermissionPages('mp_up_p3_sa_pg');
        $superadminId = $this->superadminActorId('mp_up_p3_sa');

        $targetId = $this->createUser('mp_up_p3_sa_tgt');
        $before   = auth()->getProvider()->findById($targetId)->getPermissions();

        $thrown = null;

        try {
            $this->dispatchUserPerms($superadminId, $targetId, ['perms' => [$ownedPageId => 'abc']]);
        } catch (Throwable $e) {
            $thrown = $e;
        }

        $this->assertNull($thrown, $this->crashMessage($thrown, 'user_perms() (superadmin actor, perms row is a scalar)'));

        $after = auth()->getProvider()->findById($targetId)->getPermissions();
        $this->assertSame($before, $after, 'user_perms() must not change the target\'s permissions when a perms[] row is itself a scalar, even for a superadmin actor (fail-closed).');
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * Builds a diagnostic message for a "must not throw" assertion,
     * including the real exception class/message when one was caught --
     * this is the evidence a future fix is verified against, not a
     * hardcoded guess at PHP's exact wording.
     */
    private function crashMessage(?Throwable $thrown, string $where): string
    {
        if ($thrown === null) {
            return "{$where}: no PHP Error/Exception observed.";
        }

        return sprintf(
            '%s must not let an uncaught %s escape (fail-closed reject expected instead). Message: %s',
            $where,
            get_class($thrown),
            $thrown->getMessage(),
        );
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
     * Creates two auth_permissions_pages rows (an "owned" one and a
     * "foreign" one, sharing $labelPrefix) and returns
     * [ownedPageId, ownedPagename]. Only the first element is used by most
     * callers in this class -- the second (foreign) row exists purely so
     * $labelPrefix stays consistent with the sibling suites' fixture, which
     * some helpers here are copied from.
     *
     * @return array{0: int, 1: string} [insertId, pagename]
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
     * Seeds Modules\Auth\Config\AuthGroups' 24h-cached group/permission
     * matrix directly, and populates the 'permissions' key Shield's
     * syncPermissions() validates against -- see
     * PermgroupUserPermsAuthzTest::primeAuthGroupsMatrix() for the full
     * rationale (identical helper, copied).
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
     * Creates an auth_groups row that already carries a non-empty,
     * deliberately-fake permissions matrix, so group_update() tests can
     * assert that matrix is left UNTOUCHED by a rejected request (as
     * opposed to group_create() tests, which assert no row exists at all).
     * The referenced page_id does not need to exist -- this JSON blob is
     * only ever compared for equality, never decoded against
     * auth_permissions_pages.
     */
    private function createGroupWithPermissions(string $name): int
    {
        return (int) $this->commonModel->create('auth_groups', [
            'group'       => $name,
            'description' => $name,
            'permissions' => json_encode([
                [
                    'page_id'    => 999999,
                    'create_r'   => true,
                    'update_r'   => false,
                    'read_r'     => true,
                    'delete_r'   => false,
                    'who_perm'   => 1,
                    'created_at' => '2026-01-01 00:00:00',
                ],
            ], JSON_UNESCAPED_UNICODE),
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
     * Creates (or reuses) the real "superadmin" auth_groups row and a new
     * user who is a member of it. Find-or-create mirrors
     * PermgroupPrivilegeEscalationTest::testIdorLocksOutTheRealSuperadminGroup():
     * auth_groups.group carries a UNIQUE index, so a blind create() would
     * collide with a "superadmin" row already present on the shared,
     * non-refreshed ci4ms_test schema.
     */
    private function superadminActorId(string $label): int
    {
        $existingSuperadmin = $this->commonModel->selectOne('auth_groups', ['group' => 'superadmin']);
        if ($existingSuperadmin === null) {
            $this->commonModel->create('auth_groups', [
                'group'       => 'superadmin',
                'description' => 'superadmin',
            ]);
        }

        $userId = $this->createUser($label);
        $this->addUserToGroup($userId, 'superadmin');

        return $userId;
    }

    /**
     * Logs $actorId in and runs PermgroupController::group_create() with
     * $post as the request body, bypassing routing and filters.
     *
     * @param array<string, mixed> $post
     */
    private function dispatchGroupCreate(int $actorId, array $post): ResponseInterface
    {
        $controller = new PermgroupController();
        $this->primeRequest($actorId, $post, $controller);

        return $controller->group_create();
    }

    /**
     * Logs $actorId in and runs PermgroupController::group_update($groupId)
     * with $post as the request body, bypassing routing and filters.
     *
     * @param array<string, mixed> $post
     */
    private function dispatchGroupUpdate(int $actorId, int $groupId, array $post): ResponseInterface
    {
        $controller = new PermgroupController();
        $this->primeRequest($actorId, $post, $controller);

        return $controller->group_update($groupId);
    }

    /**
     * Logs $actorId in and runs PermgroupController::user_perms($targetId)
     * with $post as the request body, bypassing routing and filters.
     *
     * @param array<string, mixed> $post
     */
    private function dispatchUserPerms(int $actorId, int $targetId, array $post): ResponseInterface
    {
        $controller = new PermgroupController();
        $this->primeRequest($actorId, $post, $controller);

        return $controller->user_perms($targetId);
    }

    /**
     * Logs $actorId in, wires a POST IncomingRequest onto $controller and
     * shares this test's CommonModel instance as $controller->commonModel.
     *
     * @param array<string, mixed> $post
     */
    private function primeRequest(int $actorId, array $post, PermgroupController $controller): void
    {
        $actor = auth()->getProvider()->findById($actorId);
        $this->actingAs($actor);

        $config  = config(App::class);
        $request = new IncomingRequest($config, new SiteURI($config), null, new UserAgent());
        $request->setMethod('POST');
        // getPost() reads the 'post' bucket, but Validation::withRequest()
        // reads getVar() -> fetchGlobal('request', ...) -- a *separate*
        // bucket that setGlobal('post', ...) does not populate. Both must be
        // set or every rule in $this->validate() fails as "required" even
        // though the field was submitted.
        $request->setGlobal('post', $post);
        $request->setGlobal('request', $post);

        $controller->commonModel = $this->commonModel;

        (new ReflectionProperty(\CodeIgniter\Controller::class, 'request'))->setValue($controller, $request);
        (new ReflectionProperty(\CodeIgniter\Controller::class, 'response'))->setValue($controller, service('response', $config, false));
    }
}

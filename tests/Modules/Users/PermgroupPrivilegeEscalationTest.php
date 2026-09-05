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

/**
 * Reproduction suite for BLOKER-1 (CRITICAL privilege escalation via
 * PermgroupController::group_update()/group_create()).
 *
 * group_update() applies none of the guards that would be required to keep
 * a group rename safe: no check that the target group is (or is becoming)
 * "superadmin" (see context.md FAZ2-K2 G2/G3), no re-check of uniqueness on
 * the transformed value (G4), and no ownership check on $id (G1's IDOR
 * angle). Consequently any authenticated user reaching this method can
 * rename a group -- their own, or one they have no relationship to -- to
 * "superadmin" and immediately pass Authorizable::inGroup('superadmin').
 * group_create() has the mirror bug: is_unique[auth_groups.group] validates
 * the *raw* groupName, but the row is persisted after esc(seflink(...)), so
 * a raw value that doesn't collide can still collide once transformed.
 *
 * T1/T2a/T2b/T3 encode the SAFE behaviour a future fix must produce, so they
 * are expected to FAIL (red) against today's controller -- the assertions
 * are the security invariant, not a description of current behaviour. T4 is
 * the sole green regression guard: a legitimate rename by a non-member of
 * an unrelated, non-superadmin group must keep working.
 *
 * The controller is instantiated directly and driven through
 * ReflectionProperty (same technique as
 * tests/Modules/Settings/UpdateRollbackControllerTest.php) instead of being
 * dispatched through routing: BLOKER-1 lives entirely inside the controller
 * body, not in Ci4MsAuthFilter/backendGuard, so exercising the filter chain
 * would only add noise and require a Methods permission row that doesn't
 * exist yet. What is therefore NOT covered here is the route/filter/
 * permission layer in front of group_update() and group_create() -- only
 * the controller's own logic.
 *
 * @internal
 */
final class PermgroupPrivilegeEscalationTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use AuthenticationTesting;

    protected $refresh = false;

    protected $migrateOnce = true;

    /**
     * null migrates every registered namespace (App + Modules\* + Shield),
     * not just Modules\Users -- the fixtures need Modules\Auth's
     * auth_groups/auth_groups_users tables and Shield's own users/
     * identities tables.
     */
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

        // Defense in depth, order-independence: group_update()/group_create()
        // both call Controller::validate() (PermgroupController.php:56,133),
        // which uses the process-shared Config\Services::validation()
        // singleton (getShared=true). CodeIgniter\Validation\Validation::run()
        // never clears $this->errors itself — only its own reset() does
        // (vendor/codeigniter4/framework/system/Validation/Validation.php:
        // 1081-1090) — so if ANY earlier test in the same PHPUnit process
        // leaves that singleton with stale errors (SecurityXSSCSRFTest used
        // to, before its own tearDown() started clearing this; see
        // context.md), dispatchGroupUpdate()/dispatchGroupCreate() below would
        // fail validation regardless of their own fields being valid. Every
        // "rejected" assertion in this class (T1/T2a/T2b/T3/T5/T7/T9) would
        // stay green either way (a stale-validation 302 and a legitimate
        // guard's 403/redirect both read as "not persisted"), but the
        // "allowed" ones (T4/T6/T8) and BLOKER-B's post-guard bystander check
        // would go red — proven empirically before adding this line.
        // resetSingle() is BaseService's test-only API for discarding one
        // shared/mock service (BaseService.php:424-435) so the next
        // service('validation') call builds a fresh instance — the same
        // narrow scope SecurityXSSCSRFTest uses, not the broader
        // Services::reset()/resetFactories(), which would also drop this
        // class's own DatabaseTestTrait connection state.
        \Config\Services::resetSingle('validation');

        // Same order-independence concern, different process-shared service.
        // The 'routes' RouteCollection is discovered exactly once per PHPUnit
        // process (vendor/codeigniter4/framework/system/Test/bootstrap.php:90)
        // and any earlier test calling Services::reset() drops it wholesale
        // along with every other shared instance (BaseService.php:366-375) --
        // seven tests/Modules/Notifications/* tearDowns do exactly that.
        // Tests dispatched through FeatureTestTrait::call() heal themselves
        // because that method reloads the routes on every call
        // (FeatureTestTrait.php:216); this class drives the controller
        // directly, so nothing else here would rebuild the collection and
        // PermgroupController's redirect()->route() calls (:62,:77,:208)
        // would throw HTTPException "The route for 'group_create'/'groupList'
        // cannot be found" (RedirectResponse.php:63) -- reproduced with
        // --order-by=random --random-order-seed=20260802, see context.md.
        // loadRoutes() is a no-op on an already-discovered collection
        // (RouteCollection.php:312-314), so this costs nothing in a clean
        // process.
        service('routes')->loadRoutes();

        // AuthGroups.php caches the group/permission matrix for 86400s and
        // Ci4MsAuthFilter caches per-route permission lookups for 3600s;
        // either one going stale can make an exploit look blocked (false
        // green) or a fix look broken (false red).
        cache()->clean();

        // Every fixture, controller and assertion query in this class goes
        // through this single instance so it shares DatabaseTestTrait's own
        // connection ($this->db, DBGroup "tests"). CommonModel's default
        // group ("default") resolves to a *different* connection object --
        // even though Config\Database aliases its values to "tests" under
        // ENVIRONMENT === 'testing' -- so writes made through a
        // default-group CommonModel would autocommit outside this
        // transaction and leak into ci4ms_test instead of being rolled
        // back in tearDown().
        $this->commonModel = new CommonModel('tests');

        $this->db->transStart();
    }

    protected function tearDown(): void
    {
        $this->db->transRollback();

        parent::tearDown();
    }

    /**
     * T1: a member of a non-superadmin group renames that same group to
     * "superadmin" and expects to pass inGroup('superadmin') afterwards.
     */
    public function testRenamingOwnGroupToSuperadminGrantsSuperadmin(): void
    {
        $editorsGroupId = $this->createGroup('editors');
        $attackerId     = $this->createUser('attacker_t1');
        $this->addUserToGroup($attackerId, 'editors');

        $this->dispatchGroupUpdate($attackerId, $editorsGroupId, [
            'groupName'   => 'Superadmin',
            'description' => 'Renamed by attacker',
            'seflink'     => 'editors',
            'perms'       => [1 => ['roles' => 'read_r']],
        ]);

        $freshGroup = $this->commonModel->selectOne('auth_groups', ['id' => $editorsGroupId]);
        $this->assertNotNull($freshGroup, 'editors group row disappeared.');
        $this->assertNotSame(
            'superadmin',
            $freshGroup->group,
            'group_update() must refuse renaming a non-superadmin group to "superadmin".',
        );

        // groupCache is populated once per User instance (Authorizable::
        // populateGroups()); the pre-rename $attacker object used for
        // actingAs() must never be reused for this check.
        $freshAttacker = auth()->getProvider()->findById($attackerId);
        $this->assertNotNull($freshAttacker, 'attacker user disappeared.');
        $this->assertFalse(
            $freshAttacker->inGroup('superadmin'),
            'Attacker must not gain the superadmin group by renaming their own group.',
        );
    }

    /**
     * T2a (IDOR, lockout direction): a member of an unrelated group renames
     * the *real* superadmin group away, which would lock every superadmin
     * out.
     */
    public function testIdorLocksOutTheRealSuperadminGroup(): void
    {
        // Use the *real* superadmin group row (see class docblock), not a
        // freshly created one: auth_groups.group carries a UNIQUE index
        // (modules/Auth/Database/Migrations/
        // 2026-08-02-225933_AddUniqueKeyToAuthGroupsAndUsers.php), so on the
        // shared, non-refreshed ci4ms_test schema a blind create() collides
        // whenever a "superadmin" row already exists from prior setup or
        // earlier tests. Find-or-create keeps this order-independent for a
        // clean schema too.
        $existingSuperadmin = $this->commonModel->selectOne('auth_groups', ['group' => 'superadmin']);
        $realSuperadminId   = $existingSuperadmin !== null
            ? (int) $existingSuperadmin->id
            : (int) $this->commonModel->create('auth_groups', [
                'group'       => 'superadmin',
                'description' => 'superadmin',
            ]);
        $this->addUserToGroup($this->createUser('superadmin_t2a'), 'superadmin');

        $this->createGroup('editors');
        $attackerId = $this->createUser('attacker_t2a');
        $this->addUserToGroup($attackerId, 'editors');

        $this->dispatchGroupUpdate($attackerId, $realSuperadminId, [
            'groupName'   => 'Locked Out',
            'description' => 'IDOR lockout by attacker',
            'seflink'     => 'locked-out',
            'perms'       => [1 => ['roles' => 'read_r']],
        ]);

        $freshSuperadmin = $this->commonModel->selectOne('auth_groups', ['id' => $realSuperadminId]);
        $this->assertNotNull($freshSuperadmin, 'real superadmin group row disappeared.');
        $this->assertSame(
            'superadmin',
            $freshSuperadmin->group,
            'group_update() must refuse renaming the real superadmin group away from "superadmin".',
        );
    }

    /**
     * T2b (IDOR, escalation direction): a member of an unrelated group
     * renames a *third* group -- neither their own nor the real superadmin
     * group -- to "superadmin", which would hand that third group's members
     * superadmin without their consent.
     */
    public function testIdorRenamesAnUninvolvedGroupToSuperadmin(): void
    {
        $this->createGroup('editors');
        $salesGroupId = $this->createGroup('sales');
        $salesMemberId  = $this->createUser('sales_member_t2b');
        $this->addUserToGroup($salesMemberId, 'sales');

        $attackerId = $this->createUser('attacker_t2b');
        $this->addUserToGroup($attackerId, 'editors');

        $this->dispatchGroupUpdate($attackerId, $salesGroupId, [
            'groupName'   => 'Superadmin',
            'description' => 'IDOR by attacker',
            'seflink'     => 'sales',
            'perms'       => [1 => ['roles' => 'read_r']],
        ]);

        $freshSales = $this->commonModel->selectOne('auth_groups', ['id' => $salesGroupId]);
        $this->assertNotNull($freshSales, 'sales group row disappeared.');
        $this->assertSame(
            'sales',
            $freshSales->group,
            'group_update() must refuse an IDOR rename of a group the actor is not a member of.',
        );

        $freshSalesMember = auth()->getProvider()->findById($salesMemberId);
        $this->assertNotNull($freshSalesMember, 'sales member user disappeared.');
        $this->assertFalse(
            $freshSalesMember->inGroup('superadmin'),
            'A group member must not gain superadmin because someone else renamed their group.',
        );
    }

    /**
     * T3: group_create() validates is_unique[auth_groups.group] against the
     * raw POST value, but persists esc(seflink($groupName)). A raw value
     * that does not collide can still collide once transformed, producing a
     * second row named "superadmin".
     */
    public function testGroupCreateSeflinkCollisionDuplicatesSuperadmin(): void
    {
        $this->commonModel->create('auth_groups', [
            'group'       => 'superadmin',
            'description' => 'superadmin',
        ]);
        $attackerId = $this->createUser('attacker_t3');

        // ci4ms_test is a persistent, non-refreshed database ($refresh =
        // false is mandatory here, see setUp()) that already carries its
        // own permanent "superadmin" row from prior project setup, on top
        // of the one just created above. A hardcoded expected count of 1
        // would be wrong regardless of this bug's presence, so the
        // assertion below is a before/after delta instead.
        $before = $this->commonModel->count('auth_groups', ['group' => 'superadmin']);

        $this->dispatchGroupCreate($attackerId, [
            'groupName'   => 'SuperAdmin!',
            'description' => 'Collides after seflink transform',
            'seflink'     => 'superadmin-clone',
            'perms'       => [1 => ['roles' => 'read_r']],
        ]);

        $this->assertSame(
            $before,
            $this->commonModel->count('auth_groups', ['group' => 'superadmin']),
            'group_create() must not allow a second "superadmin" row via a raw value that only collides after seflink().',
        );
    }

    /**
     * T4 (permanent regression guard, expected green both before and after
     * the fix): a legitimate rename of a group the actor does not belong to
     * must keep working. Deliberately built around a non-member actor so
     * that the accepted G5 regression (a future fix rejecting renames of a
     * group the actor already belongs to) cannot make this test flip red.
     */
    public function testLegitimateRenameOfAnUninvolvedGroupStillWorks(): void
    {
        [$ownedPageId] = $this->createPermissionPages('legit_rename_t4');

        $ownGroup = 'legit_rename_owngroup_t4';
        $this->createGroup($ownGroup);
        $actorId = $this->createUser('legit_renamer_t4');
        $this->addUserToGroup($actorId, $ownGroup);
        $this->primeAuthGroupsMatrix($ownGroup, [$this->pagePermission($ownedPageId, 'read')]);

        $marketingGroupId = $this->createGroup('marketing');

        $this->dispatchGroupUpdate($actorId, $marketingGroupId, [
            'groupName'   => 'Marketing Team',
            'description' => 'Legitimate rename',
            'seflink'     => 'marketing',
            'perms'       => [$ownedPageId => ['roles' => 'read_r']],
        ]);

        $fresh = $this->commonModel->selectOne('auth_groups', ['id' => $marketingGroupId]);
        $this->assertNotNull($fresh, 'marketing group row disappeared.');
        $this->assertSame('marketing-team', $fresh->group);
    }

    /**
     * HIGH: group_create()/group_update() wrote POSTed perms[] straight to
     * auth_groups.permissions without checking them against the actor's own
     * effective permission set (see context.md FAZ2-K2 "YENI BULGU (HIGH)").
     * An actor holding only users.group_update.update could hand any page
     * permission -- including ones they never held themselves -- to any
     * group. T5/T6 cover group_update(); T7/T8 cover group_create(). Each
     * pair is a block/allow duo: the "lacks" test is the security invariant
     * (expected red before the fix, green after); the "subset" test is the
     * permanent regression guard proving legitimate own-subset submissions
     * still work.
     *
     * auth()->user()->can() resolves group-inherited permissions via
     * Modules\Auth\Config\AuthGroups, whose matrix is loaded by a *second*,
     * default-group CommonModel connection distinct from this test's
     * 'tests'-group transaction (see setUp()'s CommonModel note) -- so a
     * matrix built from fixture rows written inside $this->db->transStart()
     * would never be visible to it before commit. primeAuthGroupsMatrix()
     * sidesteps that by seeding AuthGroups' own 24h cache key directly
     * (MockCache is in-memory per test, no DB round-trip involved either
     * way), which is what auth()->user()->can() consults first.
     */
    public function testPermsSubsetRejectsGroupUpdateWhenActorPostsPermissionTheyLack(): void
    {
        [$ownedPageId] = $this->createPermissionPages('permsubset_t5');
        [$foreignPageId] = $this->createPermissionPages('permsubset_t5b');

        $ownGroup = 'permsubset_owngroup_t5';
        $this->createGroup($ownGroup);
        $actorId = $this->createUser('permsubset_actor_t5');
        $this->addUserToGroup($actorId, $ownGroup);
        $this->primeAuthGroupsMatrix($ownGroup, [$this->pagePermission($ownedPageId, 'read')]);

        $targetGroupId = $this->createGroup('permsubset_target_t5');
        $before = $this->commonModel->selectOne('auth_groups', ['id' => $targetGroupId]);

        $this->dispatchGroupUpdate($actorId, $targetGroupId, [
            'groupName'   => 'permsubset_target_t5',
            'description' => 'attempted escalation',
            'seflink'     => 'permsubset-target-t5',
            'perms'       => [$foreignPageId => ['roles' => 'read_r']],
        ]);

        $after = $this->commonModel->selectOne('auth_groups', ['id' => $targetGroupId]);
        $this->assertNotNull($after, 'target group row disappeared.');
        $this->assertSame(
            $before->permissions,
            $after->permissions,
            'group_update() must not persist a permission the actor does not hold themselves.',
        );
    }

    public function testPermsSubsetAllowsGroupUpdateWhenPostedPermsAreActorsOwnSubset(): void
    {
        [$ownedPageId, $ownedPagename] = $this->createPermissionPages('permsubset_t6');

        $ownGroup = 'permsubset_owngroup_t6';
        $this->createGroup($ownGroup);
        $actorId = $this->createUser('permsubset_actor_t6');
        $this->addUserToGroup($actorId, $ownGroup);
        $this->primeAuthGroupsMatrix($ownGroup, [$this->pagePermission($ownedPageId, 'read')]);

        $targetGroupId = $this->createGroup('permsubset_target_t6');

        $this->dispatchGroupUpdate($actorId, $targetGroupId, [
            'groupName'   => 'permsubset_target_t6',
            'description' => 'legitimate subset grant',
            'seflink'     => 'permsubset-target-t6',
            'perms'       => [$ownedPageId => ['roles' => 'read_r']],
        ]);

        $after = $this->commonModel->selectOne('auth_groups', ['id' => $targetGroupId]);
        $this->assertNotNull($after, 'target group row disappeared.');
        $grantedPages = array_column(json_decode($after->permissions ?? '[]', true) ?? [], 'page_id');
        $this->assertContains(
            $ownedPageId,
            $grantedPages,
            "group_update() must persist a permission ({$ownedPagename}.read) the actor holds themselves.",
        );
    }

    public function testPermsSubsetRejectsGroupCreateWhenActorPostsPermissionTheyLack(): void
    {
        [$ownedPageId] = $this->createPermissionPages('permsubset_t7');
        [$foreignPageId] = $this->createPermissionPages('permsubset_t7b');

        $ownGroup = 'permsubset_owngroup_t7';
        $this->createGroup($ownGroup);
        $actorId = $this->createUser('permsubset_actor_t7');
        $this->addUserToGroup($actorId, $ownGroup);
        $this->primeAuthGroupsMatrix($ownGroup, [$this->pagePermission($ownedPageId, 'read')]);

        $this->dispatchGroupCreate($actorId, [
            'groupName'   => 'Permsubset New T7',
            'description' => 'attempted escalation via create',
            'seflink'     => 'permsubset-new-t7',
            'perms'       => [$foreignPageId => ['roles' => 'read_r']],
        ]);

        $this->assertSame(
            0,
            $this->commonModel->count('auth_groups', ['group' => 'permsubset-new-t7']),
            'group_create() must not create a group carrying a permission the actor does not hold themselves.',
        );
    }

    public function testPermsSubsetAllowsGroupCreateWhenPostedPermsAreActorsOwnSubset(): void
    {
        [$ownedPageId, $ownedPagename] = $this->createPermissionPages('permsubset_t8');

        $ownGroup = 'permsubset_owngroup_t8';
        $this->createGroup($ownGroup);
        $actorId = $this->createUser('permsubset_actor_t8');
        $this->addUserToGroup($actorId, $ownGroup);
        $this->primeAuthGroupsMatrix($ownGroup, [$this->pagePermission($ownedPageId, 'read')]);

        $this->dispatchGroupCreate($actorId, [
            'groupName'   => 'Permsubset New T8',
            'description' => 'legitimate subset create',
            'seflink'     => 'permsubset-new-t8',
            'perms'       => [$ownedPageId => ['roles' => 'read_r']],
        ]);

        $created = $this->commonModel->selectOne('auth_groups', ['group' => 'permsubset-new-t8']);
        $this->assertNotNull($created, "group_create() must persist a new group carrying a permission ({$ownedPagename}.read) the actor holds themselves.");
        $grantedPages = array_column(json_decode($created->permissions ?? '[]', true) ?? [], 'page_id');
        $this->assertContains($ownedPageId, $grantedPages);
    }

    /**
     * F-1 (security-audit closure on top of T5-T8): the perms[] write loops
     * in group_create()/group_update() used to persist page_id => $key
     * unconditionally, regardless of whether $key existed in
     * auth_permissions_pages. actorGrantsSubsetOfOwnPermissions() silently
     * skips page_id keys it doesn't recognize (nothing to check a
     * non-existent page against), so an unknown page_id sailed straight
     * through the subset guard and was written to auth_groups.permissions
     * anyway. A not-yet-existing page_id could therefore be "reserved"
     * ahead of time; once a future module's permission pages claimed that
     * same id, AuthGroups::loadFromDatabase() would match it and grant the
     * permission without it ever having been validated against the actor's
     * own grants.
     *
     * The actor edits their own group without renaming it -- the posted
     * groupName is already seflink-normalized (lowercase, hyphenated), so
     * esc(seflink($groupName)) === the stored group name and G5 ("cannot
     * edit own group name") never fires; that guard is orthogonal to this
     * test.
     */
    public function testPermsSubsetSkipsUnknownPageIdOnGroupUpdate(): void
    {
        $unknownPageId = 999999;
        $this->assertSame(
            0,
            $this->commonModel->count('auth_permissions_pages', ['id' => $unknownPageId]),
            'fixture assumption violated: this page id must not exist.',
        );

        $ownGroup   = 'permsubset-unknown-t9';
        $ownGroupId = $this->createGroup($ownGroup);
        // users.username is VARCHAR(30) (vendor/codeigniter4/shield/src/Database/
        // Migrations/2020-12-28-223112_create_auth_tables.php:49) with a UNIQUE
        // key (:59). createUser() appends "_" + an 8-char hex suffix, so the
        // label alone must stay <= 21 chars or the insert silently violates the
        // column width -- see context.md's build-lead debugging note for how
        // this was diagnosed (a 28-char label here previously produced an
        // unrecoverable "Attempt to read property id on null" inside Shield's
        // UserModel::saveEmailIdentity(), 100% reproducible, unrelated to the
        // F-1 fix itself).
        $actorId = $this->createUser('permsub_unknown_t9');
        $this->addUserToGroup($actorId, $ownGroup);

        $this->dispatchGroupUpdate($actorId, $ownGroupId, [
            'groupName'   => $ownGroup,
            'description' => 'reserve a not-yet-existing page id',
            'seflink'     => $ownGroup,
            'perms'       => [$unknownPageId => ['roles' => 'create_r']],
        ]);

        $after = $this->commonModel->selectOne('auth_groups', ['id' => $ownGroupId]);
        $this->assertNotNull($after, 'own group row disappeared.');
        $this->assertSame($ownGroup, $after->group, 'group name must not have changed by this request.');
        $grantedPages = array_column(json_decode($after->permissions ?? '[]', true) ?? [], 'page_id');
        $this->assertNotContains(
            $unknownPageId,
            $grantedPages,
            'group_update() must not persist a permission for a page_id that does not exist in auth_permissions_pages.',
        );
    }

    /**
     * BLOKER-B (HIGH, red-is-the-point -- same pattern as T1-T3/T5-T8, NOT
     * T4's permanent-green role): the perms[] subset guard at
     * PermgroupController::group_update():165-168 runs AFTER the
     * auth_groups_users membership rewrite at :155-157. When the posted
     * groupName transforms via seflink() into a string that differs from
     * the target group's current stored name -- exactly the trigger T5
     * already uses ('_' is not \p{L}/\p{Nd}, so app/Common.php:112 turns it
     * into '-') -- the membership rewrite fires and commits before the
     * subset guard has a chance to reject the request.
     *
     * T5 exercises this same $newGroup !== $oldGroupName path but its
     * target group has no member (addUserToGroup() is never called for it),
     * so the membership write has no row to touch and its effect goes
     * unobserved. This test adds a real member of the target group (the
     * "bystander", distinct from the actor) and asserts, in addition to the
     * 403 T5 already covers, that auth_groups_users.group for that member
     * is unchanged. Against today's code that last assertion is expected to
     * FAIL: the bystander's row is already rewritten to the new name by the
     * time group_update() discovers the actor's foreign perms[] entry and
     * returns 403, leaving auth_groups.group on the old name while
     * auth_groups_users.group points at a name no group row carries --
     * every real member of that group loses all their permissions.
     */
    public function testPermsSubsetGuardRunsAfterMembershipRewriteStrandsBystander(): void
    {
        [$ownedPageId] = $this->createPermissionPages('permsubset_tb');
        [$foreignPageId] = $this->createPermissionPages('permsubset_tbb');

        $ownGroup = 'permsubset_owngroup_tb';
        $this->createGroup($ownGroup);
        $actorId = $this->createUser('permsubset_actor_tb');
        $this->addUserToGroup($actorId, $ownGroup);
        $this->primeAuthGroupsMatrix($ownGroup, [$this->pagePermission($ownedPageId, 'read')]);

        $targetGroupName = 'permsubset_target_tb';
        $targetGroupId   = $this->createGroup($targetGroupName);
        $bystanderId     = $this->createUser('permsubset_member_tb');
        $this->addUserToGroup($bystanderId, $targetGroupName);

        $beforeGroup      = $this->commonModel->selectOne('auth_groups', ['id' => $targetGroupId]);
        $beforeMembership = $this->commonModel->selectOne('auth_groups_users', ['user_id' => $bystanderId]);
        $this->assertNotNull($beforeGroup, 'fixture setup failed: target group row missing.');
        $this->assertNotNull($beforeMembership, 'fixture setup failed: bystander membership row missing.');

        $response = $this->dispatchGroupUpdate($actorId, $targetGroupId, [
            'groupName'   => $targetGroupName,
            'description' => 'attempted escalation after membership already rewritten',
            'seflink'     => 'permsubset-target-tb',
            'perms'       => [$foreignPageId => ['roles' => 'read_r']],
        ]);

        $this->assertSame(403, $response->getStatusCode());

        $afterGroup = $this->commonModel->selectOne('auth_groups', ['id' => $targetGroupId]);
        $this->assertNotNull($afterGroup, 'target group row disappeared.');
        $this->assertSame(
            $beforeGroup->group,
            $afterGroup->group,
            'auth_groups.group must not change when the perms[] subset guard subsequently rejects the request.',
        );

        $afterMembership = $this->commonModel->selectOne('auth_groups_users', ['user_id' => $bystanderId]);
        $this->assertNotNull($afterMembership, 'bystander membership row disappeared.');
        $this->assertSame(
            $beforeMembership->group,
            $afterMembership->group,
            'auth_groups_users.group must not change for a real member of the target group when the perms[] subset guard subsequently rejects the request (BLOKER-B: the membership rewrite at :155-157 runs before the guard at :165-168).',
        );
    }

    /**
     * Builds a 'pagename.action' permission string the way
     * Modules\Auth\Config\AuthGroups::loadFromDatabase() does.
     *
     * @param int    $pageId auth_permissions_pages.id
     * @param string $action One of create|read|update|delete
     *
     * @return string 'pagename.action'
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
     * [ownedPageId, ownedPagename]. Each fixture uses a unique pagename to
     * avoid colliding with the persistent, non-refreshed ci4ms_test rows
     * that already exist in this table.
     *
     * @param string $labelPrefix Unique-per-test prefix, also used as the pagename
     *
     * @return array{0: int, 1: string} [insertId, pagename]
     */
    private function createPermissionPages(string $labelPrefix): array
    {
        // The suffix is also folded into className (not just pagename) so
        // that two calls in the same test never collide on
        // auth_permissions_pages_class_method_unique
        // (modules/Auth/Database/Migrations/2026-08-14-170018_AddUniqueKeyToAuthPermissionsPages.php)
        // -- a hardcoded shared className/methodName here was already
        // unrealistic (no two real permission pages share a route), the DB
        // constraint just made the fixture's own accidental collision
        // observable. className/methodName's value has no bearing on this
        // class's assertions (see pagePermission(), which only ever reads
        // pagename), so this changes nothing about what is being tested.
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
     * matrix directly, bypassing loadFromDatabase()'s default-group
     * CommonModel connection (see this method's callers' docblock for why
     * that connection cannot see this test's fixture writes).
     *
     * @param string        $group       Group name the matrix entry belongs to
     * @param array<string> $permissions Fully-qualified 'pagename.action' strings
     */
    private function primeAuthGroupsMatrix(string $group, array $permissions): void
    {
        cache()->save('shield_auth_dynamic_config', [
            'groups'      => [],
            'permissions' => [],
            'matrix'      => [$group => $permissions],
        ], 86400);
    }

    /**
     * Creates an auth_groups row and returns its id.
     *
     * @param string $name Group name, stored verbatim (already slug-shaped in every fixture used here)
     *
     * @return int Insert id of the new row
     */
    private function createGroup(string $name): int
    {
        return (int) $this->commonModel->create('auth_groups', [
            'group'       => $name,
            'description' => $name,
        ]);
    }

    /**
     * Creates a Shield user with a random-suffixed username/email and
     * returns their id.
     *
     * @param string $label Human-readable prefix, made unique with a random suffix
     *
     * @return int Insert id of the new user
     */
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

    /**
     * Adds an existing user to an existing group via auth_groups_users.
     *
     * @param int    $userId User id
     * @param string $group  Group name
     */
    private function addUserToGroup(int $userId, string $group): void
    {
        $this->commonModel->create('auth_groups_users', [
            'user_id'    => $userId,
            'group'      => $group,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Logs $actorId in and runs PermgroupController::group_update($groupId)
     * with $post as the request body, bypassing routing and filters.
     *
     * @param int                  $actorId Id of the user to act as
     * @param int                  $groupId Target group id (route parameter)
     * @param array<string, mixed> $post    POST body
     *
     * @return ResponseInterface Whatever the controller method returns
     */
    private function dispatchGroupUpdate(int $actorId, int $groupId, array $post): ResponseInterface
    {
        $controller = new PermgroupController();
        $this->primeRequest($actorId, $post, $controller);

        return $controller->group_update($groupId);
    }

    /**
     * Logs $actorId in and runs PermgroupController::group_create() with
     * $post as the request body, bypassing routing and filters.
     *
     * @param int                  $actorId Id of the user to act as
     * @param array<string, mixed> $post    POST body
     *
     * @return ResponseInterface Whatever the controller method returns
     */
    private function dispatchGroupCreate(int $actorId, array $post): ResponseInterface
    {
        $controller = new PermgroupController();
        $this->primeRequest($actorId, $post, $controller);

        return $controller->group_create();
    }

    /**
     * Logs $actorId in, wires a POST IncomingRequest onto $controller and
     * shares this test's CommonModel instance as $controller->commonModel.
     *
     * @param int                    $actorId    Id of the user to act as
     * @param array<string, mixed>   $post       POST body
     * @param PermgroupController    $controller Controller instance to prime
     */
    private function primeRequest(int $actorId, array $post, PermgroupController $controller): void
    {
        $actor = auth()->getProvider()->findById($actorId);
        $this->actingAs($actor);

        $config  = config(App::class);
        $request = new IncomingRequest($config, new SiteURI($config), null, new UserAgent());
        $request = $request->withMethod('POST');
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

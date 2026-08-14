<?php

namespace Tests\Modules\Notifications;

use ci4commonmodel\CommonModel;
use CodeIgniter\Config\Factories;
use CodeIgniter\Config\Services;
use CodeIgniter\Shield\Config\AuthGroups;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\Mock\MockCache;
use Modules\Notifications\Config\NotificationsConfig;
use Modules\Notifications\Controllers\ComposerController;
use ReflectionClass;
use Tests\Support\Notifications\FakeAuth;
use Tests\Support\Notifications\FakeSignalStore;
use Tests\Support\Notifications\HalfFailingChannel;
use Tests\Support\Notifications\ShadowSchemaTrait;

/**
 * ComposerController write path: who may be addressed, and who is recorded as sender.
 *
 * send() is the only place a human can put a notification in front of every account
 * on the site, so the properties that matter are not the feature but its edges: the
 * sender must come from the session, the audience must come from a server-built
 * whitelist, and nothing else in the POST body may reach a stored row. Each of those
 * is asserted against the ACTUAL row, because a claim about what "cannot" be written
 * is only settled by looking at what was.
 *
 * Every write is aimed at connection-scoped TEMPORARY shadows ({@see ShadowSchemaTrait}),
 * including a shadow of `users`: the audience rules are only meaningful against a KNOWN
 * population, and the dev database this suite runs on is shared and must not be seeded.
 * The permanent tables are proven untouched in
 * {@see testSendLeavesThePermanentTablesUntouched()}.
 *
 * Sanitisation itself is NOT re-tested here — it belongs to NotificationMessage
 * ({@see NotificationMessageTest}); what is asserted is that the composer delegates to
 * it instead of building rows of its own. index() is not exercised: it renders the full
 * backend view (sidebar, defData, layout) and is not reachable in isolation.
 *
 * @internal
 */
final class ComposerControllerTest extends CIUnitTestCase
{
    use ShadowSchemaTrait;
    use DatabaseTestTrait;

    /**
     * Never rolled back to a pre-migration state: this only APPLIES pending
     * migrations (idempotent -- MigrationRunner::latest() diffs against the
     * `migrations` history table and no-ops once everything is applied), it
     * never regresses anything.
     */
    protected $refresh = false;

    /**
     * Only ever migrate once per class, matching
     * tests/Modules/Users/PermgroupPrivilegeEscalationTest.php:60 and
     * tests/Modules/Methods/ModuleInstallerNamespaceTest.php:73.
     */
    protected $migrateOnce = true;

    /**
     * null migrates every registered namespace (App + Modules\* + Shield),
     * not just Modules\Notifications.
     *
     * This class is alphabetically first among the DB-touching Notifications
     * tests (ComposerControllerTest < InAppChannelExclusionTest <
     * NotificationCreatedByTest < NotificationRelevanceSqlTest <
     * NotifierRecipientCountTest < NotifierTest < PreferenceControllerTest <
     * RealtimeChannelTest < RealtimeConnectionCapTest <
     * RealtimeTopicAuthTest), all of which assume base tables (`users`,
     * `notifications`, `auth_groups_users`, ...) already exist, either via
     * ShadowSchemaTrait's own baseline reads or via direct db_connect()
     * writes. When `tests/Modules/` (or this directory alone) runs on a
     * database that has never had ANY migration applied, those assumptions
     * are false and every one of the ten files errors with "Table '...'
     * doesn't exist" -- reproduced on a disposable, empty scratch database
     * (never against the shared ci4ms_test); see context.md SORUN (b).
     *
     * DatabaseTestTrait::migrateOnce's own doneMigration flag is declared
     * `private static` INSIDE the trait, so PHP gives each CONSUMING CLASS
     * its own independent copy (verified empirically, not assumed -- a
     * second class using the same trait does not see the first class's
     * flag flip). That flag therefore only stops THIS class re-migrating
     * across its own test methods; it does not, by itself, help any OTHER
     * Notifications file. What actually carries the fix across files is
     * that migrate() writes to the real, shared `migrations` table and
     * creates real tables in the SAME `ci4ms_test` database every other
     * Notifications test's db_connect('default') call also resolves to --
     * once this class's setUp() (invoked first, alphabetically, via
     * CIUnitTestCase::setUp() calling setUpDatabase() automatically because
     * DatabaseTestTrait is in use, see vendor/codeigniter4/framework/
     * system/Test/CIUnitTestCase.php:253-254) has run latest() once, the
     * schema exists for the rest of the process regardless of which
     * class's doneMigration flag tracked it.
     */
    protected $namespace = null;

    /** The logged-in administrator every send() in this class acts as. */
    private const SENDER = 910100;

    /** A foreign account a hostile payload will try, and fail, to be attributed to. */
    private const IMPOSTOR = 910200;

    /** An existing user, member of {@see GROUP}. */
    private const ALICE = 910001;

    /** A second existing user, member of {@see GROUP}. */
    private const BOB = 910002;

    /** An existing user who belongs to no group. */
    private const CAROL = 910003;

    /** A soft-deleted account: still a row, never a recipient. */
    private const REMOVED = 910004;

    /** An id no `users` row carries. */
    private const MISSING = 910999;

    /** The whitelisted Shield group used as a target. */
    private const GROUP = 'editors';

    /** A group name the whitelist does not contain. */
    private const FOREIGN_GROUP = 'ghosts';

    /** The fixed type every composer broadcast is stamped with. */
    private const TYPE = 'announcement';

    private MockCache $cache;

    /** Column count of the permanent notifications table, captured before any shadow existed. */
    private int $permanentColumns = 0;

    /** Row count of the permanent notifications table, captured before any shadow existed. */
    private int $permanentRows = 0;

    /** Row count of the permanent users table, captured before any shadow existed. */
    private int $permanentUsers = 0;

    /**
     * Shadows the schema, seeds a known population and logs the sender in.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Canary: never allowed to run against anything but the throwaway
        // test schema. Matches tests/Modules/Users/PermgroupPrivilegeEscalationTest.php
        // :76-80 and tests/Modules/Methods/ModuleInstallerNamespaceTest.php:84-88.
        $this->assertStringContainsString(
            'ci4ms_test',
            (string) \Config\Database::connect()->database,
            'Refusing to run: the active connection is not pointed at the test database.',
        );

        $db                     = db_connect('default');
        $this->permanentColumns = count($db->getFieldNames('notifications'));
        $this->permanentRows    = $db->table('notifications')->countAllResults();
        $this->permanentUsers   = $db->table('users')->countAllResults();

        $this->cache = new MockCache();
        Services::injectMock('cache', $this->cache);
        Services::injectMock('auth', new FakeAuth(self::SENDER));

        // The group whitelist is read from Shield's AuthGroups config, which this
        // project fills from the database. Injecting a fixed one keeps "inside the
        // whitelist" and "outside it" from depending on the dev site's group table.
        Factories::injectMock('config', 'AuthGroups', self::authGroups());

        // send() always ends in redirect()->route('notifCompose'). Nothing loads the
        // route files in a unit-test container, so discover them here or every send()
        // dies on an unknown route name.
        service('routes')->loadRoutes();

        $this->createShadowTables(withUsers: true);
        $this->seedPopulation();
    }

    /**
     * Drops the shadows and releases the injected services.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        $this->dropShadowTables();

        // reset() drops every shared instance (BaseService.php:366-375),
        // including 'routes' -- it is discovered exactly once per PHPUnit
        // process (vendor/codeigniter4/framework/system/Test/bootstrap.php
        // :90) and nothing besides FeatureTestTrait::call()
        // (FeatureTestTrait.php:216) reloads it afterwards. setUp() above
        // reloads it for THIS class's own use (:113), but that does not
        // help any LATER test in the same process that drives a controller
        // directly (not through FeatureTestTrait) and hits
        // redirect()->route(...) -- it would otherwise fail with
        // HTTPException "The route for '...' cannot be found" (see
        // tests/Modules/Users/PermgroupPrivilegeEscalationTest.php:106-123
        // and context.md FAZ 4 / F-2), so it is reloaded below.
        //
        // reset(true) -- not reset(false) -- is required for that reload to
        // succeed: $initAutoloader=false skips re-running
        // Autoloader::initialize() (BaseService.php:372-374), and
        // Services::autoloader(true) hands out a bare `new Autoloader()`
        // with no PSR-4 map at all once the old shared instance is gone
        // (BaseService.php:276-287). loadRoutes() re-executes every
        // Config/Routes.php, including modules/Auth/Config/Routes.php:4's
        // service('auth')->routes(...), which resolves CodeIgniter\Shield\
        // Config\Auth/AuthRoutes through that same Autoloader-backed
        // FileLocator -- with an empty map those classes are not found and
        // config('Auth') returns null, throwing "Auth::__construct():
        // Argument #1 ($config) must be of type ...Auth, null given"
        // (reproduced with reset(false) before this fix; see context.md).
        // loadRoutes() itself is a no-op on an already-discovered
        // collection (RouteCollection.php:312-314), so this costs nothing
        // in a clean process.
        Services::reset(true);
        service('routes')->loadRoutes();

        Factories::reset('config');

        parent::tearDown();
    }

    /**
     * The group whitelist the controller is allowed to accept names from.
     *
     * @return AuthGroups Shield's own config object, filled with two known groups.
     */
    private static function authGroups(): AuthGroups
    {
        $config         = new AuthGroups();
        $config->groups = [
            self::GROUP  => ['title' => 'Editors', 'description' => 'Content editors.'],
            'superadmin' => ['title' => 'Super Admin', 'description' => 'Everything.'],
        ];

        return $config;
    }

    /**
     * Inserts the known accounts and their group membership in two batch writes.
     *
     * @return void
     */
    private function seedPopulation(): void
    {
        $this->shadowDb->table('users')->insertBatch([
            ['id' => self::SENDER, 'username' => 'sender', 'firstname' => 'Send', 'surname' => 'Er', 'deleted_at' => null],
            ['id' => self::ALICE, 'username' => 'alice', 'firstname' => 'Alice', 'surname' => 'Ada', 'deleted_at' => null],
            ['id' => self::BOB, 'username' => 'bob', 'firstname' => 'Bob', 'surname' => null, 'deleted_at' => null],
            ['id' => self::CAROL, 'username' => 'carol', 'firstname' => null, 'surname' => null, 'deleted_at' => null],
            ['id' => self::REMOVED, 'username' => 'removed', 'firstname' => 'Rem', 'surname' => 'Oved', 'deleted_at' => '2026-01-01 00:00:00'],
        ]);

        $this->shadowDb->table('auth_groups_users')->insertBatch([
            ['user_id' => self::ALICE, 'group' => self::GROUP, 'created_at' => '2026-01-01 00:00:00'],
            ['user_id' => self::BOB, 'group' => self::GROUP, 'created_at' => '2026-01-01 00:00:00'],
            ['user_id' => self::REMOVED, 'group' => self::GROUP, 'created_at' => '2026-01-01 00:00:00'],
            ['user_id' => self::CAROL, 'group' => 'superadmin', 'created_at' => '2026-01-01 00:00:00'],
        ]);
    }

    /**
     * Builds a ComposerController with a real CommonModel and a stubbed request.
     *
     * @param array<string, mixed> $post  Values the stub request reports for getPost().
     * @param bool                 $ajax  What the stub reports for isAJAX().
     * @param array<string, mixed> $query Values the stub request reports for getGet().
     *
     * @return ComposerController The wired-up controller.
     */
    private function makeController(array $post = [], bool $ajax = false, array $query = []): ComposerController
    {
        /** @var ComposerController $controller */
        $controller              = (new ReflectionClass(ComposerController::class))->newInstanceWithoutConstructor();
        $controller->commonModel = new CommonModel();

        $request = new class ($post, $ajax, $query) {
            /**
             * @param array<string, mixed> $post  The canned POST body.
             * @param bool                 $ajax  What isAJAX() reports.
             * @param array<string, mixed> $query The canned query string.
             */
            public function __construct(private array $post, private bool $ajax, private array $query) {}

            /**
             * Reports a single POST value, mirroring IncomingRequest::getPost().
             *
             * @param string|null $key Field name.
             *
             * @return mixed The canned value, or null when absent.
             */
            public function getPost(?string $key = null)
            {
                return $key === null ? $this->post : ($this->post[$key] ?? null);
            }

            /**
             * Reports a single query value, mirroring IncomingRequest::getGet().
             *
             * @param string|null $key Field name.
             *
             * @return mixed The canned value, or null when absent.
             */
            public function getGet(?string $key = null)
            {
                return $key === null ? $this->query : ($this->query[$key] ?? null);
            }

            /**
             * Reports whether the caller framed the request as AJAX.
             *
             * @return bool The canned answer.
             */
            public function isAJAX(): bool
            {
                return $this->ajax;
            }
        };

        $this->setPrivateProperty($controller, 'request', $request);
        $this->setPrivateProperty($controller, 'response', Services::response(null, false));

        return $controller;
    }

    /**
     * A valid POST body, with the given fields overridden.
     *
     * @param array<string, mixed> $overrides Fields to add or replace.
     *
     * @return array<string, mixed> A body send() accepts unless the override breaks it.
     */
    private static function payload(array $overrides = []): array
    {
        return array_merge([
            'title'    => 'Scheduled maintenance',
            'body'     => 'The panel is offline tonight.',
            'url'      => '/backend/notifications',
            'severity' => 'info',
            'mode'     => 'targeted',
            'users'    => [self::ALICE],
        ], $overrides);
    }

    /**
     * All rows stored in the shadowed notifications table.
     *
     * @return list<\stdClass> Rows in insertion order.
     */
    private function storedRows(): array
    {
        return array_values($this->shadowDb->table('notifications')->orderBy('id', 'ASC')->get()->getResult());
    }

    /**
     * The single stored row, failing the test when the count is not exactly one.
     *
     * @return \stdClass The only stored notification row.
     */
    private function onlyRow(): \stdClass
    {
        $rows = $this->storedRows();

        $this->assertCount(1, $rows, 'exactly one row was expected');

        return $rows[0];
    }

    /**
     * Users and groups may be targeted in the same publication, one row each.
     *
     * @return void
     */
    public function testSendWritesOneRowPerTargetForUsersAndGroupsTogether(): void
    {
        $this->makeController(self::payload([
            'users'  => [self::ALICE, self::CAROL],
            'groups' => [self::GROUP],
        ]))->send();

        $rows = $this->storedRows();

        $this->assertCount(3, $rows, 'two user rows and one group row');
        $this->assertSame(
            [['user', (string) self::ALICE], ['user', (string) self::CAROL], ['group', self::GROUP]],
            array_map(static fn (\stdClass $row): array => [$row->target_type, $row->target_value], $rows),
            'Model B writes one global row per resolved target, in directive order'
        );
    }

    /**
     * A user targeted directly AND through a group is dropped from the group row only.
     *
     * The overlap narrowing is the builder's job; what is asserted here is that the
     * composer hands both target kinds to the SAME dispatch, so the narrowing can run.
     *
     * @return void
     */
    public function testSendLetsTheBuilderNarrowAnOverlappingGroupRow(): void
    {
        $this->makeController(self::payload([
            'users'  => [self::ALICE],
            'groups' => [self::GROUP],
        ]))->send();

        $rows = $this->storedRows();

        $this->assertCount(2, $rows);
        $this->assertNull($rows[0]->exclude_users, 'the direct row stays addressed to them');
        $this->assertSame(',' . self::ALICE . ',', $rows[1]->exclude_users, 'the group row drops the already-covered user');
    }

    /**
     * The stored sender is the session user, never an id supplied by the client.
     *
     * @return void
     */
    public function testSendStampsTheRowWithTheSessionUserNotThePostedOne(): void
    {
        $this->makeController(self::payload([
            'created_by' => self::IMPOSTOR,
            'user_id'    => self::IMPOSTOR,
            'sender_id'  => self::IMPOSTOR,
        ]))->send();

        $row = $this->onlyRow();

        $this->assertSame(self::SENDER, (int) $row->created_by, 'the trace comes from auth(), not from the body');
        $this->assertNull($row->user_id, 'a Model B row is global: it has no owner column to hijack');
    }

    /**
     * Fields the form never offers cannot reach the stored row.
     *
     * Every one of these is a real column of `notifications`, so a mass-assigning
     * implementation would have written them verbatim.
     *
     * @return void
     */
    public function testSendIgnoresPostedFieldsTheFormDoesNotOffer(): void
    {
        $this->makeController(self::payload([
            'id'           => 424242,
            'channel'      => 'email',
            'read_at'      => '2000-01-01 00:00:00',
            'target_type'  => 'broadcast',
            'target_value' => 'superadmin',
            'created_at'   => '1999-12-31 23:59:59',
        ]))->send();

        $row = $this->onlyRow();

        $this->assertNotSame(424242, (int) $row->id, 'the primary key is the database\'s to assign');
        $this->assertSame('inapp', $row->channel, 'the only channel that writes a row names itself');
        $this->assertNull($row->read_at, 'a notification cannot be born already read');
        $this->assertSame('user', $row->target_type, 'the audience comes from the validated mode, not from a hidden field');
        $this->assertSame((string) self::ALICE, $row->target_value);
        $this->assertNotSame('1999-12-31 23:59:59', $row->created_at, 'the timestamp is the server\'s');
    }

    /**
     * The notification type is fixed by the controller, never taken from the client.
     *
     * A client-chosen slug would let a sender invent a type nobody has a preference
     * row for, and so walk past every existing mute.
     *
     * @return void
     */
    public function testSendIgnoresAPostedTypeAndStampsTheFixedOne(): void
    {
        $this->makeController(self::payload(['type' => 'audit.login']))->send();

        $this->assertSame(self::TYPE, $this->onlyRow()->type, 'the composer publishes exactly one type');
    }

    /**
     * Excluded users are stored as the sentinel-wrapped CSV the read path matches on.
     *
     * @return void
     */
    public function testSendStoresExclusionsAsASentinelWrappedCsv(): void
    {
        $this->makeController(self::payload([
            'users'         => [self::ALICE, self::BOB],
            'exclude_users' => [self::CAROL],
        ]))->send();

        $rows = $this->storedRows();

        $this->assertCount(2, $rows);
        $this->assertSame(
            [',' . self::CAROL . ',', ',' . self::CAROL . ','],
            array_column($rows, 'exclude_users'),
            'every row of the publication carries the exclusion'
        );
    }

    /**
     * Broadcast collapses the publication to a single row, whatever else was selected.
     *
     * @return void
     */
    public function testSendBroadcastDominatesTheSelectedTargets(): void
    {
        $this->makeController(self::payload([
            'mode'   => 'broadcast',
            'users'  => [self::ALICE],
            'groups' => [self::GROUP],
        ]))->send();

        $row = $this->onlyRow();

        $this->assertSame('broadcast', $row->target_type);
        $this->assertNull($row->target_value);
    }

    /**
     * An exclusion survives a broadcast: it is the one directive broadcast cannot absorb.
     *
     * @return void
     */
    public function testSendKeepsExclusionsOnABroadcast(): void
    {
        $this->makeController(self::payload([
            'mode'          => 'broadcast',
            'users'         => [],
            'exclude_users' => [self::BOB],
        ]))->send();

        $this->assertSame(',' . self::BOB . ',', $this->onlyRow()->exclude_users);
    }

    /**
     * A critical publication is stored as critical (the severity mutes cannot silence).
     *
     * @return void
     */
    public function testSendStoresTheCriticalSeverity(): void
    {
        $this->makeController(self::payload(['severity' => 'critical']))->send();

        $this->assertSame('critical', $this->onlyRow()->severity);
    }

    /**
     * The body and url reach storage through the message sanitiser, not around it.
     *
     * A url the sanitiser rejects (protocol-relative, an open-redirect vector) must be
     * stored as NULL even though the field itself passed the controller's length rule.
     *
     * @return void
     */
    public function testSendDelegatesFieldCleaningToTheMessageSanitiser(): void
    {
        $this->makeController(self::payload(['url' => '//evil.example.com']))->send();

        $this->assertNull($this->onlyRow()->url, 'the url contract lives in NotificationMessage and is not bypassed');
    }

    /**
     * An empty body and url are stored as NULL rather than as empty strings.
     *
     * @return void
     */
    public function testSendStoresBlankOptionalFieldsAsNull(): void
    {
        $this->makeController(self::payload(['body' => '   ', 'url' => '']))->send();

        $row = $this->onlyRow();

        $this->assertNull($row->body);
        $this->assertNull($row->url);
    }

    /**
     * A publication without a title is refused and writes nothing.
     *
     * @return void
     */
    public function testSendRejectsAnEmptyTitle(): void
    {
        $response = $this->makeController(self::payload(['title' => '']))->send();

        $this->assertSame([], $this->storedRows());
        $this->assertStringContainsString('compose', $response->getHeaderLine('Location'), 'the sender is returned to the form');
    }

    /**
     * A title carrying the forbidden markup characters is refused.
     *
     * @return void
     */
    public function testSendRejectsATitleCarryingMarkupCharacters(): void
    {
        $this->makeController(self::payload(['title' => 'Hello <script>alert(1)</script>']))->send();

        $this->assertSame([], $this->storedRows(), 'the row is refused rather than silently stripped');
    }

    /**
     * An array smuggled into a scalar field is refused, not cast.
     *
     * `title[]=x` reaches getPost() as an array; casting it would produce the string
     * 'Array', so the field is read as text or as nothing at all.
     *
     * @return void
     */
    public function testSendRejectsAnArrayInjectedTitle(): void
    {
        $this->makeController(self::payload(['title' => ['Array injection']]))->send();

        $this->assertSame([], $this->storedRows(), 'a non-scalar title is empty, and an empty title is refused');
    }

    /**
     * A severity outside the whitelist is refused rather than downgraded to info.
     *
     * @return void
     */
    public function testSendRejectsAnInvalidSeverity(): void
    {
        $this->makeController(self::payload(['severity' => 'urgent']))->send();

        $this->assertSame([], $this->storedRows());
    }

    /**
     * A mode outside the two known audiences is refused.
     *
     * @return void
     */
    public function testSendRejectsAnInvalidMode(): void
    {
        $this->makeController(self::payload(['mode' => 'everyone-but-bob']))->send();

        $this->assertSame([], $this->storedRows());
    }

    /**
     * A targeted publication with no user and no group selected is refused.
     *
     * @return void
     */
    public function testSendRejectsWhenNoTargetIsSelected(): void
    {
        $this->makeController(self::payload(['users' => [], 'groups' => []]))->send();

        $this->assertSame([], $this->storedRows());
    }

    /**
     * A group name outside the whitelist is refused, not silently dropped.
     *
     * Dropping it would send the publication to the remaining targets while the sender
     * believes the whole selected audience was addressed.
     *
     * @return void
     */
    public function testSendRejectsAGroupOutsideTheWhitelist(): void
    {
        $this->makeController(self::payload([
            'users'  => [],
            'groups' => [self::FOREIGN_GROUP],
        ]))->send();

        $this->assertSame([], $this->storedRows());
    }

    /**
     * An id no account carries is refused; nothing partial is published.
     *
     * @return void
     */
    public function testSendRejectsAnUnknownUserId(): void
    {
        $this->makeController(self::payload(['users' => [self::ALICE, self::MISSING]]))->send();

        $this->assertSame([], $this->storedRows(), 'the valid half of the selection is not published either');
    }

    /**
     * A soft-deleted account is not a valid target: the publication is refused.
     *
     * @return void
     */
    public function testSendRejectsASoftDeletedUserId(): void
    {
        $this->makeController(self::payload(['users' => [self::REMOVED]]))->send();

        $this->assertSame([], $this->storedRows());
    }

    /**
     * An unknown id in the EXCLUDE list is refused too, not quietly ignored.
     *
     * @return void
     */
    public function testSendRejectsAnUnknownExcludedUserId(): void
    {
        $this->makeController(self::payload(['exclude_users' => [self::MISSING]]))->send();

        $this->assertSame([], $this->storedRows());
    }

    /**
     * A nested array in a multi-select is dropped rather than cast to user 1.
     *
     * `(int) ['x']` is 1 in PHP, so an unfiltered cast would silently address the
     * account with id 1 — the very first, usually the superadmin.
     *
     * @return void
     */
    public function testSendDropsNestedArraysFromTheUserSelection(): void
    {
        $this->makeController(self::payload(['users' => [[self::ALICE]]]))->send();

        $this->assertSame([], $this->storedRows(), 'nothing is left to target, so the publication is refused');
    }

    /**
     * REGRESSION: a publication that stored nothing must not be reported to the sender
     * as "sent to N recipient(s)".
     *
     * send() used to decide success by accepting ANY channel result that reported ok.
     * Only InAppChannel persists anything: RealtimeChannel writes no row at all, it bumps
     * a Redis counter that tells connected browsers to re-read the feed from the
     * database. So when the durable write was refused — an exclusion that cannot be
     * enforced (here), an exclusion list above EXCLUDE_USERS_MAX, a missing table, a
     * failed insert — and realtime was enabled, the nudge alone satisfied that check and
     * the administrator was told the notification went out. Nothing was written, the
     * clients that followed the nudge found nothing, and the failure was invisible.
     * The decision now belongs to {@see \Modules\Notifications\Libraries\DispatchOutcome},
     * which counts only the channels that persist.
     *
     * The refusal being masked here is a SECURITY refusal: InAppChannel dropped the row
     * precisely so excluded users would not see it, and the sender is told it was sent.
     *
     * Both halves are forced rather than borrowed from the environment: the realtime
     * channel is switched on through an injected config and its Redis dependency is
     * replaced with {@see FakeSignalStore}, so this reproduces the same way on a machine
     * with realtime disabled or no Redis at all.
     *
     * @return void
     */
    public function testSendDoesNotReportSuccessWhenNothingWasStored(): void
    {
        $this->dropShadowTables();
        $this->createShadowTables(withExcludeUsers: false, withUsers: true);
        $this->seedPopulation();

        $config                  = new NotificationsConfig();
        $config->realtimeEnabled = true;
        Factories::injectMock('config', NotificationsConfig::class, $config);
        Services::injectMock('signalStore', new FakeSignalStore());

        $response = $this->makeController(self::payload(['exclude_users' => [self::CAROL]]))->send();

        $this->assertSame([], $this->storedRows(), 'the durable write was refused, so no notification exists');
        $this->assertStringContainsString('compose', $response->getHeaderLine('Location'));
        $this->assertNull(
            session()->getFlashdata('message'),
            'nothing was stored, so the sender must not be told the notification was sent'
        );
        $this->assertSame(
            lang('Notifications.composeFailed'),
            session()->getFlashdata('error'),
            'an undelivered publication has to be reported as one'
        );
    }

    /**
     * preview() reports the recipient count and writes nothing at all.
     *
     * @return void
     */
    public function testPreviewReportsTheRecipientCountWithoutWriting(): void
    {
        $response = $this->makeController([
            'mode'          => 'targeted',
            'users'         => [self::ALICE, self::CAROL],
            'groups'        => [self::GROUP],
            'exclude_users' => [self::CAROL],
        ], true)->preview();

        // alice + carol + the group's three membership rows (alice, bob and the stale
        // one left behind by the soft-deleted account) − carol = 3. The stale row is
        // the documented approximation of the count, not an oversight of this test:
        // see NotifierRecipientCountTest for why the second join is not paid for.
        $this->assertSame(['status' => true, 'count' => 3], json_decode((string) $response->getBody(), true));
        $this->assertSame([], $this->storedRows(), 'a preview is a read, never a write');
    }

    /**
     * preview() counts every non-deleted account for a broadcast.
     *
     * @return void
     */
    public function testPreviewCountsTheWholeSiteForABroadcast(): void
    {
        $response = $this->makeController(['mode' => 'broadcast'], true)->preview();

        $this->assertSame(['status' => true, 'count' => 4], json_decode((string) $response->getBody(), true));
    }

    /**
     * preview() drops selections that do not exist instead of counting them.
     *
     * @return void
     */
    public function testPreviewIgnoresUnknownUsersAndGroups(): void
    {
        $response = $this->makeController([
            'mode'   => 'targeted',
            'users'  => [self::ALICE, self::MISSING],
            'groups' => [self::FOREIGN_GROUP],
        ], true)->preview();

        $this->assertSame(['status' => true, 'count' => 1], json_decode((string) $response->getBody(), true));
    }

    /**
     * preview() is AJAX-only: a plain request is forbidden before anything is read.
     *
     * @return void
     */
    public function testPreviewRejectsNonAjaxRequests(): void
    {
        $this->assertSame(403, $this->makeController(['mode' => 'broadcast'], false)->preview()->getStatusCode());
    }

    /**
     * users() is AJAX-only: a plain request cannot dump the account list.
     *
     * @return void
     */
    public function testUsersEndpointRejectsNonAjaxRequests(): void
    {
        $this->assertSame(403, $this->makeController([], false)->users()->getStatusCode());
    }

    /**
     * users() returns labelled matches for the search term and hides deleted accounts.
     *
     * @return void
     */
    public function testUsersEndpointReturnsLabelledLivingAccountsOnly(): void
    {
        $response = $this->makeController([], true, ['q' => 'alice'])->users();
        $payload  = json_decode((string) $response->getBody(), true);

        $this->assertTrue($payload['status']);
        $this->assertSame([['id' => self::ALICE, 'text' => 'Alice Ada (alice)']], $payload['results']);

        $removed = json_decode((string) $this->makeController([], true, ['q' => 'removed'])->users()->getBody(), true);
        $this->assertSame([], $removed['results'], 'a soft-deleted account is never offered as a target');
    }

    /**
     * users() falls back to the username when no name is stored.
     *
     * @return void
     */
    public function testUsersEndpointLabelsANamelessAccountWithItsUsername(): void
    {
        $payload = json_decode((string) $this->makeController([], true, ['q' => 'carol'])->users()->getBody(), true);

        $this->assertSame([['id' => self::CAROL, 'text' => 'carol']], $payload['results']);
    }

    /**
     * The search term is bound, not concatenated: an injection payload is just text.
     *
     * @return void
     */
    public function testUsersEndpointBindsTheSearchTermInsteadOfConcatenatingIt(): void
    {
        $payload = json_decode((string) $this->makeController([], true, ['q' => "' OR 1=1 -- "])->users()->getBody(), true);

        $this->assertTrue($payload['status'], 'the query runs, so the payload was data rather than syntax');
        $this->assertSame([], $payload['results'], 'and it matched no account');
    }

    /**
     * The picker never returns more accounts than its page limit, whatever is asked.
     *
     * The endpoint is reachable by every backend user with read permission, so the
     * property that matters is that it is a PAGE of the account table and not a dump
     * of it — no term, and no term the caller can craft, may raise that ceiling.
     *
     * @return void
     */
    public function testUsersEndpointNeverReturnsMoreAccountsThanThePickerLimit(): void
    {
        $limit = (int) (new ReflectionClass(ComposerController::class))->getConstant('USER_PICKER_LIMIT');
        $extra = [];

        for ($i = 1; $i <= $limit + 5; $i++) {
            $extra[] = ['id' => 911000 + $i, 'username' => 'bulk' . $i, 'firstname' => null, 'surname' => null, 'deleted_at' => null];
        }

        $this->shadowDb->table('users')->insertBatch($extra);

        $payload = json_decode((string) $this->makeController([], true, ['q' => 'bulk'])->users()->getBody(), true);

        $this->assertCount($limit, $payload['results'], 'the page size holds even when far more accounts match');
    }

    /**
     * A publication whose rows all landed is still reported as sent.
     *
     * The counterpart of {@see testSendDoesNotReportSuccessWhenNothingWasStored}: the
     * delivery gate must reject the undelivered case without also swallowing the happy
     * path, which no other test in this class looks at from the sender's side.
     *
     * @return void
     */
    public function testSendReportsSuccessWhenEveryRowLanded(): void
    {
        $this->makeController(self::payload(['users' => [self::ALICE, self::BOB]]))->send();

        $this->assertCount(2, $this->storedRows());
        $this->assertSame(
            lang('Notifications.composeSent', [2]),
            session()->getFlashdata('message'),
            'both rows exist, so the sender is told the notification went out'
        );
        $this->assertNull(session()->getFlashdata('error'));
    }

    /**
     * A publication only half of which was stored is reported as a failure, not as sent.
     *
     * The dangerous shape is not "nothing was written" but "some of it was": the refusals
     * that drop a row are security refusals, so a publication that reached two thirds of
     * its audience while the rest was refused looks, from the sender's chair, exactly like
     * one that reached everybody. The count in the success message comes from
     * recipientCount(), which is derived from the SELECTION and never notices.
     *
     * The split is forced with {@see HalfFailingChannel} rather than waited for, because
     * the real refusals apply to every row of a publication at once.
     *
     * @return void
     */
    public function testSendReportsAPartialDeliveryInsteadOfClaimingSuccess(): void
    {
        $config           = new NotificationsConfig();
        $config->channels = ['inapp' => HalfFailingChannel::class];
        Factories::injectMock('config', NotificationsConfig::class, $config);

        $response = $this->makeController(self::payload(['users' => [self::ALICE, self::BOB]]))->send();

        $this->assertCount(1, $this->storedRows(), 'exactly half of the publication reached the table');
        $this->assertStringContainsString('compose', $response->getHeaderLine('Location'));
        $this->assertNull(session()->getFlashdata('message'), 'a half-delivered publication is not a sent one');
        $this->assertSame(
            lang('Notifications.composePartial', [1, 2]),
            session()->getFlashdata('error'),
            'the sender is told how much of the audience was actually reached'
        );
    }

    /**
     * A link pointing outside the site is refused instead of being dropped in silence.
     *
     * `compose.create` is not the highest permission in the panel, but a notification is
     * rendered without a visible sender and a critical one cannot be muted, so a link is
     * read as coming from the system itself. That is a phishing primitive, and the fix
     * belongs here rather than in the sanitiser, whose general contract still allows
     * http(s) for programmatic publishers.
     *
     * The silent half matters as much: an administrator who typed a host without a scheme
     * used to send a notification with no link at all and no warning.
     *
     * @return void
     */
    public function testSendRefusesALinkThatLeavesTheSite(): void
    {
        foreach (['https://evil.example/backend/login', 'http://evil.example', 'example.com/duyuru', 'javascript:alert(1)'] as $hostile) {
            $this->makeController(self::payload(['url' => $hostile]))->send();

            $this->assertSame([], $this->storedRows(), "{$hostile} must not reach a stored row");
            $this->assertSame(
                lang('Notifications.composeInvalidUrl'),
                session()->getFlashdata('errors')['url'] ?? null,
                "{$hostile} must be reported, not dropped in silence"
            );
        }
    }

    /**
     * A site-relative link is still accepted and stored.
     *
     * @return void
     */
    public function testSendKeepsASiteRelativeLink(): void
    {
        $this->makeController(self::payload(['url' => '/backend/notifications']))->send();

        $this->assertSame('/backend/notifications', $this->onlyRow()->url);
    }

    /**
     * More targets than the ceiling are refused outright, never trimmed.
     *
     * Each target directive is one INSERT plus one unread-badge cache sweep, and PHP's
     * default `max_input_vars` lets a single form post carry about a thousand of them.
     * Trimming would be worse than refusing: part of the audience the sender chose would
     * silently receive nothing.
     *
     * @return void
     */
    public function testSendRefusesMoreTargetsThanTheCeiling(): void
    {
        $ceiling = NotificationsConfig::TARGETS_MAX;

        $this->makeController(self::payload(['users' => range(920001, 920001 + $ceiling)]))->send();

        $this->assertSame([], $this->storedRows());
        $this->assertSame(
            lang('Notifications.composeTooManyTargets', [$ceiling]),
            session()->getFlashdata('errors')['users'] ?? null
        );
    }

    /**
     * A selection exactly at the ceiling passes it and is judged on its contents instead.
     *
     * Without this, a ceiling accidentally written as `>=` would look just as green.
     *
     * @return void
     */
    public function testSendLetsASelectionExactlyAtTheCeilingThrough(): void
    {
        $this->makeController(self::payload(['users' => range(920001, 920000 + NotificationsConfig::TARGETS_MAX)]))->send();

        $this->assertSame(
            lang('Notifications.composeUnknownUser'),
            session()->getFlashdata('errors')['users'] ?? null,
            'the ceiling did not fire: the selection was refused for its unknown ids instead'
        );
    }

    /**
     * A body longer than the column can hold is refused rather than silently truncated.
     *
     * @return void
     */
    public function testSendRefusesABodyLongerThanTheStorageCeiling(): void
    {
        $this->makeController(self::payload(['body' => str_repeat('a', NotificationsConfig::BODY_MAX + 1)]))->send();

        $this->assertSame([], $this->storedRows(), 'the row is refused rather than stored half-written');
    }

    /**
     * The validation message names the field instead of showing its raw placeholder.
     *
     * The message carries literal braces (it lists the forbidden characters), and the
     * validator formats every message through ICU. An unquoted brace makes the pattern
     * fail to parse, and CI4 then hands back the untouched string — so the administrator
     * read `{field}` where the field name belongs, followed by a framework warning.
     *
     * @return void
     */
    public function testTheInvalidTextMessageNamesTheFieldItRefused(): void
    {
        $this->makeController(self::payload(['title' => 'Hello <script>alert(1)</script>']))->send();

        $message = session()->getFlashdata('errors')['title'] ?? '';

        $this->assertStringContainsString(lang('Notifications.composeFieldTitle'), $message, 'the field name is interpolated');
        $this->assertStringNotContainsString('{field}', $message, 'the ICU placeholder must not survive into the message');
        $this->assertStringNotContainsString('【Warning】', $message, 'nor may the framework append its parse warning');
        $this->assertStringContainsString('{', $message, 'and the braces the message is ABOUT are still printed');
    }

    /**
     * A LIKE wildcard in the search term is matched as text, not as a pattern.
     *
     * The term is bound, so this was never an injection; it was an enumeration
     * amplifier. `%` shifts the twenty-row window over the whole account table, which
     * turns a picker into a directory.
     *
     * @return void
     */
    public function testUsersEndpointMatchesLikeWildcardsAsLiteralText(): void
    {
        $this->shadowDb->table('users')->insertBatch([
            ['id' => 912001, 'username' => 'percent', 'firstname' => null, 'surname' => null, 'deleted_at' => null],
            ['id' => 912002, 'username' => 'perc%ent', 'firstname' => null, 'surname' => null, 'deleted_at' => null],
        ]);

        $payload = json_decode((string) $this->makeController([], true, ['q' => 'perc%'])->users()->getBody(), true);

        $this->assertSame(
            [['id' => 912002, 'text' => 'perc%ent']],
            $payload['results'],
            'the % matched a literal per cent sign, not every account beginning with perc'
        );
    }

    /**
     * An underscore in a username is still findable: the wildcard is escaped, not stripped.
     *
     * Stripping the metacharacters would neutralise them too, at the cost of making
     * `john_doe` unsearchable — a silent regression in the opposite direction.
     *
     * @return void
     */
    public function testUsersEndpointStillFindsAnAccountWhoseNameCarriesAnUnderscore(): void
    {
        $this->shadowDb->table('users')->insertBatch([
            ['id' => 912011, 'username' => 'john_doe', 'firstname' => null, 'surname' => null, 'deleted_at' => null],
            ['id' => 912012, 'username' => 'johnxdoe', 'firstname' => null, 'surname' => null, 'deleted_at' => null],
        ]);

        $payload = json_decode((string) $this->makeController([], true, ['q' => 'john_doe'])->users()->getBody(), true);

        $this->assertSame([['id' => 912011, 'text' => 'john_doe']], $payload['results']);
    }

    /**
     * A banned account is neither offered as a target nor accepted as one.
     *
     * Shield refuses a banned identity at login, so the account cannot read a
     * notification at all: listing it leaks the existence of a suspended identity to
     * every operator who can open the picker, and addressing it inflates the recipient
     * count with somebody who will never see the row.
     *
     * @return void
     */
    public function testABannedAccountIsNeitherOfferedNorAccepted(): void
    {
        $this->shadowDb->table('users')->insert(
            ['id' => 912021, 'username' => 'bannedone', 'firstname' => 'Ban', 'surname' => 'Ned', 'status' => 'banned', 'deleted_at' => null]
        );

        $picker = json_decode((string) $this->makeController([], true, ['q' => 'bannedone'])->users()->getBody(), true);
        $this->assertSame([], $picker['results'], 'a suspended identity is not disclosed by the picker');

        $this->makeController(self::payload(['users' => [912021]]))->send();
        $this->assertSame([], $this->storedRows(), 'nor can it be addressed by id');

        $preview = $this->makeController(['mode' => 'broadcast'], true)->preview();
        $this->assertSame(
            ['status' => true, 'count' => 4],
            json_decode((string) $preview->getBody(), true),
            'and it is not counted among the recipients of a broadcast'
        );
    }

    /**
     * The whole publication path leaves the permanent tables byte-for-byte untouched.
     *
     * The shadow is what makes these tests safe to run against a shared database, so
     * its isolation is proven rather than assumed.
     *
     * @return void
     */
    public function testSendLeavesThePermanentTablesUntouched(): void
    {
        $this->makeController(self::payload(['mode' => 'broadcast', 'users' => []]))->send();

        $this->assertCount(1, $this->storedRows(), 'the write landed in the shadow');

        $this->dropShadowTables();

        $this->assertPermanentSchemaIntact($this->permanentColumns, $this->permanentRows);
        $this->assertSame(
            $this->permanentUsers,
            db_connect('default')->table('users')->countAllResults(),
            'the seeded population never reached the real accounts table'
        );
    }
}

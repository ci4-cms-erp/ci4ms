<?php

namespace Tests\Modules\Notifications;

use CodeIgniter\Config\Services;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\Mock\MockCache;
use Modules\Notifications\Libraries\Channels\InAppChannel;
use Modules\Notifications\Libraries\NotificationMessage;
use Modules\Notifications\Libraries\SchemaGuard;
use Tests\Support\Notifications\CapturingChannel;
use Tests\Support\Notifications\FakeDispatchNotifier;
use Tests\Support\Notifications\ShadowSchemaTrait;

/**
 * The FAZ 3 accountability trace: who produced a notification, and what it may not cost.
 *
 * `created_by` answers a question Model B rows otherwise cannot: the row is global
 * (`user_id` is always NULL, the audience lives in `target_type`/`target_value`), so
 * "who sent this" needs a column of its own. Two properties are asserted here.
 *
 * The first is that the trace is carried, unchanged, from the builder to every row of
 * a publication and influences NOTHING else — it is not a target, not an exclusion,
 * and no delivery decision reads it.
 *
 * The second is the direction it fails in. `exclude_users` fails CLOSED, because an
 * exclusion that cannot be enforced means an excluded user SEES the notification
 * ({@see InAppChannelExclusionTest}). `created_by` is the opposite: on a database where
 * the migration has not run, the row is still written and only the trace is lost. Losing
 * every notification is a heavier outcome than losing the sender's name, and that
 * asymmetry is deliberate — so it is pinned rather than left to be "cleaned up" later
 * into a symmetric guard.
 *
 * Writes land in connection-scoped TEMPORARY shadows ({@see ShadowSchemaTrait}),
 * including the pre-migration shape that omits the column.
 *
 * @internal
 */
final class NotificationCreatedByTest extends CIUnitTestCase
{
    use ShadowSchemaTrait;

    /** The administrator credited with the publications built here. */
    private const CREATOR = 930100;

    /** A directly targeted user who is also a member of {@see GROUP}. */
    private const MEMBER = 930001;

    /** The group targeted alongside {@see MEMBER}. */
    private const GROUP = 'editors';

    /** A user kept out of the audience. */
    private const EXCLUDED = 930002;

    /** Event type every message here carries (never persisted permanently). */
    private const TYPE = 'phpunit.createdby';

    private MockCache $cache;

    /**
     * Injects a mock cache so the post-write invalidation touches nothing real.
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

        $this->cache = new MockCache();
        Services::injectMock('cache', $this->cache);
    }

    /**
     * Drops the shadows and releases the injected cache.
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
        // (FeatureTestTrait.php:216) reloads it afterwards. Any later test
        // in the same process that drives a controller directly (not
        // through FeatureTestTrait) and hits redirect()->route(...) would
        // otherwise fail with HTTPException "The route for '...' cannot be
        // found" (see tests/Modules/Users/PermgroupPrivilegeEscalationTest.php
        // :106-123 and context.md FAZ 4 / F-2), so it is reloaded below.
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

        parent::tearDown();
    }

    /**
     * Builds a single-target message attributed to the given creator.
     *
     * @param int|null  $createdBy The producing user id, or null for a system event.
     * @param list<int> $exclude   Ids the caller asks to exclude (a guarantee).
     *
     * @return NotificationMessage A sanitised, dispatch-ready message.
     */
    private function message(?int $createdBy, array $exclude = []): NotificationMessage
    {
        return new NotificationMessage(
            self::TYPE,
            'info',
            'PHPUnit created_by',
            null,
            null,
            'broadcast',
            null,
            ['inapp'],
            $exclude,
            [],
            $createdBy
        );
    }

    /**
     * Rows currently stored in the shadowed notifications table.
     *
     * @return list<\stdClass> Rows in insertion order.
     */
    private function storedRows(): array
    {
        return array_values($this->shadowDb->table('notifications')->orderBy('id', 'ASC')->get()->getResult());
    }

    /**
     * An id that addresses no account is stored as "system produced" instead.
     *
     * @return void
     */
    public function testMessageReducesANonAccountCreatorToNull(): void
    {
        $this->assertNull($this->message(0)->createdBy, '0 is not a user, so it must not look like one');
        $this->assertNull($this->message(-1)->createdBy);
        $this->assertNull($this->message(null)->createdBy, 'a system event stays a system event');
        $this->assertSame(self::CREATOR, $this->message(self::CREATOR)->createdBy);
    }

    /**
     * The builder carries the creator into every row of the publication.
     *
     * @return void
     */
    public function testBuilderStampsEveryTargetRowWithTheSameCreator(): void
    {
        $channel  = new CapturingChannel();
        $notifier = new FakeDispatchNotifier($channel, [self::GROUP => [self::MEMBER]]);

        $notifier->notify(self::TYPE)
            ->title('PHPUnit')
            ->createdBy(self::CREATOR)
            ->toUser(self::MEMBER)
            ->toGroup(self::GROUP)
            ->via('inapp')
            ->dispatch();

        $this->assertCount(2, $channel->messages);
        $this->assertSame(
            [self::CREATOR, self::CREATOR],
            array_map(static fn (NotificationMessage $message): ?int => $message->createdBy, $channel->messages),
            'one publication, one sender, however many rows it resolves to'
        );
    }

    /**
     * A publication with no createdBy() call is attributed to the system.
     *
     * The distinction is the point of the nullable column: an event- or CLI-produced
     * notification has no human behind it, and must not borrow one.
     *
     * @return void
     */
    public function testBuilderLeavesEventProducedPublicationsUnattributed(): void
    {
        $channel = new CapturingChannel();

        (new FakeDispatchNotifier($channel))->notify(self::TYPE)->title('PHPUnit')->broadcast()->via('inapp')->dispatch();

        $this->assertNull($channel->messages[0]->createdBy);
    }

    /**
     * The creator is a trace, not a target: it changes no delivery decision.
     *
     * @return void
     */
    public function testCreatorIsNotTurnedIntoATargetOrAnExclusion(): void
    {
        $channel = new CapturingChannel();

        (new FakeDispatchNotifier($channel))->notify(self::TYPE)
            ->title('PHPUnit')
            ->createdBy(self::CREATOR)
            ->broadcast()
            ->via('inapp')
            ->dispatch();

        $message = $channel->messages[0];

        $this->assertSame('broadcast', $message->targetType, 'the audience is untouched');
        $this->assertSame([], $message->excludeUsers, 'the sender is not excluded from their own publication');
        $this->assertSame(self::CREATOR, $message->createdBy);
    }

    /**
     * With the column migrated, the trace reaches the stored row.
     *
     * @return void
     */
    public function testChannelStoresTheCreatorWhenTheColumnExists(): void
    {
        $this->createShadowTables();

        $result = (new InAppChannel())->send($this->message(self::CREATOR));

        $this->assertTrue($result->ok);

        $rows = $this->storedRows();
        $this->assertCount(1, $rows);
        $this->assertSame(self::CREATOR, (int) $rows[0]->created_by);
    }

    /**
     * A system-produced notification stores NULL rather than a placeholder id.
     *
     * @return void
     */
    public function testChannelStoresNullForASystemProducedNotification(): void
    {
        $this->createShadowTables();

        (new InAppChannel())->send($this->message(null));

        $this->assertNull($this->storedRows()[0]->created_by, 'NULL is the value that means "the system produced it"');
    }

    /**
     * FAIL-OPEN: on an unmigrated schema the row is still written, only the trace is lost.
     *
     * This is the branch the exclusion guard deliberately does NOT share. If it were
     * made symmetric, dropping the module into a site without running migrations would
     * silently stop every composer publication — a heavier failure than an anonymous
     * one.
     *
     * @return void
     */
    public function testChannelStillWritesTheRowWhenTheColumnIsMissing(): void
    {
        $this->createShadowTables(withCreatedBy: false);

        $result = (new InAppChannel())->send($this->message(self::CREATOR));

        $this->assertTrue($result->ok, 'an unwritable trace is not a reason to drop the notification');
        $this->assertNotNull($result->insertId);

        $rows = $this->storedRows();
        $this->assertCount(1, $rows, 'the notification still reached its audience');
        $this->assertArrayNotHasKey('created_by', (array) $rows[0], 'the column genuinely was not there');
    }

    /**
     * A missing trace column does not weaken the exclusion guarantee alongside it.
     *
     * The two capabilities are independent: the fail-open branch must not become a way
     * to get a row written that the exclusion rules would have refused.
     *
     * @return void
     */
    public function testMissingTraceColumnDoesNotSoftenTheExclusionGuarantee(): void
    {
        $this->createShadowTables(withExcludeUsers: false, withCreatedBy: false);

        $result = (new InAppChannel())->send($this->message(self::CREATOR, [self::EXCLUDED]));

        $this->assertFalse($result->ok, 'the exclusion still decides');
        $this->assertSame('exclusion-unsupported', $result->meta['reason']);
        $this->assertSame([], $this->storedRows());
    }

    /**
     * With only the trace column missing, an exclusion is still stored and enforced.
     *
     * @return void
     */
    public function testExclusionIsStillStoredWhenOnlyTheTraceColumnIsMissing(): void
    {
        $this->createShadowTables(withCreatedBy: false);

        $result = (new InAppChannel())->send($this->message(self::CREATOR, [self::EXCLUDED]));

        $this->assertTrue($result->ok);
        $this->assertSame(
            NotificationMessage::encodeExcludeUsers([self::EXCLUDED]),
            $this->storedRows()[0]->exclude_users,
            'the enforceable guarantee is kept even though the trace was dropped'
        );
    }

    /**
     * SchemaGuard reports the trace capability from the schema actually in front of it.
     *
     * @return void
     */
    public function testSchemaGuardReportsTheTraceColumnPerSchema(): void
    {
        $this->createShadowTables();
        $this->assertTrue(SchemaGuard::hasCreatedBy(db_connect('default')), 'the migrated shadow exposes the column');

        $this->dropShadowTables();
        $this->createShadowTables(withCreatedBy: false);

        $this->assertFalse(SchemaGuard::hasCreatedBy(db_connect('default')), 'the pre-migration shadow does not');
    }

    /**
     * The pre-migration shadow leaves the permanent notifications table untouched.
     *
     * @return void
     */
    public function testTraceWritesLeaveThePermanentTableUntouched(): void
    {
        $db      = db_connect('default');
        $columns = count($db->getFieldNames('notifications'));
        $rows    = $db->table('notifications')->countAllResults();

        $this->createShadowTables();

        (new InAppChannel())->send($this->message(self::CREATOR));
        $this->assertCount(1, $this->storedRows(), 'the write landed in the shadow');

        $this->dropShadowTables();

        $this->assertPermanentSchemaIntact($columns, $rows);
    }
}

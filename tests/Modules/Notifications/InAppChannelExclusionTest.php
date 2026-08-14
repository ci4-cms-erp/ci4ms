<?php

namespace Tests\Modules\Notifications;

use CodeIgniter\Config\Services;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\Mock\MockCache;
use Modules\Notifications\Config\NotificationsConfig;
use Modules\Notifications\Libraries\Channels\InAppChannel;
use Modules\Notifications\Libraries\NotificationMessage;
use Tests\Support\Notifications\FakeDispatchNotifier;
use Tests\Support\Notifications\ShadowSchemaTrait;

/**
 * InAppChannel's exclusion rule, and how it splits by where the exclusion came from.
 *
 * `exceptUser()` is documented as a guarantee ("exclusion always wins"), and the
 * read path enforces it purely in SQL. Two conditions make that SQL unable to keep
 * the promise: the `exclude_users` column may not be migrated yet, and the sentinel
 * CSV may exceed what the TEXT column can hold (this project runs `strictOn = false`,
 * so an overflow is truncated *silently* and a broken sentinel matches nobody).
 * In both cases writing the row anyway would let the excluded users SEE the
 * notification, so the write is refused instead — the property asserted here is the
 * absence of a row, which only a real table can settle.
 *
 * That refusal is scoped to EXPLICIT exclusions. The builder also DERIVES exclusions
 * when a user is targeted both directly and through a group, and that list is only an
 * optimisation against seeing the same notification twice. Refusing the write for it
 * would delete the whole group's notification on an unmigrated schema even though
 * nobody asked for an exclusion — a worse outcome than the duplicate it prevents — so
 * a derived-only list fails OPEN. Both halves are pinned below.
 *
 * Every write is aimed at connection-scoped TEMPORARY shadows ({@see ShadowSchemaTrait}),
 * including the pre-migration variant that omits `exclude_users`: even a regression
 * that writes the row cannot reach the permanent table.
 *
 * @internal
 */
final class InAppChannelExclusionTest extends CIUnitTestCase
{
    use ShadowSchemaTrait;

    /** Event type every message here carries (never persisted permanently). */
    private const TYPE = 'phpunit.exclusion';

    /** A user the caller asks to exclude. */
    private const EXCLUDED = 900301;

    /** A user targeted directly and, in the same dispatch, through {@see OVERLAP_GROUP}. */
    private const OVERLAP_USER = 5;

    /** The group whose row the overlap narrowing trims. */
    private const OVERLAP_GROUP = 'admin';

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
     * Builds a single-target message with the given exclusion lists, by provenance.
     *
     * @param list<int> $explicit Ids the caller asked to exclude (a guarantee).
     * @param list<int> $derived  Ids the overlap narrowing contributed (an optimisation).
     *
     * @return NotificationMessage A sanitised, dispatch-ready message.
     */
    private function message(array $explicit, array $derived = []): NotificationMessage
    {
        return new NotificationMessage(
            self::TYPE,
            'info',
            'PHPUnit exclusion',
            null,
            null,
            'broadcast',
            null,
            ['inapp'],
            $explicit,
            $derived
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
     * With the column unmigrated, an EXPLICIT exclusion request is refused instead of leaking.
     *
     * Before the guard the row was written without its `exclude_users` field and the
     * read path emitted no exclusion filter either, so the excluded user saw the
     * notification — the guarantee failed silently and open.
     *
     * @return void
     */
    public function testExclusionIsRefusedWhenTheColumnIsNotMigrated(): void
    {
        $this->createShadowTables(false);

        $result = (new InAppChannel())->send($this->message([self::EXCLUDED]));

        $this->assertFalse($result->ok, 'an unenforceable exclusion is not a delivery');
        $this->assertSame('exclusion-unsupported', $result->meta['reason']);
        $this->assertNull($result->insertId);
        $this->assertSame([], $this->storedRows(), 'no row may exist that would be visible to the excluded user');
    }

    /**
     * A dispatch without exclusions still writes on an unmigrated schema.
     *
     * The fail-closed branch must stay scoped to callers that asked for an exclusion;
     * otherwise dropping the module in without running migrations would stop ordinary
     * notifications, which is the reflex the guard is explicitly not allowed to break.
     *
     * @return void
     */
    public function testOrdinaryDispatchStillWritesOnAnUnmigratedSchema(): void
    {
        $this->createShadowTables(false);

        $result = (new InAppChannel())->send($this->message([]));

        $this->assertTrue($result->ok, 'a message with no exclusion is unaffected by the guard');
        $this->assertCount(1, $this->storedRows());
    }

    /**
     * A derived-only narrowing writes the row anyway on an unmigrated schema (fail-open).
     *
     * Nobody called `exceptUser()` here: the list exists solely because the same user is
     * also targeted directly, so dropping it costs one duplicate notification. Refusing
     * the write instead would cost the entire group its notification, which is why this
     * branch is the one place the exclusion machinery is allowed to fail open.
     *
     * @return void
     */
    public function testDerivedOnlyNarrowingStillWritesWhenTheColumnIsNotMigrated(): void
    {
        $this->createShadowTables(false);

        $result = (new InAppChannel())->send($this->message([], [self::OVERLAP_USER]));

        $this->assertTrue($result->ok, 'an unenforceable optimisation is not a reason to drop the row');
        $this->assertNotNull($result->insertId);
        $this->assertCount(1, $this->storedRows(), 'the row every non-overlapping recipient needs was still written');
    }

    /**
     * The real regression: an overlapping dispatch keeps BOTH rows on an unmigrated schema.
     *
     * Drives the whole builder path — `toUser(5)` plus `toGroup('admin')` with user 5 in
     * that group — into a real InAppChannel over the pre-migration shadow. The narrowing
     * is derived, so the group row must survive; when it was treated as a guarantee the
     * group row was skipped and every member of `admin` lost the notification.
     *
     * @return void
     */
    public function testOverlappingDispatchKeepsBothRowsOnAnUnmigratedSchema(): void
    {
        $this->createShadowTables(false);

        $notifier = new FakeDispatchNotifier(new InAppChannel(), [self::OVERLAP_GROUP => [self::OVERLAP_USER]]);

        $results = $notifier->notify(self::TYPE)
            ->title('PHPUnit overlap')
            ->toUser(self::OVERLAP_USER)
            ->toGroup(self::OVERLAP_GROUP)
            ->via('inapp')
            ->dispatch();

        $this->assertCount(2, $results, 'Model B writes one row per resolved target');
        $this->assertSame([true, true], array_column($results, 'ok'), 'neither row was skipped');

        $rows = $this->storedRows();
        $this->assertCount(2, $rows);
        $this->assertSame(['user', 'group'], array_column($rows, 'target_type'));
        $this->assertSame(
            [(string) self::OVERLAP_USER, self::OVERLAP_GROUP],
            array_column($rows, 'target_value'),
            'the group row is the one that used to disappear'
        );
    }

    /**
     * When both sources are present and unenforceable, the explicit one still wins.
     *
     * The fail-open branch is not a way around the guarantee: a message that carries any
     * explicitly excluded id is refused regardless of what the narrowing added.
     *
     * @return void
     */
    public function testExplicitExclusionStillFailsClosedAlongsideADerivedOne(): void
    {
        $this->createShadowTables(false);

        $result = (new InAppChannel())->send($this->message([self::EXCLUDED], [self::OVERLAP_USER]));

        $this->assertFalse($result->ok, 'the guarantee decides, not the optimisation');
        $this->assertSame('exclusion-unsupported', $result->meta['reason']);
        $this->assertSame([], $this->storedRows());
    }

    /**
     * With the column present, both sources land in ONE union CSV — storage is unaware of provenance.
     *
     * @return void
     */
    public function testBothExclusionSourcesAreStoredAsASingleUnionCsv(): void
    {
        $this->createShadowTables();

        $result = (new InAppChannel())->send($this->message([self::EXCLUDED], [self::OVERLAP_USER]));

        $this->assertTrue($result->ok);

        $rows = $this->storedRows();
        $this->assertCount(1, $rows);
        $this->assertSame(
            NotificationMessage::encodeExcludeUsers([self::OVERLAP_USER, self::EXCLUDED]),
            $rows[0]->exclude_users,
            'one sentinel CSV holds the union of the explicit and the derived list'
        );
    }

    /**
     * The cap counts both sources together: an overflow leaks whatever produced it.
     *
     * Each list stays under the cap on its own, so only a union-based check can catch
     * the combination — and the truncation it guards against is provenance-blind.
     *
     * @return void
     */
    public function testTheCapIsEvaluatedOnTheUnionOfBothSources(): void
    {
        $this->createShadowTables();

        $half     = intdiv(NotificationsConfig::EXCLUDE_USERS_MAX, 2) + 1;
        $explicit = range(1, $half);
        $derived  = range($half + 1, NotificationsConfig::EXCLUDE_USERS_MAX + 1);

        $this->assertLessThanOrEqual(NotificationsConfig::EXCLUDE_USERS_MAX, count($explicit), 'the explicit list alone is acceptable');
        $this->assertLessThanOrEqual(NotificationsConfig::EXCLUDE_USERS_MAX, count($derived), 'the derived list alone is acceptable');

        $result = (new InAppChannel())->send($this->message($explicit, $derived));

        $this->assertFalse($result->ok);
        $this->assertSame('exclusion-too-large', $result->meta['reason']);
        $this->assertSame([], $this->storedRows());
    }

    /**
     * An exclusion list above the cap is refused rather than silently truncated.
     *
     * @return void
     */
    public function testExclusionAboveTheCapIsRefused(): void
    {
        $this->createShadowTables();

        $tooMany = range(1, NotificationsConfig::EXCLUDE_USERS_MAX + 1);

        $result = (new InAppChannel())->send($this->message($tooMany));

        $this->assertFalse($result->ok);
        $this->assertSame('exclusion-too-large', $result->meta['reason']);
        $this->assertSame([], $this->storedRows(), 'a truncated exclusion list would fail open, so nothing is stored');
    }

    /**
     * An exclusion list exactly at the cap is still written, well below the TEXT limit.
     *
     * Pins both edges of the boundary: the cap itself is accepted, and the value it
     * produces keeps a wide margin under the 65 535 bytes at which MariaDB would
     * truncate silently — which is the reason the cap is a count, not a byte length.
     *
     * @return void
     */
    public function testExclusionAtTheCapIsStoredIntact(): void
    {
        $this->createShadowTables();

        $atCap = range(1, NotificationsConfig::EXCLUDE_USERS_MAX);

        $result = (new InAppChannel())->send($this->message($atCap));

        $this->assertTrue($result->ok);

        $rows = $this->storedRows();
        $this->assertCount(1, $rows);

        $stored = (string) $rows[0]->exclude_users;

        $this->assertSame(NotificationMessage::encodeExcludeUsers($atCap), $stored, 'the stored CSV was not truncated');
        $this->assertLessThan(65535, strlen($stored), 'the cap keeps the value far below the TEXT truncation limit');
        $this->assertStringContainsString(
            trim(NotificationMessage::excludeMatchPattern(NotificationsConfig::EXCLUDE_USERS_MAX), '%'),
            $stored,
            'the last id of the list survived, sentinel and all'
        );
    }

    /**
     * The pre-migration shadow leaves the permanent notifications table untouched.
     *
     * The column-less variant is the one new shape these tests introduce, so its
     * isolation is proven rather than assumed: the permanent table keeps its column
     * count and row count across a full create/write/drop cycle.
     *
     * @return void
     */
    public function testUnmigratedShadowLeavesThePermanentTableUntouched(): void
    {
        $db      = db_connect('default');
        $columns = count($db->getFieldNames('notifications'));
        $rows    = $db->table('notifications')->countAllResults();

        $this->createShadowTables(false);

        $this->assertNotContains('exclude_users', $this->shadowDb->getFieldNames('notifications'), 'the shadow reproduces a pre-migration schema');

        (new InAppChannel())->send($this->message([]));
        $this->assertCount(1, $this->storedRows(), 'the write landed in the shadow');

        $this->dropShadowTables();

        $this->assertPermanentSchemaIntact($columns, $rows);
    }
}

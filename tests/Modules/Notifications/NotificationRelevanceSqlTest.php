<?php

namespace Tests\Modules\Notifications;

use CodeIgniter\Config\Services;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\Mock\MockCache;
use Modules\Notifications\Libraries\Notifier;
use Modules\Notifications\Libraries\SchemaGuard;
use Tests\Support\Notifications\ShadowSchemaTrait;

/**
 * FAZ 2 read-side relevance SQL: row exclusion and preference muting.
 *
 * `Notifier::applyRelevance()` is the single relevance chokepoint and is expressed
 * purely as SQL — a sentinel LIKE for `exclude_users` and a LEFT JOIN anti-join for
 * `notification_preferences`. Neither can be reproduced by an in-memory double, and
 * both are invisible on this dev database because the FAZ 2 migrations have not run
 * there (SchemaGuard reports the capabilities off and the SQL is never emitted).
 *
 * So each test runs against a full set of TEMPORARY-table shadows carrying the
 * post-migration schema ({@see ShadowSchemaTrait}). Every row lives in those
 * connection-scoped tables: the permanent schema is never altered, no real row is
 * read or written, and no real user has to exist (the shadows carry no foreign
 * keys, which is why the throwaway ids below are safe).
 * {@see testShadowSchemaLeavesThePermanentTablesUntouched()} asserts that isolation
 * rather than assuming it.
 *
 * The send-side twin of these rules is covered by {@see NotificationTargetingTest}.
 *
 * @internal
 */
final class NotificationRelevanceSqlTest extends CIUnitTestCase
{
    use ShadowSchemaTrait;

    /** The reading user for most cases (no real users table row is needed). */
    private const USER = 900001;

    /** A second user proving filters are per-user, never global. */
    private const OTHER_USER = 900002;

    /** Group both throwaway users belong to. */
    private const GROUP = 'phpunit_shadow_grp';

    /** A second group used for the group-vs-group overlap limit. */
    private const OTHER_GROUP = 'phpunit_shadow_grp2';

    /** Id whose sentinel needle (',5,') is a substring risk against ',15,'. */
    private const COLLIDING_USER = 5;

    /** Id deliberately excluded to create the prefix-collision hazard. */
    private const EXCLUDED_USER = 15;

    /** Mutable prefix registered in NotificationsConfig::$preferenceTypes. */
    private const TYPE_PREFIX = 'audit';

    /** A dotted subtype the prefix must also silence. */
    private const TYPE_SUBTYPE = 'audit.login';

    /** A type that merely STARTS with the prefix and must NOT be silenced. */
    private const TYPE_SIBLING = 'auditor.report';

    private Notifier $notifier;

    private MockCache $cache;

    /**
     * Shadows the FAZ 2 schema, seeds group membership and injects a mock cache.
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

        $this->createShadowTables();

        $this->shadowDb->table('auth_groups_users')->insertBatch([
            ['user_id' => self::USER, 'group' => self::GROUP, 'created_at' => date('Y-m-d H:i:s')],
            ['user_id' => self::USER, 'group' => self::OTHER_GROUP, 'created_at' => date('Y-m-d H:i:s')],
            ['user_id' => self::OTHER_USER, 'group' => self::GROUP, 'created_at' => date('Y-m-d H:i:s')],
        ]);

        $this->notifier = new Notifier();
    }

    /**
     * Drops every shadow table and clears the memoised schema capabilities.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        $this->dropShadowTables();

        parent::tearDown();
    }

    /**
     * Inserts one shadow notification row and returns its id.
     *
     * @param array<string, mixed> $overrides Column values overriding the defaults.
     *
     * @return int The new notification id.
     */
    private function seed(array $overrides = []): int
    {
        $this->shadowDb->table('notifications')->insert(array_merge([
            'user_id'       => null,
            'type'          => self::TYPE_SUBTYPE,
            'severity'      => 'info',
            'target_type'   => 'broadcast',
            'target_value'  => null,
            'exclude_users' => null,
            'title'         => 'PHPUnit relevance',
            'body'          => null,
            'url'           => null,
            'channel'       => 'inapp',
            'read_at'       => null,
            'created_at'    => date('Y-m-d H:i:s'),
        ], $overrides));

        return (int) $this->shadowDb->insertID();
    }

    /**
     * Inserts one preference row (a mute unless $enabled is 1).
     *
     * @param int    $userId  Owner of the preference.
     * @param string $type    Full type or type prefix to silence.
     * @param string $channel '*' for every channel, or a channel slug.
     * @param int    $enabled 0 mutes; 1 is an informational row that must not filter.
     *
     * @return void
     */
    private function mute(int $userId, string $type = self::TYPE_PREFIX, string $channel = '*', int $enabled = 0): void
    {
        $this->shadowDb->table('notification_preferences')->insert([
            'user_id'    => $userId,
            'type'       => $type,
            'channel'    => $channel,
            'enabled'    => $enabled,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Notification ids the feed returns for a user.
     *
     * @param int $userId Reading user.
     *
     * @return list<int> Ids in feed order.
     */
    private function feedIds(int $userId = self::USER): array
    {
        return array_values(array_map(
            static fn (\stdClass $row): int => (int) $row->id,
            $this->notifier->listFor($userId, 100)
        ));
    }

    /**
     * Uncached unread badge count for a user.
     *
     * @param int $userId Reading user.
     *
     * @return int Relevant unread rows.
     */
    private function unreadFor(int $userId = self::USER): int
    {
        cache()->delete(Notifier::cacheKey($userId));

        return $this->notifier->unreadCount($userId);
    }

    /**
     * The shadowed run alters neither the permanent schema nor its rows.
     *
     * Runs the whole cycle explicitly — drop the shadows setUp created, record the
     * live shape, re-shadow, write into the shadow, drop again — so the isolation
     * claim the rest of this class rests on is proven rather than assumed.
     *
     * @return void
     */
    public function testShadowSchemaLeavesThePermanentTablesUntouched(): void
    {
        $this->dropShadowTables();

        $db       = db_connect('default');
        $columns  = count($db->getFieldNames('notifications'));
        $rows     = $db->table('notifications')->countAllResults();
        $hadPrefs = $db->tableExists('notification_preferences');

        $this->createShadowTables();
        $this->seed(['exclude_users' => ',1,']);
        $this->assertSame(1, $this->shadowDb->table('notifications')->countAllResults(), 'the write landed in the shadow');

        $this->dropShadowTables();

        $this->assertPermanentSchemaIntact($columns, $rows);
        $this->assertSame($hadPrefs, $db->tableExists('notification_preferences'), 'the shadow cycle neither created nor dropped the permanent preferences table');
    }

    /**
     * SchemaGuard answers once per request and re-reads the schema only after reset().
     *
     * @return void
     */
    public function testSchemaGuardMemoisesCapabilitiesUntilReset(): void
    {
        $db = db_connect('default');

        $this->assertTrue(SchemaGuard::hasExcludeUsers($db), 'the shadow exposes the FAZ 2 column');
        $this->assertTrue(SchemaGuard::hasPreferences($db), 'the shadow exposes the FAZ 2 table');

        $prefix = $db->getPrefix();
        $db->query("DROP TEMPORARY TABLE IF EXISTS `{$prefix}notifications`");
        $db->resetDataCache();

        $this->assertTrue(SchemaGuard::hasExcludeUsers($db), 'the answer is memoised, not re-queried on every statement');

        SchemaGuard::reset();

        $this->assertSame(
            $db->fieldExists('exclude_users', 'notifications'),
            SchemaGuard::hasExcludeUsers($db),
            'after reset the guard mirrors the live schema again'
        );
    }

    /**
     * A memoised answer belongs to one connection and does not leak to another.
     *
     * The guard is asked "does this schema have the capability", so keying the memo
     * on the capability alone would hand a second connection — a different database
     * or table prefix — an answer about the first one. That mistake fails OPEN: the
     * exclusion SQL would be emitted against a schema that has no such column. The
     * second connection here is a prefix that owns no tables at all, so a leaking
     * memo is the only way it could answer true.
     *
     * @return void
     */
    public function testSchemaGuardMemoIsScopedToItsConnection(): void
    {
        /** @var \Config\Database $database */
        $database = config('Database');

        $foreign = db_connect(array_merge($database->default, ['DBPrefix' => 'phpunit_absent_']), false);

        $this->assertTrue(SchemaGuard::hasPreferences(db_connect('default')), 'the shadow exposes the FAZ 2 table on this connection');
        $this->assertFalse(SchemaGuard::hasPreferences($foreign), 'another connection gets its own answer, not the memoised one');
    }

    /**
     * An excluded user loses the row from the feed while everyone else keeps it.
     *
     * @return void
     */
    public function testExcludedUserLosesTheRowFromTheFeed(): void
    {
        $row = $this->seed(['exclude_users' => ',' . self::USER . ',']);

        $this->assertNotContains($row, $this->feedIds(), 'the excluded user does not see the row');
        $this->assertContains($row, $this->feedIds(self::OTHER_USER), 'exclusion is per-user, not a global delete');
    }

    /**
     * An excluded user does not have the row counted in their unread badge.
     *
     * @return void
     */
    public function testExcludedUserLosesTheRowFromTheUnreadCount(): void
    {
        $this->seed(['exclude_users' => ',' . self::USER . ',']);

        $this->assertSame(0, $this->unreadFor(), 'the excluded row never reaches the badge');
        $this->assertSame(1, $this->unreadFor(self::OTHER_USER), 'the same row still counts for a non-excluded user');
    }

    /**
     * markRead refuses an excluded row, so the controller answers 404.
     *
     * @return void
     */
    public function testExcludedUserCannotMarkTheRowRead(): void
    {
        $row = $this->seed(['exclude_users' => ',' . self::USER . ',']);

        $this->assertFalse($this->notifier->markRead($row, self::USER), 'an excluded row is not relevant, so it cannot be read');
        $this->assertSame(
            0,
            $this->shadowDb->table('notification_reads')->where('notification_id', $row)->countAllResults(),
            'no read-state row is written for a refused mark'
        );
        $this->assertTrue($this->notifier->markRead($row, self::OTHER_USER), 'a non-excluded user can still read it');
    }

    /**
     * Excluding user 15 does not also exclude user 5 (sentinel prefix collision).
     *
     * Without the wrapping commas the stored '15' would satisfy a naive '%5%' match
     * and silently hide the row from the wrong person. This is the SQL-level proof of
     * {@see NotificationTargetingTest::testSentinelEncodingPreventsPrefixCollisionBetweenIds()}.
     *
     * @return void
     */
    public function testSentinelExclusionDoesNotHideRowFromPrefixCollidingUser(): void
    {
        $row = $this->seed(['exclude_users' => ',' . self::EXCLUDED_USER . ',']);

        $this->assertContains($row, $this->feedIds(self::COLLIDING_USER), 'user 5 must not be caught by an exclusion of 15');
        $this->assertNotContains($row, $this->feedIds(self::EXCLUDED_USER), 'user 15 is genuinely excluded');
    }

    /**
     * A user targeted directly and through a group is delivered the event exactly once.
     *
     * Drives the real builder so the send-side narrowing and the read-side SQL are
     * proven to agree: two rows are stored, only one of them is relevant to the user.
     *
     * @return void
     */
    public function testOverlappingUserAndGroupDispatchIsDeliveredExactlyOnce(): void
    {
        $this->notifier->notify(self::TYPE_SUBTYPE)->title('Overlap')
            ->toUser(self::USER)->toGroup(self::GROUP)->via('inapp')->dispatch();

        $this->assertSame(2, $this->shadowDb->table('notifications')->countAllResults(), 'one global row per target, no fan-out');

        $groupRow = $this->shadowDb->table('notifications')->where('target_type', 'group')->get()->getRow();
        $this->assertNotNull($groupRow, 'the group row was written');
        $this->assertSame(',' . self::USER . ',', $groupRow->exclude_users, 'the group row drops the already-covered user');

        $this->assertCount(1, $this->feedIds(), 'the overlapped user sees the event once');
        $this->assertSame(1, $this->unreadFor(), 'and it is counted once');
        $this->assertSame(
            [(int) $groupRow->id],
            $this->feedIds(self::OTHER_USER),
            'a group-only member still receives the group row the narrowing touched'
        );
    }

    /**
     * Targeting the same user twice in one dispatch stores a single row.
     *
     * @return void
     */
    public function testRepeatedUserTargetDispatchWritesOneRow(): void
    {
        $this->notifier->notify(self::TYPE_SUBTYPE)->title('Repeat')
            ->toUser(self::USER)->toUser(self::USER)->via('inapp')->dispatch();

        $this->assertSame(1, $this->shadowDb->table('notifications')->countAllResults());
        $this->assertCount(1, $this->feedIds());
    }

    /**
     * A member of two targeted groups sees the event twice — the documented limit.
     *
     * Narrowing a group-to-group overlap would require materialising full group
     * membership at send time, which is exactly the fan-out Model B avoids. This
     * test pins the accepted trade-off so any future change to it is deliberate.
     *
     * @return void
     */
    public function testGroupToGroupOverlapIsDeliveredTwiceByDesign(): void
    {
        $this->notifier->notify(self::TYPE_SUBTYPE)->title('Two groups')
            ->toGroup(self::GROUP)->toGroup(self::OTHER_GROUP)->via('inapp')->dispatch();

        $this->assertSame(
            0,
            $this->shadowDb->table('notifications')->where('exclude_users IS NOT NULL', null, false)->countAllResults(),
            'neither group row narrows the other'
        );
        $this->assertCount(2, $this->feedIds(), 'a member of both groups sees the event twice');
        $this->assertCount(1, $this->feedIds(self::OTHER_USER), 'a member of one group still sees it once');
    }

    /**
     * exceptUser() removes the recipient from a dispatch end to end.
     *
     * @return void
     */
    public function testExceptUserDispatchHidesTheRowFromTheExcludedRecipient(): void
    {
        $this->notifier->notify(self::TYPE_SUBTYPE)->title('Except')
            ->toGroup(self::GROUP)->exceptUser(self::USER)->via('inapp')->dispatch();

        $stored = $this->shadowDb->table('notifications')->get()->getRow();
        $this->assertNotNull($stored, 'the group row was written');
        $row = (int) $stored->id;

        $this->assertSame([], $this->feedIds(), 'the excluded member is skipped');
        $this->assertFalse($this->notifier->markRead($row, self::USER), 'and cannot reach the row directly either');
        $this->assertSame([$row], $this->feedIds(self::OTHER_USER), 'the rest of the group is unaffected');
    }

    /**
     * A muted type disappears from both the feed and the unread badge.
     *
     * @return void
     */
    public function testMutedTypeDisappearsFromFeedAndCount(): void
    {
        $this->seed();
        $this->mute(self::USER);

        $this->assertSame([], $this->feedIds(), 'the muted type is filtered out of the feed');
        $this->assertSame(0, $this->unreadFor(), 'and out of the badge');
    }

    /**
     * markRead refuses a muted row, so the controller answers 404.
     *
     * @return void
     */
    public function testMutedRowCannotBeMarkedRead(): void
    {
        $row = $this->seed();
        $this->mute(self::USER);

        $this->assertFalse($this->notifier->markRead($row, self::USER));
    }

    /**
     * A type prefix silences dotted subtypes but not a merely similar-looking type.
     *
     * 'audit' must cover 'audit' and 'audit.login' while leaving 'auditor.report'
     * alone: the JOIN matches on an exact type or on a '{prefix}.' segment boundary,
     * never on a bare string prefix.
     *
     * @return void
     */
    public function testMutePrefixCoversDottedSubtypesButNotSimilarTypes(): void
    {
        $exact   = $this->seed(['type' => self::TYPE_PREFIX]);
        $subtype = $this->seed(['type' => self::TYPE_SUBTYPE]);
        $sibling = $this->seed(['type' => self::TYPE_SIBLING]);

        $this->mute(self::USER);

        $visible = $this->feedIds();

        $this->assertNotContains($exact, $visible, 'the exact type is muted');
        $this->assertNotContains($subtype, $visible, 'a dotted subtype is muted by the prefix');
        $this->assertContains($sibling, $visible, 'a type that only starts with the same letters is NOT muted');
        $this->assertSame(1, $this->unreadFor(), 'only the sibling type is left to count');
    }

    /**
     * One user's mute never affects another user's feed.
     *
     * @return void
     */
    public function testMuteIsScopedToItsOwnUser(): void
    {
        $row = $this->seed();
        $this->mute(self::USER);

        $this->assertSame([], $this->feedIds());
        $this->assertSame([$row], $this->feedIds(self::OTHER_USER));
    }

    /**
     * A preference row with enabled = 1 is informational and filters nothing.
     *
     * @return void
     */
    public function testEnabledPreferenceRowDoesNotFilter(): void
    {
        $row = $this->seed();
        $this->mute(self::USER, self::TYPE_PREFIX, '*', 1);

        $this->assertSame([$row], $this->feedIds(), 'only enabled = 0 rows mute anything');
        $this->assertSame(1, $this->unreadFor());
    }

    /**
     * A channel-scoped mute filters only rows delivered on that channel.
     *
     * @return void
     */
    public function testChannelScopedMuteOnlyFiltersItsOwnChannel(): void
    {
        $row = $this->seed(['channel' => 'inapp']);
        $this->mute(self::USER, self::TYPE_PREFIX, 'email');

        $this->assertSame([$row], $this->feedIds(), 'a mute for another channel leaves the row visible');

        $this->mute(self::USER, self::TYPE_PREFIX, 'inapp');

        $this->assertSame([], $this->feedIds(), 'a mute matching the row channel hides it');
    }

    /**
     * A critical row is delivered even when a matching mute exists.
     *
     * @return void
     */
    public function testCriticalRowSurvivesAMatchingMute(): void
    {
        $critical = $this->seed(['severity' => 'critical']);
        $info     = $this->seed(['severity' => 'info']);
        $this->mute(self::USER);

        $this->assertSame([$critical], $this->feedIds(), 'critical cannot be muted, info can');
        $this->assertNotContains($info, $this->feedIds());
        $this->assertSame(1, $this->unreadFor());
    }

    /**
     * Two overlapping mutes neither duplicate a critical row nor inflate its count.
     *
     * The `n.severity <> 'critical'` condition sits on the JOIN's ON side, so a
     * critical row never joins at all and its match count is zero regardless of how
     * many preference rows would otherwise apply. Were that condition moved into the
     * WHERE clause the row would join twice, appear twice in the feed and be counted
     * twice by countAllResults(); this test is what would catch that move.
     *
     * @return void
     */
    public function testCriticalRowAppearsOnceDespiteTwoOverlappingMutes(): void
    {
        $critical = $this->seed(['type' => self::TYPE_SUBTYPE, 'severity' => 'critical']);

        $this->mute(self::USER, self::TYPE_PREFIX);
        $this->mute(self::USER, self::TYPE_SUBTYPE);

        $this->assertSame([$critical], $this->feedIds(), 'two matching mutes still yield exactly one feed row');
        $this->assertSame(1, $this->unreadFor(), 'and exactly one unread, not an inflated count');
    }

    /**
     * Two overlapping mutes hide a non-critical row instead of duplicating it.
     *
     * @return void
     */
    public function testNonCriticalRowWithTwoOverlappingMutesIsHiddenNotDuplicated(): void
    {
        $this->seed(['type' => self::TYPE_SUBTYPE, 'severity' => 'info']);

        $this->mute(self::USER, self::TYPE_PREFIX);
        $this->mute(self::USER, self::TYPE_SUBTYPE);

        $this->assertSame([], $this->feedIds());
        $this->assertSame(0, $this->unreadFor());
    }

    /**
     * markAllRead honours both filters: excluded and muted rows are never marked.
     *
     * Covers the fourth read path through applyRelevance, which would otherwise be
     * able to write read-state for rows the user is not allowed to see.
     *
     * @return void
     */
    public function testMarkAllReadSkipsExcludedAndMutedRows(): void
    {
        $excluded = $this->seed(['exclude_users' => ',' . self::USER . ',']);
        $muted    = $this->seed(['type' => self::TYPE_SUBTYPE]);
        $visible  = $this->seed(['type' => self::TYPE_SIBLING]);

        $this->mute(self::USER);

        $this->assertSame(1, $this->notifier->markAllRead(self::USER), 'only the one relevant row is marked');

        $marked = array_map(
            static fn ($row): int => (int) $row->notification_id,
            $this->shadowDb->table('notification_reads')->where('user_id', self::USER)->get()->getResult()
        );

        $this->assertSame([$visible], $marked);
        $this->assertNotContains($excluded, $marked, 'an excluded row is never marked read');
        $this->assertNotContains($muted, $marked, 'a muted row is never marked read');
    }
}

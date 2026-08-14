<?php

namespace Tests\Modules\Notifications;

use CodeIgniter\Config\Services;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\Mock\MockCache;
use Modules\Notifications\Libraries\Notifier;
use Redis;
use Tests\Support\Notifications\FakeCommonModel;

/**
 * Notifier Model B integration tests (global rows + per-user read-state).
 *
 * These exercise the real query-builder path against the live default schema
 * (the notifications/notification_reads tables the migrations already created),
 * because Model B relevance is expressed as a LEFT JOIN that an in-memory double
 * cannot reproduce. The suite is strictly NON-DESTRUCTIVE: every row it writes
 * carries a per-run marker type or a throwaway high-id user, and tearDown deletes
 * exactly those rows (its notifications by marker, its reads and group membership
 * by user id, then the user). Pre-existing rows are never mutated, and counts are
 * asserted as deltas so unrelated broadcasts in the dev data cannot skew them.
 *
 * The table-missing guard (f) is the one branch that must stay off the real
 * schema, so it is proven by injecting a FakeCommonModel whose fake connection
 * reports the tables absent — no table is ever dropped.
 *
 * @internal
 */
final class NotifierTest extends CIUnitTestCase
{
    /** A user id that never exists (foreign 'user' target for the isolation checks). */
    private const FOREIGN_USER = '987654';

    /** A group the test user is never a member of. */
    private const FOREIGN_GROUP = 'phpunit_notmygroup';

    private BaseConnection $connection;

    private MockCache $cache;

    private Notifier $notifier;

    /** Per-run marker written into notifications.type so cleanup is exact. */
    private string $marker;

    /** Throwaway user id created for this test (satisfies the read/group FKs). */
    private int $userId = 0;

    /** Group the throwaway user is made a member of. */
    private string $group;

    /**
     * Creates an isolated user + group, a fresh marker and an injected MockCache.
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

        // The Notifier reaches the DB through CommonModel, which hardcodes the
        // 'default' group, so it runs against the live MariaDB even under the
        // testing environment. Seed/verify on that same connection, never the
        // empty in-memory 'tests' SQLite the framework defaults to here.
        $this->connection = db_connect('default');

        $this->cache = new MockCache();
        Services::injectMock('cache', $this->cache);

        $suffix       = bin2hex(random_bytes(6));
        $this->marker = 'phpunit.modelb.' . $suffix;
        $this->group  = 'phpunit_grp_' . $suffix;

        $this->connection->table('users')->insert([
            'username'   => 'phpunit_' . $suffix,
            'active'     => 1,
            'firstname'  => 'PHPUnit',
            'surname'    => 'ModelB',
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        $this->userId = (int) $this->connection->insertID();

        $this->connection->table('auth_groups_users')->insert([
            'user_id'    => $this->userId,
            'group'      => $this->group,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $this->notifier = new Notifier();
    }

    /**
     * Removes only this run's rows: reads/group by user, notifications by marker, the user.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        if ($this->userId > 0) {
            $this->connection->table('notification_reads')->where('user_id', $this->userId)->delete();
            $this->connection->table('notifications')->where('type', $this->marker)->delete();
            $this->connection->table('auth_groups_users')->where('user_id', $this->userId)->delete();
            $this->connection->table('users')->where('id', $this->userId)->delete();
        }

        $this->dropOwnSignalKey();

        parent::tearDown();
    }

    /**
     * Deletes the one Redis signal key a real toRole() dispatch may have written.
     *
     * toRole() runs the full channel pipeline, so with realtimeEnabled on (it is, in
     * .env) RealtimeChannel bumps `notif:sig:group/{thisRunsGroup}` on the live Redis.
     * The key carries a ttl and would expire on its own, but leaving one behind per run
     * still litters a shared dev server — so this run drops the single key it minted.
     * Strictly scoped: only the per-run group name, never a pattern or a flush.
     *
     * @return void
     */
    private function dropOwnSignalKey(): void
    {
        if (! extension_loaded('redis')) {
            return;
        }

        /** @var \Config\Cache $cache */
        $cache = config('Cache');
        $conf  = $cache->redis;
        $redis = new Redis();

        try {
            if (@$redis->connect((string) ($conf['host'] ?? '127.0.0.1'), (int) ($conf['port'] ?? 6379), 1.0) === false) {
                return;
            }

            if (! empty($conf['password'])) {
                $redis->auth($conf['password']);
            }

            if (! empty($conf['database'])) {
                $redis->select((int) $conf['database']);
            }

            $redis->del('notif:sig:group/' . $this->group);
            $redis->close();
        } catch (\Throwable $e) {
            // Redis-less boxes never created the key in the first place.
        }
    }

    /**
     * Inserts a marker-tagged, unread notification and returns its id.
     *
     * @param string      $targetType  'broadcast' | 'user' | 'group'.
     * @param string|null $targetValue Recipient id / group name (null for broadcast).
     * @param string      $title       Visible title.
     *
     * @return int The new notification id.
     */
    private function seed(string $targetType, ?string $targetValue, string $title = 'PHPUnit notification'): int
    {
        $this->connection->table('notifications')->insert([
            'user_id'      => null,
            'type'         => $this->marker,
            'severity'     => 'info',
            'target_type'  => $targetType,
            'target_value' => $targetValue,
            'title'        => $title,
            'body'         => null,
            'url'          => null,
            'channel'      => 'inapp',
            'read_at'      => null,
            'created_at'   => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->connection->insertID();
    }

    /**
     * Counts read-state rows for a notification/user pair (idempotency probe).
     *
     * @param int $notificationId Notification id.
     *
     * @return int Number of read rows.
     */
    private function readCount(int $notificationId): int
    {
        return $this->connection->table('notification_reads')
            ->where('notification_id', $notificationId)
            ->where('user_id', $this->userId)
            ->countAllResults();
    }

    /**
     * Drops the cached unread badge so the next read re-queries the database.
     *
     * @return void
     */
    private function clearUnreadCache(): void
    {
        cache()->delete(Notifier::cacheKey($this->userId));
    }

    /**
     * Collects the notification ids returned by listFor for the test user.
     *
     * @param int $limit Row cap handed to listFor.
     *
     * @return list<int> Notification ids in the feed.
     */
    private function feedIds(int $limit = 200): array
    {
        return array_values(array_map(
            static fn (\stdClass $row): int => (int) $row->id,
            $this->notifier->listFor($this->userId, $limit)
        ));
    }

    /**
     * The notifier service binds to a real Notifier instance (service regression).
     *
     * @return void
     */
    public function testServiceResolvesNotifierInstance(): void
    {
        $this->assertInstanceOf(Notifier::class, service('notifier'));
    }

    /**
     * The feed shows broadcast, own user-target and own group-target rows.
     *
     * @return void
     */
    public function testUserSeesBroadcastUserAndGroupTargets(): void
    {
        $broadcast = $this->seed('broadcast', null);
        $userRow   = $this->seed('user', (string) $this->userId);
        $groupRow  = $this->seed('group', $this->group);

        $ids = $this->feedIds();

        $this->assertContains($broadcast, $ids, 'a broadcast is visible to everyone');
        $this->assertContains($userRow, $ids, 'the user sees a row targeted at their own id');
        $this->assertContains($groupRow, $ids, 'the user sees a row targeted at a group they belong to');
    }

    /**
     * The feed hides a foreign user-target and a non-member group-target.
     *
     * @return void
     */
    public function testUserDoesNotSeeForeignUserOrGroupTargets(): void
    {
        $foreignUser  = $this->seed('user', self::FOREIGN_USER);
        $foreignGroup = $this->seed('group', self::FOREIGN_GROUP);

        $ids = $this->feedIds();

        $this->assertNotContains($foreignUser, $ids, "another user's target must be invisible");
        $this->assertNotContains($foreignGroup, $ids, 'a non-member group target must be invisible');
    }

    /**
     * markRead refuses an irrelevant target and a missing id (controller -> 404).
     *
     * @return void
     */
    public function testMarkReadRejectsIrrelevantAndMissing(): void
    {
        $foreignUser = $this->seed('user', self::FOREIGN_USER);

        $this->assertFalse($this->notifier->markRead($foreignUser, $this->userId), 'cannot read a notification not addressed to you');
        $this->assertSame(0, $this->readCount($foreignUser), 'no read row is written for an irrelevant notification');
        $this->assertFalse($this->notifier->markRead(2147483647, $this->userId), 'a missing notification id is rejected');
    }

    /**
     * unreadCount counts relevant unread rows via the LEFT JOIN, ignoring foreign targets.
     *
     * @return void
     */
    public function testUnreadCountUsesLeftJoinAndRelevance(): void
    {
        $this->clearUnreadCache();
        $base = $this->notifier->unreadCount($this->userId);

        $this->seed('broadcast', null);
        $this->seed('user', (string) $this->userId);
        $this->seed('group', $this->group);

        $this->clearUnreadCache();
        $this->assertSame($base + 3, $this->notifier->unreadCount($this->userId), 'each relevant unread row adds exactly one');

        $this->seed('user', self::FOREIGN_USER);
        $this->seed('group', self::FOREIGN_GROUP);

        $this->clearUnreadCache();
        $this->assertSame($base + 3, $this->notifier->unreadCount($this->userId), 'foreign targets never enter the count');
    }

    /**
     * The unread count is cached for the polling window and not re-queried in it.
     *
     * @return void
     */
    public function testUnreadCountIsCachedWithinTheWindow(): void
    {
        $this->seed('user', (string) $this->userId);

        $this->clearUnreadCache();
        $first = $this->notifier->unreadCount($this->userId);
        $this->assertSame($first, $this->cache->get(Notifier::cacheKey($this->userId)), 'the first read persists the count');

        $this->seed('user', (string) $this->userId);
        $this->assertSame($first, $this->notifier->unreadCount($this->userId), 'a second read serves the cached value, not the changed data');
    }

    /**
     * markRead is idempotent, invalidates the cache and decrements the count by one.
     *
     * @return void
     */
    public function testMarkReadIsIdempotentAndInvalidatesCache(): void
    {
        $row = $this->seed('user', (string) $this->userId);

        $this->clearUnreadCache();
        $before = $this->notifier->unreadCount($this->userId);

        cache()->save(Notifier::cacheKey($this->userId), 999, 60);

        $this->assertTrue($this->notifier->markRead($row, $this->userId));
        $this->assertNull(cache()->get(Notifier::cacheKey($this->userId)), 'a successful read invalidates the unread cache');
        $this->assertSame(1, $this->readCount($row), 'exactly one read row is written');

        $this->assertTrue($this->notifier->markRead($row, $this->userId), 'a repeat mark stays truthy');
        $this->assertSame(1, $this->readCount($row), 'a repeat mark does not duplicate the read row');

        $this->clearUnreadCache();
        $this->assertSame($before - 1, $this->notifier->unreadCount($this->userId), 'the read row removes exactly one from the count');
    }

    /**
     * markAllRead marks every relevant unread row once, then reports nothing left.
     *
     * @return void
     */
    public function testMarkAllReadMarksRelevantUnreadThenReturnsZero(): void
    {
        $this->seed('broadcast', null);
        $this->seed('user', (string) $this->userId);
        $this->seed('group', $this->group);

        $this->clearUnreadCache();
        $expected = $this->notifier->unreadCount($this->userId);
        $this->assertGreaterThanOrEqual(3, $expected, 'the three seeded rows are at least part of the unread set');

        $this->assertSame($expected, $this->notifier->markAllRead($this->userId), 'the batch marks exactly the relevant unread set');
        $this->assertSame(0, $this->notifier->markAllRead($this->userId), 'a second batch finds nothing left to mark');

        $this->clearUnreadCache();
        $this->assertSame(0, $this->notifier->unreadCount($this->userId), 'no relevant unread rows remain');
    }

    /**
     * Every read method returns its empty default when the tables are not migrated.
     *
     * @return void
     */
    public function testGuardsReturnDefaultsWhenTablesMissing(): void
    {
        $model = new FakeCommonModel();
        $model->setTableExists(false);

        $guarded = new Notifier();
        $this->setPrivateProperty($guarded, 'model', $model);

        $this->assertSame(0, $guarded->unreadCount($this->userId), 'unreadCount is 0 without tables');
        $this->assertSame([], $guarded->listFor($this->userId, 10), 'listFor is empty without tables');
        $this->assertFalse($guarded->markRead(1, $this->userId), 'markRead is false without tables');
        $this->assertSame(0, $guarded->markAllRead($this->userId), 'markAllRead is 0 without tables');
    }

    /**
     * toRole writes a single global group row and returns 1, never a member count.
     *
     * @return void
     */
    public function testToRoleReturnsOneWhenRowCreated(): void
    {
        $result = $this->notifier->toRole($this->group, $this->marker, 'Grup bildirimi');

        $this->assertSame(1, $result, 'Model B toRole reports one created row, not the member count');

        $written = $this->connection->table('notifications')
            ->where('type', $this->marker)
            ->where('target_type', 'group')
            ->where('target_value', $this->group)
            ->countAllResults();
        $this->assertSame(1, $written, 'exactly one global group row is written (no fan-out)');
    }

    /**
     * toRole returns 0 when no channel delivers the row (no fan-out, no write).
     *
     * @return void
     */
    public function testToRoleReturnsZeroWhenNoChannelDelivers(): void
    {
        $notifier = new class extends Notifier {
            /**
             * Simulates a build with no available delivery channels.
             *
             * @return array<string, \Modules\Notifications\Libraries\Channels\ChannelInterface>
             */
            public function resolveChannels(): array
            {
                return [];
            }
        };

        $this->assertSame(0, $notifier->toRole($this->group, $this->marker, 'Grup bildirimi'));

        $written = $this->connection->table('notifications')->where('type', $this->marker)->countAllResults();
        $this->assertSame(0, $written, 'no row is written when no channel delivers');
    }
}

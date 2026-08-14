<?php

namespace Tests\Modules\Notifications;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Test\CIUnitTestCase;
use Modules\Notifications\Config\NotificationsConfig;
use Modules\Notifications\Libraries\Channels\RealtimeChannel;
use Modules\Notifications\Libraries\NotificationBuilder;
use Modules\Notifications\Libraries\NotificationMessage;
use Modules\Notifications\Libraries\Notifier;
use Modules\Notifications\Libraries\RealtimeSignal;
use Redis;
use Tests\Support\Notifications\ChannelCallLog;
use Tests\Support\Notifications\FakeSignalStore;
use Tests\Support\Notifications\RecordingChannel;

/**
 * Realtime channel + signal-path tests (Redis-backed SSE nudge side).
 *
 * The realtime transport was rearchitected from Mercure/HTTP to a Redis signal
 * store: RealtimeChannel::send() now bumps `notif:sig:{channel}` via a
 * SignalStoreInterface instead of POSTing to a hub. RealtimeChannel now depends on
 * that interface, so the channel-behavior tests inject a deterministic
 * FakeSignalStore and run WITHOUT a live Redis: a produced message bumps exactly
 * the topicFor()-authorized channel (AC-1), the disabled and signal-failed branches
 * degrade to ChannelResult::skipped with no exception and no persistence
 * (AC-6/AC-7), dispatch drives inapp before realtime (AC-8), and realtime stays off
 * by default (AC-11).
 *
 * ONE integration test (testSignalBumpIncrementsLiveRedisCounter) exercises the REAL
 * RealtimeSignal against the REAL local Redis (phpredis) to prove bump()->read()
 * increments a live counter; it self-skips gracefully via requireRedis() when Redis
 * is unreachable, so `composer test` never ERRORs on a Redis-less box.
 *
 * NON-DESTRUCTIVE: every live signal key uses a per-run `phpunit-*` suffix and is
 * deleted in tearDown; production `notif:sig:*` keys are never read or written.
 * The only DB touch is AC-7, which asserts the realtime channel writes nothing
 * by counting a per-run marker (0 before, 0 after); tearDown drops that marker.
 *
 * @internal
 */
final class RealtimeChannelTest extends CIUnitTestCase
{
    private BaseConnection $connection;

    /** Live phpredis connection, opened lazily by requireRedis(); null until then. */
    private ?Redis $redis = null;

    /** Per-run marker written into notifications.type for the AC-7 delta probe. */
    private string $marker = '';

    /** Per-run suffix keeping every test channel off any production key. */
    private string $suffix = '';

    /**
     * Signal keys (full `notif:sig:*` names) created by this run, for cleanup.
     *
     * @var list<string>
     */
    private array $keysToClean = [];

    /**
     * Opens the live DB connection and mints a fresh run marker/suffix.
     *
     * Redis is NOT connected here; only the single live-Redis integration test
     * opens it (via requireRedis()) so the fake-backed tests run Redis-less.
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

        $this->connection = db_connect('default');
        $this->suffix     = bin2hex(random_bytes(6));
        $this->marker     = 'phpunit.rt.' . $this->suffix;
    }

    /**
     * Deletes this run's signal keys (if Redis was opened) and marker rows.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        if ($this->redis instanceof Redis) {
            foreach ($this->keysToClean as $key) {
                $this->redis->del($key);
            }
            $this->redis->close();
        }

        $this->connection->table('notifications')->where('type', $this->marker)->delete();

        parent::tearDown();
    }

    /**
     * Opens the live Redis connection for the integration test, or skips gracefully.
     *
     * Keeps `composer test` from ERRORing on a box without Redis: the fake-backed
     * behavior tests never call this, so they still run.
     *
     * @return Redis The live phpredis connection.
     */
    private function requireRedis(): Redis
    {
        if (! extension_loaded('redis')) {
            $this->markTestSkipped('The phpredis extension is not loaded; skipping the real-Redis signal integration test.');
        }

        /** @var \Config\Cache $cache */
        $cache = config('Cache');
        $conf  = $cache->redis;
        $redis = new Redis();

        try {
            $connected = @$redis->connect((string) ($conf['host'] ?? '127.0.0.1'), (int) ($conf['port'] ?? 6379), 1.0);
        } catch (\Throwable $e) {
            $connected = false;
        }

        if ($connected === false) {
            $this->markTestSkipped('Live Redis is unavailable; skipping the real-Redis signal integration test.');
        }

        if (! empty($conf['database'])) {
            $redis->select((int) $conf['database']);
        }

        $this->redis = $redis;

        return $redis;
    }

    /**
     * Builds a config with realtime enabled (Redis signal ttl kept short).
     *
     * @return NotificationsConfig Config that lets send() reach the signal store.
     */
    private function enabledConfig(): NotificationsConfig
    {
        $config                    = new NotificationsConfig();
        $config->realtimeEnabled   = true;
        $config->realtimeSignalTtl = 60;

        return $config;
    }

    /**
     * Builds a single-user message aimed at a per-run, non-production user target.
     *
     * @param string $type Event type (also used as the AC-7 marker when needed).
     *
     * @return NotificationMessage A clean, single-target message.
     */
    private function userMessage(string $type = 'comment.new'): NotificationMessage
    {
        return new NotificationMessage($type, 'info', 'Yeni yorum', 'Gövde', '/backend/blog/comments', 'user', $this->targetValue(), ['realtime']);
    }

    /**
     * The per-run target user value (a string, never a real user id).
     *
     * @return string
     */
    private function targetValue(): string
    {
        return 'phpunit-' . $this->suffix;
    }

    /**
     * Registers a channel's full signal key for tearDown cleanup and returns it.
     *
     * @param string $channel Channel name (Notifier::topicFor output).
     *
     * @return string The full `notif:sig:{channel}` key.
     */
    private function trackKey(string $channel): string
    {
        $key                 = 'notif:sig:' . $channel;
        $this->keysToClean[] = $key;

        return $key;
    }

    /**
     * AC-1: a produced message bumps exactly the topicFor()-authorized channel.
     *
     * @return void
     */
    public function testSendBumpsExactlyTheAuthorizedChannel(): void
    {
        $channel = Notifier::topicFor('user', $this->targetValue());
        $fake    = new FakeSignalStore();

        $result = (new RealtimeChannel($this->enabledConfig(), $fake))->send($this->userMessage());

        $this->assertTrue($result->ok, 'a successful bump yields an ok delivery');
        $this->assertSame(['topic' => $channel], $result->meta, 'result meta carries exactly the bumped topic');
        $this->assertSame([$channel], $fake->bumps, 'exactly one bump landed on the topicFor()-authorized channel');
    }

    /**
     * AC-6a: realtime disabled short-circuits to skipped and never bumps the store.
     *
     * @return void
     */
    public function testDisabledRealtimeSkipsAndNeverBumps(): void
    {
        $config                  = new NotificationsConfig();
        $config->realtimeEnabled = false;

        $fake   = new FakeSignalStore();
        $result = (new RealtimeChannel($config, $fake))->send($this->userMessage());

        $this->assertFalse($result->ok, 'a disabled channel never reports a delivery');
        $this->assertSame('realtime-disabled', $result->meta['reason']);
        $this->assertSame([], $fake->bumps, 'no bump is ever attempted when realtime is disabled');
    }

    /**
     * AC-6b: a failed signal bump degrades to skipped('signal-failed') without throwing.
     *
     * @return void
     */
    public function testRedisDownReturnsSkippedWithoutThrowing(): void
    {
        $fake           = new FakeSignalStore();
        $fake->failNext = true;

        // Reaching the assertions at all proves no exception leaked out of send().
        $result = (new RealtimeChannel($this->enabledConfig(), $fake))->send($this->userMessage());

        $this->assertFalse($result->ok, 'a failed signal degrades silently');
        $this->assertSame('signal-failed', $result->meta['reason']);
        $this->assertSame(Notifier::topicFor('user', $this->targetValue()), $result->meta['topic'], 'the topic is still reported on failure');
    }

    /**
     * AC-7: the realtime channel is a nudge only — it writes no notifications row.
     *
     * @return void
     */
    public function testRealtimeChannelDoesNotPersistNotification(): void
    {
        $before = $this->markerCount();

        $fake = new FakeSignalStore();
        (new RealtimeChannel($this->enabledConfig(), $fake))->send($this->userMessage($this->marker));

        $this->assertSame($before, $this->markerCount(), 'realtime emit must not change the feed row count');
        $this->assertSame(0, $this->markerCount(), 'the DB source-of-truth stays with InAppChannel, not realtime');
    }

    /**
     * AC (real Redis): RealtimeSignal.bump() increments the counter read() reports.
     *
     * @return void
     */
    public function testSignalBumpIncrementsLiveRedisCounter(): void
    {
        $this->requireRedis();

        $channel = 'phpunit-' . $this->suffix;
        $this->trackKey($channel);

        $signal = new RealtimeSignal($this->enabledConfig());

        $before = $signal->read([$channel])[$channel];
        $this->assertSame(0, $before, 'a fresh channel reads a baseline of 0');

        $this->assertTrue($signal->bump($channel), 'bump reports success against the live Redis');
        $this->assertTrue($signal->bump($channel), 'a second bump also succeeds');

        $after = $signal->read([$channel])[$channel];
        $this->assertSame($before + 2, $after, 'each bump increments the counter read() returns');
    }

    /**
     * AC-8: the builder's default channel order is inapp then realtime.
     *
     * @return void
     */
    public function testBuilderDefaultChannelOrderIsInappThenRealtime(): void
    {
        $default = (new \ReflectionProperty(NotificationBuilder::class, 'channels'))->getDefaultValue();

        $this->assertSame(['inapp', 'realtime'], $default, 'inapp (DB commit) is declared before realtime (emit)');
    }

    /**
     * AC-8: dispatch invokes inapp before realtime so the row is committed first.
     *
     * @return void
     */
    public function testDispatchInvokesInappBeforeRealtime(): void
    {
        $log = new ChannelCallLog();

        $notifier = new class ($log) extends Notifier {
            private ChannelCallLog $callLog;

            /**
             * @param ChannelCallLog $log Shared ledger recording channel order.
             */
            public function __construct(ChannelCallLog $log)
            {
                parent::__construct();
                $this->callLog = $log;
            }

            /**
             * Returns order-recording stand-ins for the default channel slugs.
             *
             * @return array<string, \Modules\Notifications\Libraries\Channels\ChannelInterface>
             */
            public function resolveChannels(): array
            {
                return [
                    'inapp'    => new RecordingChannel('inapp', $this->callLog),
                    'realtime' => new RecordingChannel('realtime', $this->callLog),
                ];
            }
        };

        $notifier->notify('phpunit.order')->title('Sıra testi')->broadcast()->dispatch();

        $this->assertSame(['inapp', 'realtime'], $log->slugs, 'inapp commit runs before the realtime emit');
    }

    /**
     * AC-11: realtime is opt-in — the config default is false (behavior unchanged).
     *
     * @return void
     */
    public function testRealtimeDisabledByConfigDefault(): void
    {
        $default = (new \ReflectionProperty(NotificationsConfig::class, 'realtimeEnabled'))->getDefaultValue();

        $this->assertFalse($default, 'default keeps the bell on 60s polling, identical to the pre-realtime behavior');
    }

    /**
     * Counts notifications rows carrying this run's marker.
     *
     * @return int Number of marker-tagged rows (expected 0 for the realtime path).
     */
    private function markerCount(): int
    {
        return $this->connection->table('notifications')->where('type', $this->marker)->countAllResults();
    }
}

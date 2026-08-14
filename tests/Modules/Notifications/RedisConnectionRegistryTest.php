<?php

namespace Tests\Modules\Notifications;

use CodeIgniter\Test\CIUnitTestCase;
use Modules\Notifications\Libraries\RedisConnectionRegistry;
use Redis;
use RedisException;
use ReflectionClass;
use Tests\Support\Notifications\SpyRedis;

/**
 * RedisConnectionRegistry integration tests — the real ZSET slot bookkeeping.
 *
 * The controller-side cap policy runs against a double ({@see RealtimeConnectionCapTest});
 * this class exercises the only production implementation against a LIVE Redis, because
 * the properties that matter are Redis semantics: a full `notif:conn:{userId}` ZSET must
 * deny, a released slot must come back, expired members must be pruned on the next
 * acquire (no counter leak — the reason a ZSET replaced an INCR/DECR counter), and the
 * key must carry an EXPIRE as a second safety net.
 *
 * The Redis-backed tests self-skip via requireRedis() when no server answers, so
 * `composer test` never fails on a Redis-less box. The two fail-closed tests need no
 * server at all: they flip the private connectionFailed flag by reflection, which is
 * exactly the state a refused connect() leaves behind.
 *
 * NON-DESTRUCTIVE: every key belongs to a per-run pseudo user id far above any real
 * users.id, tearDown deletes exactly the keys this run created, and no FLUSHDB/FLUSHALL
 * is ever issued. Production `notif:conn:*` keys are never read or written.
 *
 * @internal
 */
final class RedisConnectionRegistryTest extends CIUnitTestCase
{
    /** Redis key prefix mirrored from the class under test. */
    private const KEY_PREFIX = 'notif:conn:';

    /** Slot lifetime used by the tests; must stay >= 1 (EXPIRE 0 deletes the key). */
    private const SLOT_TTL = 60;

    /** A deliberately shorter slot lifetime, used to prove EXPIRE never shortens the key. */
    private const SHORT_SLOT_TTL = 5;

    /**
     * Seconds of slack allowed when comparing a clock reading against a stored score.
     *
     * Covers the round-trip between the two readings plus a second boundary landing
     * between them; it stays far below SLOT_TTL, so a score written from the wrong clock
     * (or a timestamp smuggled through ARGV) still fails the comparison.
     */
    private const CLOCK_ARGV_DELTA = 2;

    /** How far ahead the "skewed peer" of the L-7 control runs (one day). */
    private const CLOCK_SKEW_SECONDS = 86400;

    /** Lowest pseudo user id used for test keys — far above any real users.id. */
    private const PSEUDO_USER_BASE = 900000000;

    /** Processes fired at the same key in the concurrency test. */
    private const CONCURRENT_WORKERS = 16;

    /** Cap the concurrent workers race for; grants must equal it EXACTLY. */
    private const CONCURRENT_CAP = 5;

    /** Seconds the concurrency test gives every worker to boot before the barrier opens. */
    private const CONCURRENT_BARRIER_SECONDS = 2.0;

    /** Live phpredis connection, opened lazily by requireRedis(); null until then. */
    private ?Redis $redis = null;

    /** Per-run pseudo user id whose ZSET key this run owns exclusively. */
    private int $userId = 0;

    /**
     * Mints the per-run pseudo user id; Redis is connected only where needed.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->userId = self::PSEUDO_USER_BASE + random_int(1, 99999999);
    }

    /**
     * Deletes this run's single connection key, if Redis was ever opened.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        if ($this->redis instanceof Redis) {
            $this->redis->del($this->key());
            $this->redis->close();
            $this->redis = null;
        }

        parent::tearDown();
    }

    /**
     * The full ZSET key this run owns.
     *
     * @return string `notif:conn:{pseudoUserId}`.
     */
    private function key(): string
    {
        return self::KEY_PREFIX . $this->userId;
    }

    /**
     * Builds the spy connection, or skips when phpredis is missing.
     *
     * SpyRedis extends the extension's own Redis class, so it cannot be
     * instantiated at all without phpredis — even though the spy never opens a
     * socket. Without this guard these tests fatal instead of skipping like
     * their live-Redis siblings.
     *
     * @return SpyRedis The spy standing in for a live connection.
     */
    private function requireSpy(): SpyRedis
    {
        if (! extension_loaded('redis')) {
            $this->markTestSkipped('The phpredis extension is not loaded; SpyRedis extends Redis and cannot be built.');
        }

        return new SpyRedis();
    }

    /**
     * Opens the live Redis connection for an integration test, or skips gracefully.
     *
     * Connection parameters come from config('Cache')->redis — the same source the
     * class under test reads — so the test inspects exactly the keys it writes.
     *
     * @return Redis The live phpredis connection.
     */
    private function requireRedis(): Redis
    {
        if (! extension_loaded('redis')) {
            $this->markTestSkipped('The phpredis extension is not loaded; skipping the live connection-registry tests.');
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
            $this->markTestSkipped('Live Redis is unavailable; skipping the live connection-registry tests.');
        }

        if (! empty($conf['password'])) {
            $redis->auth($conf['password']);
        }

        if (! empty($conf['database'])) {
            $redis->select((int) $conf['database']);
        }

        $this->redis = $redis;

        return $redis;
    }

    /**
     * Builds a registry whose lazy connection is already marked as failed.
     *
     * That private flag is what a refused connect() sets, so flipping it reproduces
     * the Redis-unreachable state without needing an unreachable server.
     *
     * @return RedisConnectionRegistry A registry that can never reach Redis.
     */
    private function unreachableRegistry(): RedisConnectionRegistry
    {
        $registry = new RedisConnectionRegistry();
        $this->setPrivateProperty($registry, 'connectionFailed', true);

        return $registry;
    }

    /**
     * Builds a registry whose lazy connection slot already holds a command spy.
     *
     * connection() short-circuits as soon as the property holds a Redis instance, so
     * nothing is ever dialled and the test observes the exact command traffic acquire()
     * would have put on the wire.
     *
     * @param SpyRedis $spy The double to plant in the connection slot.
     *
     * @return RedisConnectionRegistry A registry talking only to the spy.
     */
    private function spiedRegistry(SpyRedis $spy): RedisConnectionRegistry
    {
        $registry = new RedisConnectionRegistry();
        $this->setPrivateProperty($registry, 'redis', $spy);

        return $registry;
    }

    /**
     * The Lua source the registry ships, read straight off the class.
     *
     * @return string The ACQUIRE_SCRIPT constant.
     */
    private function acquireScript(): string
    {
        $script = (new ReflectionClass(RedisConnectionRegistry::class))->getConstant('ACQUIRE_SCRIPT');

        if (! is_string($script)) {
            $this->fail('RedisConnectionRegistry::ACQUIRE_SCRIPT is gone or is no longer a Lua string.');
        }

        return $script;
    }

    /**
     * Blocks until the wall clock has just rolled into a new second.
     *
     * Slot scores are whole seconds (`now + ttl`), so a test that acquires twice at the
     * ttl floor would read a different `now` — and therefore a different pruning
     * outcome — if the two calls straddled a second boundary. Starting right after a
     * boundary leaves an entire second of headroom and makes the outcome deterministic.
     *
     * @return void
     */
    private function alignToSecondBoundary(): void
    {
        $second = time();

        while (time() === $second) {
            usleep(2000);
        }
    }

    /**
     * Races N separate PHP processes for the same connection key and reports their answers.
     *
     * Real parallelism cannot be staged inside one PHPUnit process, so the workers are
     * spawned as plain CLI processes that all sleep until a shared wall-clock barrier and
     * then fire the very Lua script the registry ships. The workers talk raw phpredis, so
     * what is under test here is the SCRIPT's atomicity rather than the class wiring
     * (the class wiring is pinned separately by the single-round-trip spy test).
     *
     * @param string $key The connection ZSET key every worker competes for.
     * @param int    $cap Cap handed to each worker.
     * @param int    $ttl Slot lifetime handed to each worker.
     *
     * @return list<string> One raw answer per worker: `GRANTED:<id>`, `DENIED` or `ERROR:*`.
     */
    private function runConcurrentWorkers(string $key, int $cap, int $ttl): array
    {
        /** @var \Config\Cache $cache */
        $cache      = config('Cache');
        $scriptFile = (string) tempnam(sys_get_temp_dir(), 'ci4ms_lua_');
        $workerFile = (string) tempnam(sys_get_temp_dir(), 'ci4ms_worker_');

        file_put_contents($scriptFile, $this->acquireScript());
        file_put_contents($workerFile, $this->workerSource());

        $startAt   = microtime(true) + self::CONCURRENT_BARRIER_SECONDS;
        $processes = [];
        $pipes     = [];

        try {
            for ($i = 0; $i < self::CONCURRENT_WORKERS; $i++) {
                $command = sprintf(
                    '%s %s %s %s %s %s %s %s',
                    escapeshellarg(PHP_BINARY),
                    escapeshellarg($workerFile),
                    escapeshellarg($key),
                    escapeshellarg((string) $cap),
                    escapeshellarg((string) $ttl),
                    escapeshellarg((string) $startAt),
                    escapeshellarg($scriptFile),
                    escapeshellarg((string) json_encode($cache->redis))
                );

                $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $handles);

                if (is_resource($process)) {
                    $processes[$i] = $process;
                    $pipes[$i]     = $handles;
                }
            }

            $answers = [];

            foreach ($processes as $i => $process) {
                $answers[] = trim((string) stream_get_contents($pipes[$i][1]));
                fclose($pipes[$i][1]);
                fclose($pipes[$i][2]);
                proc_close($process);
            }

            return $answers;
        } finally {
            @unlink($scriptFile);
            @unlink($workerFile);
        }
    }

    /**
     * Source of the throwaway CLI worker used by the concurrency test.
     *
     * It deliberately avoids the framework: booting CodeIgniter 16 times would blur the
     * barrier the whole test depends on. Every failure path prints an ERROR line the
     * caller asserts on, so a worker that never reached the script cannot masquerade as
     * a legitimate DENIED answer.
     *
     * @return string A standalone PHP script.
     */
    private function workerSource(): string
    {
        return <<<'PHP'
            <?php

            [$key, $cap, $ttl, $startAt, $scriptFile, $conf] = array_slice($argv, 1);

            $conf   = (array) json_decode($conf, true);
            $lua    = (string) file_get_contents($scriptFile);
            $connId = bin2hex(random_bytes(8));
            $redis  = new Redis();

            try {
                if (@$redis->connect((string) ($conf['host'] ?? '127.0.0.1'), (int) ($conf['port'] ?? 6379), 1.0) === false) {
                    echo 'ERROR:connect';
                } else {
                    if (! empty($conf['password'])) {
                        $redis->auth($conf['password']);
                    }

                    if (! empty($conf['database'])) {
                        $redis->select((int) $conf['database']);
                    }

                    // Warm the socket so the barrier is not spent on the first round-trip.
                    $redis->ping();

                    $wait = (float) $startAt - microtime(true);
                    if ($wait > 0) {
                        usleep((int) ($wait * 1000000));
                    }

                    // ARGV mirrors RedisConnectionRegistry::acquire() exactly: [cap, ttl, connId].
                    // No timestamp is passed — the script reads its clock from Redis.
                    $granted = $redis->eval($lua, [$key, $cap, $ttl, $connId], 1);

                    echo $granted === $connId ? 'GRANTED:' . $connId : 'DENIED';
                }
            } catch (Throwable $e) {
                echo 'ERROR:' . $e->getMessage();
            }
            PHP;
    }

    /**
     * Slots are granted up to the cap and denied from then on.
     *
     * @return void
     */
    public function testAcquireDeniesOnceTheCapIsFull(): void
    {
        $redis    = $this->requireRedis();
        $registry = new RedisConnectionRegistry();

        $first  = $registry->acquire($this->userId, 2, self::SLOT_TTL);
        $second = $registry->acquire($this->userId, 2, self::SLOT_TTL);
        $third  = $registry->acquire($this->userId, 2, self::SLOT_TTL);

        $this->assertIsString($first, 'the first connection fits under a cap of 2');
        $this->assertIsString($second, 'the second connection still fits');
        $this->assertNull($third, 'the third connection is denied once the cap is full');
        $this->assertNotSame($first, $second, 'each granted slot carries its own connection id');
        $this->assertSame(2, $redis->zCard($this->key()), 'a denied acquire adds no member to the set');
    }

    /**
     * Releasing a slot frees capacity for a fresh acquire.
     *
     * @return void
     */
    public function testReleaseFreesTheSlotForANewAcquire(): void
    {
        $redis    = $this->requireRedis();
        $registry = new RedisConnectionRegistry();

        $connId = $registry->acquire($this->userId, 1, self::SLOT_TTL);
        $this->assertIsString($connId, 'the only slot is granted');
        $this->assertNull($registry->acquire($this->userId, 1, self::SLOT_TTL), 'the single slot is now taken');

        $registry->release($this->userId, $connId);

        $this->assertSame(0, $redis->zCard($this->key()), 'release removes the member from the set');
        $this->assertIsString($registry->acquire($this->userId, 1, self::SLOT_TTL), 'the freed slot is grantable again');
    }

    /**
     * Expired members are pruned by the next acquire instead of leaking capacity.
     *
     * This is the regression the ZSET design exists for: with an INCR/DECR counter a
     * client that vanished without releasing would permanently consume its own cap.
     *
     * @return void
     */
    public function testExpiredSlotsArePrunedOnTheNextAcquire(): void
    {
        $redis    = $this->requireRedis();
        $registry = new RedisConnectionRegistry();

        // A slot whose expiry timestamp already passed — a stream killed mid-flight.
        $redis->zAdd($this->key(), (float) (time() - 300), 'phpunit-stale-slot');
        $this->assertSame(1, $redis->zCard($this->key()), 'the stale member starts out occupying the cap');

        $connId = $registry->acquire($this->userId, 1, self::SLOT_TTL);

        $this->assertIsString($connId, 'a stale slot must not lock the user out of their own cap');
        $this->assertFalse($redis->zScore($this->key(), 'phpunit-stale-slot'), 'the stale member is pruned, not merely ignored');
        $this->assertSame(1, $redis->zCard($this->key()), 'only the fresh slot remains — no leak');
    }

    /**
     * The connection key carries an EXPIRE as the second safety net.
     *
     * @return void
     */
    public function testAcquireSetsAnExpiryOnTheConnectionKey(): void
    {
        $redis    = $this->requireRedis();
        $registry = new RedisConnectionRegistry();

        $registry->acquire($this->userId, 1, self::SLOT_TTL);
        $ttl = $redis->ttl($this->key());

        $this->assertGreaterThan(0, $ttl, 'the key must expire on its own if release never runs');
        $this->assertLessThanOrEqual(self::SLOT_TTL, $ttl, 'the expiry never outlives the requested slot ttl');
    }

    /**
     * A granted slot id is the documented 16-char hex token.
     *
     * @return void
     */
    public function testGrantedSlotIdIsARandomHexToken(): void
    {
        $this->requireRedis();
        $registry = new RedisConnectionRegistry();

        $connId = $registry->acquire($this->userId, 1, self::SLOT_TTL);

        $this->assertIsString($connId);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{16}$/', $connId, 'the slot id is 8 random bytes in hex');
    }

    /**
     * FAIL-CLOSED: an unreachable Redis denies the slot instead of waving it through.
     *
     * @return void
     */
    public function testAcquireIsFailClosedWhenRedisIsUnreachable(): void
    {
        $registry = $this->unreachableRegistry();

        $this->assertNull(
            $registry->acquire($this->userId, 3, self::SLOT_TTL),
            'an unenforceable cap must deny the stream (429), never grant an unmetered connection'
        );
    }

    /**
     * FAIL-CLOSED: release stays a silent no-op when Redis is unreachable.
     *
     * @return void
     */
    public function testReleaseIsASilentNoOpWhenRedisIsUnreachable(): void
    {
        $registry = $this->unreachableRegistry();

        $registry->release($this->userId, 'phpunit-never-granted');

        // Reaching the assertion at all proves no exception leaked out of release();
        // the assertion itself proves the no-op left the deny state intact.
        $this->assertNull(
            $registry->acquire($this->userId, 3, self::SLOT_TTL),
            'a no-op release must not talk the registry back into granting slots'
        );
    }

    /**
     * ATOMICITY: prune, count, add and expire travel as ONE round-trip.
     *
     * This is the property no sequential integration test can see. A ZCARD followed by a
     * separate ZADD passes every test in this class and still lets N concurrent streams
     * blow past the cap, because each of them reads the count before any of them writes.
     * Pinning the traffic shape — exactly one command, and it is an eval — is what stops
     * that regression from ever being reintroduced quietly.
     *
     * @return void
     */
    public function testAcquireSpendsExactlyOneRoundTripOnAnEvalScript(): void
    {
        $spy      = $this->requireSpy();
        $registry = $this->spiedRegistry($spy);

        $connId = $registry->acquire($this->userId, 4, self::SLOT_TTL);

        $this->assertIsString($connId, 'the spy answers the way the real script does on success');
        $this->assertSame(['eval'], $spy->calls, 'the cap decision must not be split across PHP round-trips');
        $this->assertCount(1, $spy->evalCalls, 'exactly one script invocation carries the whole decision');

        $call = $spy->evalCalls[0];
        $args = $call['args'];

        $this->assertSame(1, $call['numKeys'], 'only the connection key is a KEY; everything else is ARGV');
        $this->assertCount(4, $args, 'ARGV carries exactly cap, ttl and connId — no PHP timestamp travels with them');
        $this->assertSame(self::KEY_PREFIX . $this->userId, $args[0], 'the script operates on this user\'s key alone');
        $this->assertSame('4', $args[1], 'the cap is handed to the script, not compared in PHP');
        $this->assertSame((string) self::SLOT_TTL, $args[2], 'the ttl is handed to the script too');
        $this->assertSame($connId, $args[3], 'the returned slot id is the one the script echoed back');

        foreach (['TIME', 'ZREMRANGEBYSCORE', 'ZCARD', 'ZADD', 'TTL', 'EXPIRE'] as $command) {
            $this->assertStringContainsString($command, $call['script'], $command . ' belongs inside the atomic script');
        }
    }

    /**
     * No ARGV entry is a wall-clock timestamp: the script must read TIME itself.
     *
     * Shipping PHP's `time()` in ARGV let a single app server with a skewed clock prune
     * every OTHER server's still-live slots on each acquire, silently voiding the cap in
     * a multi-server deployment. Scores have to be written and compared against one clock,
     * and the only clock every node shares is the Redis one.
     *
     * @return void
     */
    public function testNoWallClockTimestampIsSentToTheScript(): void
    {
        $spy      = $this->requireSpy();
        $registry = $this->spiedRegistry($spy);

        $now = time();
        $registry->acquire($this->userId, 4, self::SLOT_TTL);

        $args = $spy->evalCalls[0]['args'];

        foreach (array_slice($args, 1) as $index => $argv) {
            $this->assertNotEqualsWithDelta(
                $now,
                (int) $argv,
                self::CLOCK_ARGV_DELTA,
                'ARGV[' . ($index + 1) . '] looks like a PHP wall-clock timestamp; the script reads TIME itself'
            );
        }

        $this->assertStringContainsString("redis.call('TIME')", $spy->evalCalls[0]['script'], 'the clock is read inside the script');
    }

    /**
     * L-7: slot scores are written from the REDIS clock, not the caller's.
     *
     * Proven by comparing the stored score against Redis' own TIME rather than PHP's:
     * a `now + ttl` score computed on the app server would drift away from the server
     * clock exactly as far as the two machines disagree.
     *
     * @return void
     */
    public function testSlotScoreIsWrittenFromTheRedisClock(): void
    {
        $redis    = $this->requireRedis();
        $registry = new RedisConnectionRegistry();

        $connId = $registry->acquire($this->userId, 1, self::SLOT_TTL);
        $this->assertIsString($connId, 'the slot is granted');

        $clock = $redis->time();
        $this->assertIsArray($clock, 'the server answers TIME');

        $score      = (float) $redis->zScore($this->key(), $connId);
        $redisNow   = (int) $clock[0];
        $expectedAt = $redisNow + self::SLOT_TTL;

        $this->assertEqualsWithDelta(
            $expectedAt,
            $score,
            self::CLOCK_ARGV_DELTA,
            'the expiry score is the Redis clock plus the ttl'
        );
    }

    /**
     * L-7: a caller whose wall clock is a day fast cannot prune anyone's live slots.
     *
     * The multi-server hazard the TIME switch closes: while the pruning bound travelled in
     * ARGV, one app server with a skewed clock pruned every OTHER server's still-valid
     * slots on each acquire, so the cap stopped being enforced for as long as the skew
     * lasted. A real skewed clock cannot be staged inside PHPUnit (it would mean changing
     * the system clock, or libfaketime), so it is staged where the skew actually entered
     * the system — the script's input. The shipped script has no such input at all, which
     * is the first half; the second half runs the LEGACY shape by hand to show the hazard
     * was real and is exactly what the current design removes.
     *
     * @return void
     */
    public function testASkewedCallerClockCannotPruneLiveSlots(): void
    {
        $redis    = $this->requireRedis();
        $registry = new RedisConnectionRegistry();

        $live = $registry->acquire($this->userId, 3, self::SLOT_TTL);
        $this->assertIsString($live, 'precondition: one live slot exists');

        // The "skewed peer": its acquire() is byte for byte everyone else's, because the
        // only clock the script consults is the server's own.
        $this->assertIsString($registry->acquire($this->userId, 3, self::SLOT_TTL), 'the peer gets its own slot');
        $this->assertNotFalse($redis->zScore($this->key(), $live), 'the first slot survives the peer entirely');
        $this->assertSame(2, $redis->zCard($this->key()), 'both slots still count against the cap');

        // Control: the same request under the legacy ARGV-clock shape wipes the live slot.
        $legacy = <<<'LUA'
            local key = KEYS[1]
            local now = tonumber(ARGV[1])
            redis.call('ZREMRANGEBYSCORE', key, '-inf', now)
            return redis.call('ZCARD', key)
            LUA;

        $survivors = $redis->eval($legacy, [$this->key(), (string) (time() + self::CLOCK_SKEW_SECONDS)], 1);

        $this->assertSame(0, $survivors, 'control: a clock supplied by the caller really did prune live slots');
        $this->assertFalse($redis->zScore($this->key(), $live), 'control: including slots belonging to other servers');
    }

    /**
     * L-6: a short-ttl acquire never shortens the key expiry a long-ttl slot set.
     *
     * An unconditional EXPIRE let a 6s slot delete the whole key out from under a 65s one:
     * the key disappears WITH its live members, so the cap stops being enforced for that
     * user until someone reconnects. The script therefore only writes EXPIRE while the
     * current TTL is smaller than the requested one.
     *
     * @return void
     */
    public function testAShorterTtlAcquireDoesNotShortenTheKeyExpiry(): void
    {
        $redis    = $this->requireRedis();
        $registry = new RedisConnectionRegistry();

        $this->assertSame(-2, $redis->ttl($this->key()), 'precondition: this run owns a key that does not exist yet');

        $registry->acquire($this->userId, 2, self::SLOT_TTL);
        $afterLongTtl = $redis->ttl($this->key());

        // The first acquire creates the key, i.e. TTL is -1 when the script checks it.
        // -1 < ttl, so the safety net is armed on the very first call — the reason the
        // check is a plain comparison and not `EXPIRE ... GT` (GT reads a missing TTL as
        // infinite and writes nothing).
        $this->assertGreaterThan(0, $afterLongTtl, 'the first acquire really arms the expiry on a brand-new key');
        $this->assertLessThanOrEqual(self::SLOT_TTL, $afterLongTtl, 'and it arms it at the requested ttl');

        $registry->acquire($this->userId, 2, self::SHORT_SLOT_TTL);
        $afterShortTtl = $redis->ttl($this->key());

        $this->assertGreaterThan(
            self::SHORT_SLOT_TTL,
            $afterShortTtl,
            'a short-lived slot must not drag the key expiry down to its own ttl'
        );
        $this->assertGreaterThanOrEqual(
            $afterLongTtl - 1,
            $afterShortTtl,
            'the surviving expiry is still the long one (allowing a single second of clock roll)'
        );
        $this->assertSame(2, $redis->zCard($this->key()), 'both slots are still counted against the cap');
    }

    /**
     * The key expiry is extended when a LONGER ttl arrives.
     *
     * The counterpart of the test above: "never shorten" must not degrade into
     * "never touch", or a key first created by a short slot would outlive nothing and
     * take the long-lived slot's second safety net with it.
     *
     * @return void
     */
    public function testALongerTtlAcquireExtendsTheKeyExpiry(): void
    {
        $redis    = $this->requireRedis();
        $registry = new RedisConnectionRegistry();

        $registry->acquire($this->userId, 2, self::SHORT_SLOT_TTL);
        $afterShortTtl = $redis->ttl($this->key());

        $this->assertLessThanOrEqual(self::SHORT_SLOT_TTL, $afterShortTtl, 'precondition: the key starts on the short expiry');

        $registry->acquire($this->userId, 2, self::SLOT_TTL);

        $this->assertGreaterThan(
            self::SHORT_SLOT_TTL,
            $redis->ttl($this->key()),
            'a longer slot pushes the key expiry out so its own safety net survives'
        );
    }

    /**
     * The script prunes from -inf, so a slot with a score <= 0 cannot squat the cap.
     *
     * The former '0' lower bound left any member scored below zero untouched — reachable
     * through a backwards clock step or a hand-written member — and such a slot would
     * then occupy the caller's cap forever, since nothing else ever removes it.
     *
     * @return void
     */
    public function testSlotsScoredAtOrBelowZeroArePrunedOnTheNextAcquire(): void
    {
        $redis    = $this->requireRedis();
        $registry = new RedisConnectionRegistry();

        $redis->zAdd($this->key(), 0.0, 'phpunit-zero-score-slot');
        $redis->zAdd($this->key(), -86400.0, 'phpunit-negative-score-slot');
        $this->assertSame(2, $redis->zCard($this->key()), 'both squatters start out occupying the cap');

        // Cap 1 on purpose: with the old '0' lower bound the negatively scored member
        // survives the prune and the caller is denied a slot it should own.
        $connId = $registry->acquire($this->userId, 1, self::SLOT_TTL);

        $this->assertIsString($connId, 'a slot scored below zero must not lock the user out of their own cap');
        $this->assertFalse($redis->zScore($this->key(), 'phpunit-zero-score-slot'), 'the zero-scored member is pruned');
        $this->assertFalse($redis->zScore($this->key(), 'phpunit-negative-score-slot'), 'the negatively scored member is pruned too');
        $this->assertSame(1, $redis->zCard($this->key()), 'only the fresh slot survives');
        $this->assertStringContainsString("'-inf'", $this->acquireScript(), 'the lower pruning bound is -inf, not 0');
    }

    /**
     * FAIL-CLOSED: a script-side failure denies the slot without leaking an exception.
     *
     * @return void
     */
    public function testAcquireIsFailClosedWhenTheScriptErrors(): void
    {
        $spy             = $this->requireSpy();
        $spy->evalThrows = new RedisException('NOSCRIPT No matching script.');

        $this->assertNull(
            $this->spiedRegistry($spy)->acquire($this->userId, 3, self::SLOT_TTL),
            'a failing script must deny the stream, and the caller must never see the exception'
        );
    }

    /**
     * FAIL-CLOSED: anything other than the requested slot id counts as a denial.
     *
     * false is the documented "capacity full" answer, but a truthy or foreign value —
     * a stale cached script, a proxy rewriting the reply — must not be mistaken for a
     * grant, or the caller would go on to release a slot it never held.
     *
     * @return void
     */
    public function testAcquireIsFailClosedOnAnUnexpectedScriptAnswer(): void
    {
        foreach ([false, true, 1, 'someone-elses-connection-id', []] as $answer) {
            $spy             = $this->requireSpy();
            $spy->evalResult = $answer;

            $this->assertNull(
                $this->spiedRegistry($spy)->acquire($this->userId, 3, self::SLOT_TTL),
                'only the echoed connection id counts as a grant'
            );
        }
    }

    /**
     * A zero ttl is raised to one second instead of deleting the key it just wrote.
     *
     * `EXPIRE key 0` removes the key outright, so the slot would be recorded, the caller
     * would be handed a perfectly valid connection id, and the cap would silently stop
     * being enforced for that user — the worst possible failure mode for a guard.
     *
     * @return void
     */
    public function testZeroTtlIsRaisedToTheOneSecondFloor(): void
    {
        $redis    = $this->requireRedis();
        $registry = new RedisConnectionRegistry();

        $this->alignToSecondBoundary();

        $connId = $registry->acquire($this->userId, 1, 0);

        $this->assertIsString($connId, 'the slot is still granted at the ttl floor');
        $this->assertSame(1, $redis->exists($this->key()), 'EXPIRE 0 would have deleted the key the script just wrote');
        $this->assertSame(1, $redis->ttl($this->key()), 'the ttl floor is exactly one second');
        $this->assertSame(1, $redis->zCard($this->key()), 'the slot really is recorded');
        $this->assertNull($registry->acquire($this->userId, 1, 0), 'and it really counts against the cap');
    }

    /**
     * A negative ttl lands on the same one-second floor.
     *
     * @return void
     */
    public function testNegativeTtlIsRaisedToTheSameFloor(): void
    {
        $redis    = $this->requireRedis();
        $registry = new RedisConnectionRegistry();

        $this->alignToSecondBoundary();

        $connId = $registry->acquire($this->userId, 1, -30);

        $this->assertIsString($connId, 'a negative ttl is clamped, not rejected');
        $this->assertSame(1, $redis->exists($this->key()), 'a negative EXPIRE would have deleted the key as well');
        $this->assertSame(1, $redis->ttl($this->key()), 'the floor does not depend on how negative the input was');
        $this->assertNull($registry->acquire($this->userId, 1, -30), 'the cap stays enforced at the floor');
    }

    /**
     * CONCURRENCY: N processes racing for the same key are granted EXACTLY cap slots.
     *
     * The workers are real, separate PHP processes released by a shared wall-clock
     * barrier, so this is genuine parallelism rather than a simulation. A correct
     * (single-EVAL) implementation satisfies the assertion under every interleaving, so
     * the test cannot go red by timing luck; a split ZCARD/ZADD implementation can only
     * satisfy it if no two workers overlap.
     *
     * @return void
     */
    public function testConcurrentAcquiresNeverExceedTheCap(): void
    {
        $redis = $this->requireRedis();

        if (! function_exists('proc_open')) {
            $this->markTestSkipped('proc_open() is disabled; skipping the real-concurrency race.');
        }

        $answers = $this->runConcurrentWorkers($this->key(), self::CONCURRENT_CAP, self::SLOT_TTL);

        $this->assertCount(self::CONCURRENT_WORKERS, $answers, 'every worker process reported back');
        $this->assertSame([], array_values(array_filter(
            $answers,
            static fn (string $answer): bool => ! str_starts_with($answer, 'GRANTED:') && $answer !== 'DENIED'
        )), 'no worker failed before reaching the script — an error must not be counted as a denial');

        $grantedIds = array_values(array_map(
            static fn (string $answer): string => substr($answer, strlen('GRANTED:')),
            array_filter($answers, static fn (string $answer): bool => str_starts_with($answer, 'GRANTED:'))
        ));

        $this->assertCount(self::CONCURRENT_CAP, $grantedIds, 'the cap is honoured to the slot under real parallelism');
        $this->assertSame(self::CONCURRENT_CAP, $redis->zCard($this->key()), 'the set holds exactly the granted slots');

        $members = $redis->zRange($this->key(), 0, -1);
        sort($grantedIds);
        sort($members);

        $this->assertSame($grantedIds, $members, 'every stored member is a slot some worker was actually told it won');
    }
}

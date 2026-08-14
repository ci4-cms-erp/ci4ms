<?php

declare(strict_types=1);

namespace Modules\Notifications\Libraries;

/**
 * Redis-backed concurrent SSE connection registry (the SOLE access point for the role-based connection cap).
 *
 * Open connections per user are kept in the `notif:conn:{userId}` ZSET:
 * member = connection ID, score = the slot's expiry timestamp. An INCR/DECR
 * COUNTER IS NOT USED; if the client disconnects, DECR never runs and the
 * counter would leak permanently, locking the user out of their own cap. The
 * ZSET self-heals: every `acquire()` first prunes expired slots via
 * ZREMRANGEBYSCORE, and the key itself also carries a second safety net via
 * EXPIRE. SCAN MATCH isn't used because it's O(N) over the keyspace.
 *
 * ATOMICITY: the prune -> count -> add -> expire sequence runs in a SINGLE
 * `EVAL` script. A ZCARD/ZADD pair split across PHP round-trips could let
 * concurrent N requests exceed the cap (TOCTOU); also, if the process died
 * between ZADD and EXPIRE, the key would leak permanently without a TTL. The
 * single script closes both races and also reduces 4 round-trips to 1.
 *
 * DENY IF REDIS IS UNREACHABLE: `acquire()` returns null, i.e. the stream gets
 * a 429. Rationale — when Redis is down, the signal store (RealtimeSignal)
 * also always returns 0, so SSE can't deliver anything anyway; keeping a
 * connection open for the TTL is pure worker waste and consumes the pool
 * we're trying to protect. The client falls back to polling in this case.
 * This is a deliberate opposite of the sibling class RealtimeSignal's
 * best-effort (fail-open) behavior: that one is signaling, this one is
 * resource protection.
 *
 * The connection is lazily established via RedisConnectionTrait (short
 * connect + read timeout); no exception EVER leaks on a connection or command
 * error.
 */
final class RedisConnectionRegistry implements ConnectionRegistryInterface
{
    use RedisConnectionTrait;

    /** Redis key prefix; the full key has the form `notif:conn:{userId}`. */
    private const KEY_PREFIX = 'notif:conn:';

    /** Byte length of the connection ID (16 characters once hex-encoded). */
    private const CONN_ID_BYTES = 8;

    /**
     * The atomic script for slot allocation: prune, cap check, add, and EXPIRE in one step.
     *
     * KEYS[1] = `notif:conn:{userId}`, ARGV = [cap, ttl, connId].
     * The lower bound is `'-inf'`, NOT `'0'`: otherwise a slot whose score is
     * <= 0 due to the system clock being turned back or a manually written
     * member would never be pruned and would permanently occupy a slot on an
     * active user.
     *
     * THE CLOCK SOURCE IS REDIS (`TIME`), NOT PHP's `time()`: in a
     * multi-server setup, a single app server whose clock has drifted forward
     * would, via the `now` it sends in ARGV, prune OTHER servers' still-live
     * slots on every `acquire()` and effectively remove the cap. Scores must
     * be written and compared with a single clock.
     *
     * THE TTL IS NEVER SHORTENED: `EXPIRE` is only written when the current
     * TTL is smaller. An unconditional `EXPIRE` would let a short-TTL acquire
     * prematurely delete the key of a long-TTL slot (the key expires along
     * with the live slots, temporarily lifting the cap). Since the absence of
     * `TTL` returns -1 and -1 is smaller than every ttl, the expiry is set
     * normally on the call that first creates the key. That's why
     * `EXPIRE ... GT` ISN'T USED: GT treats a TTL-less key as having infinite
     * TTL and writes nothing (measured: on Redis 8.6.3, `EXPIRE key 60 GT`
     * after a ZADD -> 0, TTL stays -1), meaning the second safety net would be
     * completely lost on the very first acquire.
     *
     * Return: connId on success, nil (false in phpredis) if the cap is full.
     */
    private const ACQUIRE_SCRIPT = <<<'LUA'
        local key    = KEYS[1]
        local cap    = tonumber(ARGV[1])
        local ttl    = tonumber(ARGV[2])
        local connId = ARGV[3]

        local clock = redis.call('TIME')
        local now   = tonumber(clock[1])

        redis.call('ZREMRANGEBYSCORE', key, '-inf', now)

        if redis.call('ZCARD', key) >= cap then
            return false
        end

        redis.call('ZADD', key, now + ttl, connId)

        if redis.call('TTL', key) < ttl then
            redis.call('EXPIRE', key, ttl)
        end

        return connId
        LUA;

    /**
     * Attempts to allocate a connection slot for the user (pruning expired ones).
     *
     * The whole operation runs atomically via a single `EVAL`: TIME (clock
     * from Redis) -> ZREMRANGEBYSCORE (self-healing) -> deny if
     * ZCARD >= cap -> ZADD (score = now + ttl) -> EXPIRE if the current TTL is
     * smaller (second safety net, never shortens).
     * `$ttl` is raised to at least 1; `EXPIRE key 0` DELETES the key, meaning
     * the slot would be recorded but the cap would silently stop being
     * enforced. Returns null (deny) if Redis is unreachable, the script
     * errors, or the cap is full; never throws.
     *
     * @param int $userId ID of the user in session.
     * @param int $cap    Allowed number of concurrent connections (the caller always passes >= 1).
     * @param int $ttl    Slot lifetime in seconds (connection TTL + buffer); raised to 1 if < 1.
     *
     * @return string|null Connection ID, or null (capacity full / cap couldn't be enforced).
     */
    public function acquire(int $userId, int $cap, int $ttl): ?string
    {
        $ttl = max(1, $ttl);

        $redis = $this->connection();
        if ($redis === null) {
            return null;
        }

        try {
            $connId = bin2hex(random_bytes(self::CONN_ID_BYTES));

            // phpredis signature: eval(script, args, numKeys) — the first numKeys arguments go to KEYS.
            $granted = $redis->eval(
                self::ACQUIRE_SCRIPT,
                [self::KEY_PREFIX . $userId, (string) $cap, (string) $ttl, $connId],
                1
            );

            // The script only returns connId on success; false (cap full) and
            // any unexpected response are a deny — the fail-closed contract
            // also applies here.
            return $granted === $connId ? $connId : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Releases an allocated connection slot via ZREM (fast path).
     *
     * Even if this call is never made or errors, the slot drops on its own via
     * the score/EXPIRE pair from `acquire()`; that's why the error is swallowed silently.
     *
     * @param int    $userId ID of the user who owns the slot.
     * @param string $connId Connection ID returned by `acquire()`.
     */
    public function release(int $userId, string $connId): void
    {
        $redis = $this->connection();
        if ($redis === null) {
            return;
        }

        try {
            $redis->zRem(self::KEY_PREFIX . $userId, $connId);
        } catch (\Throwable $e) {
            // The TTL safety net will drop the slot anyway; return silently.
        }
    }
}

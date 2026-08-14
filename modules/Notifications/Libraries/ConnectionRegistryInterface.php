<?php

declare(strict_types=1);

namespace Modules\Notifications\Libraries;

/**
 * Contract for the concurrent SSE connection registry (narrow interface for a
 * role-based connection cap).
 *
 * Every open SSE connection keeps a PHP-FPM worker busy for its TTL; without a
 * cap, a single identity could exhaust the `pm.max_children` pool.
 * RedisConnectionRegistry (final) implements this; RealtimeController::stream()
 * depends only on this type, so tests can inject a test double without a live
 * Redis (the Services::connectionRegistry() seam).
 *
 * WHAT SETS IT APART from SignalStoreInterface: the signal store is
 * best-effort and silently says "no signal" on error; the connection registry,
 * on the other hand, is a SECURITY counter — when it cannot be enforced,
 * `acquire()` returns null (deny). Implementations must still NEVER leak an
 * exception.
 */
interface ConnectionRegistryInterface
{
    /**
     * Attempts to allocate a connection slot for the user.
     *
     * CONTRACT: `$ttl` must be >= 1. A zero or negative lifetime means
     * `EXPIRE key 0` in Redis, i.e. it DELETES the key: the slot doesn't
     * count, a valid identity is returned, and the cap ends up SILENTLY
     * unenforced. Implementations must clamp this value to at least 1.
     *
     * @param int $userId Id of the user in the session.
     * @param int $cap    Number of concurrent connections allowed (the caller always passes >= 1).
     * @param int $ttl    Lifetime of the slot in seconds (connection TTL + buffer), >= 1.
     *
     * @return string|null The connection id to pass back to `release()` if a
     *                     slot was allocated; null if capacity is full or the
     *                     cap cannot be enforced.
     */
    public function acquire(int $userId, int $cap, int $ttl): ?string;

    /**
     * Releases an allocated connection slot (fast path).
     *
     * Even if this is never called, the slot expires on its own via the TTL given at `acquire()` time.
     *
     * @param int    $userId Id of the user who owns the slot.
     * @param string $connId Connection id returned by `acquire()`.
     */
    public function release(int $userId, string $connId): void;
}

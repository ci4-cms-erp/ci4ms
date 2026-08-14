<?php

namespace Tests\Support\Notifications;

use LogicException;
use Modules\Notifications\Libraries\ConnectionRegistryInterface;

/**
 * In-memory ConnectionRegistryInterface double for the SSE connection-cap tests.
 *
 * It implements the narrow ConnectionRegistryInterface (acquire/release), so it can be
 * injected into RealtimeController::stream() through the `connectionRegistry` service
 * seam — letting every cap-policy test run deterministically without a live Redis.
 *
 * It records the ($userId, $cap, $ttl) triple of every acquire() and the
 * ($userId, $connId) pair of every release(), in call order, so a test can prove which
 * cap the controller actually resolved from the user's groups and that the slot handed
 * out by acquire() is the exact one given back. Two switches drive the branches:
 * {@see $denyAcquire} makes acquire() report "capacity full" (the 429 path), and
 * {@see $failIfAcquired} turns any acquire() call into a hard failure, which is how the
 * unlimited escape hatch proves the registry is never touched at all.
 *
 * NOTE ON "UNLIMITED": only a NEGATIVE configured cap opens that hatch. A configured 0 is
 * invalid rather than unlimited — it fails closed to NotificationsConfig::CONN_CAP_FALLBACK
 * — so a test arming $failIfAcquired for a zero cap is asserting the wrong contract and
 * will (correctly) blow up here.
 */
final class FakeConnectionRegistry implements ConnectionRegistryInterface
{
    /** The slot id a successful acquire() hands out (asserted back on release()). */
    public string $connId = 'phpunit-conn-0123456789abcdef';

    /**
     * Arguments of every acquire() call, in call order.
     *
     * @var list<array{userId: int, cap: int, ttl: int}>
     */
    public array $acquires = [];

    /**
     * Arguments of every release() call, in call order.
     *
     * @var list<array{userId: int, connId: string}>
     */
    public array $releases = [];

    /** When true, acquire() denies the slot (capacity full / registry unenforceable). */
    public bool $denyAcquire = false;

    /** When true, any acquire() call throws — proof the unlimited path skips the registry. */
    public bool $failIfAcquired = false;

    /**
     * Records the requested slot and hands out {@see $connId} unless denial is armed.
     *
     * @param int $userId Session user the slot belongs to.
     * @param int $cap    Cap the controller resolved from the user's groups.
     * @param int $ttl    Slot lifetime in seconds (stream ttl + buffer).
     *
     * @return string|null The fixed connection id, or null when denial is armed.
     *
     * @throws LogicException When {@see $failIfAcquired} is set (an unlimited cap must not reach here).
     */
    public function acquire(int $userId, int $cap, int $ttl): ?string
    {
        if ($this->failIfAcquired) {
            throw new LogicException('acquire() must never run when the resolved cap is unlimited.');
        }

        $this->acquires[] = ['userId' => $userId, 'cap' => $cap, 'ttl' => $ttl];

        return $this->denyAcquire ? null : $this->connId;
    }

    /**
     * Records the released slot; the double holds no state to free.
     *
     * @param int    $userId Owner of the slot being released.
     * @param string $connId The id acquire() previously handed out.
     *
     * @return void
     */
    public function release(int $userId, string $connId): void
    {
        $this->releases[] = ['userId' => $userId, 'connId' => $connId];
    }
}

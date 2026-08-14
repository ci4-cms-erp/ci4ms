<?php

namespace Tests\Support\Notifications;

use Modules\Notifications\Libraries\SignalStoreInterface;

/**
 * In-memory SignalStoreInterface double injected wherever the store is needed.
 *
 * It implements the narrow SignalStoreInterface (bump/read), so it can be injected
 * both into RealtimeController::stream() (via the `signalStore` service seam) and
 * directly into RealtimeChannel's constructor — letting the channel and SSE tests
 * run deterministically without a live Redis.
 *
 * It records every bump() channel and every read() channel-list in call order so
 * a test can prove the SSE stream polls exactly the server-derived topic set
 * (never a client-supplied one), and can toggle bump() to fail for the
 * Redis-down branch.
 */
final class FakeSignalStore implements SignalStoreInterface
{
    /**
     * Channels passed to bump(), in call order.
     *
     * @var list<string>
     */
    public array $bumps = [];

    /**
     * Channel-lists passed to read(), in call order.
     *
     * @var list<list<string>>
     */
    public array $reads = [];

    /** When true, the next bump() reports failure (Redis-down simulation). */
    public bool $failNext = false;

    /**
     * Controllable counter map read() reports for known channels (default 0).
     *
     * @var array<string, int>
     */
    public array $counters = [];

    /**
     * Records the bumped channel and reports success unless failNext is set.
     *
     * @param string $channel Notifier::topicFor() output being signalled.
     *
     * @return bool False once when failNext was set (then it resets), else true.
     */
    public function bump(string $channel): bool
    {
        $this->bumps[] = $channel;

        if ($this->failNext) {
            $this->failNext = false;

            return false;
        }

        return true;
    }

    /**
     * Records the requested channel list and returns the controllable counters.
     *
     * @param list<string> $channels Notifier::topicsFor() output being polled.
     *
     * @return array<string, int> channel => counter (from {@see $counters}, else 0).
     */
    public function read(array $channels): array
    {
        $this->reads[] = $channels;

        $result = [];
        foreach ($channels as $channel) {
            $result[$channel] = $this->counters[$channel] ?? 0;
        }

        return $result;
    }
}

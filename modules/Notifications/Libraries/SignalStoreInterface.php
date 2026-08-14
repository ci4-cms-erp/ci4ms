<?php

declare(strict_types=1);

namespace Modules\Notifications\Libraries;

/**
 * The realtime-signal store contract (a narrow interface for SSE nudge counters).
 *
 * RealtimeSignal (final) implements this; RealtimeChannel and the SSE
 * stream() depend on this type, so tests can inject a test double without
 * live Redis (the Services::signalStore() seam). Implementations NEVER leak
 * an exception — bump returns false, read returns baseline-0.
 */
interface SignalStoreInterface
{
    /**
     * Increments a channel's signal counter (best-effort).
     *
     * @param string $channel Channel name that is the output of Notifier::topicFor().
     *
     * @return bool True if the counter could be incremented; false otherwise.
     */
    public function bump(string $channel): bool;

    /**
     * Reads the current signal counters for the given channels (0 if missing/unreachable).
     *
     * @param list<string> $channels Channel names derived via Notifier::topicsFor().
     *
     * @return array<string, int> channel => current counter (0 if missing).
     */
    public function read(array $channels): array;
}

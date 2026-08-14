<?php

declare(strict_types=1);

namespace Modules\Notifications\Libraries;

use Modules\Notifications\Config\NotificationsConfig;

/**
 * Redis-backed realtime-signal store (the SOLE access point for SSE nudge counters).
 *
 * PERSISTENCE IS NOT HERE: InAppChannel writes the notification row to the DB.
 * This store only holds the "something new happened, reconcile" signal in the
 * `notif:sig:{channel}` counter; `{channel}` is ALWAYS the output of
 * `Notifier::topicFor()` (server-derived; never client input). The writer is
 * RealtimeChannel (bump), the reader is the SSE stream() (read). Having a
 * single seam provides both isolation and testability
 * (Services::signalStore() -> injectMock).
 *
 * The connection is lazily established via RedisConnectionTrait; it uses a
 * short connect and read timeout: it must not block the triggering request or
 * SSE opening while Redis is down (or unresponsive). No exception EVER leaks
 * on a connection/command error — bump returns false, read returns baseline-0.
 */
final class RealtimeSignal implements SignalStoreInterface
{
    use RedisConnectionTrait;

    /** Redis key prefix; the full key has the form `notif:sig:{channel}`. */
    private const KEY_PREFIX = 'notif:sig:';

    private NotificationsConfig $config;

    /**
     * @param NotificationsConfig|null $config The global config is resolved if not injected.
     */
    public function __construct(?NotificationsConfig $config = null)
    {
        $this->config = $config ?? config(NotificationsConfig::class);
    }

    /**
     * Increments a channel's signal counter and refreshes its TTL (best-effort).
     *
     * `INCR notif:sig:{channel}` + `EXPIRE realtimeSignalTtl`. Returns false
     * silently if Redis is unreachable or the command errors (never throws).
     *
     * @param string $channel Channel name that is the output of Notifier::topicFor().
     *
     * @return bool True if the counter could be incremented; false otherwise.
     */
    public function bump(string $channel): bool
    {
        $redis = $this->connection();
        if ($redis === null) {
            return false;
        }

        try {
            $key = self::KEY_PREFIX . $channel;
            $redis->incr($key);
            $redis->expire($key, $this->config->realtimeSignalTtl);

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Reads the current signal counters for the given channels (0 if missing/unreachable).
     *
     * The returned array ALWAYS carries a key for each requested channel, so
     * the caller can safely compare the baseline diff. All channels are read
     * in a single `MGET` round-trip; calling it once a second in the SSE loop
     * is cheap and never touches the DB.
     *
     * @param list<string> $channels Channel names derived via Notifier::topicsFor().
     *
     * @return array<string, int> channel => current counter (0 if missing).
     */
    public function read(array $channels): array
    {
        $result = [];
        foreach ($channels as $channel) {
            $result[$channel] = 0;
        }

        $redis = $this->connection();
        if ($redis === null || $channels === []) {
            return $result;
        }

        try {
            // Single round-trip: MGET instead of a separate GET per channel (the SSE loop reads once a second).
            $values = $redis->mget(array_map(
                static fn (string $channel): string => self::KEY_PREFIX . $channel,
                $channels
            ));

            if (! is_array($values)) {
                return $result;
            }

            // MGET values come back in the same order as $channels; map them positionally.
            $values = array_values($values);

            foreach ($channels as $index => $channel) {
                $value            = $values[$index] ?? false;
                $result[$channel] = is_numeric($value) ? (int) $value : 0;
            }
        } catch (\Throwable $e) {
            // Baseline is already filled with 0s; return silently.
        }

        return $result;
    }
}

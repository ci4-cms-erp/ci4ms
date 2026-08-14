<?php

namespace Modules\Notifications\Controllers;

use Modules\Notifications\Config\NotificationsConfig;
use Modules\Notifications\Libraries\ConnectionRegistryInterface;
use Modules\Notifications\Libraries\Notifier;
use Modules\Notifications\Libraries\SignalStoreInterface;

/**
 * Notification Center — our own Redis-backed PHP SSE (Server-Sent Events) endpoint.
 *
 * Endpoint (see Config/Routes.php):
 *   GET backend/notifications/stream  stream()  — text/event-stream stream (role: read)
 *
 * SECURITY (IDOR gate): the channels to listen on are STRICTLY derived from the session
 * user via `Notifier::topicsFor()` (the transport mirror of applyRelevance). The client
 * never sends any channel/topic parameter; channels are resolved server-side from the
 * session only. The payload is a minimal "nudge" — the client reconciles by re-reading
 * the DB from the feed endpoint. The persistent source of truth is InAppChannel's DB
 * write; this stream is a best-effort signal.
 *
 * RESOURCE PROTECTION (connection cap): every open stream occupies a PHP-FPM worker for
 * the duration of its TTL, so the number of concurrent connections per identity is
 * capped on a role basis (NotificationsConfig::$realtimeConnCapDefault /
 * $realtimeConnCapByGroup). Slots are allocated via ConnectionRegistryInterface; if
 * capacity is full, the stream never opens, and the client gets 429 and falls back to polling.
 */
class RealtimeController extends \Modules\Backend\Controllers\BaseController
{
    /** Polling interval for the Redis signal counters (microseconds) — cheap since Redis GET is sub-ms. */
    private const STREAM_POLL_US = 1_000_000;

    /** Seconds between two heartbeat comments (keeps idle proxy/browser timeouts alive). */
    private const HEARTBEAT_SECONDS = 15;

    /** Max allowed lifetime of an SSE connection (sec) — the .env TTL cannot exceed this. */
    private const STREAM_TTL_CAP_SECONDS = 120;

    /**
     * Buffer added to the connection slot TTL (sec) — guarantees the slot expires even if release() never runs.
     *
     * Kept short: the longer a slot stays alive after the stream ends, the longer an
     * orphaned slot eats into the user's own cap.
     */
    private const CONN_TTL_BUFFER_SECONDS = 5;

    /**
     * Shared Notifier service instance.
     *
     * @return Notifier
     */
    private function notifier(): Notifier
    {
        /** @var Notifier $notifier */
        $notifier = service('notifier');

        return $notifier;
    }

    /**
     * Shared concurrent connection registry service instance.
     *
     * @return ConnectionRegistryInterface
     */
    private function registry(): ConnectionRegistryInterface
    {
        /** @var ConnectionRegistryInterface $registry */
        $registry = service('connectionRegistry');

        return $registry;
    }

    /** One warning per process for an invalid cap default — see warnAboutInvalidCapDefault(). */
    private static bool $capFallbackWarned = false;

    /**
     * Resolves the effective SSE connection cap from the user's groups.
     *
     * SENTINEL SEMANTICS — NEGATIVE for unlimited, NOT 0. Since cap values are declared
     * `int`, CI4 (`BaseConfig::initEnvValue()`) casts every non-numeric `.env` value to 0
     * BEFORE it reaches `resolveConnectionCap()`; meaning a one-letter typo like
     * `... = ten` produces 0. 0 therefore CANNOT mean "turn off protection": it's
     * treated as invalid and falls back fail-closed to
     * `NotificationsConfig::CONN_CAP_FALLBACK`. A negative value is the escape valve
     * because it can never come out of a typo's cast.
     *
     * If `$realtimeConnCapDefault` is NEGATIVE, the feature is GLOBALLY unlimited and
     * the by-group table is never consulted: an operator opening the escape valve
     * doesn't expect the product's built-in `['superadmin' => 10]` row to still apply.
     * Otherwise, the default applies if no group matches; if multiple match, the
     * HIGHEST cap applies. If even one match is defined NEGATIVE (UNLIMITED), unlimited
     * wins — the escape valve must not be narrowed.
     *
     * 0 or NON-NUMERIC values in the group table are IGNORED, treated as if that group
     * never matched. The `is_numeric()` filter is dead on the `.env` path (the value
     * already arrives as int there) but protects call paths that assign a string
     * directly to the property.
     *
     * @param NotificationsConfig $config Module configuration carrying the cap table.
     * @param string[]            $groups Shield groups the user is a member of.
     *
     * @return int The cap to apply; only 0 means UNLIMITED (the return value is never negative).
     */
    private function resolveConnectionCap(NotificationsConfig $config, array $groups): int
    {
        $default = $config->realtimeConnCapDefault;

        if ($default < 0) {
            return 0;
        }

        if ($default === 0) {
            $this->warnAboutInvalidCapDefault();
            $default = NotificationsConfig::CONN_CAP_FALLBACK;
        }

        $matched = array_filter(
            array_intersect_key($config->realtimeConnCapByGroup, array_flip($groups)),
            'is_numeric'
        );

        $caps = array_filter(
            array_map('intval', array_values($matched)),
            static fn (int $cap): bool => $cap !== 0
        );

        if ($caps === []) {
            return $default;
        }

        return min($caps) < 0 ? 0 : max($caps);
    }

    /**
     * Logs that an invalid (0) cap default was handled fail-closed.
     *
     * Writes only ONCE per process: since the stream endpoint reconnects from every tab
     * once per TTL, logging unconditionally would flood the log for the duration of the
     * misconfiguration. In FPM each worker will write its own warning, so the record
     * still stays visible.
     */
    private function warnAboutInvalidCapDefault(): void
    {
        if (self::$capFallbackWarned) {
            return;
        }

        self::$capFallbackWarned = true;

        log_message(
            'warning',
            'NotificationsConfig::$realtimeConnCapDefault is 0, which is not a valid cap '
            . '(a non-numeric .env value is cast to 0 before it reaches the code). Falling back to '
            . NotificationsConfig::CONN_CAP_FALLBACK . ' concurrent SSE connections per identity. '
            . 'Set notificationsconfig.realtimeConnCapDefault to a positive number, or to -1 for unlimited.'
        );
    }

    /**
     * Opens the SSE stream: polls the Redis signal of the user's authorized channels,
     * and sends a minimal "notification" nudge event on change.
     *
     * Stream lifetime is CAPPED by `NotificationsConfig::$realtimeStreamTtl` (sec); when
     * the time expires it closes with a clean `return`, and the browser's EventSource
     * reconnects automatically.
     *
     * CONSTRAINT (session lock): IMMEDIATELY after userId + channels are resolved,
     * `session()->close()` releases the session write lock. If the lock stayed open
     * under the FileHandler driver, ALL of that user's backend requests would block for
     * the duration of this long-lived connection.
     *
     * CONSTRAINT (CI4 output pipeline): headers are set on `$this->response` and sent
     * once via `sendHeaders()` BEFORE the loop; the framework's end-of-request `send()`
     * won't send them again since `headers_sent()` is true (a no-op in `pretend()` mode
     * during tests). The body is streamed via echo+flush; the method returns
     * `$this->response->setBody('')`. Per project convention, exit;/die; is NOT USED —
     * the loop ends normally once the capped duration elapses.
     *
     * RESOURCE PROTECTION: before the stream opens, a connection slot is allocated per
     * identity; if the cap is full (or the cap can't be enforced), the stream never
     * opens and returns 429. The slot is released two ways: the fast path when the loop
     * ends normally, and `register_shutdown_function` in EVERY case. The latter is
     * mandatory — under `ignore_user_abort(false)`, PHP detects a client disconnect
     * during a write and terminates the script via zend_bailout, meaning code AFTER the
     * loop never runs; the shutdown function also runs after a bailout/fatal and after
     * `max_execution_time` (it does NOT run under FPM's `request_terminate_timeout`,
     * where the slot only expires via its TTL).
     *
     * @return \CodeIgniter\HTTP\ResponseInterface
     */
    public function stream()
    {
        if ((int) auth()->id() <= 0) {
            return $this->failForbidden();
        }

        /** @var NotificationsConfig $config */
        $config = config(NotificationsConfig::class);

        if (! $config->realtimeEnabled) {
            // Empty 204 so the client falls back to polling.
            return $this->response->setStatusCode(204);
        }

        $userId   = (int) auth()->id();
        $notifier = $this->notifier();

        // Cap policy is resolved while the DB is still open.
        $groups = $notifier->groupsFor($userId);

        // Don't let the worker be held for too long if the operator sets an excessively
        // large TTL in .env: clamp to the upper bound. Lower bound is 0 (0 = the loop
        // never runs; the test seam uses this).
        $ttl = max(0, min($config->realtimeStreamTtl, self::STREAM_TTL_CAP_SECONDS));

        // cap 0 = UNLIMITED (only the escape valve opened by a NEGATIVE configuration):
        // the registry is never engaged, nothing is written to Redis.
        $cap      = $this->resolveConnectionCap($config, $groups);
        $registry = null;
        $connId   = null;
        $released = false;

        if ($cap > 0) {
            $slotTtl  = $ttl + self::CONN_TTL_BUFFER_SECONDS;
            $registry = $this->registry();
            $connId   = $registry->acquire($userId, $cap, $slotTtl);

            if ($connId === null) {
                // Capacity is full, or the registry is unreachable. A rejected request
                // should be as cheap as possible: only a SINGLE group query has run up
                // to this point, the channel list (topicsFor) was never derived. Short
                // plain-text body: the client's only need is a non-2xx status
                // (EventSource closes permanently), no cap/role detail is leaked.
                // Retry-After = slot lifetime, i.e. the earliest it could free up.
                return $this->response
                    ->setStatusCode(429)
                    ->setContentType('text/plain')
                    ->setHeader('Retry-After', (string) max(1, $slotTtl))
                    ->setHeader('X-Content-Type-Options', 'nosniff')
                    ->setBody(lang('Notifications.realtimeConnLimit'));
            }

            // The ONLY reliable way to release the slot: under ignore_user_abort(false),
            // PHP detects a client disconnect during a write and terminates the script
            // via zend_bailout, so the fast path after the loop DOES NOT RUN. Shutdown
            // functions also run after a client disconnect and after a
            // max_execution_time fatal; the $released flag prevents a double release
            // with the fast path. OUT OF SCOPE: FPM's request_terminate_timeout kills
            // the child process itself, shutdown functions DO NOT RUN — in that
            // scenario the slot only expires via its TTL.
            register_shutdown_function(static function () use ($registry, $userId, $connId, &$released): void {
                if (! $released) {
                    $released = true;
                    $registry->release($userId, $connId);
                }
            });
        }

        // Channels (including group membership) are only derived after the slot is
        // allocated. topicsFor() re-queries the groups itself; because its signature is
        // the IDOR gate, it does NOT ACCEPT a ready-made group list from outside — a
        // single implementation, two queries.
        $channels = $notifier->topicsFor($userId);

        // Channels resolved; release the session write lock right away.
        session()->close();

        // The poll loop only touches Redis; close the default DB connection opened by
        // BaseController's bootstrap (CommonModel) here so the worker doesn't hold an
        // idle connection for the duration of the TTL. There's no further DB access after the loop.
        db_connect('default')->close();

        /** @var SignalStoreInterface $signal */
        $signal = service('signalStore');

        $this->response->setContentType('text/event-stream');
        $this->response->setHeader('Cache-Control', 'no-cache');
        $this->response->setHeader('X-Accel-Buffering', 'no');
        $this->response->setHeader('Connection', 'keep-alive');

        @set_time_limit($ttl + 5);
        ignore_user_abort(false);

        $this->response->sendHeaders();

        $baseline      = $signal->read($channels);
        $deadline      = time() + $ttl;
        $lastHeartbeat = time();

        while (time() < $deadline) {
            if (connection_aborted()) {
                break;
            }

            $current = $signal->read($channels);
            if ($current !== $baseline) {
                echo "event: notification\n";
                echo 'data: ' . json_encode(['t' => time()]) . "\n\n";
                $this->flushBuffers();
                $baseline = $current;
            }

            if (time() - $lastHeartbeat >= self::HEARTBEAT_SECONDS) {
                echo ": ping\n\n";
                $this->flushBuffers();
                $lastHeartbeat = time();
            }

            usleep(self::STREAM_POLL_US);
        }

        // Fast path: if the loop ended normally, release the slot without waiting. If
        // execution never reaches here (disconnect/fatal), the shutdown function does the same job.
        if ($registry !== null && $connId !== null && ! $released) {
            $released = true;
            $registry->release($userId, $connId);
        }

        return $this->response->setBody('');
    }

    /**
     * Flushes open output buffers to the client (after every SSE event).
     */
    private function flushBuffers(): void
    {
        if (ob_get_level() > 0) {
            @ob_flush();
        }

        flush();
    }
}

<?php

declare(strict_types=1);

namespace Modules\Notifications\Libraries;

use Config\Cache;
use Redis;

/**
 * The SOLE source of the module's phpredis connection setup (RealtimeSignal + RedisConnectionRegistry).
 *
 * Both store classes set up the same lazy connection; if the setup code were
 * duplicated, one would get hardened while the other is forgotten (that's
 * exactly how the timeout setting slipped through before). Connection
 * parameters come from the `config('Cache')->redis` (host/port/password/database)
 * array — no new connection key is invented.
 *
 * TIMEOUT CONTRACT: both the connect and READ timeout are short. If Redis
 * accepts the connection but doesn't respond (a packet-dropping firewall, a
 * BGSAVE stall), commands would block for `default_socket_timeout`
 * (typically 60s) without a read timeout; that turns the connection cap that
 * exists to protect the worker pool into an amplification that locks up the
 * pool instead. If the extension isn't loaded, or the connect fails/times
 * out, the connection is marked `connectionFailed` once and isn't retried for
 * the rest of the request.
 */
trait RedisConnectionTrait
{
    /** Lazy connection setup timeout (s) — releases the caller quickly while Redis is down. */
    private const CONNECT_TIMEOUT_SECONDS = 0.5;

    /** Command response wait timeout (s) — so a hung Redis worker isn't held for minutes. */
    private const READ_TIMEOUT_SECONDS = 0.5;

    private ?Redis $redis = null;

    /** A connection that has failed once isn't retried for the rest of the request. */
    private bool $connectionFailed = false;

    /**
     * Lazy phpredis connection; reads from the Cache config, error -> null.
     *
     * @return Redis|null Ready-to-use connection, or null.
     */
    private function connection(): ?Redis
    {
        if ($this->redis instanceof Redis) {
            return $this->redis;
        }

        if ($this->connectionFailed || ! extension_loaded('redis')) {
            return null;
        }

        /** @var Cache $cache */
        $cache = config('Cache');
        $conf  = $cache->redis;

        try {
            $redis     = new Redis();
            $connected = $redis->connect(
                $conf['host'] ?? '127.0.0.1',
                (int) ($conf['port'] ?? 6379),
                self::CONNECT_TIMEOUT_SECONDS,
                null,
                0,
                self::READ_TIMEOUT_SECONDS
            );

            if ($connected === false) {
                $this->connectionFailed = true;

                return null;
            }

            // connect()'s read_timeout parameter only applies at setup time;
            // make sure the same limit applies to every subsequent command too.
            $redis->setOption(Redis::OPT_READ_TIMEOUT, self::READ_TIMEOUT_SECONDS);

            if (! empty($conf['password'])) {
                $redis->auth($conf['password']);
            }

            if (! empty($conf['database'])) {
                $redis->select((int) $conf['database']);
            }

            $this->redis = $redis;

            return $this->redis;
        } catch (\Throwable $e) {
            // Connection couldn't be established or timed out; not retried within this request.
            $this->connectionFailed = true;

            return null;
        }
    }
}

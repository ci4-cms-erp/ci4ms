<?php

namespace Tests\Support\Notifications;

use Redis;
use Throwable;

/**
 * phpredis command spy injected into RedisConnectionRegistry's lazy connection slot.
 *
 * The registry's whole cap guarantee rests on ONE property that a live-Redis test can
 * only observe indirectly: prune, count, add and expire happen inside a single EVAL,
 * with no PHP round-trip in between. A split ZCARD/ZADD pair would still pass every
 * sequential integration test and only break under real concurrency (TOCTOU), so this
 * double pins the shape of the traffic instead of its outcome — it records every
 * command the registry issues, which makes "exactly one round-trip, and it is an eval"
 * a deterministic, always-runnable assertion.
 *
 * It extends the real Redis class (phpredis is not final) so it satisfies the trait's
 * `?Redis $redis` property type, and it never connects to anything: every overridden
 * command answers from memory. The commands other than eval() are overridden purely so
 * that a regression which splits the script back into separate calls is RECORDED rather
 * than fatal.
 *
 * {@see $evalResult} shapes the script's answer (grant / capacity-full / a hostile
 * unexpected value) and {@see $evalThrows} reproduces a Redis-side failure such as
 * NOSCRIPT or a read timeout — both feed the fail-closed tests.
 */
final class SpyRedis extends Redis
{
    /** Sentinel for {@see $evalResult}: answer like the real script does on success. */
    public const GRANT = '__spy_grants_the_requested_slot__';

    /**
     * Name of every command the class under test issued, in call order.
     *
     * @var list<string>
     */
    public array $calls = [];

    /**
     * Full arguments of every eval() call, in call order.
     *
     * @var list<array{script: string, args: array<int, mixed>, numKeys: int}>
     */
    public array $evalCalls = [];

    /** What eval() returns: the GRANT sentinel, false (capacity full) or any other value. */
    public mixed $evalResult = self::GRANT;

    /** When set, eval() throws this instead of answering (Redis-side failure). */
    public ?Throwable $evalThrows = null;

    /**
     * Records the script call and answers according to the configured mode.
     *
     * @param string       $script   The Lua source the registry sent.
     * @param array<mixed> $args     KEYS + ARGV, flattened as phpredis expects.
     * @param int          $num_keys How many leading $args entries are KEYS.
     *
     * @return mixed The connection id on GRANT, else {@see $evalResult}.
     *
     * @throws Throwable When {@see $evalThrows} is armed.
     */
    public function eval(string $script, array $args = [], int $num_keys = 0): mixed
    {
        $this->calls[]     = 'eval';
        $this->evalCalls[] = ['script' => $script, 'args' => array_values($args), 'numKeys' => $num_keys];

        if ($this->evalThrows !== null) {
            throw $this->evalThrows;
        }

        if ($this->evalResult === self::GRANT) {
            // ARGV[3], i.e. $args[3] once the single KEY is counted in — the connection id
            // the script echoes back on success. The script reads its clock from Redis
            // (`TIME`), so no timestamp travels in ARGV and connId sits right after ttl.
            return $args[3] ?? false;
        }

        return $this->evalResult;
    }

    /**
     * Records a standalone ZCARD (a regression: the cap count belongs inside the script).
     *
     * @param string $key The connection ZSET key.
     *
     * @return int Always 0; the spy holds no set.
     */
    public function zCard(string $key): int
    {
        $this->calls[] = 'zCard';

        return 0;
    }

    /**
     * Records a standalone TTL (a regression: the "never shorten" check belongs inside the script).
     *
     * @param string $key The connection ZSET key.
     *
     * @return int Always -2 (key missing); the spy holds no key.
     */
    public function ttl(string $key): int
    {
        $this->calls[] = 'ttl';

        return -2;
    }

    /**
     * Records a standalone TIME (a regression: the clock read belongs inside the script).
     *
     * @return array{0: string, 1: string} A frozen [seconds, microseconds] pair.
     */
    public function time(): array
    {
        $this->calls[] = 'time';

        return ['0', '0'];
    }

    /**
     * Records a standalone ZADD (a regression: the slot insert belongs inside the script).
     *
     * @param string             $key                     The connection ZSET key.
     * @param array<mixed>|float $score_or_options        Score or phpredis option array.
     * @param mixed              ...$more_scores_and_mems Remaining score/member pairs.
     *
     * @return int Always 0; the spy holds no set.
     */
    public function zAdd(string $key, array|float $score_or_options, mixed ...$more_scores_and_mems): int
    {
        $this->calls[] = 'zAdd';

        return 0;
    }

    /**
     * Records a standalone EXPIRE (a regression: the safety net belongs inside the script).
     *
     * @param string      $key     The connection ZSET key.
     * @param int         $timeout Lifetime in seconds.
     * @param string|null $mode    phpredis expiry mode (NX/XX/GT/LT).
     *
     * @return bool Always true.
     */
    public function expire(string $key, int $timeout, ?string $mode = null): bool
    {
        $this->calls[] = 'expire';

        return true;
    }

    /**
     * Records a standalone ZREMRANGEBYSCORE (a regression: pruning belongs inside the script).
     *
     * @param string $key   The connection ZSET key.
     * @param string $start Lower score bound.
     * @param string $end   Upper score bound.
     *
     * @return int Always 0; the spy holds no set.
     */
    public function zRemRangeByScore(string $key, string $start, string $end): int
    {
        $this->calls[] = 'zRemRangeByScore';

        return 0;
    }

    /**
     * Records the release-path ZREM.
     *
     * @param mixed $key               The connection ZSET key.
     * @param mixed $member            The connection id being dropped.
     * @param mixed ...$other_members  Further members (unused by the registry).
     *
     * @return int Always 1.
     */
    public function zRem(mixed $key, mixed $member, mixed ...$other_members): int
    {
        $this->calls[] = 'zRem';

        return 1;
    }
}

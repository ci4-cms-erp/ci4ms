<?php

namespace Tests\Modules\Notifications;

use CodeIgniter\Config\Factories;
use CodeIgniter\Config\Services;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Cache;
use Modules\Notifications\Libraries\RealtimeSignal;
use Modules\Notifications\Libraries\RedisConnectionRegistry;
use Modules\Notifications\Libraries\RedisConnectionTrait;
use ReflectionClass;

/**
 * RedisConnectionTrait tests — the shared connect/read timeout seam.
 *
 * Both Redis-backed stores of this module (RedisConnectionRegistry and RealtimeSignal)
 * dial Redis through one trait. That consolidation is the point: while the setup code
 * was duplicated, one copy got a timeout and the other did not.
 *
 * The failure mode under test is NOT "Redis is down" — a refused connection returns in
 * microseconds and was always handled. It is "Redis accepts the socket and then says
 * nothing": a packet-dropping firewall, a BGSAVE stall, a hung server. Without a read
 * timeout every command blocks for default_socket_timeout (typically 60s), which turns
 * the connection cap — a guard that exists to protect the PHP-FPM worker pool — into the
 * very thing that pins the whole pool. So each test points the config at a socket that
 * accepts and never answers, and asserts the call comes back fast AND degrades the way
 * its class documents (registry: deny; signal store: best-effort silence).
 *
 * NON-DESTRUCTIVE: the deaf socket is a throwaway listener on an ephemeral loopback
 * port, closed in tearDown. No live Redis is contacted, no key is read or written, and
 * the phpredis extension is the only requirement (tests self-skip without it).
 *
 * @internal
 */
final class RedisConnectionTraitTest extends CIUnitTestCase
{
    /** Upper bound for any call that hits the deaf socket; the timeout itself is 0.5s. */
    private const TIMEOUT_BUDGET_SECONDS = 2.0;

    /** Both timeouts the trait documents, in seconds. */
    private const EXPECTED_TIMEOUT_SECONDS = 0.5;

    /** Pseudo user id for the registry calls; nothing is ever stored under it. */
    private const PSEUDO_USER_ID = 900000001;

    /** Channel asked of the signal store; nothing is ever stored under it. */
    private const PROBE_CHANNEL = 'phpunit-timeout-probe';

    /**
     * The listening-but-never-accepting socket, or null when none is open.
     *
     * @var resource|null
     */
    private $deafServer;

    /**
     * Closes the throwaway listener and drops the injected Cache config.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        if (is_resource($this->deafServer)) {
            fclose($this->deafServer);
        }

        $this->deafServer = null;

        Services::reset(true);

        // reset(true) above drops every shared instance, including 'routes'
        // (BaseService.php:366-375) -- it is discovered exactly once per
        // PHPUnit process (vendor/codeigniter4/framework/system/Test/
        // bootstrap.php:90) and nothing besides FeatureTestTrait::call()
        // (FeatureTestTrait.php:216) reloads it afterwards. Any later test
        // in the same process that drives a controller directly (not
        // through FeatureTestTrait) and hits redirect()->route(...) would
        // otherwise fail with HTTPException "The route for '...' cannot be
        // found" (see tests/Modules/Users/PermgroupPrivilegeEscalationTest.php
        // :106-123 and context.md FAZ 4 / F-2). loadRoutes() is a no-op on
        // an already-discovered collection (RouteCollection.php:312-314),
        // so this costs nothing in a clean process.
        service('routes')->loadRoutes();

        Factories::reset('config');

        parent::tearDown();
    }

    /**
     * Skips the test unless phpredis is available.
     *
     * @return void
     */
    private function requireExtension(): void
    {
        if (! extension_loaded('redis')) {
            $this->markTestSkipped('The phpredis extension is not loaded; skipping the connection-timeout tests.');
        }
    }

    /**
     * Points config('Cache')->redis at a socket that accepts connections and never replies.
     *
     * The listener is never accept()ed, so the kernel completes the TCP handshake from
     * the backlog — connect() succeeds and every command then waits for a reply that
     * never comes. That is precisely the hung-server case a connect timeout alone does
     * not cover.
     *
     * @return void
     */
    private function pointConfigAtADeafRedis(): void
    {
        $server = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);

        if ($server === false) {
            $this->markTestSkipped('Could not open a loopback listener for the timeout probe: ' . $errstr);
        }

        $this->deafServer = $server;

        $this->injectRedisPort($this->portOf($server));
    }

    /**
     * Points config('Cache')->redis at a port nothing listens on (refused connection).
     *
     * @return void
     */
    private function pointConfigAtAClosedPort(): void
    {
        $server = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);

        if ($server === false) {
            $this->markTestSkipped('Could not reserve a loopback port for the refused-connection probe: ' . $errstr);
        }

        $port = $this->portOf($server);
        fclose($server);

        $this->injectRedisPort($port);
    }

    /**
     * The local port a listening socket was bound to.
     *
     * @param resource $server The listening socket.
     *
     * @return int The ephemeral port number.
     */
    private function portOf($server): int
    {
        $name = (string) stream_socket_get_name($server, false);

        return (int) substr($name, (int) strrpos($name, ':') + 1);
    }

    /**
     * Injects a Cache config whose redis block points at the given loopback port.
     *
     * @param int $port Port the stores should dial.
     *
     * @return void
     */
    private function injectRedisPort(int $port): void
    {
        $cache        = new Cache();
        $cache->redis = [
            'host'     => '127.0.0.1',
            'password' => null,
            'port'     => $port,
            'timeout'  => 0,
            'database' => 0,
        ];

        Factories::injectMock('config', Cache::class, $cache);
    }

    /**
     * A hung Redis denies the slot instead of pinning the worker for a minute.
     *
     * @return void
     */
    public function testRegistryAcquireGivesUpWithinTheReadTimeout(): void
    {
        $this->requireExtension();
        $this->pointConfigAtADeafRedis();

        $registry = new RedisConnectionRegistry();

        $start  = microtime(true);
        $connId = $registry->acquire(self::PSEUDO_USER_ID, 3, 60);
        $elapsed = microtime(true) - $start;

        $this->assertNull($connId, 'a registry that cannot answer is fail-closed: no slot, no exception');
        $this->assertLessThan(self::TIMEOUT_BUDGET_SECONDS, $elapsed, 'the read timeout must cut the call short instead of waiting out default_socket_timeout');
    }

    /**
     * A hung Redis leaves the signal baseline at zero instead of blocking the SSE open.
     *
     * @return void
     */
    public function testSignalReadGivesUpWithinTheReadTimeout(): void
    {
        $this->requireExtension();
        $this->pointConfigAtADeafRedis();

        $signal = new RealtimeSignal();

        $start   = microtime(true);
        $result  = $signal->read([self::PROBE_CHANNEL]);
        $elapsed = microtime(true) - $start;

        $this->assertSame([self::PROBE_CHANNEL => 0], $result, 'an unreadable store degrades to the documented all-zero baseline');
        $this->assertLessThan(self::TIMEOUT_BUDGET_SECONDS, $elapsed, 'the poll loop must not stall on a hung Redis');
    }

    /**
     * A hung Redis makes bump() report failure fast instead of delaying the trigger request.
     *
     * bump() runs inside the request that CREATED the notification, so a stall here is
     * paid by an ordinary backend user, not by an SSE connection.
     *
     * @return void
     */
    public function testSignalBumpGivesUpWithinTheReadTimeout(): void
    {
        $this->requireExtension();
        $this->pointConfigAtADeafRedis();

        $signal = new RealtimeSignal();

        $start   = microtime(true);
        $bumped  = $signal->bump(self::PROBE_CHANNEL);
        $elapsed = microtime(true) - $start;

        $this->assertFalse($bumped, 'a store that cannot answer reports a best-effort failure');
        $this->assertLessThan(self::TIMEOUT_BUDGET_SECONDS, $elapsed, 'the notifying request must not hang on the signal side');
    }

    /**
     * A refused connection is remembered, so one request never re-dials a dead Redis.
     *
     * @return void
     */
    public function testARefusedConnectionIsNotRetriedWithinTheSameRequest(): void
    {
        $this->requireExtension();
        $this->pointConfigAtAClosedPort();

        $registry = new RedisConnectionRegistry();

        $this->assertNull($registry->acquire(self::PSEUDO_USER_ID, 3, 60), 'a refused connection denies the slot');
        $this->assertTrue($this->getPrivateProperty($registry, 'connectionFailed'), 'the failure is latched for the rest of the request');

        $start = microtime(true);
        $this->assertNull($registry->acquire(self::PSEUDO_USER_ID, 3, 60), 'the second call is denied from the latch');
        $this->assertLessThan(self::TIMEOUT_BUDGET_SECONDS, microtime(true) - $start, 'the latched failure short-circuits instead of dialling again');
    }

    /**
     * Both Redis-backed stores share ONE connection seam, with both timeouts set.
     *
     * A structural pin rather than a behavioural one: the duplicated-setup bug could not
     * be caught by testing either class alone, because each was individually correct.
     *
     * @return void
     */
    public function testBothStoresShareTheSameTimedConnectionSeam(): void
    {
        foreach ([RedisConnectionRegistry::class, RealtimeSignal::class] as $class) {
            $this->assertContains(RedisConnectionTrait::class, class_uses($class), $class . ' must not grow its own connection setup');

            $reflection = new ReflectionClass($class);

            $this->assertSame(self::EXPECTED_TIMEOUT_SECONDS, $reflection->getConstant('CONNECT_TIMEOUT_SECONDS'), $class . ' inherits the short connect timeout');
            $this->assertSame(self::EXPECTED_TIMEOUT_SECONDS, $reflection->getConstant('READ_TIMEOUT_SECONDS'), $class . ' inherits the short read timeout');
        }
    }
}

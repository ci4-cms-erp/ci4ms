<?php

namespace Tests\Modules\Notifications;

use CodeIgniter\Config\Factories;
use CodeIgniter\Config\Services;
use CodeIgniter\Shield\Auth;
use CodeIgniter\Test\CIUnitTestCase;
use Modules\Notifications\Config\NotificationsConfig;
use Modules\Notifications\Controllers\RealtimeController;
use Modules\Notifications\Libraries\Notifier;
use ReflectionClass;
use Tests\Support\Notifications\FakeConnectionRegistry;
use Tests\Support\Notifications\FakeSession;
use Tests\Support\Notifications\FakeSignalStore;

/**
 * SSE stream() controller-guard tests (auth + early-exit + IDOR).
 *
 * stream() replaced the old Mercure token() minting endpoint. The channels it
 * polls are derived STRICTLY server-side from the session user via
 * Notifier::topicsFor(); the client sends no topic parameter. These tests pin
 * that gate without ever entering the real polling loop: realtimeStreamTtl is
 * forced to 0 so the while-loop runs zero iterations and the method returns at
 * once. auth() and signalStore are mocked (a fake Auth returns a fixed id; a
 * FakeSignalStore records exactly which channels read() is asked for), and the
 * config is injected via Factories so realtimeEnabled/Ttl are controllable.
 *
 * The response is put in pretend() mode so sendHeaders() is a no-op, and each
 * call is wrapped in an output buffer asserted empty — nothing is streamed when
 * the loop is capped at zero, keeping the tests safe under
 * beStrictAboutOutputDuringTests. No DB writes, no Redis writes.
 *
 * Every test that reaches the connection-cap gate injects a FakeConnectionRegistry
 * as well: the default cap is positive, so without the double the controller would
 * resolve the real RedisConnectionRegistry and — being fail-closed — answer 429 on
 * any box without Redis. The cap policy itself is covered by
 * {@see RealtimeConnectionCapTest}; here the double only keeps the IDOR/session
 * assertions Redis-independent.
 *
 * @internal
 */
final class RealtimeStreamTest extends CIUnitTestCase
{
    /** A session user id high enough to own no group rows (topics stay minimal). */
    private const SESSION_USER_ID = 987654;

    /**
     * Drops every service/config mock installed by a test.
     *
     * reset(true) is used rather than resetSingle() because the signalStore
     * seam is registered under its exact camelCase key via override(), which
     * resetSingle()'s lowercasing would miss.
     *
     * @return void
     */
    protected function tearDown(): void
    {
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
     * Installs a fake Auth whose id() returns the given value.
     *
     * @param int $userId The id auth()->id() should report (0 = unauthenticated).
     *
     * @return void
     */
    private function actAsUser(int $userId): void
    {
        $auth = new class ($userId) extends Auth {
            /**
             * @param int $fakeId The id this fake auth reports as logged in.
             */
            public function __construct(private int $fakeId)
            {
            }

            /**
             * @return int The fixed, test-supplied user id.
             */
            public function id()
            {
                return $this->fakeId;
            }
        };

        Services::injectMock('auth', $auth);
    }

    /**
     * Installs a fake session recording whether close() was called.
     *
     * @return FakeSession The fake session (read $closed after the call).
     */
    private function installFakeSession(): FakeSession
    {
        $session = new FakeSession();

        Services::injectMock('session', $session);

        return $session;
    }

    /**
     * Injects a controllable NotificationsConfig for the realtime flags.
     *
     * @param bool $enabled   Value for realtimeEnabled.
     * @param int  $streamTtl Value for realtimeStreamTtl (0 = loop runs zero turns).
     *
     * @return void
     */
    private function installConfig(bool $enabled, int $streamTtl): void
    {
        $config                    = new NotificationsConfig();
        $config->realtimeEnabled   = $enabled;
        $config->realtimeStreamTtl = $streamTtl;

        Factories::injectMock('config', NotificationsConfig::class, $config);
    }

    /**
     * Registers the fake signal store under the exact key the controller resolves.
     *
     * The controller calls service('signalStore') (camelCase); Services::get()
     * looks up $instances by that exact key, so override() (which does not
     * lowercase) is required for the double to short-circuit the typed factory.
     *
     * @param FakeSignalStore $fake The double to install.
     *
     * @return void
     */
    private function injectSignalStore(FakeSignalStore $fake): void
    {
        Services::override('signalStore', $fake);
    }

    /**
     * Registers a fake connection registry so the cap gate never needs a live Redis.
     *
     * Same seam mechanics as {@see injectSignalStore()}: the controller resolves
     * service('connectionRegistry') by that exact camelCase key, so override() is
     * required for the double to short-circuit the typed factory.
     *
     * @return FakeConnectionRegistry The installed double (always grants a slot).
     */
    private function injectConnectionRegistry(): FakeConnectionRegistry
    {
        $fake = new FakeConnectionRegistry();

        Services::override('connectionRegistry', $fake);

        return $fake;
    }

    /**
     * Builds a RealtimeController with a pretend response and a stub request.
     *
     * @return RealtimeController The controller with response/request injected.
     */
    private function makeController(): RealtimeController
    {
        /** @var RealtimeController $controller */
        $controller = (new ReflectionClass(RealtimeController::class))->newInstanceWithoutConstructor();

        /** @var \CodeIgniter\HTTP\Response $response */
        $response = Services::response(null, false);
        $response->pretend(true);

        $request = new class {
            /**
             * @return bool Always false; stream() never inspects the request.
             */
            public function isAJAX(): bool
            {
                return false;
            }
        };

        $this->setPrivateProperty($controller, 'response', $response);
        $this->setPrivateProperty($controller, 'request', $request);

        return $controller;
    }

    /**
     * Runs stream() with output buffered and asserts nothing was streamed.
     *
     * @param RealtimeController $controller The controller under test.
     *
     * @return \CodeIgniter\HTTP\ResponseInterface The returned response.
     */
    private function runStream(RealtimeController $controller)
    {
        ob_start();

        try {
            $response = $controller->stream();
        } finally {
            $output = (string) ob_get_clean();

            // stream() arms the PHP execution timer for its own ttl (@set_time_limit).
            // In a long-lived CLI test process that budget would otherwise keep counting
            // into every later test and eventually abort the run; restore CLI's default.
            @set_time_limit(0);
        }

        $this->assertSame('', $output, 'a zero-ttl stream must not echo any SSE body');

        return $response;
    }

    /**
     * realtimeEnabled=false makes stream() answer 204 and never touch the signal store.
     *
     * @return void
     */
    public function testDisabledRealtimeReturns204AndNeverTouchesSignalStore(): void
    {
        $this->actAsUser(self::SESSION_USER_ID);
        $fake = new FakeSignalStore();
        $this->injectSignalStore($fake);
        $this->installConfig(false, 0);

        $response = $this->runStream($this->makeController());

        $this->assertSame(204, $response->getStatusCode(), 'disabled realtime falls back to an empty 204');
        $this->assertSame('', (string) $response->getBody(), 'the 204 carries no body');
        $this->assertSame([], $fake->reads, 'a disabled stream never reads a signal counter');
        $this->assertSame([], $fake->bumps, 'a disabled stream never writes a signal counter');
    }

    /**
     * An unauthenticated caller (id <= 0) is forbidden before any config/Redis work.
     *
     * @return void
     */
    public function testUnauthenticatedUserIsForbidden(): void
    {
        $this->actAsUser(0);
        $fake = new FakeSignalStore();
        $this->injectSignalStore($fake);
        $this->installConfig(true, 0);

        $response = $this->runStream($this->makeController());

        $this->assertSame(403, $response->getStatusCode(), 'no session user means the stream is forbidden');
        $this->assertSame([], $fake->reads, 'the forbidden branch never reaches the signal store');
    }

    /**
     * stream() polls EXACTLY the server-derived topicsFor(sessionUser) — never client input.
     *
     * @return void
     */
    public function testStreamPollsExactlyServerDerivedTopics(): void
    {
        $this->actAsUser(self::SESSION_USER_ID);
        $this->installFakeSession();
        $this->injectConnectionRegistry();
        $fake = new FakeSignalStore();
        $this->injectSignalStore($fake);
        $this->installConfig(true, 0);

        $expected = (new Notifier())->topicsFor(self::SESSION_USER_ID);

        $this->runStream($this->makeController());

        $this->assertCount(1, $fake->reads, 'a zero-ttl stream reads only the baseline once');
        $this->assertSame($expected, $fake->reads[0], 'the polled channels are exactly topicsFor(sessionUser)');
        $this->assertContains('user/' . self::SESSION_USER_ID, $fake->reads[0], 'the session user owns their own topic');
        $this->assertContains('broadcast', $fake->reads[0], 'broadcast is always polled');
        $this->assertSame([], $fake->bumps, 'the read-side stream never bumps a counter');
    }

    /**
     * stream() releases the session write lock and returns promptly when ttl-capped.
     *
     * @return void
     */
    public function testStreamReleasesSessionLockAndReturnsPromptly(): void
    {
        $this->actAsUser(self::SESSION_USER_ID);
        $session = $this->installFakeSession();
        $this->injectConnectionRegistry();
        $this->injectSignalStore(new FakeSignalStore());
        $this->installConfig(true, 0);

        $start    = microtime(true);
        $response = $this->runStream($this->makeController());
        $elapsed  = microtime(true) - $start;

        $this->assertTrue($session->closed, 'the session write lock is released before the poll loop');
        $this->assertLessThan(2.0, $elapsed, 'a zero-ttl stream returns at once instead of holding the connection');
        $this->assertSame('', (string) $response->getBody(), 'the capped stream returns an empty body');
    }
}

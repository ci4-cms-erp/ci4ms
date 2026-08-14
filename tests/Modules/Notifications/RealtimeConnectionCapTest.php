<?php

namespace Tests\Modules\Notifications;

use CodeIgniter\Config\Factories;
use CodeIgniter\Config\Services;
use CodeIgniter\Shield\Auth;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\TestLogger;
use Modules\Notifications\Config\NotificationsConfig;
use Modules\Notifications\Controllers\RealtimeController;
use ReflectionClass;
use ReflectionProperty;
use Tests\Support\Notifications\FakeConnectionRegistry;
use Tests\Support\Notifications\FakeNotifier;
use Tests\Support\Notifications\FakeSession;
use Tests\Support\Notifications\FakeSignalStore;
use Tests\Support\Notifications\ShutdownSpy;

// Installs the namespaced shadow of register_shutdown_function() inside the controller's
// namespace. It has to be in place before the FIRST stream() call of the whole process,
// because PHP caches the resolved function per call site; requiring it at file scope runs
// it while PHPUnit loads this file, i.e. before any test method executes.
require_once dirname(__DIR__, 2) . '/_support/Notifications/ShutdownSpy.php';

/**
 * Role-based SSE connection-cap tests — the worker-pool resource guard.
 *
 * Every open SSE stream pins one PHP-FPM worker for its whole ttl, so stream()
 * reserves a per-identity slot before opening anything. These tests pin the two
 * halves of that guard: which cap the controller resolves from the caller's Shield
 * groups (default / non-matching group / matching group / highest of several /
 * unlimited escape hatch), and what happens once the registry answers — a denied
 * slot must produce a 429 before the signal store, the session lock or the SSE body
 * are ever touched, and a granted slot must be handed back exactly once.
 *
 * SENTINEL SEMANTICS UNDER TEST: a POSITIVE cap is a cap, a NEGATIVE one is the
 * unlimited escape hatch, and 0 is INVALID — it fails closed to
 * NotificationsConfig::CONN_CAP_FALLBACK with a one-off warning rather than switching
 * the guard off. The reason 0 cannot be the "unlimited" sentinel is a framework
 * behaviour, not a preference: the cap properties are declared `int`, so
 * BaseConfig::initEnvValue() casts every non-numeric `.env` value — every typo — to 0
 * before the controller ever sees it. That path is exercised for real by the
 * hydrateConfigFromEnv() tests; a value NEGATIVE can never fall out of such a cast,
 * which is what makes it the safe escape hatch.
 *
 * Mechanics follow {@see RealtimeStreamTest}: realtimeStreamTtl is forced to 0 so the
 * poll loop runs zero turns, auth()/session/signalStore are doubles, the response is
 * in pretend() mode and every call is wrapped in an output buffer asserted empty
 * (beStrictAboutOutputDuringTests). The registry itself is a FakeConnectionRegistry,
 * so the whole class runs WITHOUT a live Redis; the real implementation is covered by
 * {@see RedisConnectionRegistryTest}.
 *
 * NON-DESTRUCTIVE: group resolution is normally driven by a Notifier double with no
 * DB at all. The single end-to-end test that proves the cap really comes from live
 * group membership inserts one throwaway user plus one membership row, each tagged
 * with a per-run suffix, and tearDown deletes exactly those two rows by id. No
 * pre-existing row is ever read for mutation, and no Redis key is written.
 *
 * @internal
 */
final class RealtimeConnectionCapTest extends CIUnitTestCase
{
    /** A session user id high enough to own no group rows of its own. */
    private const SESSION_USER_ID = 987654;

    /** Cap applied when no group of the caller matches the by-group table. */
    private const DEFAULT_CAP = 3;

    /** Cap of the privileged group in the by-group table. */
    private const PRIVILEGED_CAP = 10;

    /** Cap of a second matching group, deliberately lower than PRIVILEGED_CAP. */
    private const SECONDARY_CAP = 5;

    /** Cap wired to the live-membership group in the end-to-end test. */
    private const SEEDED_GROUP_CAP = 7;

    /**
     * Buffer stream() adds on top of the clamped ttl before reserving a slot.
     *
     * Mirrors RealtimeController::CONN_TTL_BUFFER_SECONDS. It is deliberately SHORT: the
     * longer a slot outlives its stream, the longer an orphaned slot eats the caller's
     * own cap when release() never runs.
     */
    private const CONN_TTL_BUFFER = 5;

    /** Upper bound stream() clamps realtimeStreamTtl to. */
    private const STREAM_TTL_CAP = 120;

    /** Shipped cap default pinned by testShippedDefaultsPinTheCapAndTheTtlBuffer(). */
    private const SHIPPED_DEFAULT_CAP = 6;

    /** The invalid cap value every `.env` typo collapses into once CI4 has cast it. */
    private const INVALID_CAP = 0;

    /** The unlimited sentinel; no int cast of a typo can ever produce a negative number. */
    private const UNLIMITED_CAP = -1;

    /** `.env` key prefix BaseConfig derives from the config class' short name. */
    private const ENV_PREFIX = 'notificationsconfig.';

    /** A `.env` value that is not a number — the typo the whole fail-closed rule exists for. */
    private const ENV_TYPO = 'ten';

    /** Throwaway user id created by seedGroupMembership(); 0 while nothing is seeded. */
    private int $seededUserId = 0;

    /**
     * `$_ENV` entries this test overwrote: key => [it existed before, its prior value].
     *
     * The presence flag is kept separately from the value because "absent" and "present
     * but empty" restore differently, and only the flag can tell them apart.
     *
     * @var array<string, array{bool, mixed}>
     */
    private array $envBackup = [];

    /**
     * Drops per-process state an earlier test may have left behind.
     *
     * Three statics survive between tests and would otherwise make the outcome depend on
     * execution ORDER: the recorded shutdown callbacks, the controller's one-shot
     * "invalid cap" warning latch, and TestLogger's log buffer. Nothing else in the suite
     * asserts on logs, so clearing the buffer here costs no coverage.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Canary: never allowed to run against anything but the throwaway
        // test schema. Matches tests/Modules/Users/PermgroupPrivilegeEscalationTest.php
        // :76-80 and tests/Modules/Methods/ModuleInstallerNamespaceTest.php:84-88.
        // Guards the whole class even though only seedGroupMembership() callers
        // actually touch the DB, matching the blanket-assertion convention.
        $this->assertStringContainsString(
            'ci4ms_test',
            (string) \Config\Database::connect()->database,
            'Refusing to run: the active connection is not pointed at the test database.',
        );

        ShutdownSpy::reset();
        $this->resetCapFallbackLatch();
        $this->clearLoggedMessages();
    }

    /**
     * Restores the environment, removes any seeded fixture row, then drops every mock.
     *
     * The `.env` hydration tests write real `$_ENV` keys, so those are put back exactly
     * as they were found FIRST: a leaked key would silently reconfigure every later
     * config instantiation in the process, including other test classes'.
     *
     * The DB cleanup runs before Services::reset() so the connection is still the
     * live 'default' one. reset(true) is used rather than resetSingle() because the
     * signalStore/connectionRegistry seams are registered under their exact
     * camelCase keys via override(), which resetSingle()'s lowercasing would miss.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        ShutdownSpy::reset();
        $this->restoreEnv();
        $this->resetCapFallbackLatch();

        if ($this->seededUserId > 0) {
            // stream() closes the shared 'default' connection; query() re-initializes
            // it transparently, so the same handle still deletes this run's rows.
            $connection = db_connect('default');
            $connection->table('auth_groups_users')->where('user_id', $this->seededUserId)->delete();
            $connection->table('users')->where('id', $this->seededUserId)->delete();
            $this->seededUserId = 0;
        }

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
     * @param int $userId The id auth()->id() should report.
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
     * Injects a NotificationsConfig carrying the cap policy under test.
     *
     * SCOPE — this seam assigns the properties DIRECTLY, so it deliberately bypasses
     * BaseConfig::initEnvValue(). What it therefore models is the PROGRAMMATIC call path
     * (a Registrar, a module `Config/Registrar.php`, an `injectMock()` in someone else's
     * test), which really can put a string, a bool or a null into the by-group table —
     * hence the loose `mixed` value type. It does NOT model `.env`: on that path CI4 has
     * already cast the value to int, and a typo has already become 0. The `.env` path has
     * its own tests, built on {@see hydrateConfigFromEnv()}; using this helper to "prove"
     * a `.env` typo is safe would be a green that means nothing.
     *
     * @param array<string, mixed> $capByGroup Group => cap table (empty = default only).
     * @param int                  $streamTtl  realtimeStreamTtl (0 = loop runs zero turns).
     * @param int                  $defaultCap realtimeConnCapDefault.
     *
     * @return void
     */
    private function installConfig(array $capByGroup, int $streamTtl = 0, int $defaultCap = self::DEFAULT_CAP): void
    {
        $config                         = new NotificationsConfig();
        $config->realtimeEnabled        = true;
        $config->realtimeStreamTtl      = $streamTtl;
        $config->realtimeConnCapDefault = $defaultCap;
        $config->realtimeConnCapByGroup = $capByGroup;

        Factories::injectMock('config', NotificationsConfig::class, $config);
    }

    /**
     * Builds and injects a NotificationsConfig hydrated through the REAL `.env` path.
     *
     * This is the only helper whose values travel through BaseConfig::initEnvValue(), so
     * it is the only one that reproduces what an operator's `.env` actually does — most
     * importantly the `int` cast that turns a typo into 0 long before the controller runs.
     * Keys are given without the `notificationsconfig.` prefix; nested table entries use
     * dot notation exactly as `.env` does (`realtimeConnCapByGroup.superadmin`).
     *
     * @param array<string, string> $values Property (or `property.key`) => raw `.env` string.
     *
     * @return NotificationsConfig The hydrated config, already injected as the config mock.
     */
    private function hydrateConfigFromEnv(array $values): NotificationsConfig
    {
        foreach ($values as $property => $value) {
            $this->setEnv(self::ENV_PREFIX . $property, $value);
        }

        $config = new NotificationsConfig();

        Factories::injectMock('config', NotificationsConfig::class, $config);

        return $config;
    }

    /**
     * Overwrites one `$_ENV` key, remembering what was there before.
     *
     * @param string $key   Full environment key, prefix included.
     * @param string $value Raw string value, exactly as `.env` would deliver it.
     *
     * @return void
     */
    private function setEnv(string $key, string $value): void
    {
        if (! array_key_exists($key, $this->envBackup)) {
            $this->envBackup[$key] = [array_key_exists($key, $_ENV), $_ENV[$key] ?? null];
        }

        $_ENV[$key] = $value;
    }

    /**
     * Puts every key {@see setEnv()} touched back the way it was found.
     *
     * @return void
     */
    private function restoreEnv(): void
    {
        foreach ($this->envBackup as $key => [$existed, $value]) {
            if (! $existed) {
                unset($_ENV[$key]);

                continue;
            }

            $_ENV[$key] = $value;
        }

        $this->envBackup = [];
    }

    /**
     * Clears the controller's one-shot "invalid cap default" warning latch.
     *
     * The latch is a private static, so without this reset only the FIRST test in the
     * process that hits an invalid default would see the warning and every later one
     * would silently pass or fail depending on execution order.
     *
     * @return void
     */
    private function resetCapFallbackLatch(): void
    {
        $latch = new ReflectionProperty(RealtimeController::class, 'capFallbackWarned');
        $latch->setValue(null, false);
    }

    /**
     * Empties TestLogger's process-wide log buffer.
     *
     * @return void
     */
    private function clearLoggedMessages(): void
    {
        $logs = new ReflectionProperty(TestLogger::class, 'op_logs');
        $logs->setValue(null, []);
    }

    /**
     * Every message logged at `warning` level since the last clear, in order.
     *
     * @return list<string> The warning messages.
     */
    private function loggedWarnings(): array
    {
        $logs = new ReflectionProperty(TestLogger::class, 'op_logs');

        /** @var list<array{level: mixed, message: string, file: string|null}> $entries */
        $entries = $logs->getValue();

        return array_values(array_map(
            static fn (array $entry): string => $entry['message'],
            array_filter($entries, static fn (array $entry): bool => strtolower((string) $entry['level']) === 'warning')
        ));
    }

    /**
     * Installs a Notifier double reporting a fixed group list and a trivial topic list.
     *
     * @param list<string> $groups Shield groups the caller should look like a member of.
     *
     * @return FakeNotifier The installed double (read its call logs afterwards).
     */
    private function injectNotifierWithGroups(array $groups): FakeNotifier
    {
        $notifier = new FakeNotifier($groups);

        Services::injectMock('notifier', $notifier);

        return $notifier;
    }

    /**
     * Registers the fake connection registry under the exact key the controller resolves.
     *
     * @param FakeConnectionRegistry $fake The double to install.
     *
     * @return void
     */
    private function injectConnectionRegistry(FakeConnectionRegistry $fake): void
    {
        Services::override('connectionRegistry', $fake);
    }

    /**
     * Registers the fake signal store under the exact key the controller resolves.
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

        $this->assertSame('', $output, 'neither the capped loop nor the 429 branch may echo an SSE body');

        return $response;
    }

    /**
     * Wires the standard doubles and runs stream() for a caller in the given groups.
     *
     * @param list<string>           $groups   Groups the Notifier double reports.
     * @param FakeConnectionRegistry $registry The registry double to observe.
     * @param FakeSignalStore|null   $signal   Signal-store double to observe, if needed.
     *
     * @return \CodeIgniter\HTTP\ResponseInterface The returned response.
     */
    private function streamAsMemberOf(array $groups, FakeConnectionRegistry $registry, ?FakeSignalStore $signal = null)
    {
        $this->actAsUser(self::SESSION_USER_ID);
        $this->installFakeSession();
        $this->injectNotifierWithGroups($groups);
        $this->injectConnectionRegistry($registry);
        $this->injectSignalStore($signal ?? new FakeSignalStore());

        return $this->runStream($this->makeController());
    }

    /**
     * Asserts exactly one slot was requested and returns the cap it was asked for.
     *
     * @param FakeConnectionRegistry $registry The observed registry double.
     *
     * @return int The cap stream() resolved and passed to acquire().
     */
    private function acquiredCap(FakeConnectionRegistry $registry): int
    {
        $this->assertCount(1, $registry->acquires, 'a stream reserves exactly one slot');

        return $registry->acquires[0]['cap'];
    }

    /**
     * Creates a throwaway user with one group membership on the live DB.
     *
     * @return string The unique group name the seeded user belongs to.
     */
    private function seedGroupMembership(): string
    {
        // Notifier -> CommonModel hardcodes the 'default' group, so the membership
        // lookup runs against the live MariaDB even under testing. Seed on that exact
        // connection, never the empty in-memory SQLite.
        $connection = db_connect('default');

        $suffix = bin2hex(random_bytes(6));
        $group  = 'phpunit_cap_' . $suffix;

        $connection->table('users')->insert([
            'username'   => 'phpunit_cap_' . $suffix,
            'active'     => 1,
            'firstname'  => 'PHPUnit',
            'surname'    => 'ConnCap',
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        $this->seededUserId = (int) $connection->insertID();

        $connection->table('auth_groups_users')->insert([
            'user_id'    => $this->seededUserId,
            'group'      => $group,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return $group;
    }

    /**
     * A denied slot answers 429 with a non-empty body and opens no stream at all.
     *
     * @return void
     */
    public function testCapExhaustedReturns429WithoutOpeningTheStream(): void
    {
        $registry              = new FakeConnectionRegistry();
        $registry->denyAcquire = true;

        $this->actAsUser(self::SESSION_USER_ID);
        $session = $this->installFakeSession();
        $this->injectNotifierWithGroups([]);
        $this->injectConnectionRegistry($registry);
        $signal = new FakeSignalStore();
        $this->injectSignalStore($signal);
        $this->installConfig([]);

        $response = $this->runStream($this->makeController());
        $body     = (string) $response->getBody();

        $this->assertSame(429, $response->getStatusCode(), 'a full cap rejects the stream with Too Many Requests');
        $this->assertNotSame('', $body, 'the 429 carries a client-readable reason');
        $this->assertSame(lang('Notifications.realtimeConnLimit'), $body, 'the body is the translated cap message');
        $this->assertNotSame('Notifications.realtimeConnLimit', $body, 'the language key must actually resolve');
        $this->assertStringStartsWith('text/plain', (string) $response->getHeaderLine('Content-Type'), 'the rejection is plain text, not an event stream');
        $this->assertSame([], $signal->reads, 'a rejected stream never reads a signal counter');
        $this->assertSame([], $signal->bumps, 'a rejected stream never writes a signal counter');
        // Negative pin only; its positive control lives in
        // testSessionLockIsReleasedOnlyOnTheGrantedPath(), which proves the same double
        // does flip to true whenever stream() really reaches session()->close().
        $this->assertFalse($session->closed, 'the slot is reserved BEFORE the session lock is released');
        $this->assertSame([], $registry->releases, 'nothing was reserved, so nothing is released');
    }

    /**
     * The session write lock survives a rejection and is released only once a slot is granted.
     *
     * Runs both branches against the same double, so the "still locked" half of the 429
     * contract cannot pass by the close() call merely having been deleted.
     *
     * @return void
     */
    public function testSessionLockIsReleasedOnlyOnTheGrantedPath(): void
    {
        $denying              = new FakeConnectionRegistry();
        $denying->denyAcquire = true;

        $this->actAsUser(self::SESSION_USER_ID);
        $deniedSession = $this->installFakeSession();
        $this->injectNotifierWithGroups([]);
        $this->injectConnectionRegistry($denying);
        $this->injectSignalStore(new FakeSignalStore());
        $this->installConfig([]);

        $this->runStream($this->makeController());

        $this->assertFalse($deniedSession->closed, 'a rejected stream leaves the session lock exactly as it found it');

        // Same wiring, same session double type, only the registry answer differs.
        $grantedSession = $this->installFakeSession();
        $this->injectConnectionRegistry(new FakeConnectionRegistry());

        $this->runStream($this->makeController());

        $this->assertTrue($grantedSession->closed, 'a granted stream does release the lock before the poll loop');
    }

    /**
     * A caller in no group is capped by realtimeConnCapDefault.
     *
     * @return void
     */
    public function testCapFallsBackToTheDefaultForAGrouplessUser(): void
    {
        $registry = new FakeConnectionRegistry();
        $this->installConfig(['superadmin' => self::PRIVILEGED_CAP]);

        $this->streamAsMemberOf([], $registry);

        $this->assertSame(self::DEFAULT_CAP, $this->acquiredCap($registry), 'no group membership means the default cap applies');
    }

    /**
     * A caller whose groups are absent from the table is capped by the default.
     *
     * @return void
     */
    public function testCapFallsBackToTheDefaultForANonMatchingGroup(): void
    {
        $registry = new FakeConnectionRegistry();
        $this->installConfig(['superadmin' => self::PRIVILEGED_CAP]);

        $this->streamAsMemberOf(['editor'], $registry);

        $this->assertSame(self::DEFAULT_CAP, $this->acquiredCap($registry), 'an unlisted group does not earn a custom cap');
    }

    /**
     * A caller in a listed group is capped by that group's entry, not the default.
     *
     * @return void
     */
    public function testCapUsesTheMatchingGroupEntry(): void
    {
        $registry = new FakeConnectionRegistry();
        $this->installConfig(['superadmin' => self::PRIVILEGED_CAP]);

        $this->streamAsMemberOf(['superadmin'], $registry);

        $this->assertSame(self::PRIVILEGED_CAP, $this->acquiredCap($registry), 'a listed group overrides the default cap');
    }

    /**
     * With several matching groups the HIGHEST cap wins.
     *
     * @return void
     */
    public function testCapTakesTheHighestOfSeveralMatchingGroups(): void
    {
        $registry = new FakeConnectionRegistry();
        $this->installConfig([
            'superadmin' => self::PRIVILEGED_CAP,
            'auditor'    => self::SECONDARY_CAP,
        ]);

        $this->streamAsMemberOf(['auditor', 'superadmin'], $registry);

        $this->assertSame(self::PRIVILEGED_CAP, $this->acquiredCap($registry), 'the most permissive matching group decides');
    }

    /**
     * One unlimited (NEGATIVE) matching group beats every finite one — the escape hatch.
     *
     * @return void
     */
    public function testUnlimitedGroupBeatsAFiniteMatchingGroup(): void
    {
        $registry                 = new FakeConnectionRegistry();
        $registry->failIfAcquired = true;

        $this->installConfig([
            'superadmin' => self::PRIVILEGED_CAP,
            'unmetered'  => self::UNLIMITED_CAP,
        ]);

        $this->streamAsMemberOf(['superadmin', 'unmetered'], $registry);

        $this->assertSame([], $registry->acquires, 'an unlimited group must not be narrowed by a finite one');
    }

    /**
     * A group row of 0 is DISCARDED, so a finite sibling row still decides.
     *
     * The counterpart of the test above and the reason the two sentinels had to be split:
     * 0 is what a `.env` typo becomes, so treating it as "unlimited" handed the escape
     * hatch to whichever group the operator mistyped — here it would have unmetered a
     * caller who is only entitled to PRIVILEGED_CAP.
     *
     * @return void
     */
    public function testZeroGroupCapIsDiscardedSoTheFiniteSiblingStillApplies(): void
    {
        $registry = new FakeConnectionRegistry();

        $this->installConfig([
            'superadmin' => self::PRIVILEGED_CAP,
            'unmetered'  => self::INVALID_CAP,
        ]);

        $this->streamAsMemberOf(['superadmin', 'unmetered'], $registry);

        $this->assertSame(self::PRIVILEGED_CAP, $this->acquiredCap($registry), 'an invalid row is ignored; the valid sibling still caps the caller');
    }

    /**
     * A group row of 0 on its own leaves the caller on the default, not unmetered.
     *
     * @return void
     */
    public function testZeroGroupCapAloneFallsBackToTheDefault(): void
    {
        $registry = new FakeConnectionRegistry();

        $this->installConfig(['unmetered' => self::INVALID_CAP]);

        $this->streamAsMemberOf(['unmetered'], $registry);

        $this->assertSame(self::DEFAULT_CAP, $this->acquiredCap($registry), 'a discarded row means the group never matched at all');
    }

    /**
     * A negative group cap is the escape hatch and skips the registry entirely.
     *
     * @return void
     */
    public function testNegativeGroupCapIsTreatedAsUnlimited(): void
    {
        $registry                 = new FakeConnectionRegistry();
        $registry->failIfAcquired = true;

        $this->installConfig(['unmetered' => self::UNLIMITED_CAP]);

        $this->streamAsMemberOf(['unmetered'], $registry);

        $this->assertSame([], $registry->acquires, 'only a negative value opens the escape hatch');
    }

    /**
     * A NEGATIVE default cap means unlimited: the registry is never touched at all.
     *
     * @return void
     */
    public function testNegativeDefaultCapNeverTouchesTheRegistry(): void
    {
        $registry                 = new FakeConnectionRegistry();
        $registry->failIfAcquired = true;
        $signal                   = new FakeSignalStore();

        $this->installConfig([], 0, self::UNLIMITED_CAP);

        $response = $this->streamAsMemberOf([], $registry, $signal);

        $this->assertSame([], $registry->acquires, 'an unlimited cap must not reserve a slot');
        $this->assertSame([], $registry->releases, 'an unlimited cap must not release a slot either');
        $this->assertCount(1, $signal->reads, 'the unlimited path really opens the stream: the baseline is read');
        $this->assertStringStartsWith(
            'text/event-stream',
            (string) $response->getHeaderLine('Content-Type'),
            'skipping the registry still yields an SSE response, not the 429 plain-text one'
        );
    }

    /**
     * FAIL-CLOSED: a default cap of 0 still reserves a slot, at the fallback cap.
     *
     * The exact opposite of the negative case above and the whole point of the split:
     * an invalid default must not disable the guard. The registry IS called, with
     * NotificationsConfig::CONN_CAP_FALLBACK, and the stream opens under that cap.
     *
     * @return void
     */
    public function testInvalidDefaultCapFailsClosedToTheFallbackAndStillReservesASlot(): void
    {
        $registry = new FakeConnectionRegistry();
        $signal   = new FakeSignalStore();

        $this->installConfig([], 0, self::INVALID_CAP);

        $response = $this->streamAsMemberOf([], $registry, $signal);

        $this->assertSame(
            NotificationsConfig::CONN_CAP_FALLBACK,
            $this->acquiredCap($registry),
            'an invalid default falls back to a real cap instead of switching the guard off'
        );
        $this->assertCount(1, $registry->releases, 'the slot is a real one and is handed back');
        $this->assertCount(1, $signal->reads, 'the stream still opens under the fallback cap');
        $this->assertStringStartsWith(
            'text/event-stream',
            (string) $response->getHeaderLine('Content-Type'),
            'failing closed caps the caller, it does not reject them'
        );
    }

    /**
     * A normally finished stream releases exactly the slot acquire() handed out.
     *
     * @return void
     */
    public function testSlotIsReleasedOnceWithTheAcquiredConnectionId(): void
    {
        $registry         = new FakeConnectionRegistry();
        $registry->connId = 'phpunit-conn-' . bin2hex(random_bytes(4));

        $this->installConfig([]);

        $this->streamAsMemberOf([], $registry);

        $this->assertCount(1, $registry->releases, 'the slot is handed back exactly once');
        $this->assertSame(
            ['userId' => self::SESSION_USER_ID, 'connId' => $registry->connId],
            $registry->releases[0],
            'release() gets the same identity and slot id acquire() returned'
        );
    }

    /**
     * The reserved slot is scoped to the session user and outlives the stream ttl.
     *
     * @return void
     */
    public function testSlotTtlIsTheStreamTtlPlusBuffer(): void
    {
        $registry = new FakeConnectionRegistry();
        $this->installConfig([]);

        $this->streamAsMemberOf([], $registry);

        $this->assertSame(self::SESSION_USER_ID, $registry->acquires[0]['userId'], 'the slot belongs to the session user');
        $this->assertSame(self::CONN_TTL_BUFFER, $registry->acquires[0]['ttl'], 'a zero-ttl stream still reserves the slot for the buffer');
    }

    /**
     * An oversized .env ttl is clamped before it becomes the slot lifetime.
     *
     * The registry denies the slot so the assertion is reached instantly instead of
     * holding the test for the clamped two minutes.
     *
     * @return void
     */
    public function testSlotTtlUsesTheClampedStreamTtl(): void
    {
        $registry              = new FakeConnectionRegistry();
        $registry->denyAcquire = true;

        $this->installConfig([], 9999);

        $response = $this->streamAsMemberOf([], $registry);

        $this->assertSame(429, $response->getStatusCode(), 'the denied slot short-circuits before the poll loop');
        $this->assertSame(
            self::STREAM_TTL_CAP + self::CONN_TTL_BUFFER,
            $registry->acquires[0]['ttl'],
            'an oversized ttl is clamped to the hard cap before the buffer is added'
        );
    }

    /**
     * End-to-end: the cap really comes from live auth_groups_users membership.
     *
     * Uses the REAL Notifier, so this pins the groupsFor() -> resolveConnectionCap()
     * wiring the doubles above stub out.
     *
     * @return void
     */
    public function testCapIsResolvedFromLiveGroupMembership(): void
    {
        $group    = $this->seedGroupMembership();
        $registry = new FakeConnectionRegistry();

        $this->installConfig([$group => self::SEEDED_GROUP_CAP]);
        $this->actAsUser($this->seededUserId);
        $this->installFakeSession();
        $this->injectConnectionRegistry($registry);
        $this->injectSignalStore(new FakeSignalStore());

        $this->runStream($this->makeController());

        $this->assertSame($this->seededUserId, $registry->acquires[0]['userId'], 'the slot is scoped to the seeded user');
        $this->assertSame(self::SEEDED_GROUP_CAP, $this->acquiredCap($registry), 'the cap is read from the real group row, not the default');
    }

    /**
     * PROGRAMMATIC PATH: a non-numeric group cap is ignored, never read as "unlimited".
     *
     * Scope note — this feeds the value through {@see installConfig()}, i.e. by direct
     * property assignment, which is what a Registrar or another module's config code can
     * really do. It is NOT the `.env` path (see
     * {@see testEnvTypoOnAGroupCapIsDiscardedInsteadOfUnmeteringTheGroup()}, where CI4 has
     * already turned the same typo into 0). The `is_numeric()` filter exists for exactly
     * this seam, so the entry must be dropped, leaving the default.
     *
     * @return void
     */
    public function testNonNumericGroupCapIsIgnoredAndTheDefaultApplies(): void
    {
        $registry = new FakeConnectionRegistry();
        $this->installConfig(['superadmin' => self::ENV_TYPO]);

        $this->streamAsMemberOf(['superadmin'], $registry);

        $this->assertSame(self::DEFAULT_CAP, $this->acquiredCap($registry), 'a typo must not turn a capped group into an unmetered one');
    }

    /**
     * PROGRAMMATIC PATH: boolean and null group caps are ignored the same way a typo is.
     *
     * Neither value can reach the table through `.env` (CI4 casts to int first); both can
     * through a Registrar, which is the seam {@see installConfig()} models.
     *
     * @return void
     */
    public function testBooleanAndNullGroupCapsAreIgnored(): void
    {
        $registry = new FakeConnectionRegistry();
        $this->installConfig(['superadmin' => true, 'auditor' => null]);

        $this->streamAsMemberOf(['superadmin', 'auditor'], $registry);

        $this->assertSame(self::DEFAULT_CAP, $this->acquiredCap($registry), 'only numeric entries may override the default');
    }

    /**
     * PROGRAMMATIC PATH: a numeric STRING cap is still honoured.
     *
     * The `is_numeric()` filter must reject typos without also rejecting a caller that
     * simply assigned `'10'` instead of `10`.
     *
     * @return void
     */
    public function testNumericStringGroupCapIsStillHonoured(): void
    {
        $registry = new FakeConnectionRegistry();
        $this->installConfig(['superadmin' => (string) self::PRIVILEGED_CAP]);

        $this->streamAsMemberOf(['superadmin'], $registry);

        $this->assertSame(self::PRIVILEGED_CAP, $this->acquiredCap($registry), 'the type filter must not reject a legitimate numeric string');
    }

    /**
     * PROGRAMMATIC PATH: a discarded entry does not drag a valid matching group down.
     *
     * Before the guard was split, a broken entry became 0 and 0 won the min() escape-hatch
     * check — so one bad row disabled the cap for every group the caller belongs to, not
     * just its own. It must now simply drop out and let the surviving row decide.
     *
     * @return void
     */
    public function testDiscardedGroupCapDoesNotUnlockTheEscapeHatch(): void
    {
        $registry = new FakeConnectionRegistry();
        $this->installConfig([
            'superadmin' => self::ENV_TYPO,
            'auditor'    => self::SECONDARY_CAP,
        ]);

        $this->streamAsMemberOf(['superadmin', 'auditor'], $registry);

        $this->assertSame(self::SECONDARY_CAP, $this->acquiredCap($registry), 'the surviving numeric entry decides, and it is still finite');
    }

    /**
     * REAL `.env` PATH: a typo on the default cap fails closed instead of disabling the guard.
     *
     * This is the scenario the whole sentinel split exists for, and the only test that
     * proves it, because it is the only one whose value crosses
     * BaseConfig::initEnvValue(). `notificationsconfig.realtimeConnCapDefault = ten` never
     * reaches the controller as a string: the property is declared `int`, so CI4 casts it
     * to 0 first. Under the old "0 = unlimited" reading, that single typo silently removed
     * the connection cap for every user on the installation.
     *
     * @return void
     */
    public function testEnvTypoOnTheDefaultCapFailsClosedToTheFallback(): void
    {
        $config = $this->hydrateConfigFromEnv([
            'realtimeEnabled'        => 'true',
            'realtimeStreamTtl'      => '0',
            'realtimeConnCapDefault' => self::ENV_TYPO,
        ]);

        $this->assertSame(
            self::INVALID_CAP,
            $config->realtimeConnCapDefault,
            'precondition: CI4 casts the typo to 0 before a single line of module code runs'
        );

        $registry = new FakeConnectionRegistry();
        $this->streamAsMemberOf([], $registry);

        $this->assertSame(
            NotificationsConfig::CONN_CAP_FALLBACK,
            $this->acquiredCap($registry),
            'a mistyped .env line must leave the guard armed, not switch it off'
        );
    }

    /**
     * REAL `.env` PATH: a typo on a group cap drops the row instead of unmetering the group.
     *
     * Same cast, one level deeper: initEnvValue() recurses over the by-group table, so
     * `notificationsconfig.realtimeConnCapByGroup.superadmin = ten` lands as 0 — and 0 for
     * the single shipped row used to hand the escape hatch to the most privileged group of
     * all. The default here is deliberately NOT the fallback constant, so the assertion
     * cannot pass by landing on the wrong number for the right reason.
     *
     * @return void
     */
    public function testEnvTypoOnAGroupCapIsDiscardedInsteadOfUnmeteringTheGroup(): void
    {
        $config = $this->hydrateConfigFromEnv([
            'realtimeEnabled'                   => 'true',
            'realtimeStreamTtl'                 => '0',
            'realtimeConnCapDefault'            => (string) self::DEFAULT_CAP,
            'realtimeConnCapByGroup.superadmin' => self::ENV_TYPO,
        ]);

        $this->assertSame(
            ['superadmin' => self::INVALID_CAP],
            $config->realtimeConnCapByGroup,
            'precondition: the typo arrives in the table as int 0, never as the raw string'
        );
        $this->assertSame(self::DEFAULT_CAP, $config->realtimeConnCapDefault, 'precondition: the default itself hydrated cleanly');

        $registry = new FakeConnectionRegistry();
        $this->streamAsMemberOf(['superadmin'], $registry);

        $this->assertSame(
            self::DEFAULT_CAP,
            $this->acquiredCap($registry),
            'the broken row is discarded, so the caller drops to the default rather than becoming unmetered'
        );
    }

    /**
     * REAL `.env` PATH: `-1` really does reach the code as the unlimited escape hatch.
     *
     * The positive control for the two tests above: a value an operator writes on purpose
     * survives the int cast intact, which is precisely why NEGATIVE — and not 0 — is the
     * sentinel a typo can never counterfeit.
     *
     * @return void
     */
    public function testEnvUnlimitedSentinelSurvivesHydrationAndSkipsTheRegistry(): void
    {
        $config = $this->hydrateConfigFromEnv([
            'realtimeEnabled'        => 'true',
            'realtimeStreamTtl'      => '0',
            'realtimeConnCapDefault' => (string) self::UNLIMITED_CAP,
        ]);

        $this->assertSame(self::UNLIMITED_CAP, $config->realtimeConnCapDefault, 'precondition: a deliberate -1 is not mangled by the cast');

        $registry                 = new FakeConnectionRegistry();
        $registry->failIfAcquired = true;

        $this->streamAsMemberOf([], $registry);

        $this->assertSame([], $registry->acquires, 'the documented escape hatch works through the documented channel');
    }

    /**
     * An invalid (0) default still READS the by-group table; a matching row wins.
     *
     * A misconfigured default must degrade as little as possible: it costs the caller
     * the fallback cap only when nothing else applies. The shipped `['superadmin' => 10]`
     * row keeps working, so a typo in one `.env` line does not quietly downgrade every
     * privileged operator to 6 connections either.
     *
     * @return void
     */
    public function testInvalidDefaultCapStillHonoursAMatchingByGroupRow(): void
    {
        $registry = new FakeConnectionRegistry();

        $this->installConfig(['superadmin' => self::PRIVILEGED_CAP], 0, self::INVALID_CAP);

        $this->streamAsMemberOf(['superadmin'], $registry);

        $this->assertSame(self::PRIVILEGED_CAP, $this->acquiredCap($registry), 'the by-group row outranks the fallback the invalid default triggered');
    }

    /**
     * A NEGATIVE default cap is the global escape hatch: the by-group table is not read.
     *
     * An operator opening the escape hatch does not expect the shipped
     * `['superadmin' => 10]` row to keep matching and re-arm the guard.
     *
     * @return void
     */
    public function testNegativeDefaultCapIgnoresAPopulatedByGroupTable(): void
    {
        $registry                 = new FakeConnectionRegistry();
        $registry->failIfAcquired = true;

        $this->installConfig(['superadmin' => self::PRIVILEGED_CAP], 0, self::UNLIMITED_CAP);

        $this->streamAsMemberOf(['superadmin'], $registry);

        $this->assertSame([], $registry->acquires, 'a negative default short-circuits before the table is even looked at');
    }

    /**
     * Pins the shipped cap default and slot-ttl buffer against silent drift.
     *
     * The default is read off the class declaration rather than an instance, so a local
     * `.env` override cannot make the shipped value look like something else. Both
     * numbers are product decisions: 6 keeps a multi-tab admin under the cap while
     * staying safe for a typical pm.max_children, and a 5s buffer keeps an orphaned slot
     * from eating the caller's own cap for long.
     *
     * @return void
     */
    public function testShippedDefaultsPinTheCapAndTheTtlBuffer(): void
    {
        $declared = (new ReflectionClass(NotificationsConfig::class))->getDefaultProperties();

        $this->assertSame(self::SHIPPED_DEFAULT_CAP, $declared['realtimeConnCapDefault'], 'the shipped per-identity connection cap is 6');
        $this->assertSame(['superadmin' => self::PRIVILEGED_CAP], $declared['realtimeConnCapByGroup'], 'the shipped by-group table still carries exactly the superadmin row');

        $controller = new ReflectionClass(RealtimeController::class);

        $this->assertSame(self::CONN_TTL_BUFFER, $controller->getConstant('CONN_TTL_BUFFER_SECONDS'), 'the slot buffer mirrored by this class is the one the controller uses');
        $this->assertSame(self::STREAM_TTL_CAP, $controller->getConstant('STREAM_TTL_CAP_SECONDS'), 'the hard stream ttl cap mirrored by this class is the one the controller uses');
    }

    /**
     * The fail-closed fallback is a real cap and is exactly what shipping would apply.
     *
     * Two invariants in one place. The fallback must be POSITIVE, or "failing closed"
     * would quietly mean "unlimited" — the failure it was introduced to prevent. And it
     * must equal the shipped default, so a broken `.env` line degrades to the behaviour
     * of no `.env` line at all rather than to some third, undocumented cap.
     *
     * @return void
     */
    public function testTheFailClosedFallbackIsPositiveAndMatchesTheShippedDefault(): void
    {
        $declared = (new ReflectionClass(NotificationsConfig::class))->getDefaultProperties();

        $this->assertSame(self::SHIPPED_DEFAULT_CAP, NotificationsConfig::CONN_CAP_FALLBACK, 'the fail-closed fallback is the documented 6');
        $this->assertGreaterThan(self::INVALID_CAP, NotificationsConfig::CONN_CAP_FALLBACK, 'a fallback of 0 or less would be no guard at all');
        $this->assertSame(
            NotificationsConfig::CONN_CAP_FALLBACK,
            $declared['realtimeConnCapDefault'],
            'a config that hydrates invalid must land on exactly what the shipped default would have given'
        );
    }

    /**
     * The `.env` seam is reversible: every key it touches goes back exactly as it was.
     *
     * The hydration tests write REAL environment keys, and PHPUnit runs with
     * backupGlobals="false", so a key left behind would silently reconfigure every config
     * instantiated afterwards — including in other test classes, where the symptom would
     * look like anything but an environment leak. The baseline is measured rather than
     * hardcoded, so the test says the same thing on a box whose `.env` does pin the cap.
     *
     * @return void
     */
    public function testTheEnvSeamRestoresEveryKeyItTouches(): void
    {
        $key      = self::ENV_PREFIX . 'realtimeConnCapDefault';
        $existed  = array_key_exists($key, $_ENV);
        $original = $_ENV[$key] ?? null;
        $baseline = (new NotificationsConfig())->realtimeConnCapDefault;

        $this->setEnv($key, self::ENV_TYPO);

        $this->assertSame(self::INVALID_CAP, (new NotificationsConfig())->realtimeConnCapDefault, 'precondition: the override really reaches a freshly built config');

        $this->restoreEnv();

        $this->assertSame($existed, array_key_exists($key, $_ENV), 'a key absent before must be absent again, a present one must survive');
        $this->assertSame($original, $_ENV[$key] ?? null, 'and it carries exactly the value found before');
        $this->assertSame($baseline, (new NotificationsConfig())->realtimeConnCapDefault, 'later configs hydrate the way they did before this test ran');
    }

    /**
     * An invalid cap default is logged as a warning — once per process, not once per stream.
     *
     * A misconfiguration that silently fails closed is a misconfiguration nobody fixes, so
     * it has to be visible. But the SSE endpoint reconnects every ttl from every open tab,
     * so logging unconditionally would bury the log under the same line for as long as the
     * `.env` stays broken. Hence the latch, and hence the second stream below.
     *
     * @return void
     */
    public function testInvalidCapDefaultIsWarnedAboutExactlyOncePerProcess(): void
    {
        $this->installConfig([], 0, self::INVALID_CAP);
        $this->streamAsMemberOf([], new FakeConnectionRegistry());

        $this->assertLogContains('warning', 'realtimeConnCapDefault is 0');
        $this->assertLogContains('warning', 'or to -1 for unlimited');
        $this->assertCount(1, $this->loggedWarnings(), 'the misconfiguration is reported once');

        // A second stream in the same process is the reconnect the latch exists for.
        $this->streamAsMemberOf([], new FakeConnectionRegistry());

        $this->assertCount(1, $this->loggedWarnings(), 'every later reconnect stays silent instead of flooding the log');
    }

    /**
     * A valid cap default logs nothing at all.
     *
     * The negative control for the test above: without it, a warning fired on every
     * resolve would still satisfy "was it logged once" after a single stream.
     *
     * @return void
     */
    public function testAValidCapDefaultLogsNoWarning(): void
    {
        $this->installConfig(['superadmin' => self::PRIVILEGED_CAP]);
        $this->streamAsMemberOf(['superadmin'], new FakeConnectionRegistry());

        $this->assertSame([], $this->loggedWarnings(), 'a correctly configured cap is not something to warn about');
    }

    /**
     * The 429 tells the client when to come back and forbids MIME sniffing.
     *
     * Retry-After is the slot lifetime, i.e. the earliest moment capacity can free up;
     * anything shorter invites a reconnect storm from the very clients the cap exists to
     * throttle. nosniff keeps the short plain-text body from being re-interpreted.
     *
     * @return void
     */
    public function testRejectedStreamCarriesRetryAfterAndNosniff(): void
    {
        $registry              = new FakeConnectionRegistry();
        $registry->denyAcquire = true;
        $streamTtl             = 30;

        $this->installConfig([], $streamTtl);

        $response = $this->streamAsMemberOf([], $registry);

        $this->assertSame(429, $response->getStatusCode(), 'the denied slot answers Too Many Requests');
        $this->assertSame(
            (string) ($streamTtl + self::CONN_TTL_BUFFER),
            $response->getHeaderLine('Retry-After'),
            'Retry-After is the slot lifetime, so the client waits until a slot can actually free up'
        );
        $this->assertSame($streamTtl + self::CONN_TTL_BUFFER, $registry->acquires[0]['ttl'], 'Retry-After and the reserved slot lifetime are the same number');
        $this->assertSame('nosniff', $response->getHeaderLine('X-Content-Type-Options'), 'the plain-text rejection must not be sniffed into another type');
    }

    /**
     * A rejected stream costs one group query and never derives the channel list.
     *
     * topicsFor() runs its OWN group lookup, so deriving channels before the cap gate
     * doubled the DB cost of exactly the requests the cap exists to shed. The granted
     * control run proves the counter really tracks the call.
     *
     * @return void
     */
    public function testRejectedStreamNeverDerivesTheTopicList(): void
    {
        $registry              = new FakeConnectionRegistry();
        $registry->denyAcquire = true;

        $this->actAsUser(self::SESSION_USER_ID);
        $this->installFakeSession();
        $denied = $this->injectNotifierWithGroups([]);
        $this->injectConnectionRegistry($registry);
        $this->injectSignalStore(new FakeSignalStore());
        $this->installConfig([]);

        $this->runStream($this->makeController());

        $this->assertSame([self::SESSION_USER_ID], $denied->groupsForCalls, 'the cap policy still needs exactly one group lookup');
        $this->assertSame([], $denied->topicsForCalls, 'a rejected request must not pay for the channel list');

        // Control: the same double DOES record a topicsFor() call once a slot is granted.
        $granted = $this->injectNotifierWithGroups([]);
        $this->injectConnectionRegistry(new FakeConnectionRegistry());

        $this->runStream($this->makeController());

        $this->assertSame([self::SESSION_USER_ID], $granted->topicsForCalls, 'a granted stream derives its channels exactly once');
    }

    /**
     * A granted slot arms exactly one shutdown release, and the fast path still runs.
     *
     * @return void
     */
    public function testGrantedSlotArmsExactlyOneShutdownRelease(): void
    {
        $registry = new FakeConnectionRegistry();
        $this->installConfig([]);

        $this->streamAsMemberOf([], $registry);

        $this->assertTrue(ShutdownSpy::isInstalled(), 'the shutdown shim must shadow the global function for this assertion to mean anything');
        $this->assertSame(1, ShutdownSpy::count(), 'a granted slot arms exactly one shutdown release');
        $this->assertCount(1, $registry->releases, 'the normal finish already handed the slot back through the fast path');
    }

    /**
     * The shutdown callback is idempotent: after a normal finish it releases nothing more.
     *
     * PHP runs shutdown callbacks after the response is over, so without the shared
     * $released flag every clean stream would ZREM a slot id it no longer owns — and
     * that id may by then belong to a reconnected stream of the same user.
     *
     * @return void
     */
    public function testShutdownCallbackDoesNotReleaseTwiceAfterANormalFinish(): void
    {
        $registry = new FakeConnectionRegistry();
        $this->installConfig([]);

        $this->streamAsMemberOf([], $registry);
        $this->assertCount(1, $registry->releases, 'precondition: the fast path released the slot');

        ShutdownSpy::invokeAll();

        $this->assertCount(1, $registry->releases, 'the shutdown callback must see the slot as already released');
    }

    /**
     * When shutdown wins the race, the slot is released once and the fast path stands down.
     *
     * Arming invokeImmediately fires the callback the instant stream() registers it,
     * which is the abort case: under ignore_user_abort(false) a dropped client ends the
     * script mid-write and the code after the poll loop never executes.
     *
     * @return void
     */
    public function testShutdownReleasesTheSlotWhenTheFastPathIsPreEmpted(): void
    {
        $registry         = new FakeConnectionRegistry();
        $registry->connId = 'phpunit-conn-' . bin2hex(random_bytes(4));

        ShutdownSpy::$invokeImmediately = true;
        $this->installConfig([]);

        $this->streamAsMemberOf([], $registry);

        $this->assertCount(1, $registry->releases, 'the shutdown path releases the slot exactly once, and the fast path does not repeat it');
        $this->assertSame(
            ['userId' => self::SESSION_USER_ID, 'connId' => $registry->connId],
            $registry->releases[0],
            'the shutdown callback hands back the very slot acquire() granted'
        );
    }

    /**
     * Streams that never reserved a slot arm no shutdown callback at all.
     *
     * @return void
     */
    public function testStreamsWithoutASlotArmNoShutdownCallback(): void
    {
        $denying              = new FakeConnectionRegistry();
        $denying->denyAcquire = true;

        $this->installConfig([]);
        $this->streamAsMemberOf([], $denying);

        $this->assertSame(0, ShutdownSpy::count(), 'the 429 branch has no slot to hand back');

        ShutdownSpy::reset();

        $unlimited                 = new FakeConnectionRegistry();
        $unlimited->failIfAcquired = true;

        $this->installConfig([], 0, self::UNLIMITED_CAP);
        $this->streamAsMemberOf([], $unlimited);

        $this->assertSame(0, ShutdownSpy::count(), 'the unlimited path never touches the registry, so it arms nothing');
    }
}

<?php

declare(strict_types=1);

namespace Tests\Modules\Settings;

use CodeIgniter\Test\CIUnitTestCase;
use Modules\Backend\Libraries\RunLock;
use Modules\Settings\Libraries\UpdateService;
use ReflectionProperty;
use Tests\Support\Settings\LockStateProbe;
use Tests\Support\Settings\StubCurlRequest;

/**
 * UpdateService::applyUpdate() lock lifecycle tests.
 *
 * A2 moved `applyUpdate()` off its old TTL/mtime lock onto
 * `Modules\Backend\Libraries\RunLock` at `WRITEPATH.'locks/updater.lock'`, with
 * `release()` now living inside a `finally` block instead of being called by hand
 * on the success and `catch (\Exception)` branches only. This file is the
 * regression lock for that change — referenced by name from
 * `UpdateGateTest::testApplyUpdateRejectsUnsignedContentWithoutTouchingDisk()`
 * and `testApplyUpdateRejectsAnEmptyFileSetWithSignedHashes()`, whose own
 * assertions only cover the two early-return branches that never reach
 * `RunLock::acquire()` at all.
 *
 * Two different claims live here, deliberately kept apart:
 *  - "the lock is not held once applyUpdate() has returned or thrown" — the
 *    invariant every caller depends on, covered by the success, \Error and
 *    file-on-disk tests. `$lock` is a local, so this stays true (and those
 *    tests stay green) even with the `finally` deleted: unwinding the frame
 *    drops the refcount, the handle closes and flock lets go by itself.
 *  - "the `finally` is what released it" — a strictly stronger claim, and the
 *    only one that would notice the `finally` being removed. It needs an
 *    observation window strictly between the `finally` and the frame teardown;
 *    `testTheLockIsFreeBeforeApplyUpdateDestroysItsLocals()` builds that window
 *    out of PHP's compiled-variable destruction order and is the single test in
 *    this file that turns red when the `finally` goes away.
 *
 * Every test here reaches the real `acquire()` call, which means every test
 * shares the SAME lock path across the whole PHPUnit process (the path is
 * hardcoded inside `applyUpdate()`, not injectable) — each test tracks every
 * `RunLock` it opens in `$locksToRelease` and releases them all in `tearDown()`
 * so a failing assertion never leaves the flock held for the rest of the suite.
 *
 * The happy-path helper (`applySuccessfully()`) calls the real
 * `applyUpdate()` all the way through to `return ['result' => true, ...]`,
 * which also runs `updateEnvVersion()` and `runMigrations()` for real. Two
 * measures keep that safe:
 *  - `.env` is made temporarily read-only (`is_writable()` gates the write in
 *    `updateEnvVersion()`) so the project's real `.env` is never mutated;
 *    content is fingerprinted before/after as proof.
 *  - `runMigrations()` runs the real `App` namespace migrations, but both are
 *    already applied in `ci4ms_test` (verified independently below) so
 *    `latest()` is a no-op; the migration row count is compared before/after
 *    as proof no new one got applied.
 * `cache()->clean()` (called on the success path too) is left alone: it only
 * wipes the file cache under `WRITEPATH.'cache/'`, the same effect as the
 * documented `php spark cache:clear`, and is fully recoverable.
 *
 * @internal
 */
final class UpdateServiceRunLockTest extends CIUnitTestCase
{
    /** Harmless scratch path under WRITEPATH the write loop is allowed to touch. */
    private const PROBE_PATH = 'writable/tmp/qa_update_run_lock_probe.txt';

    /**
     * Every RunLock this test class opened; released unconditionally in tearDown().
     *
     * @var list<RunLock>
     */
    private array $locksToRelease = [];

    /** Scratch directory standing in for WRITEPATH.'backups/'; removed in tearDown(). */
    private ?string $scratchDir = null;

    /**
     * Original `.env` permission bits, set only while `applySuccessfully()` has
     * made it temporarily read-only — the safety net that restores them even if
     * the calling test fails an assertion before its own restore runs.
     */
    private ?int $envPermsBeforeReadOnly = null;

    /**
     * Whether `WRITEPATH.'locks/updater.lock'` already existed before this
     * test ran — this class is the only test suite that ever reaches
     * `RunLock::acquire()` on that exact path (`UpdateGateTest` only exercises
     * the two early-return branches that never construct a `RunLock`), and
     * `release()` intentionally never `unlink()`s it (see test 4's docblock).
     * Left behind, the file would make `UpdateGateTest`'s
     * `assertFileDoesNotExist($lockFile)` assertions fail for a completely
     * unrelated reason whenever this file runs first in the same PHPUnit
     * process. Removing a file this class itself created in `tearDown()`
     * does not weaken any assertion here — persistence is asserted mid-test,
     * well before this cleanup runs.
     */
    private bool $lockFileExistedBeforeTest = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->lockFileExistedBeforeTest = is_file($this->lockFile());
    }

    protected function tearDown(): void
    {
        if ($this->envPermsBeforeReadOnly !== null) {
            chmod($this->envPath(), $this->envPermsBeforeReadOnly);
            $this->envPermsBeforeReadOnly = null;
        }

        foreach ($this->locksToRelease as $lock) {
            $lock->release();
        }
        $this->locksToRelease = [];

        if (! $this->lockFileExistedBeforeTest && is_file($this->lockFile())) {
            unlink($this->lockFile());
        }

        $probeFile = ROOTPATH . self::PROBE_PATH;
        if (is_file($probeFile)) {
            unlink($probeFile);
        }

        if ($this->scratchDir !== null) {
            $this->removeTree($this->scratchDir);
            $this->scratchDir = null;
        }

        parent::tearDown();
    }

    /**
     * applyUpdate() successfully completing (the true `return ['result' => true, ...]`
     * branch, no exception anywhere) leaves the lock unheld — proven by a fresh
     * RunLock on the same path acquiring right after.
     *
     * Scope of this assertion: it pins "the caller gets the lock back", not
     * WHICH mechanism handed it back. `$lock` is a local of `applyUpdate()`, so
     * once the frame is gone the handle closes and flock lets go on its own;
     * this test therefore stays green even with the `finally` deleted. The
     * `finally` itself is pinned by
     * `testTheLockIsFreeBeforeApplyUpdateDestroysItsLocals()`.
     *
     * @return void
     */
    public function testSuccessfulApplyUpdateReleasesTheLockForANewAcquire(): void
    {
        $this->assertFileDoesNotExist(ROOTPATH . self::PROBE_PATH, 'test setup collision: probe file already exists');

        $envFingerprintBefore = $this->envFingerprint();
        $appMigrationsBefore  = $this->countAppMigrations();

        $result = $this->applySuccessfully();

        $this->assertTrue($result['result'], 'applyUpdate() must report success on the happy path');
        $this->assertSame(1, $result['applied_count'] ?? null);
        $this->assertFileExists(ROOTPATH . self::PROBE_PATH);

        $this->assertSame(
            $envFingerprintBefore,
            $this->envFingerprint(),
            '.env content must stay untouched — it was kept read-only for the duration of the call.',
        );
        $this->assertSame(
            $appMigrationsBefore,
            $this->countAppMigrations(),
            'runMigrations() must not have applied a new App migration — both are already current in ci4ms_test.',
        );

        $freshLock = new RunLock($this->lockFile());
        $this->assertTrue(
            $freshLock->acquire(),
            'A fresh RunLock on the same path must acquire immediately after a successful applyUpdate() — the lock '
                . 'must not still be held once the call has returned.',
        );
        $this->locksToRelease[] = $freshLock;
    }

    /**
     * Regression test for A2: the old code only had `catch (\Exception $e)` with
     * `release()` called by hand inside the success and catch branches — an
     * `\Error`/`\ValueError` (never an `\Exception`) would skip both and leak the
     * lock. `release()` now lives in `finally`, which runs for every Throwable.
     *
     * Trigger: `$latestVersion` is interpolated straight into `$backupDir`
     * (`UpdateService.php:374`) with no validation anywhere in `applyUpdate()`
     * (only the paths inside `$filesContent` go through `safeTargetPath()`). A
     * null byte in `$latestVersion` therefore reaches `mkdir($backupDir, ...)`
     * (`UpdateService.php:379`) — the very first statement inside the `try`
     * block, right after a successful `acquire()` — and PHP 8's filesystem
     * null-byte guard throws `\ValueError` there (confirmed with a standalone
     * `mkdir()` probe: `ValueError: mkdir(): Argument #1 ($directory) must not
     * contain any null bytes`, `$e instanceof \Error` true, `instanceof
     * \Exception` false). Because this happens before the write loop even
     * starts, nothing in `$filesContent` is ever written.
     *
     * Scope of the lock assertion below: it pins "an escaping \Error leaves the
     * lock unheld", which is the invariant callers depend on and which would go
     * red if nothing released the lock at all. It does NOT pin the `finally`
     * itself — see `testTheLockIsFreeBeforeApplyUpdateDestroysItsLocals()` for
     * that, and the note on this file's class docblock for why the two are
     * different claims. The probe technique used there cannot be reused on this
     * branch: `zend.exception_ignore_args` is Off in this environment, so the
     * escaping \ValueError keeps the argument arrays alive in its backtrace and
     * the parameter would never be destroyed inside the frame teardown.
     *
     * @return void
     */
    public function testApplyUpdateReleasesTheLockWhenAnUncaughtErrorEscapesTheTryBlock(): void
    {
        $service = $this->service();
        $content = "qa error-path probe\n";

        $thrown = null;

        try {
            $service->applyUpdate(
                "1.0.0\0evil",
                [self::PROBE_PATH => $content],
                [],
                [self::PROBE_PATH => hash('sha256', $content)],
            );
            $this->fail('applyUpdate() must let the ValueError escape uncaught — catch (\Exception) cannot catch it.');
        } catch (\Throwable $e) {
            $thrown = $e;
        }

        $this->assertInstanceOf(
            \Error::class,
            $thrown,
            'The trigger must be a genuine \Error (\ValueError) — that is exactly the class catch (\Exception) misses.',
        );
        $this->assertNotInstanceOf(\Exception::class, $thrown);
        $this->assertFileDoesNotExist(
            ROOTPATH . self::PROBE_PATH,
            'mkdir() fails before the write loop runs — the probe file must never be written.',
        );

        $freshLock = new RunLock($this->lockFile());
        $this->assertTrue(
            $freshLock->acquire(),
            'An \Error (not \Exception) propagating out of applyUpdate() must not leave the lock held — the old '
                . 'hand-rolled release() calls on the success and catch branches alone would have.',
        );
        $this->locksToRelease[] = $freshLock;
    }

    /**
     * A RunLock already holding `WRITEPATH.'locks/updater.lock'` makes
     * applyUpdate() refuse the work — `updateInProgress`, no write, `.env`
     * untouched. Covers the branch just after `acquire()` returns `false`
     * (`UpdateService.php:369-371`), distinct from the two early-return branches
     * already covered by `UpdateGateTest`'s unsigned/empty-set tests.
     *
     * @return void
     */
    public function testApplyUpdateRefusesToRunWhileAnotherProcessHoldsTheLock(): void
    {
        $holder = new RunLock($this->lockFile());
        $this->assertTrue($holder->acquire(), 'setup: the holder must win the lock first');
        $this->locksToRelease[] = $holder;

        $envFingerprintBefore = $this->envFingerprint();

        $service = $this->service();
        $content = "qa contention probe\n";

        $result = $service->applyUpdate(
            '99.99.98',
            [self::PROBE_PATH => $content],
            [],
            [self::PROBE_PATH => hash('sha256', $content)],
        );

        $this->assertFalse($result['result']);
        $this->assertSame(lang('Settings.updateInProgress'), $result['message']);
        $this->assertFileDoesNotExist(ROOTPATH . self::PROBE_PATH, 'a rejected applyUpdate() must not write anything');
        $this->assertSame(
            $envFingerprintBefore,
            $this->envFingerprint(),
            '.env must stay untouched on the contention-rejection path',
        );
    }

    /**
     * `RunLock::release()` intentionally never `unlink()`s the lock file —
     * deleting a flock'd file is a classic TOCTOU/inode-reuse hazard
     * (`RunLock.php:216-227`). So after a successful `applyUpdate()` the lock
     * file at `WRITEPATH.'locks/updater.lock'` is expected to still be sitting
     * on disk; this is NOT a leak. The file's mere existence does not mean the
     * lock is still held — that is only proven by actually re-acquiring it,
     * exactly like `testSuccessfulApplyUpdateReleasesTheLockForANewAcquire()`
     * already does; this test asserts both facts together explicitly.
     *
     * @return void
     */
    public function testReleasedLockFileStaysOnDiskButIsNotStillHeld(): void
    {
        $this->applySuccessfully();

        $this->assertFileExists(
            $this->lockFile(),
            'RunLock::release() intentionally never unlink()s the lock file — its presence is expected, not a leak.',
        );

        $freshLock = new RunLock($this->lockFile());
        $this->assertTrue(
            $freshLock->acquire(),
            'The lock file existing on disk does not mean the lock is still held — only a successful re-acquire proves that.',
        );
        $this->locksToRelease[] = $freshLock;
    }

    /**
     * The `finally { $lock->release(); }` at `UpdateService.php:450-452` is what
     * actually releases the lock — not the implicit refcount drop that happens a
     * moment later when the frame is torn down.
     *
     * Why the release tests above cannot show this: `$lock` is a local of
     * `applyUpdate()`, so once the frame is gone its handle closes and flock
     * lets go regardless. Measured, not assumed — deleting the `finally` from
     * UpdateService.php leaves every other test in this file green and turns
     * only this one red.
     *
     * The window this test uses: Zend frees a frame's compiled variables in
     * ascending slot order (`zend_free_compiled_variables()`) and the calling
     * convention puts arguments in slots `0..num_args-1`, so `$allChangedFiles`
     * (a parameter) is destroyed strictly BEFORE `$lock` (a local). A
     * `LockStateProbe` riding inside `$allChangedFiles` therefore runs its
     * destructor at a point where the refcount release provably has not happened
     * yet, and an independent `acquire()` there answers the actual question:
     *
     *  - `finally` present → probe acquires → the release was control-flow driven
     *  - `finally` deleted → probe is refused → nothing had released it yet
     *
     * This is the same instrument
     * `Ci4msMigrateCommandTest::testRunLockIsReleasedByTheSignalSafeInnerReleaseNotJustTheOuterFinally()`
     * uses (a probe `acquire()` from inside an injected callback); only the
     * window differs, because `applyUpdate()` has no injectable seam and no
     * second release point to observe between.
     *
     * Three shape constraints keep the measurement honest, and breaking any of
     * them turns the test into a false pass rather than a failure — hence the
     * control assertion and the "probe ran" assertion below:
     *  - the probe must NOT be the last `$allChangedFiles` entry, or the loop's
     *    `$f` (a local, freed after `$lock`) keeps it alive too long;
     *  - the argument array must be built inline in the `applyUpdate()` call, or
     *    this method's own reference keeps its refcount above zero;
     *  - the call must take the success path: with `zend.exception_ignore_args`
     *    Off, a throwing branch would park the arguments in a backtrace instead.
     *
     * @return void
     */
    public function testTheLockIsFreeBeforeApplyUpdateDestroysItsLocals(): void
    {
        $probeRan    = false;
        $lockWasFree = null;
        $record      = static function (bool $free) use (&$probeRan, &$lockWasFree): void {
            $probeRan    = true;
            $lockWasFree = $free;
        };

        // Control: the probe must be able to see a HELD lock, otherwise the
        // measurement below would read "free" no matter what the code does.
        $holder = new RunLock($this->lockFile());
        $this->assertTrue($holder->acquire(), 'control setup: the holder must win the lock first');
        $control = new LockStateProbe($this->lockFile(), $record);
        unset($control);
        $this->assertTrue($probeRan, 'control: the probe destructor must have run on unset()');
        $this->assertFalse($lockWasFree, 'control: a probe must report false while another RunLock holds the file');
        $holder->release();

        $probeRan    = false;
        $lockWasFree = null;

        $result = $this->withEnvKeptReadOnly(function () use ($record): array {
            $service = $this->service();
            $content = "qa finally-window probe\n";

            return $service->applyUpdate(
                '99.99.99',
                [self::PROBE_PATH => $content],
                [
                    new LockStateProbe($this->lockFile(), $record),
                    ['status' => 'modified', 'filename' => 'qa-tail-entry'],
                ],
                [self::PROBE_PATH => hash('sha256', $content)],
            );
        });

        $this->assertTrue($result['result'], 'Setup invariant: the measurement is only valid on the success path.');
        $this->assertTrue(
            $probeRan,
            'The probe destructor never ran inside applyUpdate() — something is still holding a reference to it, so '
                . 'the assertion below would be measuring nothing.',
        );
        $this->assertTrue(
            $lockWasFree,
            'The lock was still held while applyUpdate() was destroying its parameters, i.e. nothing released it on '
                . 'the way out — finally { $lock->release(); } is gone or no longer runs.',
        );
    }

    /**
     * Runs a real applyUpdate() success path while keeping its two ambient
     * side effects harmless: `.env` is made read-only for the duration of the
     * call (`updateEnvVersion()` no-ops when `is_writable()` is false) and
     * `backupBaseDir` is redirected to a scratch directory so nothing lands
     * under the real `writable/backups/`.
     *
     * @return array<string, mixed> Same shape UpdateService::applyUpdate() itself declares (@return array)
     */
    private function applySuccessfully(): array
    {
        return $this->withEnvKeptReadOnly(function (): array {
            $service = $this->service();
            $content = "qa run-lock lifecycle probe\n";

            return $service->applyUpdate(
                '99.99.99',
                [self::PROBE_PATH => $content],
                [],
                [self::PROBE_PATH => hash('sha256', $content)],
            );
        });
    }

    /**
     * Runs $call with `.env` chmod'ed to 0444, restoring the bits afterwards.
     *
     * Split out of `applySuccessfully()` so a caller that needs to build the
     * `applyUpdate()` argument list itself still gets the same protection —
     * `testTheLockIsFreeBeforeApplyUpdateDestroysItsLocals()` cannot delegate
     * the call, because its probe must be created inline in the argument list.
     *
     * @param \Closure(): array<string, mixed> $call Performs the applyUpdate() call
     *
     * @return array<string, mixed> Whatever $call returned
     */
    private function withEnvKeptReadOnly(\Closure $call): array
    {
        $envPath = $this->envPath();
        $this->envPermsBeforeReadOnly = fileperms($envPath) & 0777;
        chmod($envPath, 0444);

        try {
            return $call();
        } finally {
            chmod($envPath, $this->envPermsBeforeReadOnly);
            $this->envPermsBeforeReadOnly = null;
        }
    }

    /**
     * An UpdateService whose backup base points at a scratch tree instead of
     * the real `writable/backups/`.
     */
    private function service(): UpdateService
    {
        $service = new UpdateService(new StubCurlRequest());

        (new ReflectionProperty(UpdateService::class, 'backupBaseDir'))
            ->setValue($service, $this->scratchBackupBase());

        return $service;
    }

    private function scratchBackupBase(): string
    {
        if ($this->scratchDir === null) {
            $this->scratchDir = sys_get_temp_dir() . '/ci4ms_update_runlock_test_' . bin2hex(random_bytes(6));
            mkdir($this->scratchDir, 0777, true);
        }

        return rtrim($this->scratchDir, '/') . '/backups/';
    }

    /** The lock path applyUpdate() hardcodes internally. */
    private function lockFile(): string
    {
        return WRITEPATH . 'locks/updater.lock';
    }

    private function envPath(): string
    {
        return ROOTPATH . '.env';
    }

    /**
     * SHA-256 of the project .env, used to prove a call never bumped the version.
     *
     * @return string Empty string when there is no .env to protect
     */
    private function envFingerprint(): string
    {
        $path = $this->envPath();

        return is_file($path) ? (string) hash_file('sha256', $path) : '';
    }

    /**
     * Rows in the migrations table for the `App` namespace — both of the
     * project's App-level migrations are already applied in `ci4ms_test`, so
     * this count must stay unchanged across a call that runs `runMigrations()`.
     */
    private function countAppMigrations(): int
    {
        return (int) \Config\Database::connect()
            ->table('migrations')
            ->where('namespace', 'App')
            ->countAllResults();
    }

    private function removeTree(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        foreach ((array) glob($dir . '/*') as $path) {
            $path = (string) $path;

            if (is_link($path) || is_file($path)) {
                unlink($path);
            } elseif (is_dir($path)) {
                $this->removeTree($path);
            }
        }

        rmdir($dir);
    }
}

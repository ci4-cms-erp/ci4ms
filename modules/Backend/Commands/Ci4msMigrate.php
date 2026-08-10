<?php

declare(strict_types=1);

namespace Modules\Backend\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use CodeIgniter\CLI\SignalTrait;
use Config\Database;
use Config\Services;
use Modules\MigrationManager\Libraries\RunLock;

/**
 * Applies all pending migrations across every namespace (App + Modules\*)
 * and records one audit row in `migration_runs`.
 *
 * Honest note on overlap with `php spark migrate --all`: the stock command
 * runs the exact same `setNamespace(null)->latest()` call this command does
 * (`vendor/codeigniter4/framework/system/Commands/Database/Migrate.php:82-83`)
 * — they are NOT functionally different in what gets migrated, and bare
 * `php spark migrate` (no flag) is the one that only runs the App namespace.
 * The real, verified difference is this command additionally writes a
 * `migration_runs` row per run (`kind='migration'`, sentinel `target='*'`
 * for the whole-namespace run, `run_source='cli'`) so CLI runs show up in
 * the same audit trail as superadmin web runs
 * (`modules/MigrationManager/Controllers/MigrationManager.php`) — `--all`
 * gives no such trail. This command is a standalone, re-runnable,
 * audited version of the migrate step in `ci4ms:setup`. Idempotent.
 *
 * Concurrency: this command now takes the same `Modules\MigrationManager\
 * Libraries\RunLock` the web run endpoints use
 * (`Controllers/MigrationManager.php:94-103,134-143`) so a CLI run and a
 * superadmin web run can't stomp on each other. This is a deliberate,
 * user-approved reverse dependency from `Modules\Backend` onto
 * `Modules\MigrationManager` — accepted because the alternative (a
 * duplicated locking library) was worse. The dependency is soft: a
 * `class_exists()` guard (`runLockClassName()`/`createRunLock()`) means
 * this command still runs, unlocked, on an install with
 * `Modules\MigrationManager` removed.
 *
 * Usage: php spark ci4ms:migrate
 */
class Ci4msMigrate extends BaseCommand
{
    use SignalTrait;

    protected $group       = 'Ci4MS';
    protected $name        = 'ci4ms:migrate';
    protected $description = 'Apply all pending migrations across every namespace (App + Modules).';
    protected $usage       = 'ci4ms:migrate';

    /**
     * Sentinel written to `migration_runs.target` for this whole-namespace
     * run. Cannot collide with a real namespace or seed FQCN: neither is a
     * syntactically valid PHP namespace segment / class name, so no code
     * path that fills `target` can ever produce `*`.
     */
    private const AUDIT_TARGET_ALL = '*';

    /**
     * Runs `MigrationRunner::latest()` across every PSR-4 namespace in
     * global timestamp order, reports what was actually applied, and
     * records one best-effort audit row in `migration_runs`.
     *
     * Uses a non-shared `MigrationRunner` (`Services::migrations(null, null,
     * false)`, produced by `createMigrationRunner()`) so `setNamespace(null)`
     * never leaks into the shared instance other callers (web
     * MigrationManager, `ci4ms:setup`) rely on — same pattern as
     * `Controllers/MigrationManager.php:229` and
     * `Libraries/MigrationInspector.php:58`. The runner is produced by a
     * separate `createMigrationRunner()` method purely for testability: it
     * can be overridden in a test subclass to inject a runner double whose
     * `latest()` throws, which is otherwise impossible to exercise since
     * `$getShared=false` makes `Services::injectMock('migrations', ...)`
     * ineffective — production behavior (non-shared, `setNamespace(null)`)
     * is unchanged. Applied migrations are determined by diffing
     * `getHistory()` before/after `latest()`
     * (`Controllers/MigrationManager.php::diffAppliedMigrations()` pattern),
     * not by assuming success — if nothing was pending this reports
     * "already up to date" instead of "applied".
     *
     * If `latest()` fails, `MigrationRunner::latest()` has already called
     * `regress(-1)` internally before returning/throwing
     * (`vendor/codeigniter4/framework/system/Database/MigrationRunner.php:210-211`),
     * rolling back the previous batch — this is surfaced explicitly via
     * `CLI::error()`, not left implicit.
     *
     * Acquires `Modules\MigrationManager\Libraries\RunLock` (via
     * `createRunLock()`, `null` if that module isn't installed) BEFORE
     * constructing the migration runner, so a CLI run can't race a
     * superadmin web run of the same lock and no runner is built at all
     * when the lock is already held. `createRunLock()` can throw
     * `\RuntimeException` (lock directory couldn't be created or isn't
     * writable, `RunLock::ensureDirectory()`); that call is wrapped in its
     * own `try`/`catch` and reported the same way as an already-held lock —
     * `EXIT_ERROR`, no runner touched. If the lock is already held, also
     * returns `EXIT_ERROR` immediately without calling `latest()`. The lock
     * is released from two places, deliberately: (1) inside the
     * `withSignalsBlocked()` closure, in its own `finally`, right after
     * `latest()` returns/throws — this is the ONLY point where releasing it
     * is signal-safe, because `SignalTrait` here never calls
     * `registerSignals()`; it only wraps `pcntl_sigprocmask(SIG_BLOCK/UNBLOCK,
     * ...)` around the closure
     * (`vendor/codeigniter4/framework/system/CLI/SignalTrait.php:240-249,
     * 255-284`), so outside that OS-level block window an unhandled
     * SIGTERM/SIGINT kills the process via the OS default disposition
     * *without running PHP's `finally`*. (2) the outer `try`/`finally` that
     * wraps the runner's execution (`:159`-`:192-194` — `getHistory()`,
     * `withSignalsBlocked()`, `diffAppliedMigrations()`, `recordRun()`), as a
     * safety net for anything that throws inside that block after the
     * closure has already returned. That outer `try` does NOT wrap "the
     * whole method body": `createMigrationRunner()` and
     * `$runner->setNamespace(null)` (right after the lock is acquired, at
     * `:156-157`) run while the lock is still held but outside any
     * `try`/`finally`. That narrow window needs no explicit handler either —
     * if either call throws, the local `$lock` variable goes out of scope
     * during stack unwind, its refcount drops to zero, the `RunLock`
     * instance is destructed immediately, and PHP closes its `$handle`
     * resource property as part of that destruction; closing the handle
     * releases the underlying `flock()` the instant it happens, independent
     * of any `finally`. This is a measured fact, not an assumption —
     * `ci4ms-perf` confirmed there is no real lock leak across this window.
     * None of these release paths — the two explicit `release()` calls above
     * or this implicit GC-driven one — can ever delete another process's
     * lock: `RunLock` tracks ownership per-instance (`$held`, set only when
     * `acquire()` genuinely wins the lock) and `release()` no-ops unless
     * this exact instance still holds it — so the second explicit call is a
     * true no-op: it does not call `flock()`/`fclose()` again on an
     * already-closed handle, which would otherwise raise a PHP `E_WARNING`
     * ("supplied resource is not a valid stream resource")
     * (`modules/MigrationManager/Libraries/RunLock.php::acquire()/release()`).
     *
     * @param array<int|string, string|null> $params
     *
     * @return int `EXIT_SUCCESS` (0) on success or when nothing was pending;
     *              `EXIT_ERROR` (1) if `latest()` failed and regressed, or if
     *              the run lock is already held by another run.
     */
    public function run(array $params): int
    {
        try {
            $lock = $this->createRunLock();
        } catch (\RuntimeException $e) {
            CLI::error('Failed to prepare the migration run lock: ' . $e->getMessage());

            return EXIT_ERROR;
        }

        if ($lock !== null && !$lock->acquire()) {
            CLI::error('Another migration/seed run is already in progress (MigrationManager run lock held). Try again once it finishes.');

            return EXIT_ERROR;
        }

        $runner = $this->createMigrationRunner();
        $runner->setNamespace(null);

        try {
            $group  = (string) config(Database::class)->defaultGroup;
            $before = $runner->getHistory($group);

            $startedAt    = microtime(true);
            $regressed    = false;
            $errorMessage = null;
            $result       = null;

            $this->withSignalsBlocked(function () use ($runner, $lock, &$result, &$regressed, &$errorMessage): void {
                try {
                    $result = $runner->latest();
                } catch (\Throwable $e) {
                    $regressed    = true;
                    $errorMessage = $e->getMessage();
                } finally {
                    $lock?->release();
                }
            });

            if ($result === false) {
                $regressed = true;
            }

            $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
            $after      = $runner->getHistory($group);
            $applied    = $this->diffAppliedMigrations($before, $after);

            $this->reportOutcome($regressed, $errorMessage, $applied, $runner);

            $this->recordRun($applied, $after, $regressed, $errorMessage, $durationMs);

            return $regressed ? EXIT_ERROR : EXIT_SUCCESS;
        } finally {
            $lock?->release();
        }
    }

    /**
     * Fully-qualified class name of the run lock this command uses.
     *
     * Extracted into its own method purely for testability: a test
     * subclass can override this to return a non-existent FQCN and exercise
     * the `class_exists() === false` branch of `createRunLock()`. Returning
     * a class-constant string here is safe even if `Modules\MigrationManager`
     * is fully removed — `::class` on an unresolved name is just a string
     * literal, it does not trigger autoloading and does not require the
     * class to exist.
     *
     * @return class-string
     */
    protected function runLockClassName(): string
    {
        return RunLock::class;
    }

    /**
     * Produces the run lock used by `run()`, or `null` if
     * `Modules\MigrationManager` isn't installed.
     *
     * Return type is deliberately `?object`, not `?RunLock` — this file
     * lives in `Modules\Backend` and must not carry a hard type reference
     * to a class in `Modules\MigrationManager`; the reverse dependency this
     * command takes on that module is soft (`class_exists()` guarded), and
     * the method signature reflects that.
     *
     * @return RunLock|null Instance of `Modules\MigrationManager\Libraries\RunLock`, or `null`.
     */
    protected function createRunLock(): ?object
    {
        $lockClass = $this->runLockClassName();

        if (!class_exists($lockClass)) {
            CLI::write('running without a run lock: Modules\\MigrationManager not installed', 'dark_gray');

            return null;
        }

        return new $lockClass();
    }

    /**
     * Produces the non-shared `MigrationRunner` used by `run()`.
     *
     * Extracted into its own method purely for testability: `MigrationRunner`
     * is not `final`, so a test subclass can override this method to inject
     * a mock (e.g. one whose `latest()` throws) and exercise `run()`'s error
     * branch — otherwise unreachable because `$getShared=false` defeats
     * `Services::injectMock('migrations', ...)`. Production behavior is
     * unchanged: still non-shared (`Services::migrations(null, null, false)`)
     * so `setNamespace(null)` never leaks into the shared instance.
     *
     * @return \CodeIgniter\Database\MigrationRunner
     */
    protected function createMigrationRunner(): \CodeIgniter\Database\MigrationRunner
    {
        return Services::migrations(null, null, false);
    }

    /**
     * Writes the CLI-visible outcome of the `latest()` run.
     *
     * On failure: the caught error plus a note that the framework
     * auto-regressed the previous batch. On success: the framework's own
     * `getCliMessages()` dump followed by an "already up to date" or
     * "applied N migration(s)" summary. Behavior is identical to the block
     * this was extracted from — only the code location changed.
     *
     * @param bool                                 $regressed    Whether `latest()` failed/regressed.
     * @param string|null                          $errorMessage Caught exception message, if any.
     * @param list<object>                         $applied      Rows newly applied by this run.
     * @param \CodeIgniter\Database\MigrationRunner $runner       Runner used for this run (for `getCliMessages()`).
     *
     * @return void
     */
    private function reportOutcome(bool $regressed, ?string $errorMessage, array $applied, \CodeIgniter\Database\MigrationRunner $runner): void
    {
        if ($regressed) {
            CLI::error('Migration error: ' . ($errorMessage ?? 'unknown error'));
            CLI::error('The framework automatically regressed (rolled back) the previous migration batch before failing — schema may now be at an earlier state than before this run.');

            return;
        }

        foreach ($runner->getCliMessages() as $message) {
            CLI::write($message);
        }

        if ($applied === []) {
            CLI::write('Already up to date — no pending migrations across App + Modules.', 'green');
        } else {
            CLI::write(sprintf('Applied %d migration(s) across App + Modules.', count($applied)), 'green');
        }
    }

    /**
     * Compares `getHistory()` snapshots taken before/after `latest()` and
     * returns the rows that are new.
     *
     * Matches on a `version + class` composite key, not `version` alone.
     * `run()` uses a non-namespaced runner (`setNamespace(null)`), so
     * `getHistory()` returns rows across every namespace un-filtered
     * (`vendor/codeigniter4/framework/system/Database/MigrationRunner.php:708-711`
     * only restricts the query by namespace when `$this->namespace !== null`)
     * — and this repo has a real version collision across namespaces
     * (`modules/Blog/Database/Migrations/2026-03-12-001000_CreateBlogLangsTable.php`
     * vs `modules/Pages/Database/Migrations/2026-03-12-001000_CreatePagesLangsTable.php`).
     * Matching on `version` alone would silently drop a genuinely-applied row
     * whenever its version collides with an already-applied row from another
     * namespace. The composite key replicates the framework's own
     * `MigrationRunner::getObjectUid()`
     * (`vendor/codeigniter4/framework/system/Database/MigrationRunner.php:604-608`:
     * `preg_replace('/[^0-9]/', '', $object->version) . $object->class`) —
     * the same formula the framework itself already treats as a sortable
     * unique key for migration/history rows.
     *
     * @param list<object> $before `getHistory()` rows captured before `latest()`.
     * @param list<object> $after  `getHistory()` rows captured after `latest()`.
     *
     * @return list<object> Rows present in `$after` but not in `$before`.
     */
    private function diffAppliedMigrations(array $before, array $after): array
    {
        $objectUid = static fn (object $row): string => preg_replace('/[^0-9]/', '', $row->version) . $row->class;

        $beforeUids = array_map($objectUid, $before);

        return array_values(array_filter(
            $after,
            static fn (object $row): bool => !in_array($objectUid($row), $beforeUids, true)
        ));
    }

    /**
     * Resolves the batch number to record for this run: the batch of the
     * newly applied rows, or (if nothing was applied) the batch of the most
     * recent existing row.
     *
     * @param list<object> $applied Rows newly applied by this run.
     * @param list<object> $after   Full history after `latest()`.
     *
     * @return int|null `null` if there is no history at all.
     */
    private function resolveBatch(array $applied, array $after): ?int
    {
        if ($applied !== []) {
            return (int) $applied[0]->batch;
        }

        if ($after === []) {
            return null;
        }

        return (int) end($after)->batch;
    }

    /**
     * Writes one best-effort `migration_runs` audit row for this
     * whole-namespace run (sentinel `target='*'`, `run_source='cli'`,
     * `run_by=null` — no CLI session/IP to record).
     *
     * `Modules\Backend` does not own `migration_runs` (it belongs to the
     * removable `Modules\MigrationManager`), so this never assumes the
     * table exists and never lets an audit failure change the command's
     * outcome — the exit code is decided solely by `run()` from `$regressed`.
     *
     * @param list<object> $applied    Rows newly applied by this run.
     * @param list<object> $after      Full history after `latest()`.
     * @param bool         $regressed  Whether `latest()` failed/regressed.
     * @param string|null  $error      Caught exception message, if any.
     * @param int          $durationMs Wall-clock duration of the `latest()` call, in milliseconds.
     *
     * @return void
     */
    private function recordRun(array $applied, array $after, bool $regressed, ?string $error, int $durationMs): void
    {
        try {
            $db = db_connect();

            if (!$db->tableExists('migration_runs')) {
                CLI::write('audit skipped: migration_runs table not present', 'dark_gray');
                log_message('info', '[ci4ms:migrate] audit skipped: migration_runs table not present (Modules\\MigrationManager not installed)');

                return;
            }

            $message = $regressed
                ? ($error ?? 'Migration failed; framework automatically regressed (rolled back) the previous batch.')
                : json_encode([
                    'applied' => array_map(
                        static fn (object $row): array => ['version' => $row->version, 'class' => $row->class],
                        $applied
                    ),
                ], JSON_UNESCAPED_UNICODE);

            $db->table('migration_runs')->insert([
                'kind'          => 'migration',
                'target'        => self::AUDIT_TARGET_ALL,
                'status'        => $regressed ? 'failed' : 'success',
                'applied_count' => count($applied),
                'batch'         => $this->resolveBatch($applied, $after),
                'message'       => $message,
                'duration_ms'   => $durationMs,
                'run_by'        => null,
                'ip'            => null,
                'run_source'    => 'cli',
            ]);
        } catch (\Throwable $e) {
            CLI::error('audit skipped: failed to write migration_runs row: ' . $e->getMessage());
            log_message('warning', '[ci4ms:migrate] audit insert failed: ' . $e->getMessage());
        }
    }
}

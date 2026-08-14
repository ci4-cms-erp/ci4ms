<?php

declare(strict_types=1);

namespace Modules\MigrationManager\Controllers;

use CodeIgniter\Events\Events;
use Config\Database;
use Config\Services;
use Modules\Backend\Controllers\BaseController;
use Modules\MigrationManager\Libraries\MigrationInspector;
use Modules\MigrationManager\Libraries\RunLock;
use Modules\MigrationManager\Libraries\SeederScanner;

/**
 * Backend controller that lets a superadmin discover and run migrations and
 * seeds from the web.
 *
 * Access is enforced in three layers: the route filter (`backendGuard`,
 * `Modules\MigrationManager\Config\MigrationManagerConfig::$filters`), the
 * `Modules\Methods` permission record (fail-closed), and the
 * `auth()->user()->inGroup('superadmin')` check on the first line of every
 * public method in this class. The third layer holds even if the filter
 * configuration breaks; it's never removed from any method (`Methods::update()`
 * can loosen the permission matrix, so the filter/permission layers alone
 * cannot be trusted).
 */
class MigrationManager extends BaseController
{
    /**
     * Renders the migration/seed status dashboard.
     *
     * Passes the view the disk namespaces merged with the DB history
     * (`MigrationInspector::buildStatusReport()`), the list of web-runnable
     * seeds (`SeederScanner::discover()` — returns an EMPTY array today
     * since no seeder implements `WebRunnableSeeder` yet, this is expected
     * behavior), and the last 50 run records (`Backup.php:14` pattern,
     * `ORDER BY migration_runs.id DESC LIMIT 50`). It does a `LEFT JOIN`
     * with the `users` table and adds `run_by_username` to every row
     * (BUG-2: the username instead of the raw `run_by` user ID); if the
     * user was deleted (`run_by` FK `ON DELETE SET NULL`) or the record
     * came from the system/automation, `run_by_username` is `null`, which
     * the view renders via `MigrationManager.deletedUser`. After the join,
     * `ORDER BY` QUALIFIES `migration_runs.id` — `id` exists in both
     * tables, and leaving it unqualified risks an ambiguous-column error
     * on MySQL/MariaDB.
     *
     * @return \CodeIgniter\HTTP\ResponseInterface
     */
    public function index()
    {
        if (!auth()->user()->inGroup('superadmin')) {
            return $this->failForbidden();
        }

        $this->defData['statusReport'] = (new MigrationInspector())->buildStatusReport();
        $this->defData['seeders']      = (new SeederScanner())->discover();
        $this->defData['recentRuns']   = $this->commonModel->lists('migration_runs', 'migration_runs.*, users.username AS run_by_username', [], 'migration_runs.id DESC', 50, 0, [], [], [
            ['table' => 'users', 'cond' => 'users.id = migration_runs.run_by', 'type' => 'left'],
        ]);

        return view('Modules\MigrationManager\Views\list', $this->defData);
    }

    /**
     * Validates the posted namespace against the server-side allowlist, runs
     * it with `MigrationRunner::latest()`, and records the outcome in
     * `migration_runs`.
     *
     * The posted value is never concatenated into any path/namespace; it's
     * only matched against the allowlist derived from `MigrationInspector::
     * getDiscoveredNamespaces()` via `in_array(..., true)`. Vendor
     * namespaces (`readOnly=true`, e.g. `CodeIgniter\Settings`) are NOT in
     * that list, so they're rejected even if posted — the client-side
     * `disabled` attribute is never trusted.
     *
     * @return \CodeIgniter\HTTP\ResponseInterface
     */
    public function runMigration()
    {
        if (!$this->request->isAJAX()) {
            return $this->failForbidden();
        }

        if (!auth()->user()->inGroup('superadmin')) {
            return $this->failForbidden();
        }

        $namespace = trim((string) $this->request->getPost('namespace'));

        if ($namespace === '' || !in_array($namespace, $this->allowedMigrationNamespaces(), true)) {
            return $this->respond(['success' => false, 'error' => lang('MigrationManager.invalidNamespaceSelection')], 400);
        }

        try {
            $lock = new RunLock();
        } catch (\RuntimeException $e) {
            log_message('error', '[MigrationManager] ' . $e->getMessage());

            return $this->respond(['success' => false, 'error' => lang('MigrationManager.runLockUnavailable')], 500);
        }

        if (!$lock->acquire()) {
            return $this->respond(['success' => false, 'error' => lang('MigrationManager.runAlreadyInProgress')], 409);
        }

        try {
            return $this->executeMigration($namespace);
        } finally {
            $lock->release();
        }
    }

    /**
     * Validates the posted seeder FQCN against the `SeederScanner::discover()`
     * allowlist, runs it with `Config\Database::seeder()->call()`, and
     * records the outcome in `migration_runs`.
     *
     * Since `SeederScanner::discover()` returns an EMPTY array today (no
     * seeder implements `WebRunnableSeeder` yet), this endpoint ALWAYS
     * rejects in PRODUCTION — this is expected behavior, not a bug.
     *
     * @return \CodeIgniter\HTTP\ResponseInterface
     */
    public function runSeed()
    {
        if (!$this->request->isAJAX()) {
            return $this->failForbidden();
        }

        if (!auth()->user()->inGroup('superadmin')) {
            return $this->failForbidden();
        }

        $seederClass = trim((string) $this->request->getPost('seeder'));
        $entry       = $this->findSeederEntry((new SeederScanner())->discover(), $seederClass);

        if ($entry === null) {
            return $this->respond(['success' => false, 'error' => lang('MigrationManager.invalidSeederSelection')], 400);
        }

        try {
            $lock = new RunLock();
        } catch (\RuntimeException $e) {
            log_message('error', '[MigrationManager] ' . $e->getMessage());

            return $this->respond(['success' => false, 'error' => lang('MigrationManager.runLockUnavailable')], 500);
        }

        if (!$lock->acquire()) {
            return $this->respond(['success' => false, 'error' => lang('MigrationManager.runAlreadyInProgress')], 409);
        }

        try {
            return $this->executeSeed($entry);
        } finally {
            $lock->release();
        }
    }

    /**
     * Returns the DataTables-compatible paginated list of the `migration_runs`
     * table.
     *
     * This is a direct application of the AJAX-datatables pattern in
     * `modules/Backup/Controllers/Backup.php:9-33`. It does a `LEFT JOIN`
     * with the `users` table and adds `run_by_username` to every row
     * (BUG-2); if `run_by` is `null` (system/automation-originated or the
     * user was deleted), `run_by_username` is also `null`. The response
     * carries RAW data — HTML escaping only happens at the render boundary,
     * via the `escapeHtml()` calls in `Views/list.php`'s column `render`
     * callbacks. Applying `esc()` here as well would double-escape on the
     * client (a username containing `'` would show up as `O&#039;Brien`).
     * The `$total` count does NOT require the join; `count('migration_runs', ...)`
     * is unchanged.
     *
     * @return \CodeIgniter\HTTP\ResponseInterface
     */
    public function history()
    {
        if (!$this->request->isAJAX()) {
            return $this->failForbidden();
        }

        if (!auth()->user()->inGroup('superadmin')) {
            return $this->failForbidden();
        }

        $parsed = $this->commonBackendLibrary->getDatatablesPagination($this->request->getPost());
        $like   = $parsed['searchString'] !== '' ? ['target' => $parsed['searchString']] : [];

        $results = $this->commonModel->lists('migration_runs', 'migration_runs.*, users.username AS run_by_username', [], 'migration_runs.id DESC', $parsed['length'], $parsed['start'], $like, [], [
            ['table' => 'users', 'cond' => 'users.id = migration_runs.run_by', 'type' => 'left'],
        ]);
        $total   = $this->commonModel->count('migration_runs', [], $like);

        // Escaping happens ONLY at the render boundary (escapeHtml() on every
        // DataTables column in Views/list.php). Applying esc() here would
        // double-escape on the client, so a username containing ' or & would
        // show up in the table as "O&#039;Brien". Single rule: the server
        // returns raw data, the column render escapes — this also applies
        // when adding a new column.
        return $this->respond([
            'draw'                 => $parsed['draw'],
            'iTotalRecords'        => $total,
            'iTotalDisplayRecords' => $total,
            'aaData'               => $results,
        ]);
    }

    /**
     * Extracts the NON-read-only (non-vendor) namespace names from
     * `MigrationInspector::getDiscoveredNamespaces()`.
     *
     * @return list<string>
     */
    private function allowedMigrationNamespaces(): array
    {
        $writable = array_filter(
            (new MigrationInspector())->getDiscoveredNamespaces(),
            static fn (array $entry): bool => $entry['readOnly'] === false
        );

        return array_column($writable, 'namespace');
    }

    /**
     * Does the actual run-and-record work once the lock is held. Called
     * ASSUMING `RunLock::acquire()` already succeeded.
     *
     * A non-shared `MigrationRunner` is obtained via
     * `Services::migrations(null, null, false)` (avoids repeating the
     * mistake in `Settings.php:238` of leaking a `setNamespace()` call into
     * the shared instance). Since `getCliMessages()` always returns empty
     * in a web context (`MigrationRunner.php:661,683` is guarded by
     * `is_cli()`), the "what ran" information is derived from the
     * `getHistory()` diff before/after `latest()`.
     *
     * @param string $namespace Namespace already validated against the allowlist.
     *
     * @return \CodeIgniter\HTTP\ResponseInterface
     */
    private function executeMigration(string $namespace)
    {
        @set_time_limit(0);
        ignore_user_abort(true);

        $group  = $this->historyGroup();
        $runner = Services::migrations(null, null, false);
        $runner->setNamespace($namespace);

        $before    = $runner->getHistory($group);
        $startedAt = microtime(true);
        $regressed = false;
        $error     = null;

        try {
            if ($runner->latest() === false) {
                $regressed = true;
            }
        } catch (\Throwable $e) {
            $regressed = true;
            $error     = $e->getMessage();
        }

        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
        $after      = $runner->getHistory($group);
        $applied    = $this->diffAppliedMigrations($before, $after);

        return $this->finishMigrationRun($namespace, $applied, $after, $regressed, $error, $durationMs);
    }

    /**
     * Compares the `getHistory()` snapshots before/after `latest()` by
     * `version` field and extracts the newly applied rows.
     *
     * @param list<object> $before `getHistory()` rows before `latest()`.
     * @param list<object> $after  `getHistory()` rows after `latest()`.
     *
     * @return list<object> Rows present in `$after` but not in `$before`.
     */
    private function diffAppliedMigrations(array $before, array $after): array
    {
        $beforeVersions = array_map(static fn (object $row): string => $row->version, $before);

        return array_values(array_filter(
            $after,
            static fn (object $row): bool => !in_array($row->version, $beforeVersions, true)
        ));
    }

    /**
     * Builds the messages to store in the DB / return to the user based on
     * the diff result, writes the row to `migration_runs`, fires the audit
     * event, and returns the JSON response.
     *
     * If `latest()` returns `false` OR throws (`$regressed`), the framework
     * has automatically called `regress(-1)`
     * (`vendor/codeigniter4/framework/system/Database/MigrationRunner.php:210-211`)
     * — this is stated EXPLICITLY in the response via the
     * `migrationRunFailedRegressed` message.
     *
     * @param string       $namespace  Namespace that was run.
     * @param list<object> $applied    Newly applied migration rows.
     * @param list<object> $after      All history rows after `latest()`.
     * @param bool         $regressed  Whether the framework auto-called `regress(-1)`.
     * @param string|null  $error      Caught exception message (if any).
     * @param int          $durationMs Run duration (milliseconds).
     *
     * @return \CodeIgniter\HTTP\ResponseInterface
     */
    private function finishMigrationRun(string $namespace, array $applied, array $after, bool $regressed, ?string $error, int $durationMs)
    {
        $appliedCount = count($applied);
        $batch        = $this->resolveBatch($applied, $after);
        $status       = $regressed ? 'failed' : 'success';

        if ($regressed) {
            $dbMessage       = $error ?? lang('MigrationManager.migrationRunFailedRegressed', [$namespace]);
            $responseMessage = lang('MigrationManager.migrationRunFailedRegressed', [$namespace]);
        } else {
            $dbMessage = (string) json_encode([
                'applied' => array_map(
                    static fn (object $row): array => ['version' => $row->version, 'class' => $row->class],
                    $applied
                ),
            ], JSON_UNESCAPED_UNICODE);
            $responseMessage = $appliedCount > 0
                ? lang('MigrationManager.migrationRunSuccess', [$appliedCount, $namespace])
                : lang('MigrationManager.migrationRunNoChange', [$namespace]);
        }

        $this->recordRun('migration', $namespace, $status, $appliedCount, $batch, $dbMessage, $durationMs);
        $this->auditRun('runMigration', $namespace, $status);

        return $this->respond([
            'success'      => $status === 'success',
            'message'      => $responseMessage,
            'appliedCount' => $appliedCount,
            'regressed'    => $regressed,
        ], $status === 'success' ? 200 : 500);
    }

    /**
     * If `applied_count>0`, returns the batch of the newly applied rows;
     * otherwise (namespace already up to date) returns the current last batch.
     *
     * @param list<object> $applied Newly applied migration rows.
     * @param list<object> $after   All history rows after `latest()`.
     *
     * @return int|null `null` if there's no history at all.
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
     * Resolves the `group` value to pass to `getHistory()` from the runtime
     * DB configuration (same source as `MigrationInspector::historyGroup()`
     * — the literal `'default'` is NEVER used; under `ENVIRONMENT===
     * 'testing'`, `Config\Database::$defaultGroup` resolves to `'tests'`,
     * see `app/Config/Database.php:200-201`).
     *
     * @return string
     */
    private function historyGroup(): string
    {
        return (string) config(Database::class)->defaultGroup;
    }

    /**
     * Looks up the posted FQCN in the discovered seeder list.
     *
     * @param list<array{class: class-string, label: string, repeatable: bool}> $seeders
     * @param string                                                            $seederClass
     *
     * @return array{class: class-string, label: string, repeatable: bool}|null
     */
    private function findSeederEntry(array $seeders, string $seederClass): ?array
    {
        if ($seederClass === '') {
            return null;
        }

        foreach ($seeders as $entry) {
            if ($entry['class'] === $seederClass) {
                return $entry;
            }
        }

        return null;
    }

    /**
     * Does the actual run-and-record work once the lock is held.
     *
     * @param array{class: class-string, label: string, repeatable: bool} $entry Seeder entry already validated against the allowlist.
     *
     * @return \CodeIgniter\HTTP\ResponseInterface
     */
    private function executeSeed(array $entry)
    {
        @set_time_limit(0);
        ignore_user_abort(true);

        $label            = lang('MigrationManager.' . $entry['label']);
        $startedAt        = microtime(true);
        $status           = 'success';
        $dbMessage        = (string) json_encode(['seeded' => $entry['class']], JSON_UNESCAPED_UNICODE);
        $responseMessage  = lang('MigrationManager.seedRunSuccess', [$label]);

        try {
            Database::seeder()->call($entry['class']);
        } catch (\Throwable $e) {
            $status          = 'failed';
            $dbMessage       = $e->getMessage();
            $responseMessage = lang('MigrationManager.seedRunFailed', [$label, $e->getMessage()]);
        }

        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

        $this->recordRun('seed', $entry['class'], $status, $status === 'success' ? 1 : 0, null, $dbMessage, $durationMs);
        $this->auditRun('runSeed', $entry['class'], $status);

        return $this->respond([
            'success' => $status === 'success',
            'message' => $responseMessage,
        ], $status === 'success' ? 200 : 500);
    }

    /**
     * Inserts a run row into the `migration_runs` table.
     *
     * @param 'migration'|'seed'  $kind
     * @param string              $target       Migration namespace or seed FQCN.
     * @param 'success'|'failed'  $status
     * @param int                 $appliedCount
     * @param int|null            $batch
     * @param string|null         $message      JSON or error text.
     * @param int                 $durationMs
     *
     * @return void
     */
    private function recordRun(string $kind, string $target, string $status, int $appliedCount, ?int $batch, ?string $message, int $durationMs): void
    {
        $this->commonModel->create('migration_runs', [
            'kind'          => $kind,
            'target'        => $target,
            'status'        => $status,
            'applied_count' => $appliedCount,
            'batch'         => $batch,
            'message'       => $message,
            'duration_ms'   => $durationMs,
            'run_by'        => auth()->id(),
            'ip'            => $this->request->getIPAddress(),
        ]);
    }

    /**
     * Fires the `ci4ms.audit` event (`Fileeditor::triggerFileevent()`
     * pattern, `modules/Fileeditor/Controllers/Fileeditor.php:97-105`).
     *
     * @param string $action E.g. `'runMigration'`, `'runSeed'`.
     * @param string $target Migration namespace or seed FQCN.
     * @param string $status `'success'` or `'failed'`.
     *
     * @return void
     */
    private function auditRun(string $action, string $target, string $status): void
    {
        Events::trigger('ci4ms.audit', [
            'severity' => 'warning',
            'action'   => 'migrationManager.' . $action,
            'message'  => sprintf('%s (%s) %s tarafından çalıştırıldı: %s', $target, $action, auth()->user()->username, $status),
            'url'      => base_url('backend/migration-manager'),
        ]);
    }
}

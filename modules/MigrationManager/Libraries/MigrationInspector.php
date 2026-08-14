<?php

declare(strict_types=1);

namespace Modules\MigrationManager\Libraries;

use CodeIgniter\Database\MigrationRunner;
use Config\Database;
use Config\Services;

/**
 * Discovers the migration namespaces on disk and merges them with the DB
 * history to build a status report.
 *
 * The source is ALWAYS disk, NEVER `findMigrations()`: `findMigrations()`
 * (`vendor/codeigniter4/framework/system/Database/MigrationRunner.php:445`)
 * with namespace=null walks composer's ENTIRE registered PSR-4 prefix list
 * (`service('autoloader')->getNamespace()`, 50-100+ packages). Instead, this
 * class loops over the known namespace list and calls
 * `findNamespaceMigrations()` (public, `:469`). This design also guarantees
 * that "orphan" namespaces registered in the DB but with no file anymore
 * (e.g. `Modules\Crm`) never enter the report — there's NO separate hiding
 * filter, because the report list is built from disk and the DB is only
 * read for enrichment.
 *
 * `getHistory()` (`:697-714`) does NOT TAKE a namespace parameter; it looks
 * at the `$namespace` internal state of the `MigrationRunner` instance it's
 * called on. Calling `getHistory()` separately per namespace falls into N+1
 * (the mistake made by `system/Commands/Database/MigrateStatus.php`,
 * `:114-115`) — instead, this filter is turned off with `setNamespace(null)`,
 * the ENTIRE history is fetched in ONE query and grouped by namespace on
 * the PHP side.
 */
class MigrationInspector
{
    /**
     * Vendor migration namespaces that have no disk counterpart and are
     * listed read-only.
     *
     * @var list<string>
     */
    public const VENDOR_NAMESPACES = ['CodeIgniter\Settings', 'CodeIgniter\Shield'];

    private MigrationRunner $runner;

    /**
     * @param MigrationRunner|null $runner Can be injected for testability
     *                                     (e.g. a spy/mock that counts
     *                                     calls). If `null`,
     *                                     `Services::migrations(null, null, false)`
     *                                     (non-shared) is used — no
     *                                     `setNamespace()` state leaks into
     *                                     the shared instance (avoids the
     *                                     mistake `Settings.php:238` falls
     *                                     into).
     */
    public function __construct(?MigrationRunner $runner = null)
    {
        $this->runner = $runner ?? Services::migrations(null, null, false);
    }

    /**
     * Discovers the migration namespaces found on disk.
     *
     * Returns, in order, `App` + every `modules/*` directory (with a
     * `Modules\{Basename}` prefix, matching the
     * `app/Config/Autoload.php:106` convention exactly) + the fixed
     * `VENDOR_NAMESPACES`. Vendor namespaces are flagged `readOnly=true`;
     * the rest are `false`.
     *
     * @return list<array{namespace: string, readOnly: bool}>
     */
    public function getDiscoveredNamespaces(): array
    {
        $namespaces = [['namespace' => 'App', 'readOnly' => false]];

        foreach (glob(ROOTPATH . 'modules/*', GLOB_ONLYDIR) ?: [] as $moduleDir) {
            $namespaces[] = ['namespace' => 'Modules\\' . basename($moduleDir), 'readOnly' => false];
        }

        foreach (self::VENDOR_NAMESPACES as $vendorNamespace) {
            $namespaces[] = ['namespace' => $vendorNamespace, 'readOnly' => true];
        }

        return $namespaces;
    }

    /**
     * Fetches the entire migration history in ONE `getHistory()` call and
     * groups it by normalized namespace.
     *
     * `\Modules\Notifications` (with a leading backslash) is stored broken
     * in the DB, under a separate key (root cause: while
     * `Autoloader.php:254` applies `trim($prefix,'\\')` in the PSR-4
     * mapping, `MigrationRunner` itself writes the namespace as-is to the
     * history row, `:656`, and `getHistory()` matches it as a literal
     * string, `:709-710`) — it's normalized with `ltrim($namespace, '\\')`
     * and joined to the correct group.
     *
     * @return array<string, list<object>> Key is the normalized namespace,
     *                                     value is the `getHistory()` rows
     *                                     (`stdClass` objects with
     *                                     `version`, `class`, `namespace`,
     *                                     `time`, `batch` fields).
     */
    public function getHistoryByNamespace(): array
    {
        $this->runner->setNamespace(null);

        $grouped = [];

        foreach ($this->runner->getHistory($this->historyGroup()) as $row) {
            $grouped[ltrim($row->namespace, '\\')][] = $row;
        }

        return $grouped;
    }

    /**
     * Merges `getDiscoveredNamespaces()` with `getHistoryByNamespace()` and
     * produces one status row per namespace.
     *
     * "Orphan" namespaces registered in the DB but with no disk counterpart
     * (since they're not in `getDiscoveredNamespaces()`) never enter the
     * report.
     *
     * @return list<array{
     *     namespace: string,
     *     readOnly: bool,
     *     total_count: int,
     *     applied_count: int,
     *     last_batch: int|null,
     *     last_date: string|null
     * }>
     */
    public function buildStatusReport(): array
    {
        $historyByNamespace = $this->getHistoryByNamespace();
        $report             = [];

        foreach ($this->getDiscoveredNamespaces() as $entry) {
            $history               = $historyByNamespace[$entry['namespace']] ?? [];
            [$lastBatch, $lastDate] = $this->lastRun($history);

            $report[] = [
                'namespace'     => $entry['namespace'],
                'readOnly'      => $entry['readOnly'],
                'total_count'   => count($this->runner->findNamespaceMigrations($entry['namespace'])),
                'applied_count' => count($history),
                'last_batch'    => $lastBatch,
                'last_date'     => $lastDate,
            ];
        }

        return $report;
    }

    /**
     * Determines the most recently run batch and its date among a
     * namespace's history rows.
     *
     * @param list<object> $history A single group from `getHistoryByNamespace()`.
     *
     * @return array{0: int|null, 1: string|null} `[lastBatch, lastDate]`;
     *                                             `[null, null]` if `$history` is empty.
     */
    private function lastRun(array $history): array
    {
        if ($history === []) {
            return [null, null];
        }

        $lastBatch = null;
        $lastTime  = null;

        foreach ($history as $row) {
            $batch = (int) $row->batch;
            $time  = (int) $row->time;

            if ($lastBatch === null || $batch > $lastBatch || ($batch === $lastBatch && $time > $lastTime)) {
                $lastBatch = $batch;
                $lastTime  = $time;
            }
        }

        return [$lastBatch, date('Y-m-d H:i:s', $lastTime)];
    }

    /**
     * Resolves the `group` value to pass to `getHistory()` from the runtime
     * DB configuration (instead of the literal `'default'`).
     *
     * REASON FOR THE DEVIATION: under `ENVIRONMENT==='testing'` (PHPUnit,
     * `vendor/codeigniter4/framework/system/Test/bootstrap.php:29`), this
     * project turns `Config\Database::$defaultGroup` into `'tests'`
     * (`app/Config/Database.php:200-201`) and the migration history rows'
     * `group` column is written with exactly that value
     * (`MigrationRunner::__construct()` `:154`, `addHistory()` `:655`).
     * Evidence (measured in this session, read-only `SELECT`): live
     * `ci4ms_migrations` → `group='default'` (51 rows);
     * `ci4ms_test.ci4ms_migrations` → `group='tests'` (40 rows). A literal
     * `getHistory('default')` would ALWAYS return 0 rows under PHPUnit
     * (false negative, doesn't throw but the report content would be
     * wrong). This method guarantees the correct filter by using the SAME
     * source (`config(Database::class)->defaultGroup`) that
     * `MigrationRunner::__construct()` uses to compute the group.
     */
    private function historyGroup(): string
    {
        return (string) config(Database::class)->defaultGroup;
    }
}

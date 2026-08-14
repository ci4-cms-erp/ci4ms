<?php

namespace Modules\Auth\Database\Migrations;

use CodeIgniter\Database\Migration;
use RuntimeException;

/**
 * Adds a DB-level UNIQUE constraint on `auth_permissions_pages.(className,
 * methodName)` to close the TOCTOU race that the application-level
 * `Methods::classNameMethodNameCollides()` guard (see
 * `modules/Methods/Controllers/Methods.php:530-561`) cannot close on its own
 * — two concurrent requests can both pass the application check and then
 * both write a colliding row (bkz. Round 1 context.md "Group C / F3 kalan
 * risk").
 *
 * `Forge::addUniqueKey()` cannot be used directly here because the table
 * legitimately contains duplicate `('', '')` pairs: `className=''` +
 * `methodName=''` is the pattern `ModuleScanner::createVirtualParents()`
 * (`modules/Methods/Libraries/ModuleScanner.php:364-379`) uses for
 * menu-only "parent" rows that have no route. A plain
 * `UNIQUE(className, methodName)` would (a) fail immediately on `up()`
 * because of the existing `('', '')` rows and (b) permanently break any
 * *future* virtual-parent row from being created (functional regression in
 * normal `ModuleScanner` operation).
 *
 * Chosen approach: a MariaDB/MySQL `GENERATED ALWAYS ... STORED` column that
 * evaluates to `NULL` whenever the pair is `('', '')`, and to
 * `className|methodName` otherwise. The UNIQUE key sits on that generated
 * column instead of the raw pair. This relies on standard SQL UNIQUE-index
 * semantics: `NULL <> NULL`, so a UNIQUE index treats every `NULL` as
 * distinct from every other `NULL` and allows any number of them — meaning
 * every `('', '')` row is automatically exempt from the constraint, while
 * every *real* (className, methodName) pair is still fully unique.
 *
 * An earlier version of this migration tried to disambiguate `('', '')`
 * rows via `CONCAT('_virtual_', id)` instead of `NULL`. That failed at
 * `up()` time against `ci4ms_test` with a real DB error (evidence kept for
 * future maintainers): `Function or expression 'AUTO_INCREMENT' cannot be
 * used in the GENERATED ALWAYS AS clause of `id``. MariaDB/MySQL do not
 * allow a generated column's expression to reference an AUTO_INCREMENT
 * column at all (not even indirectly through another generated column) —
 * the `NULL` approach below sidesteps this entirely and is the more
 * idiomatic way to express "conditional/partial unique index" in
 * MySQL/MariaDB (which has no native partial-index syntax, unlike
 * PostgreSQL).
 *
 * `CodeIgniter\Database\Forge` has no API for generated columns (verified:
 * `grep -n "GENERATED" vendor/codeigniter4/framework/system/Database/Forge.php`
 * returns nothing), so raw `$this->db->query()` is used — an established
 * pattern in this project's own migrations (see
 * `modules/Notifications/Database/Migrations/2026-07-11-090000_AddModelBColumnsToNotifications.php:88-92`
 * for the same raw-ALTER-with-SHOW-INDEX-guard style).
 *
 * DB engine/version verified against `ci4ms_test` before writing this file:
 * `SELECT VERSION()` → `12.0.2-MariaDB-log`. MariaDB's generated-column
 * syntax is a superset-compatible match of the MySQL syntax used below.
 *
 * `up()` first guards (read-only) against any *unexpected* duplicate — i.e.
 * a real (non-empty) (className, methodName) pair that already collides.
 * None were found against `ci4ms_test` at authoring time (only the expected
 * 3-row `('', '')` pattern), but the guard exists so this migration never
 * silently drops/alters data if that assumption is ever wrong; it raises
 * instead.
 */
class AddUniqueKeyToAuthPermissionsPages extends Migration
{
    private const TABLE             = 'auth_permissions_pages';
    private const GENERATED_COLUMN  = 'class_method_key';
    private const UNIQUE_KEY        = 'auth_permissions_pages_class_method_unique';

    /**
     * Adds the generated `class_method_key` column and its UNIQUE index.
     *
     * Idempotent: if the generated column already exists, this is a no-op
     * (allows the migration to be safely re-run, mirroring the guard style
     * used in `AddUniqueKeyToAuthGroupsAndUsers::up()`).
     *
     * @throws RuntimeException If a real (non-empty) (className, methodName)
     *                          pair is duplicated — this must be resolved
     *                          manually before the UNIQUE constraint can be
     *                          added; data is never modified by this method.
     */
    public function up()
    {
        $this->db->resetDataCache();

        $this->guardNoUnexpectedDuplicates();

        if ($this->db->fieldExists(self::GENERATED_COLUMN, self::TABLE)) {
            return;
        }

        $prefixed = $this->db->prefixTable(self::TABLE);

        $this->db->query(
            "ALTER TABLE `{$prefixed}` "
            . 'ADD COLUMN `' . self::GENERATED_COLUMN . '` VARCHAR(511) '
            . "GENERATED ALWAYS AS (IF(className = '' AND methodName = '', NULL, CONCAT(className, '|', methodName))) STORED, "
            . 'ADD UNIQUE KEY `' . self::UNIQUE_KEY . '` (`' . self::GENERATED_COLUMN . '`)',
        );

        // The idempotency check above populated CI4's per-connection field
        // cache with the pre-ALTER column list; without this, an immediate
        // fieldExists()/fieldData() call right after up() returns would see
        // a stale "column does not exist" result even though the ALTER
        // already committed.
        $this->db->resetDataCache();
    }

    /**
     * Drops the UNIQUE index and the generated column. Schema-only — no data
     * row is touched, `className`/`methodName` and every other column are
     * left exactly as they were.
     *
     * Idempotent: if the generated column is already gone, this is a no-op.
     */
    public function down()
    {
        $this->db->resetDataCache();

        if (! $this->db->fieldExists(self::GENERATED_COLUMN, self::TABLE)) {
            return;
        }

        $prefixed = $this->db->prefixTable(self::TABLE);

        $this->db->query(
            "ALTER TABLE `{$prefixed}` "
            . 'DROP INDEX `' . self::UNIQUE_KEY . '`, '
            . 'DROP COLUMN `' . self::GENERATED_COLUMN . '`',
        );

        // Same rationale as the matching call in up() — invalidate the
        // field cache populated by the idempotency check above so a caller
        // that inspects the schema right after down() returns sees the
        // post-DROP state, not the pre-DROP one.
        $this->db->resetDataCache();
    }

    /**
     * Read-only check for duplicate (className, methodName) pairs that are
     * NOT the legitimate `('', '')` virtual-parent pattern.
     *
     * @throws RuntimeException If any such duplicate is found. The message
     *                          names the offending className/methodName and
     *                          the number of times it repeats.
     */
    private function guardNoUnexpectedDuplicates(): void
    {
        $duplicates = $this->db->table(self::TABLE)
            ->select('className, methodName, COUNT(*) AS duplicate_count')
            ->groupBy(['className', 'methodName'])
            ->having('COUNT(*) >', 1)
            ->get()
            ->getResultArray();

        $unexpected = array_values(array_filter(
            $duplicates,
            static fn (array $row): bool => $row['className'] !== '' || $row['methodName'] !== ''
        ));

        if ($unexpected === []) {
            return;
        }

        $details = implode(', ', array_map(
            static fn (array $row): string => sprintf(
                'className="%s",methodName="%s" (x%d)',
                $row['className'],
                $row['methodName'],
                (int) $row['duplicate_count'],
            ),
            $unexpected,
        ));

        throw new RuntimeException(
            'auth_permissions_pages: (className, methodName) duplicate(s) that are NOT the legitimate '
            . "empty/empty virtual-parent pattern were found — {$details}. "
            . 'These must be resolved manually before this migration can add the UNIQUE constraint.',
        );
    }
}

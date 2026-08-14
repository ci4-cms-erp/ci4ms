<?php

namespace Tests\Support\Notifications;

/**
 * Schema-probe double for the migration guard tests.
 *
 * The FAZ 2 migrations ask the connection two yes/no questions before touching
 * anything (`fieldExists('exclude_users', 'notifications')` and
 * `tableExists(...)`), and those answers are the whole guard. This double supplies
 * them from a mutable set, so a test can flip an answer between two `up()` calls
 * and reproduce the real replay sequence — absent on the first run, present on the
 * second — without any DDL.
 *
 * {@see FakeConnection} answers a single global tableExists() flag, which cannot
 * distinguish `notification_preferences` from `users`; the FK branch needs exactly
 * that distinction, hence this per-name double.
 */
final class FakeSchema
{
    /**
     * Table names reported as existing.
     *
     * @var list<string>
     */
    public array $tables = [];

    /**
     * Field names reported as existing, as '{table}.{field}'.
     *
     * @var list<string>
     */
    public array $fields = [];

    /**
     * @param list<string> $tables Table names that should appear present.
     * @param list<string> $fields '{table}.{field}' pairs that should appear present.
     */
    public function __construct(array $tables = [], array $fields = [])
    {
        $this->tables = $tables;
        $this->fields = $fields;
    }

    /**
     * Guard'lı migration'lar fieldExists() önbelleğini bilerek sıfırlar; burada
     * önbellek yok, bu yüzden çağrının karşılığı olması yeterli.
     */
    public function resetDataCache(): self
    {
        return $this;
    }

    /**
     * Reports whether the named table is present.
     *
     * @param string $table Table name being probed (unprefixed).
     *
     * @return bool True when the name is in {@see $tables}.
     */
    public function tableExists(string $table): bool
    {
        return in_array($table, $this->tables, true);
    }

    /**
     * Reports whether the named column is present on the named table.
     *
     * @param string $field Column name being probed.
     * @param string $table Table name being probed (unprefixed).
     *
     * @return bool True when '{table}.{field}' is in {@see $fields}.
     */
    public function fieldExists(string $field, string $table): bool
    {
        return in_array($table . '.' . $field, $this->fields, true);
    }

    /**
     * Marks a table as present from now on (simulates a completed migration step).
     *
     * @param string $table Table name to start reporting as present.
     *
     * @return void
     */
    public function addTable(string $table): void
    {
        $this->tables[] = $table;
    }

    /**
     * Marks a column as present from now on (simulates a completed migration step).
     *
     * @param string $field Column name to start reporting as present.
     * @param string $table Table the column belongs to.
     *
     * @return void
     */
    public function addField(string $field, string $table): void
    {
        $this->fields[] = $table . '.' . $field;
    }
}

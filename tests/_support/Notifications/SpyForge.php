<?php

namespace Tests\Support\Notifications;

/**
 * Forge double that records DDL intent instead of executing it.
 *
 * The FAZ 2 migrations are guarded so they can be replayed on an already-migrated
 * schema. Proving that by actually running them would mean issuing ALTER/CREATE
 * against the shared dev database — a destructive schema change this suite must
 * never make, and one that a TEMPORARY-table shadow cannot stand in for either
 * (Forge would still target the permanent table by name).
 *
 * So the guard is tested where it actually lives: the migration is instantiated
 * without its constructor, this spy is injected over the protected `$forge`, and
 * the assertion becomes "addColumn/createTable was or was not CALLED". Zero DDL
 * reaches the server while the exact branch under test still runs.
 *
 * Method signatures mirror CodeIgniter\Database\Forge so the migrations call them
 * unchanged; the fluent ones return $this because the production code chains them.
 */
final class SpyForge
{
    /**
     * Column sets passed to addColumn(), keyed in call order.
     *
     * @var list<array{table: string, fields: array<array-key, mixed>}>
     */
    public array $addedColumns = [];

    /**
     * Field definitions passed to addField(), in call order.
     *
     * @var list<mixed>
     */
    public array $addedFields = [];

    /**
     * Keys passed to addKey(), in call order.
     *
     * @var list<array{key: mixed, primary: bool, unique: bool, name: string}>
     */
    public array $addedKeys = [];

    /**
     * Foreign keys passed to addForeignKey(), in call order.
     *
     * @var list<array{field: mixed, table: string, tableField: mixed, onUpdate: string, onDelete: string}>
     */
    public array $addedForeignKeys = [];

    /**
     * Tables passed to createTable(), in call order.
     *
     * @var list<array{table: string, ifNotExists: bool, attributes: array<string, mixed>}>
     */
    public array $createdTables = [];

    /**
     * Records an ALTER TABLE ... ADD COLUMN intent.
     *
     * Forge also accepts a raw string here; it is normalised to an array so the
     * recorded shape stays uniform for assertions.
     *
     * @param string                      $table  Target table (unprefixed).
     * @param array<string, mixed>|string $fields Column definitions.
     *
     * @return bool Always true, matching Forge's contract.
     */
    public function addColumn(string $table, $fields): bool
    {
        $this->addedColumns[] = ['table' => $table, 'fields' => (array) $fields];

        return true;
    }

    /**
     * Records a pending field set for the next createTable().
     *
     * @param array<string, mixed>|string $fields Column definitions.
     *
     * @return self
     */
    public function addField($fields): self
    {
        $this->addedFields[] = $fields;

        return $this;
    }

    /**
     * Records a pending key for the next createTable().
     *
     * @param list<string>|string $key     Column(s) the key covers.
     * @param bool                $primary Whether this is the primary key.
     * @param bool                $unique  Whether the key is unique.
     * @param string              $keyName Explicit index name.
     *
     * @return self
     */
    public function addKey($key, bool $primary = false, bool $unique = false, string $keyName = ''): self
    {
        $this->addedKeys[] = ['key' => $key, 'primary' => $primary, 'unique' => $unique, 'name' => $keyName];

        return $this;
    }

    /**
     * Records a pending foreign key for the next createTable().
     *
     * @param list<string>|string $fieldName  Local column(s).
     * @param string              $tableName  Referenced table.
     * @param list<string>|string $tableField Referenced column(s).
     * @param string              $onUpdate   ON UPDATE action.
     * @param string              $onDelete   ON DELETE action.
     * @param string              $fkName     Explicit constraint name.
     *
     * @return self
     */
    public function addForeignKey($fieldName = '', string $tableName = '', $tableField = '', string $onUpdate = '', string $onDelete = '', string $fkName = ''): self
    {
        $this->addedForeignKeys[] = [
            'field'      => $fieldName,
            'table'      => $tableName,
            'tableField' => $tableField,
            'onUpdate'   => $onUpdate,
            'onDelete'   => $onDelete,
        ];

        return $this;
    }

    /**
     * Records a CREATE TABLE intent.
     *
     * @param string               $table       Table to create (unprefixed).
     * @param bool                 $ifNotExists Whether IF NOT EXISTS was requested.
     * @param array<string, mixed> $attributes  Engine and other table attributes.
     *
     * @return bool Always true, matching Forge's contract.
     */
    public function createTable(string $table, bool $ifNotExists = false, array $attributes = []): bool
    {
        $this->createdTables[] = ['table' => $table, 'ifNotExists' => $ifNotExists, 'attributes' => $attributes];

        return true;
    }
}

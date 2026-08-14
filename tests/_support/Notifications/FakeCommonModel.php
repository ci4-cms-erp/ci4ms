<?php

namespace Tests\Support\Notifications;

use ci4commonmodel\CommonModel;

/**
 * In-memory CommonModel double for the Notifier unit tests.
 *
 * Extends the real CommonModel so it satisfies the `private CommonModel $model`
 * type hint inside Notifier (injected via setPrivateProperty), but overrides
 * every method Notifier touches to record calls and return canned values
 * instead of hitting the database. This keeps the Notifier logic tests fully
 * DB-free: no live connection, no migrations, no mutation of the dev schema.
 */
final class FakeCommonModel extends CommonModel
{
    /** Value {@see create()} returns (insert id; 0 simulates a failed insert). */
    public int $createReturn = 1;

    /** Value {@see count()} returns. */
    public int $countReturn = 0;

    /** How many times {@see count()} was invoked (proves cache reuse). */
    public int $countCalls = 0;

    /**
     * Rows captured by {@see create()}, in call order.
     *
     * @var list<array<string, mixed>>
     */
    public array $created = [];

    /**
     * Rows captured by {@see createMany()} on the last call.
     *
     * @var list<array<string, mixed>>
     */
    public array $createdMany = [];

    /**
     * Rows {@see lists()} returns (role fan-out members).
     *
     * @var list<object>
     */
    public array $listsReturn = [];

    /** Table name passed to {@see lists()} on the last call. */
    public ?string $lastListsTable = null;

    /**
     * WHERE clause passed to {@see count()} on the last call.
     *
     * @var array<string, mixed>
     */
    public array $lastCountWhere = [];

    private FakeConnection $connection;

    public function __construct()
    {
        $this->connection = new FakeConnection();
        $this->db         = $this->connection;
    }

    /**
     * Toggles what the fake connection reports for tableExists().
     *
     * @param bool $exists Whether the notifications table should appear present.
     *
     * @return void
     */
    public function setTableExists(bool $exists): void
    {
        $this->connection->exists = $exists;
    }

    /**
     * Records the inserted row and returns the canned insert id.
     *
     * @param string               $table Ignored; captured only implicitly.
     * @param array<string, mixed> $data  The row Notifier built.
     *
     * @return int {@see $createReturn}.
     */
    public function create(string $table, array $data = []): int
    {
        $this->created[] = $data;

        return $this->createReturn;
    }

    /**
     * Records the batch and returns the number of rows.
     *
     * @param string                    $table Ignored.
     * @param list<array<string, mixed>> $data The fan-out rows.
     *
     * @return int The row count.
     */
    public function createMany(string $table, array $data): mixed
    {
        $this->createdMany = $data;

        return count($data);
    }

    /**
     * Records the WHERE clause, counts the call and returns the canned count.
     *
     * @param string               $table    Ignored.
     * @param array<string, mixed> $where    Captured into {@see $lastCountWhere}.
     * @param array<string, mixed> $like     Ignored.
     * @param string               $select   Ignored.
     * @param bool                 $distinct Ignored.
     *
     * @return int {@see $countReturn}.
     */
    public function count(string $table, array $where = [], array $like = [], string $select = '', bool $distinct = false): int
    {
        $this->countCalls++;
        $this->lastCountWhere = $where;

        return $this->countReturn;
    }

    /**
     * Records the table name and returns the canned member list.
     *
     * @param string                       $table   Captured into {@see $lastListsTable}.
     * @param string                       $select  Ignored.
     * @param array<string, mixed>         $where   Ignored.
     * @param string                       $order   Ignored.
     * @param int                          $limit   Ignored.
     * @param int                          $pkCount Ignored.
     * @param array<string, mixed>         $like    Ignored.
     * @param array<string, mixed>         $orWhere Ignored.
     * @param list<array<string, mixed>>   $joins   Ignored.
     * @param array<string, mixed>         $options Ignored.
     * @param array<string, mixed>         $whereIn Ignored.
     *
     * @return list<object> {@see $listsReturn}.
     */
    public function lists(string $table, string $select = '*', array $where = [], string $order = 'id ASC', int $limit = 0, int $pkCount = 0, array $like = [], array $orWhere = [], array $joins = [], array $options = ['isReset' => false], array $whereIn = []): mixed
    {
        $this->lastListsTable = $table;

        return $this->listsReturn;
    }
}

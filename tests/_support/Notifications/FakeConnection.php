<?php

namespace Tests\Support\Notifications;

/**
 * Minimal DB-connection stand-in for the Notifier unit tests.
 *
 * The only method the production code reaches on the connection is
 * `tableExists()`; this double lets a test flip that answer without opening a
 * real database connection, so the "table not migrated yet" guard can be
 * exercised deterministically and non-destructively.
 */
final class FakeConnection
{
    /** Whether {@see tableExists()} reports the table as present. */
    public bool $exists = true;

    /**
     * Reports whether the given table exists (test-controlled).
     *
     * @param string $table Table name the caller is probing.
     *
     * @return bool The value of {@see $exists}, regardless of the table name.
     */
    public function tableExists(string $table): bool
    {
        return $this->exists;
    }
}

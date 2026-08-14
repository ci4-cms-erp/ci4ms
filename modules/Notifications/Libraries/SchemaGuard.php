<?php

declare(strict_types=1);

namespace Modules\Notifications\Libraries;

use CodeIgniter\Database\BaseConnection;

/**
 * Request-lived guard for additive schema capabilities (a new column / a new table).
 *
 * The module folder may have been dropped in while migrations have NOT run
 * yet; in that case neither the `notifications.exclude_users` /
 * `notifications.created_by` columns nor the `notification_preferences` table
 * exist. Read and write paths check these guards and skip the relevant SQL
 * fragment entirely — it doesn't throw a fatal before the module is migrated
 * (the same defensive reflex as Notifier::tablesReady(), but a SEPARATE
 * contract: tablesReady() is for Model B's two REQUIRED tables, these are
 * optional capabilities).
 *
 * Results are memoized per request (static): the schema doesn't change within
 * a single request, so there's no need to call `tableExists()`/`fieldExists()`
 * on every query and wear out the DB. The memo key carries the connection
 * identity (database name + table prefix) ALONGSIDE the capability: in setups
 * that work with multiple DB groups in the same request (multi-tenant, a
 * separate reporting connection), one connection's answer must not leak into
 * another — if it leaked, the error would be in the fail-open direction, i.e.
 * producing exclusion SQL against a schema that doesn't have the column.
 *
 * LONG-LIVED PROCESS WARNING: the memo relies on the request-lifetime
 * assumption. If a queue worker / daemon keeps running in the same PHP
 * process after a migration, it keeps carrying the stale answer; {@see reset()}
 * must be called after a migration in such a process.
 */
final class SchemaGuard
{
    /**
     * '{database}|{prefix}|{capability}' => exists (memoized for the request lifetime).
     *
     * @var array<string, bool>
     */
    private static array $memo = [];

    /**
     * Whether the `notifications.exclude_users` column exists (whether exclusion is read/writable).
     *
     * @param BaseConnection<object, object> $db Connection to query the schema on.
     *
     * @return bool True if the column exists.
     */
    public static function hasExcludeUsers(BaseConnection $db): bool
    {
        return self::$memo[self::memoKey($db, 'exclude_users')] ??= $db->fieldExists('exclude_users', 'notifications');
    }

    /**
     * Whether the `notifications.created_by` column exists (whether the accountability trail can be written).
     *
     * Same pattern as `hasExcludeUsers()`, BUT a different delivery contract:
     * when this capability is absent, the row is STILL written (fail-open),
     * only the trail is lost
     * ({@see \Modules\Notifications\Libraries\Channels\InAppChannel::buildRow()}).
     *
     * @param BaseConnection<object, object> $db Connection to query the schema on.
     *
     * @return bool True if the column exists.
     */
    public static function hasCreatedBy(BaseConnection $db): bool
    {
        return self::$memo[self::memoKey($db, 'created_by')] ??= $db->fieldExists('created_by', 'notifications');
    }

    /**
     * Whether the `notification_preferences` table exists (whether the preference JOIN can be added).
     *
     * @param BaseConnection<object, object> $db Connection to query the schema on.
     *
     * @return bool True if the table exists.
     */
    public static function hasPreferences(BaseConnection $db): bool
    {
        return self::$memo[self::memoKey($db, 'notification_preferences')] ??= $db->tableExists('notification_preferences');
    }

    /**
     * The connection-specific memo key for a capability.
     *
     * @param BaseConnection<object, object> $db         Connection the answer was obtained from.
     * @param string                         $capability Capability name.
     *
     * @return string '{database}|{prefix}|{capability}'.
     */
    private static function memoKey(BaseConnection $db, string $capability): string
    {
        return $db->getDatabase() . '|' . $db->getPrefix() . '|' . $capability;
    }

    /**
     * Resets the memoized capabilities.
     *
     * @internal Only for test support: tests that run a migration within the
     *           same process or inject a fake connection.
     *
     * @return void
     */
    public static function reset(): void
    {
        self::$memo = [];
    }
}

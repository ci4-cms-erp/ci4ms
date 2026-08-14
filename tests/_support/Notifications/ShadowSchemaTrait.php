<?php

namespace Tests\Support\Notifications;

use CodeIgniter\Database\BaseConnection;
use Modules\Notifications\Libraries\SchemaGuard;
use Throwable;

/**
 * Session-scoped TEMPORARY-table shadows of the Model B tables (FAZ 2 + FAZ 3 schema).
 *
 * The dev database this suite runs against has NOT had the later migrations
 * applied: `notifications.exclude_users`, `notifications.created_by` and
 * `notification_preferences` are absent, so SchemaGuard reports those
 * capabilities off and the exclusion / trace / preference SQL is never emitted.
 * Running the migration to test it would be a destructive schema change on a
 * shared database, which this suite must never do.
 *
 * Instead every shadowed table is created as a `CREATE TEMPORARY TABLE` on the
 * SAME connection the production code uses (CommonModel resolves the shared
 * 'default' connection, so Notifier/InAppChannel see these automatically). In
 * MySQL/MariaDB a temporary table MASKS a permanent table of the same name for
 * the lifetime of that one connection: the real schema is not altered, the real
 * rows are neither read nor written, and the shadow disappears when the
 * connection closes. tearDown still drops them explicitly — always with the
 * TEMPORARY keyword, which makes the statement a no-op against a permanent
 * table even if a shadow was never created.
 *
 * SchemaGuard memoises its answers per request, so both create and drop must
 * reset it; otherwise a stale `true` would leak into later test classes and make
 * them emit `exclude_users` SQL against the real, column-less table.
 */
trait ShadowSchemaTrait
{
    /**
     * The shared connection the shadows live on (production code uses the same one).
     *
     * @var BaseConnection<object, object>|null
     */
    private ?BaseConnection $shadowDb = null;

    /**
     * Creates the shadow tables and makes the new schema visible to SchemaGuard.
     *
     * Skips the test (rather than weakening it) when the MySQL user may not create
     * temporary tables, because without the shadow the FAZ 2 SQL is not emitted at
     * all and any assertion would be vacuous.
     *
     * @param bool $withExcludeUsers Whether the `notifications` shadow carries the FAZ 2
     *                               column. Pass false to reproduce a database where the
     *                               targeting migration has NOT run — the write still lands
     *                               in a throwaway temporary table, so the fail-closed path
     *                               can be asserted without addressing the real table.
     * @param bool $withCreatedBy    Whether the `notifications` shadow carries the FAZ 3
     *                               accountability column. Pass false to reproduce a database
     *                               where the `created_by` migration has NOT run, which is
     *                               the shape the fail-OPEN write path is asserted against.
     * @param bool $withUsers        Whether `users` is shadowed too. Off by default: masking
     *                               the account table is only wanted by tests that count or
     *                               validate recipients, and those need a KNOWN population
     *                               rather than whatever the dev database happens to hold.
     *
     * @return void
     */
    protected function createShadowTables(bool $withExcludeUsers = true, bool $withCreatedBy = true, bool $withUsers = false): void
    {
        $this->shadowDb = db_connect('default');
        $prefix         = $this->shadowDb->getPrefix();

        try {
            foreach (self::shadowDefinitions($withExcludeUsers, $withCreatedBy) as $table => $columns) {
                if ($table === 'users' && ! $withUsers) {
                    continue;
                }

                $this->shadowDb->query(
                    "CREATE TEMPORARY TABLE `{$prefix}{$table}` ({$columns})"
                    . ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci'
                );
            }
        } catch (Throwable $e) {
            $this->dropShadowTables();

            $this->markTestSkipped(
                'FAZ 2 schema cannot be shadowed on this server (CREATE TEMPORARY TABLE failed): ' . $e->getMessage()
            );
        }

        $this->shadowDb->resetDataCache();
        SchemaGuard::reset();
    }

    /**
     * Drops every shadow table and clears the memoised schema capabilities.
     *
     * @return void
     */
    protected function dropShadowTables(): void
    {
        if ($this->shadowDb === null) {
            return;
        }

        $prefix = $this->shadowDb->getPrefix();

        foreach (array_keys(self::shadowDefinitions()) as $table) {
            // TEMPORARY is mandatory: it scopes the DROP to this connection's
            // shadow and turns the statement into a no-op for the real table.
            $this->shadowDb->query("DROP TEMPORARY TABLE IF EXISTS `{$prefix}{$table}`");
        }

        $this->shadowDb->resetDataCache();
        SchemaGuard::reset();
    }

    /**
     * Asserts the permanent schema came through the test untouched.
     *
     * Call after {@see dropShadowTables()}: the real `notifications` table must
     * still lack the FAZ 2 column and `notification_preferences` must still be
     * absent, which proves the shadow never leaked into a real DDL.
     *
     * @param int $expectedColumns Column count captured before the shadow existed.
     * @param int $expectedRows    Row count captured before the shadow existed.
     *
     * @return void
     */
    protected function assertPermanentSchemaIntact(int $expectedColumns, int $expectedRows): void
    {
        $db = db_connect('default');
        $db->resetDataCache();

        $this->assertCount($expectedColumns, $db->getFieldNames('notifications'), 'the real notifications table was not altered');
        $this->assertSame($expectedRows, $db->table('notifications')->countAllResults(), 'no real notification row was written or removed');
    }

    /**
     * Column definitions of every shadowed table, keyed by unprefixed table name.
     *
     * `notifications` mirrors the live schema PLUS the FAZ 2 `exclude_users` and
     * FAZ 3 `created_by` columns, i.e. exactly the post-migration shape. Foreign
     * keys are omitted on purpose: temporary tables cannot carry them, and their
     * absence is what lets these tests address throwaway user ids without
     * inserting real users.
     *
     * `users` is listed here even though it is created only on request, so that
     * {@see dropShadowTables()} always tries to drop it — the DROP carries the
     * TEMPORARY keyword and is therefore a no-op against the permanent table.
     * Its column set is the subset the notification code reads (the picker's
     * label fields, the soft-delete flag and Shield's `status`, which carries the
     * ban state the addressable-user filter excludes), not the whole Shield schema.
     *
     * @param bool $withExcludeUsers false drops the FAZ 2 column from the
     *                               `notifications` definition (pre-migration shape).
     * @param bool $withCreatedBy    false drops the FAZ 3 column from the
     *                               `notifications` definition (pre-migration shape).
     *
     * @return array<string, string> Table name => column/key list for CREATE TABLE.
     */
    private static function shadowDefinitions(bool $withExcludeUsers = true, bool $withCreatedBy = true): array
    {
        $excludeUsers = $withExcludeUsers ? '`exclude_users` text DEFAULT NULL,' : '';
        $createdBy    = $withCreatedBy ? '`created_by` int(11) unsigned DEFAULT NULL,' : '';

        return [
            'notifications' => '
                `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                `user_id` int(11) unsigned DEFAULT NULL,
                `type` varchar(64) NOT NULL,
                `severity` varchar(16) NOT NULL DEFAULT \'info\',
                `target_type` varchar(16) NOT NULL DEFAULT \'broadcast\',
                `target_value` varchar(255) DEFAULT NULL,
                ' . $excludeUsers . $createdBy . '
                `title` varchar(255) NOT NULL,
                `body` text DEFAULT NULL,
                `url` varchar(255) DEFAULT NULL,
                `channel` varchar(32) NOT NULL DEFAULT \'inapp\',
                `read_at` datetime DEFAULT NULL,
                `created_at` datetime NOT NULL,
                PRIMARY KEY (`id`),
                KEY `notif_target` (`target_type`,`target_value`)
            ',
            'notification_reads' => '
                `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                `notification_id` int(11) unsigned NOT NULL,
                `user_id` int(11) unsigned NOT NULL,
                `read_at` datetime NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `notification_id_user_id` (`notification_id`,`user_id`)
            ',
            'notification_preferences' => '
                `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                `user_id` int(11) unsigned NOT NULL,
                `type` varchar(64) NOT NULL,
                `channel` varchar(32) NOT NULL DEFAULT \'*\',
                `enabled` tinyint(1) NOT NULL DEFAULT 1,
                `created_at` datetime DEFAULT NULL,
                `updated_at` datetime DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `notif_pref_unique` (`user_id`,`type`,`channel`),
                KEY `notif_pref_lookup` (`user_id`,`enabled`)
            ',
            'auth_groups_users' => '
                `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                `user_id` int(11) unsigned NOT NULL,
                `group` varchar(255) NOT NULL,
                `created_at` datetime NOT NULL,
                PRIMARY KEY (`id`)
            ',
            'users' => '
                `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                `username` varchar(30) DEFAULT NULL,
                `status` varchar(255) DEFAULT NULL,
                `firstname` varchar(255) DEFAULT NULL,
                `surname` varchar(255) DEFAULT NULL,
                `created_at` datetime DEFAULT NULL,
                `updated_at` datetime DEFAULT NULL,
                `deleted_at` datetime DEFAULT NULL,
                PRIMARY KEY (`id`)
            ',
        ];
    }
}

<?php

namespace Modules\Auth\Database\Migrations;

use CodeIgniter\Database\Migration;
use RuntimeException;

/**
 * Adds a UNIQUE index to `auth_groups.group` and to the
 * `auth_groups_users.(user_id,group)` pair (see DECISION-2,
 * `.ci4ms/plans/migration-manager/context.md:614-625`).
 *
 * An explicit name is REQUIRED: the explicit `$keyName` passed to
 * `Forge::addUniqueKey()` is NOT COMBINED with the table, unlike
 * `Forge::createTable()` (only the auto-generated name uses the
 * `$table . '_' . implode('_', $fields)` pattern, see
 * `vendor/codeigniter4/framework/system/Database/Forge.php:1179-1181`).
 * That's why the `dropKey()` calls in `down()` are made WITH
 * `$prefixKeyName=false` — otherwise `dropKey()` would re-add the
 * `DBPrefix` (`ci4ms_` in this environment) to the name and try to look up
 * an index name that doesn't exist (`Forge.php:450-453`).
 *
 * `auth_groups_users.user_id` has an FK set up by Shield (`user_id` →
 * `users.id`, `vendor/codeigniter4/shield/src/Database/Migrations/
 * 2020-12-28-223112_create_auth_tables.php:150-152` — `addForeignKey()` is
 * called WITHOUT an explicit index, so MySQL/InnoDB automatically creates
 * its own implicit support index for the FK). EMPIRICALLY CONFIRMED (in
 * this task's report): when the `(user_id, group)` composite UNIQUE is
 * added, MySQL automatically DROPS the FK's implicit support index IN
 * FAVOR OF the composite index (the leftmost column matches) — as a
 * result the composite UNIQUE becomes the FK's ONLY supporting index, and
 * `DROP INDEX` in `down()` BLOWS UP with "Cannot drop index ...: needed in
 * a foreign key constraint". Solution: BEFORE the composite UNIQUE, a
 * standalone, persistent, explicitly named `user_id` index is added,
 * independent of it — this index becomes the FK's permanent supporter,
 * leaving the composite UNIQUE free to be added and dropped.
 */
class AddUniqueKeyToAuthGroupsAndUsers extends Migration
{
    private const AUTH_GROUPS_UNIQUE_KEY        = 'auth_groups_group_unique';
    private const AUTH_GROUPS_USERS_UNIQUE_KEY  = 'auth_groups_users_user_id_group_unique';
    private const AUTH_GROUPS_USERS_USER_ID_KEY = 'auth_groups_users_user_id_index';

    /**
     * Adds a UNIQUE index for `auth_groups.group` and for
     * `auth_groups_users.(user_id,group)`. If there are duplicate rows, it
     * throws a meaningful exception before falling into a raw "Duplicate
     * entry" DB error — this guard is defensive: the state measured
     * read-only on the live `ci4ms` in this session is `auth_groups` 2
     * rows, `auth_groups_users` 3 rows, 0 duplicates, and only a
     * `PRIMARY(id)` index on both tables (the unique indexes this
     * migration adds don't exist yet) — i.e. this measurement is not the
     * evidence for DECISION-2 (the unique index decision), it's a
     * precaution taken against duplicates that could occur on different
     * or future setups.
     *
     * @throws RuntimeException If either target table contains duplicates.
     */
    public function up()
    {
        $this->guardNoDuplicateAuthGroups();
        $this->guardNoDuplicateAuthGroupsUsers();

        $this->forge->addUniqueKey('group', self::AUTH_GROUPS_UNIQUE_KEY);
        $this->forge->processIndexes('auth_groups');

        // A persistent, standalone single-column index is added first to
        // prevent the composite UNIQUE from "swallowing" the FK's
        // (user_id -> users.id) implicit support index (see class docblock).
        if (! $this->indexExists('auth_groups_users', self::AUTH_GROUPS_USERS_USER_ID_KEY)) {
            $this->forge->addKey('user_id', false, false, self::AUTH_GROUPS_USERS_USER_ID_KEY);
            $this->forge->processIndexes('auth_groups_users');
        }

        $this->forge->addUniqueKey(['user_id', 'group'], self::AUTH_GROUPS_USERS_UNIQUE_KEY);
        $this->forge->processIndexes('auth_groups_users');
    }

    /**
     * Removes the two UNIQUE indexes added in `up()`. There's no data
     * loss — only the constraint is lifted, rows are left untouched.
     *
     * `auth_groups_users_user_id_index` is DELIBERATELY NOT dropped here:
     * it's the `user_id` FK's (see class docblock) permanent support
     * index — dropping it would cause an FK constraint error (since the
     * composite UNIQUE is already dropped, it's the only remaining
     * alternative). This is a structure the FK (implicitly) NEEDED even
     * before `up()`; this migration just makes it visible/permanent, it
     * doesn't move data.
     */
    public function down()
    {
        $this->forge->dropKey('auth_groups', self::AUTH_GROUPS_UNIQUE_KEY, false);
        $this->forge->dropKey('auth_groups_users', self::AUTH_GROUPS_USERS_UNIQUE_KEY, false);
    }

    /**
     * Checks read-only, via `SHOW INDEX`, whether an index with the given
     * name exists on the given table (idempotency guard — to avoid a
     * "Duplicate key name" error if `up()` is called twice).
     */
    private function indexExists(string $table, string $keyName): bool
    {
        $prefixed = $this->db->DBPrefix . $table;

        return $this->db->query('SHOW INDEX FROM `' . $prefixed . '` WHERE Key_name = ?', [$keyName])->getResultArray() !== [];
    }

    /**
     * Checks read-only whether there's a duplicate value on
     * `auth_groups.group`.
     *
     * @throws RuntimeException If a duplicate `group` value is found, with
     *                          the message listing which values repeat how
     *                          many times.
     */
    private function guardNoDuplicateAuthGroups(): void
    {
        $duplicates = $this->db->table('auth_groups')
            ->select('group, COUNT(*) AS duplicate_count')
            ->groupBy('group')
            ->having('COUNT(*) >', 1)
            ->get()
            ->getResultArray();

        if ($duplicates === []) {
            return;
        }

        $details = implode(', ', array_map(
            static fn (array $row): string => sprintf('"%s" (x%d)', $row['group'], (int) $row['duplicate_count']),
            $duplicates,
        ));

        throw new RuntimeException(
            "auth_groups: 'group' sütununda UNIQUE index eklenmeden önce temizlenmesi gereken duplicate değer(ler) var — {$details}. "
            . 'Bu migration çalıştırılmadan önce fazla satırlar manuel olarak silinmeli (bkz. KARAR-2).',
        );
    }

    /**
     * Checks read-only whether there's a duplicate on the
     * `auth_groups_users.(user_id,group)` pair.
     *
     * @throws RuntimeException If a duplicate (user_id, group) pair is
     *                          found, with the message listing which pairs
     *                          repeat how many times.
     */
    private function guardNoDuplicateAuthGroupsUsers(): void
    {
        $duplicates = $this->db->table('auth_groups_users')
            ->select('user_id, group, COUNT(*) AS duplicate_count')
            ->groupBy(['user_id', 'group'])
            ->having('COUNT(*) >', 1)
            ->get()
            ->getResultArray();

        if ($duplicates === []) {
            return;
        }

        $details = implode(', ', array_map(
            static fn (array $row): string => sprintf('user_id=%s,group="%s" (x%d)', $row['user_id'], $row['group'], (int) $row['duplicate_count']),
            $duplicates,
        ));

        throw new RuntimeException(
            "auth_groups_users: (user_id, group) çiftinde UNIQUE index eklenmeden önce temizlenmesi gereken duplicate(lar) var — {$details}. "
            . 'Bu migration çalıştırılmadan önce fazla satırlar manuel olarak silinmeli (bkz. KARAR-2).',
        );
    }
}

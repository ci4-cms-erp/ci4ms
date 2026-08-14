<?php

namespace Tests\Modules\Notifications;

use CodeIgniter\Test\CIUnitTestCase;
use Modules\Notifications\Libraries\Notifier;
use Tests\Support\Notifications\ShadowSchemaTrait;

/**
 * Notifier::recipientCount(): the number the composer shows before anything is sent.
 *
 * Model B never materialises recipients — a publication is one global row plus a
 * relevance query — so "how many people will see this" is not stored anywhere and has
 * to be DERIVED from the publication definition. That derivation is the only place in
 * the module where set arithmetic (union of explicit ids and group members, minus the
 * exclusions, deduplicated) is done by hand, which is exactly why it is pinned here
 * rather than trusted: an off-by-one in a preview is a number an administrator makes
 * an irreversible decision on.
 *
 * The counts are asserted against a KNOWN population living in connection-scoped
 * TEMPORARY shadows ({@see ShadowSchemaTrait}), including a shadow of `users`; the dev
 * database this suite runs on is shared, and its real account table is neither read nor
 * written ({@see testCountingLeavesThePermanentAccountTableUntouched()}).
 *
 * SCOPE SPLIT: recipientCount() does not decide whether a TARGETED id exists — that
 * check belongs to ComposerController::existingUserIds(), which filters the selection
 * before this method ever sees it ({@see ComposerControllerTest::testPreviewIgnoresUnknownUsersAndGroups()}).
 * What it does verify against the table is the BROADCAST exclusion set, because there
 * the count is a subtraction and a phantom id would understate the audience.
 *
 * @internal
 */
final class NotifierRecipientCountTest extends CIUnitTestCase
{
    use ShadowSchemaTrait;

    /** An existing user, member of {@see GROUP}. */
    private const ALICE = 920001;

    /** A second existing user, member of {@see GROUP}. */
    private const BOB = 920002;

    /** An existing user who belongs to no group. */
    private const CAROL = 920003;

    /** A soft-deleted account: still a row, and still a stale membership row. */
    private const REMOVED = 920004;

    /** An id no `users` row carries. */
    private const MISSING = 920999;

    /** The group whose membership the count resolves. */
    private const GROUP = 'editors';

    /** Living accounts in the seeded population (REMOVED is soft-deleted). */
    private const LIVING = 3;

    /** Membership rows of {@see GROUP}: alice, bob and the stale one for REMOVED. */
    private const GROUP_ROWS = 3;

    /** Row count of the permanent users table, captured before any shadow existed. */
    private int $permanentUsers = 0;

    /**
     * Shadows the schema and seeds a known population in two batch writes.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Canary: never allowed to run against anything but the throwaway
        // test schema. Matches tests/Modules/Users/PermgroupPrivilegeEscalationTest.php
        // :76-80 and tests/Modules/Methods/ModuleInstallerNamespaceTest.php:84-88.
        $this->assertStringContainsString(
            'ci4ms_test',
            (string) \Config\Database::connect()->database,
            'Refusing to run: the active connection is not pointed at the test database.',
        );

        $this->permanentUsers = db_connect('default')->table('users')->countAllResults();

        $this->createShadowTables(withUsers: true);

        $this->shadowDb->table('users')->insertBatch([
            ['id' => self::ALICE, 'username' => 'alice', 'deleted_at' => null],
            ['id' => self::BOB, 'username' => 'bob', 'deleted_at' => null],
            ['id' => self::CAROL, 'username' => 'carol', 'deleted_at' => null],
            ['id' => self::REMOVED, 'username' => 'removed', 'deleted_at' => '2026-01-01 00:00:00'],
        ]);

        $this->shadowDb->table('auth_groups_users')->insertBatch([
            ['user_id' => self::ALICE, 'group' => self::GROUP, 'created_at' => '2026-01-01 00:00:00'],
            ['user_id' => self::BOB, 'group' => self::GROUP, 'created_at' => '2026-01-01 00:00:00'],
            ['user_id' => self::REMOVED, 'group' => self::GROUP, 'created_at' => '2026-01-01 00:00:00'],
            ['user_id' => self::CAROL, 'group' => 'superadmin', 'created_at' => '2026-01-01 00:00:00'],
        ]);
    }

    /**
     * Drops the shadows.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        $this->dropShadowTables();

        parent::tearDown();
    }

    /**
     * Counts the recipients of one publication definition.
     *
     * @param bool              $broadcast Whether the publication goes to everyone.
     * @param array<int, mixed> $users     Explicitly targeted ids.
     * @param array<int, mixed> $groups    Targeted group names.
     * @param array<int, mixed> $exclude   Ids kept out of the audience.
     *
     * @return int The estimated recipient count.
     */
    private function recipients(bool $broadcast, array $users = [], array $groups = [], array $exclude = []): int
    {
        return (new Notifier())->recipientCount($broadcast, $users, $groups, $exclude);
    }

    /**
     * A broadcast reaches every account that is not soft-deleted.
     *
     * @return void
     */
    public function testBroadcastCountsEveryLivingAccount(): void
    {
        $this->assertSame(self::LIVING, $this->recipients(true));
    }

    /**
     * Broadcast dominates the other arguments, exactly as the dispatch does.
     *
     * @return void
     */
    public function testBroadcastIgnoresTheSelectedUsersAndGroups(): void
    {
        $this->assertSame(self::LIVING, $this->recipients(true, [self::ALICE], [self::GROUP]));
    }

    /**
     * An exclusion that matches a living account is subtracted from a broadcast.
     *
     * @return void
     */
    public function testBroadcastSubtractsAnExcludedAccount(): void
    {
        $this->assertSame(self::LIVING - 1, $this->recipients(true, [], [], [self::ALICE]));
    }

    /**
     * An exclusion nobody matches is not subtracted: it would understate the audience.
     *
     * @return void
     */
    public function testBroadcastIgnoresAnExclusionThatMatchesNoAccount(): void
    {
        $this->assertSame(self::LIVING, $this->recipients(true, [], [], [self::MISSING]));
    }

    /**
     * Excluding an already soft-deleted account changes nothing: it was never counted.
     *
     * @return void
     */
    public function testBroadcastIgnoresAnExcludedSoftDeletedAccount(): void
    {
        $this->assertSame(self::LIVING, $this->recipients(true, [], [], [self::REMOVED]));
    }

    /**
     * Excluding everyone reports zero rather than a negative audience.
     *
     * @return void
     */
    public function testBroadcastNeverReportsANegativeCount(): void
    {
        $this->assertSame(0, $this->recipients(true, [], [], [self::ALICE, self::BOB, self::CAROL, self::REMOVED, self::MISSING]));
    }

    /**
     * A user-only publication counts exactly the selected ids.
     *
     * @return void
     */
    public function testTargetedCountsTheSelectedUsers(): void
    {
        $this->assertSame(2, $this->recipients(false, [self::ALICE, self::CAROL]));
    }

    /**
     * The same id selected twice is one recipient.
     *
     * @return void
     */
    public function testTargetedDeduplicatesARepeatedSelection(): void
    {
        $this->assertSame(1, $this->recipients(false, [self::ALICE, self::ALICE, (string) self::ALICE]));
    }

    /**
     * A group-only publication counts the group's membership rows.
     *
     * The stale membership of a soft-deleted account is counted with them: the count
     * is read from `auth_groups_users` alone, without a join back to `users`. That
     * documented approximation is pinned here so a change to it is deliberate — the
     * value is a preview, not a delivery guarantee, and the second join is not worth
     * its cost on the write path.
     *
     * @return void
     */
    public function testTargetedCountsGroupMembershipRowsIncludingStaleOnes(): void
    {
        $this->assertSame(self::GROUP_ROWS, $this->recipients(false, [], [self::GROUP]));
    }

    /**
     * A user reached both directly and through a group is counted once.
     *
     * @return void
     */
    public function testTargetedDeduplicatesAUserAlsoReachedThroughAGroup(): void
    {
        $this->assertSame(self::GROUP_ROWS, $this->recipients(false, [self::ALICE], [self::GROUP]), 'alice is in the group, so she adds nobody');
        $this->assertSame(self::GROUP_ROWS + 1, $this->recipients(false, [self::CAROL], [self::GROUP]), 'carol is not, so she does');
    }

    /**
     * Exclusions are subtracted from a targeted audience too.
     *
     * @return void
     */
    public function testTargetedSubtractsExclusions(): void
    {
        $this->assertSame(1, $this->recipients(false, [self::ALICE, self::BOB], [], [self::BOB]));
        $this->assertSame(self::GROUP_ROWS - 1, $this->recipients(false, [], [self::GROUP], [self::ALICE]));
    }

    /**
     * A publication with no target at all reaches nobody.
     *
     * @return void
     */
    public function testTargetedWithoutAnySelectionCountsZero(): void
    {
        $this->assertSame(0, $this->recipients(false));
    }

    /**
     * An unknown group name contributes nobody instead of raising the count.
     *
     * @return void
     */
    public function testTargetedIgnoresAGroupNobodyBelongsTo(): void
    {
        $this->assertSame(0, $this->recipients(false, [], ['ghosts']));
    }

    /**
     * Group names are trimmed and deduplicated before the single membership query.
     *
     * @return void
     */
    public function testTargetedNormalisesTheGroupNames(): void
    {
        $this->assertSame(self::GROUP_ROWS, $this->recipients(false, [], [' ' . self::GROUP . ' ', self::GROUP, '', '   ']));
    }

    /**
     * Ids that address nobody are dropped rather than counted or cast.
     *
     * A nested array casts to 1 in PHP, which would silently count the account with
     * id 1 — the first one, usually the superadmin.
     *
     * @return void
     */
    public function testTargetedDropsNonPositiveAndNonScalarIds(): void
    {
        $this->assertSame(1, $this->recipients(false, [self::ALICE, 0, -7, 'abc', [self::BOB], null]));
    }

    /**
     * Counting reads nothing from, and writes nothing to, the permanent account table.
     *
     * @return void
     */
    public function testCountingLeavesThePermanentAccountTableUntouched(): void
    {
        $this->assertSame(self::LIVING, $this->recipients(true), 'the count came from the shadowed population');

        $this->dropShadowTables();

        $this->assertSame(
            $this->permanentUsers,
            db_connect('default')->table('users')->countAllResults(),
            'the seeded accounts never reached the real table'
        );
    }
}

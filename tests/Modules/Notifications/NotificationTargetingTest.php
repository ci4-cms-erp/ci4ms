<?php

namespace Tests\Modules\Notifications;

use CodeIgniter\Test\CIUnitTestCase;
use Modules\Notifications\Libraries\NotificationBuilder;
use Modules\Notifications\Libraries\NotificationMessage;
use Modules\Notifications\Libraries\TargetResolver;
use Tests\Support\Notifications\CapturingChannel;
use Tests\Support\Notifications\FakeDispatchNotifier;

/**
 * FAZ 2 send-side targeting rules: exclusion encoding and overlap narrowing (DB-free).
 *
 * Everything asserted here is decided before a row reaches the database — how an
 * exclusion list is normalised and encoded, and which `exclude_users` set each
 * resolved target row is built with. Driving that through a real dispatch keeps the
 * builder/resolver/message collaboration honest while a capturing channel stands in
 * for delivery, so no connection is opened and no row is written. The read-side twin
 * of these rules (what the SQL then hides) is covered by
 * {@see NotificationRelevanceSqlTest}.
 *
 * @internal
 */
final class NotificationTargetingTest extends CIUnitTestCase
{
    /** Directly targeted user who is also a member of {@see OVERLAP_GROUP}. */
    private const OVERLAP_USER = 5;

    /** Group whose membership overlaps the directly targeted user. */
    private const OVERLAP_GROUP = 'admin';

    /** A second group used for the group-vs-group intersection limit. */
    private const OTHER_GROUP = 'editor';

    /** Event type used by every dispatch here (never persisted). */
    private const TYPE = 'phpunit.targeting';

    private CapturingChannel $channel;

    /**
     * Provides a fresh capturing channel for each dispatch.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->channel = new CapturingChannel();
    }

    /**
     * Builds a dispatch-ready builder wired to the capturing channel.
     *
     * @param array<string, list<int>> $membership Canned group => targeted-member map.
     *
     * @return array{0: NotificationBuilder, 1: FakeDispatchNotifier} Builder and its notifier double.
     */
    private function builder(array $membership = []): array
    {
        $notifier = new FakeDispatchNotifier($this->channel, $membership);

        return [$notifier->notify(self::TYPE)->title('PHPUnit'), $notifier];
    }

    /**
     * normalizeExcludeUsers casts to int, drops non-positive ids, dedupes and sorts.
     *
     * @return void
     */
    public function testNormalizeExcludeUsersCastsDropsDedupesAndSorts(): void
    {
        $this->assertSame(
            [3, 5, 12],
            NotificationMessage::normalizeExcludeUsers(['12', 5, 3, '5', 0, -7, 'abc', null]),
            'ids are cast, non-positive and non-numeric entries dropped, then deduped and sorted'
        );
    }

    /**
     * normalizeExcludeUsers is deterministic: the same set always yields the same list.
     *
     * @return void
     */
    public function testNormalizeExcludeUsersIsOrderIndependent(): void
    {
        $this->assertSame(
            NotificationMessage::normalizeExcludeUsers([12, 3, 5]),
            NotificationMessage::normalizeExcludeUsers([5, 12, 3]),
            'the stored value must not depend on the order exclusions were added in'
        );
    }

    /**
     * encodeExcludeUsers wraps the CSV in sentinel commas (the storage contract).
     *
     * @return void
     */
    public function testEncodeExcludeUsersWrapsCsvInSentinelCommas(): void
    {
        $this->assertSame(',5,12,', NotificationMessage::encodeExcludeUsers([12, 5]));
    }

    /**
     * encodeExcludeUsers stores NULL rather than an empty string for an empty list.
     *
     * @return void
     */
    public function testEncodeExcludeUsersReturnsNullWhenNobodyIsExcluded(): void
    {
        $this->assertNull(NotificationMessage::encodeExcludeUsers([]));
        $this->assertNull(NotificationMessage::encodeExcludeUsers([0, -1]), 'a list that normalises to empty also stores NULL');
    }

    /**
     * excludeMatchPattern is the exact twin of the storage format.
     *
     * @return void
     */
    public function testExcludeMatchPatternWrapsTheUserIdInSentinelCommas(): void
    {
        $this->assertSame('%,5,%', NotificationMessage::excludeMatchPattern(self::OVERLAP_USER));
    }

    /**
     * The sentinel wrapping makes ',15,' unmatchable by user 5's needle (no prefix collision).
     *
     * Encodes the guarantee the LIKE pattern relies on, at the string level: the
     * needle carried by the pattern must not occur inside the stored value.
     * {@see NotificationRelevanceSqlTest::testSentinelExclusionDoesNotHideRowFromPrefixCollidingUser()}
     * proves the same property through the actual SQL.
     *
     * @return void
     */
    public function testSentinelEncodingPreventsPrefixCollisionBetweenIds(): void
    {
        $stored = NotificationMessage::encodeExcludeUsers([15]);
        $needle = trim(NotificationMessage::excludeMatchPattern(self::OVERLAP_USER), '%');

        $this->assertSame(',15,', $stored);
        $this->assertStringNotContainsString($needle, (string) $stored, 'excluding 15 must not also exclude 5');
        $this->assertStringContainsString($needle, (string) NotificationMessage::encodeExcludeUsers([5, 12]), 'excluding 5 must match 5');
    }

    /**
     * The message DTO normalises the exclusion list handed to its constructor.
     *
     * @return void
     */
    public function testMessageNormalizesExclusionsOnConstruction(): void
    {
        $message = new NotificationMessage(self::TYPE, 'info', 'T', null, null, 'broadcast', null, ['inapp'], ['9', 4, 4, 0]);

        $this->assertSame([4, 9], $message->excludeUsers);
    }

    /**
     * The message keeps each exclusion source separately and exposes their union for storage.
     *
     * Storage stays provenance-blind — one CSV, both sources — while the channel can still
     * tell a guarantee (`exceptUser()`) from an optimisation (overlap narrowing) and decide
     * whether an unenforceable list should stop the write.
     *
     * @return void
     */
    public function testMessageKeepsExclusionProvenanceAndExposesTheUnion(): void
    {
        $message = new NotificationMessage(self::TYPE, 'info', 'T', null, null, 'group', self::OVERLAP_GROUP, ['inapp'], [77, '77'], ['5', 0]);

        $this->assertSame([77], $message->explicitExcludeUsers, 'the explicit list is normalised on its own');
        $this->assertSame([5], $message->derivedExcludeUsers, 'so is the derived one');
        $this->assertSame([5, 77], $message->excludeUsers, 'what gets stored is the deduped, sorted union');
    }

    /**
     * A message with no exclusion at all reports both sources empty.
     *
     * @return void
     */
    public function testMessageWithoutExclusionsHasBothSourcesEmpty(): void
    {
        $message = new NotificationMessage(self::TYPE, 'info', 'T', null, null, 'broadcast', null, ['inapp']);

        $this->assertSame([], $message->explicitExcludeUsers);
        $this->assertSame([], $message->derivedExcludeUsers);
        $this->assertSame([], $message->excludeUsers);
    }

    /**
     * exceptUser accumulates across calls and accepts both a scalar and an array.
     *
     * @return void
     */
    public function testExceptUserAccumulatesScalarAndArrayArguments(): void
    {
        [$builder] = $this->builder();
        $builder->broadcast()->exceptUser(12)->exceptUser([5, 12])->via('inapp')->dispatch();

        $this->assertSame([5, 12], $this->channel->exclusionsFor('broadcast', null));
    }

    /**
     * exceptUser overrides a direct toUser target: exclusion always wins.
     *
     * @return void
     */
    public function testExceptUserOverridesItsOwnDirectUserTarget(): void
    {
        [$builder] = $this->builder();
        $builder->toUser(self::OVERLAP_USER)->exceptUser(self::OVERLAP_USER)->via('inapp')->dispatch();

        $this->assertCount(1, $this->channel->messages, 'the row is still written; the exclusion is applied on read');
        $this->assertSame(
            [self::OVERLAP_USER],
            $this->channel->exclusionsFor('user', (string) self::OVERLAP_USER),
            'the directly targeted user is carried into the exclusion list'
        );
    }

    /**
     * A user targeted twice produces exactly one row (resolver dedup).
     *
     * @return void
     */
    public function testRepeatedUserTargetProducesASingleRow(): void
    {
        [$builder] = $this->builder();
        $builder->toUser(self::OVERLAP_USER)->toUser(self::OVERLAP_USER)->via('inapp')->dispatch();

        $this->assertCount(1, $this->channel->messages);
    }

    /**
     * A user targeted both directly and through a group is dropped from the GROUP row.
     *
     * Both rows are still written — the direct target survives so the user keeps the
     * notification after leaving the group — but only one of them is relevant to them.
     *
     * @return void
     */
    public function testOverlappingUserIsExcludedFromTheGroupRowOnly(): void
    {
        [$builder] = $this->builder([self::OVERLAP_GROUP => [self::OVERLAP_USER]]);
        $builder->toUser(self::OVERLAP_USER)->toGroup(self::OVERLAP_GROUP)->via('inapp')->dispatch();

        $this->assertCount(2, $this->channel->messages, 'Model B writes one row per target, no fan-out');
        $this->assertSame([], $this->channel->exclusionsFor('user', (string) self::OVERLAP_USER), 'the direct row stays addressed to them');
        $this->assertSame([self::OVERLAP_USER], $this->channel->exclusionsFor('group', self::OVERLAP_GROUP), 'the group row drops the already-covered user');
    }

    /**
     * Overlap narrowing merges with, rather than replaces, an explicit exceptUser list.
     *
     * @return void
     */
    public function testOverlapNarrowingMergesWithExplicitExclusions(): void
    {
        [$builder] = $this->builder([self::OVERLAP_GROUP => [self::OVERLAP_USER]]);
        $builder->toUser(self::OVERLAP_USER)->toGroup(self::OVERLAP_GROUP)->exceptUser(77)->via('inapp')->dispatch();

        $this->assertSame([5, 77], $this->channel->exclusionsFor('group', self::OVERLAP_GROUP), 'both the covered user and the explicit exclusion apply');
        $this->assertSame([77], $this->channel->exclusionsFor('user', (string) self::OVERLAP_USER), 'a non-group row carries only the explicit exclusions');
    }

    /**
     * The builder labels each exclusion with its source: exceptUser explicit, narrowing derived.
     *
     * The union is what reaches storage, but the split is what lets InAppChannel fail closed
     * on a guarantee it cannot keep while still writing a row whose only unenforceable
     * exclusion was an optimisation ({@see InAppChannelExclusionTest}).
     *
     * @return void
     */
    public function testOverlapNarrowingIsMarkedDerivedAndExceptUserExplicit(): void
    {
        [$builder] = $this->builder([self::OVERLAP_GROUP => [self::OVERLAP_USER]]);
        $builder->toUser(self::OVERLAP_USER)->toGroup(self::OVERLAP_GROUP)->exceptUser(77)->via('inapp')->dispatch();

        $groupRow = $this->channel->messageFor('group', self::OVERLAP_GROUP);
        $this->assertNotNull($groupRow);
        $this->assertSame([77], $groupRow->explicitExcludeUsers, 'only exceptUser() is a guarantee');
        $this->assertSame([self::OVERLAP_USER], $groupRow->derivedExcludeUsers, 'the narrowing is derived, so it may fail open');

        $userRow = $this->channel->messageFor('user', (string) self::OVERLAP_USER);
        $this->assertNotNull($userRow);
        $this->assertSame([77], $userRow->explicitExcludeUsers);
        $this->assertSame([], $userRow->derivedExcludeUsers, 'narrowing applies to group rows only');
    }

    /**
     * The membership intersection is resolved in one query, for all targets at once.
     *
     * @return void
     */
    public function testOverlapNarrowingUsesASingleMembershipQuery(): void
    {
        [$builder, $notifier] = $this->builder([self::OVERLAP_GROUP => [self::OVERLAP_USER]]);
        $builder->toUser(self::OVERLAP_USER)->toUser(6)->toGroup(self::OVERLAP_GROUP)->toGroup(self::OTHER_GROUP)->via('inapp')->dispatch();

        $this->assertCount(1, $notifier->groupMembersCalls, 'four targets still cost one membership query, not one per row');
        $this->assertSame(
            ['users' => [self::OVERLAP_USER, 6], 'groups' => [self::OVERLAP_GROUP, self::OTHER_GROUP]],
            $notifier->groupMembersCalls[0],
            'the single query is scoped to the explicitly targeted ids and groups'
        );
    }

    /**
     * A dispatch that cannot overlap never pays for the membership query.
     *
     * @return void
     */
    public function testNoMembershipQueryWhenOnlyOneTargetKindIsUsed(): void
    {
        [$userOnly, $userNotifier] = $this->builder();
        $userOnly->toUser(self::OVERLAP_USER)->via('inapp')->dispatch();
        $this->assertSame([], $userNotifier->groupMembersCalls, 'user-only dispatch cannot overlap');

        $this->channel = new CapturingChannel();
        [$groupOnly, $groupNotifier] = $this->builder();
        $groupOnly->toGroup(self::OVERLAP_GROUP)->via('inapp')->dispatch();
        $this->assertSame([], $groupNotifier->groupMembersCalls, 'group-only dispatch cannot overlap');
    }

    /**
     * Two overlapping GROUP targets are NOT narrowed — a documented Model B limit.
     *
     * Resolving a group-to-group intersection would mean materialising full group
     * membership at send time (the fan-out Model B exists to avoid) and growing the
     * stored `exclude_users` text to group size. A user in both groups therefore
     * receives two rows; this test pins that accepted behaviour so a future change
     * to it is a deliberate decision rather than an accident.
     *
     * @return void
     */
    public function testGroupToGroupOverlapIsNotNarrowed(): void
    {
        [$builder, $notifier] = $this->builder([self::OVERLAP_GROUP => [self::OVERLAP_USER]]);
        $builder->toGroup(self::OVERLAP_GROUP)->toGroup(self::OTHER_GROUP)->via('inapp')->dispatch();

        $this->assertSame([], $notifier->groupMembersCalls, 'no membership is read when only groups are targeted');
        $this->assertSame([], $this->channel->exclusionsFor('group', self::OVERLAP_GROUP), 'neither group row narrows the other');
        $this->assertSame([], $this->channel->exclusionsFor('group', self::OTHER_GROUP));
    }

    /**
     * broadcast() collapses every target to one row that keeps the explicit exclusions.
     *
     * @return void
     */
    public function testBroadcastCollapsesTargetsAndKeepsExplicitExclusions(): void
    {
        [$builder] = $this->builder([self::OVERLAP_GROUP => [self::OVERLAP_USER]]);
        $builder->toUser(self::OVERLAP_USER)->toGroup(self::OVERLAP_GROUP)->broadcast()->exceptUser(self::OVERLAP_USER)->via('inapp')->dispatch();

        $this->assertCount(1, $this->channel->messages, 'broadcast dominates every other target directive');
        $this->assertSame('broadcast', $this->channel->messages[0]->targetType);
        $this->assertSame([self::OVERLAP_USER], $this->channel->exclusionsFor('broadcast', null), 'exceptUser applies to a broadcast too');
    }

    /**
     * The resolver dedupes repeated directives and lets broadcast dominate.
     *
     * @return void
     */
    public function testTargetResolverDedupesDirectivesAndPrefersBroadcast(): void
    {
        $resolver = new TargetResolver();

        $this->assertSame(
            [['user', '5'], ['group', 'admin']],
            $resolver->resolve([['user', '5'], ['user', '5'], ['group', 'admin'], ['group', 'admin']], false),
            'each distinct target directive resolves to exactly one row'
        );

        $this->assertSame(
            [['broadcast', null]],
            $resolver->resolve([['user', '5'], ['group', 'admin']], true),
            'broadcast replaces every other directive'
        );
    }

    /**
     * A dispatch with no target at all writes nothing.
     *
     * @return void
     */
    public function testDispatchWithoutTargetsDeliversNothing(): void
    {
        [$builder] = $this->builder();

        $this->assertSame([], $builder->via('inapp')->dispatch());
        $this->assertSame([], $this->channel->messages);
    }
}

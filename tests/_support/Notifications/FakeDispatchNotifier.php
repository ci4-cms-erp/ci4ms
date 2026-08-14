<?php

namespace Tests\Support\Notifications;

use Modules\Notifications\Libraries\Channels\ChannelInterface;
use Modules\Notifications\Libraries\Notifier;

/**
 * Notifier double for the NotificationBuilder::dispatch() tests.
 *
 * dispatch() reaches the Notifier for exactly two things: the channel map and the
 * targeted-user/group intersection used to narrow overlapping rows. Both are
 * DB-backed in production, so this double replaces them with canned answers and a
 * call log — the log is what lets a test prove the intersection query is NOT run
 * when a dispatch cannot overlap (only users, or only groups, were targeted),
 * which is a query-count contract a plain stub could not express.
 *
 * {@see FakeNotifier} is the sibling double for the SSE controller (groupsFor /
 * topicsFor); it is final and answers different questions, hence a second class
 * rather than an extension of it.
 *
 * The parent constructor is deliberately NOT called: it builds a CommonModel and
 * would open a DB connection this double never needs.
 */
final class FakeDispatchNotifier extends Notifier
{
    /**
     * Arguments of every groupMembersAmong() call, in call order.
     *
     * @var list<array{users: list<int>, groups: list<string>}>
     */
    public array $groupMembersCalls = [];

    /**
     * @param ChannelInterface         $channel    Substituted for the whole channel map. Usually a
     *                                             {@see CapturingChannel}; a real channel can be passed
     *                                             to drive a dispatch all the way into storage.
     * @param array<string, list<int>> $membership Canned intersection: group => targeted member ids.
     */
    public function __construct(private ChannelInterface $channel, private array $membership = [])
    {
    }

    /**
     * Reports a single-entry channel map holding the capturing channel.
     *
     * @return array<string, ChannelInterface> Slug => channel, always just 'inapp'.
     */
    public function resolveChannels(): array
    {
        return ['inapp' => $this->channel];
    }

    /**
     * Records the call and reports the test-supplied group/member intersection.
     *
     * @param list<int>    $userIds Explicitly targeted user ids.
     * @param list<string> $groups  Group names targeted in the same dispatch.
     *
     * @return array<string, list<int>> The test-supplied membership map.
     */
    public function groupMembersAmong(array $userIds, array $groups): array
    {
        $this->groupMembersCalls[] = ['users' => $userIds, 'groups' => $groups];

        return $this->membership;
    }
}

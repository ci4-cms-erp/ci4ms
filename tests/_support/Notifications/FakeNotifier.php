<?php

namespace Tests\Support\Notifications;

use Modules\Notifications\Libraries\Notifier;

/**
 * Recording Notifier double for the SSE controller tests (no DB at all).
 *
 * RealtimeController::stream() asks the Notifier two separate questions: which Shield
 * groups the caller belongs to (groupsFor, the input of the connection-cap policy) and
 * which signal channels the caller may poll (topicsFor, the IDOR gate). Both are
 * DB-backed in production, so the cap tests replace the whole service seam with this
 * double.
 *
 * It records the user id of every call to either method, in call order, which is what
 * lets a test prove ORDERING rather than just outcome: a rejected stream (429) must
 * cost exactly one group query and must never derive the channel list, because
 * topicsFor() re-queries the groups a second time. A plain stub could not tell those
 * two apart.
 *
 * The parent constructor is deliberately NOT called: it builds a CommonModel and would
 * open a DB connection, while both DB-backed methods are overridden here.
 */
final class FakeNotifier extends Notifier
{
    /**
     * User ids passed to groupsFor(), in call order.
     *
     * @var list<int>
     */
    public array $groupsForCalls = [];

    /**
     * User ids passed to topicsFor(), in call order.
     *
     * @var list<int>
     */
    public array $topicsForCalls = [];

    /**
     * @param list<string> $fakeGroups Group names groupsFor() reports.
     * @param list<string> $fakeTopics Channel names topicsFor() reports.
     */
    public function __construct(private array $fakeGroups = [], private array $fakeTopics = ['broadcast'])
    {
    }

    /**
     * Records the call and reports the test-supplied group list.
     *
     * @param int $userId The id stream() resolved from the session.
     *
     * @return list<string> The test-supplied group names.
     */
    public function groupsFor(int $userId): array
    {
        $this->groupsForCalls[] = $userId;

        return $this->fakeGroups;
    }

    /**
     * Records the call and reports the test-supplied channel list.
     *
     * @param int $userId The id stream() resolved from the session.
     *
     * @return list<string> The test-supplied channel names.
     */
    public function topicsFor(int $userId): array
    {
        $this->topicsForCalls[] = $userId;

        return $this->fakeTopics;
    }
}

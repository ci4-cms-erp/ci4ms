<?php

namespace Tests\Support\Notifications;

/**
 * Shared, ordered ledger of channel slugs as dispatch() invokes them.
 *
 * Passed to {@see RecordingChannel} instances so a test can assert the exact
 * order in which NotificationBuilder::dispatch() drives the channels (inapp
 * must run before realtime, so the DB row is committed before the SSE nudge).
 */
final class ChannelCallLog
{
    /**
     * Channel slugs in the order their send() was called.
     *
     * @var list<string>
     */
    public array $slugs = [];

    /**
     * Appends a slug to the ledger.
     *
     * @param string $slug The channel slug whose send() just ran.
     *
     * @return void
     */
    public function record(string $slug): void
    {
        $this->slugs[] = $slug;
    }
}

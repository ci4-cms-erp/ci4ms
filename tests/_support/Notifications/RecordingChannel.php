<?php

namespace Tests\Support\Notifications;

use Modules\Notifications\Libraries\Channels\ChannelInterface;
use Modules\Notifications\Libraries\Channels\ChannelResult;
use Modules\Notifications\Libraries\NotificationMessage;

/**
 * A no-op delivery channel that records its own invocation order.
 *
 * Substituted for the real inapp/realtime channels (via a Notifier whose
 * resolveChannels() returns these) so dispatch()'s channel ordering can be
 * asserted without writing to the database or hitting the Mercure hub.
 */
final class RecordingChannel implements ChannelInterface
{
    /**
     * @param string         $slug The slug this channel answers to.
     * @param ChannelCallLog $log  Shared ledger recording send() order.
     */
    public function __construct(private string $slug, private ChannelCallLog $log)
    {
    }

    /**
     * Records this channel's slug and reports a successful (no-op) delivery.
     *
     * @param NotificationMessage $message Ignored; only the call is recorded.
     *
     * @return ChannelResult Always ok, so dispatch() treats it as delivered.
     */
    public function send(NotificationMessage $message): ChannelResult
    {
        $this->log->record($this->slug);

        return ChannelResult::ok(1);
    }
}

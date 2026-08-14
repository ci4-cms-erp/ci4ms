<?php

namespace Tests\Support\Notifications;

use Modules\Notifications\Libraries\Channels\ChannelResult;
use Modules\Notifications\Libraries\Channels\DurableChannelInterface;
use Modules\Notifications\Libraries\Channels\InAppChannel;
use Modules\Notifications\Libraries\NotificationMessage;

/**
 * A durable channel that stores the FIRST row of a publication and refuses the rest.
 *
 * Partial delivery is the state the composer must not paper over: some of the audience
 * was addressed, some was not, and reporting that as "sent" hides a security refusal
 * ({@see InAppChannel::refuseUnenforceableExclusion()}) behind a success message. It is
 * also the state that is hardest to reach from the outside — the real refusals apply to
 * every row of a publication at once — so it is produced here instead of being waited for.
 *
 * The first row is written through the REAL {@see InAppChannel}, so the stored half is a
 * genuine notification row rather than a claim about one. The refusal reason mirrors the
 * over-size exclusion refusal, which is per-row in principle.
 *
 * Instantiated by {@see \Modules\Notifications\Libraries\Notifier::resolveChannels()},
 * which does `new $class()`, so the constructor must take nothing. One instance handles
 * the whole dispatch, which is what makes "first row" meaningful.
 */
final class HalfFailingChannel implements DurableChannelInterface
{
    /** The real channel the first row is written through. */
    private InAppChannel $inner;

    /** Whether a row has already been accepted in this dispatch. */
    private bool $used = false;

    public function __construct()
    {
        $this->inner = new InAppChannel();
    }

    /**
     * Stores the first message and refuses every later one.
     *
     * @param NotificationMessage $message The message being delivered.
     *
     * @return ChannelResult The real channel's result for the first row, skipped after that.
     */
    public function send(NotificationMessage $message): ChannelResult
    {
        if ($this->used) {
            return ChannelResult::skipped('exclusion-too-large');
        }

        $this->used = true;

        return $this->inner->send($message);
    }
}

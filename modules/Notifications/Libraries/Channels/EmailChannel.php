<?php

declare(strict_types=1);

namespace Modules\Notifications\Libraries\Channels;

use Modules\Notifications\Libraries\NotificationMessage;

/**
 * Email channel — not implemented yet; skips delivery (no-op stub).
 *
 * Channel discovery and the builder's `via('email')` path already work today
 * thanks to this stub; the actual sending will be implemented here later.
 */
final class EmailChannel implements ChannelInterface
{
    /**
     * Skips the message without processing it.
     *
     * @param NotificationMessage $message The message (unused).
     *
     * @return ChannelResult Always skipped('not-implemented').
     */
    public function send(NotificationMessage $message): ChannelResult
    {
        return ChannelResult::skipped('not-implemented');
    }
}

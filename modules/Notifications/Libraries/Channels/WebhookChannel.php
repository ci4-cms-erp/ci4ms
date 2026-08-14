<?php

declare(strict_types=1);

namespace Modules\Notifications\Libraries\Channels;

use Modules\Notifications\Libraries\NotificationMessage;

/**
 * Webhook channel — not implemented yet; skips delivery (no-op stub).
 *
 * Channel discovery and the builder's `via('webhook')` path already work today
 * thanks to this stub; the actual HTTP delivery will be implemented here later.
 */
final class WebhookChannel implements ChannelInterface
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

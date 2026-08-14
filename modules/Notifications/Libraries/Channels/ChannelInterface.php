<?php

declare(strict_types=1);

namespace Modules\Notifications\Libraries\Channels;

use Modules\Notifications\Libraries\NotificationMessage;

/**
 * Contract for a notification delivery channel (in-app, email, webhook ...).
 */
interface ChannelInterface
{
    /**
     * Delivers a single notification message through this channel.
     *
     * @param NotificationMessage $message Sanitized, single-target message.
     *
     * @return ChannelResult Delivery result (ok/skipped + meta).
     */
    public function send(NotificationMessage $message): ChannelResult;
}

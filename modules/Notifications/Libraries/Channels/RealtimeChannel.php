<?php

declare(strict_types=1);

namespace Modules\Notifications\Libraries\Channels;

use Modules\Notifications\Config\NotificationsConfig;
use Modules\Notifications\Libraries\NotificationMessage;
use Modules\Notifications\Libraries\Notifier;
use Modules\Notifications\Libraries\SignalStoreInterface;

/**
 * Realtime channel — bumps an SSE "nudge" signal in Redis for the message.
 *
 * PERSISTENCE IS NOT HERE: InAppChannel writes the notifications row. This
 * channel only increments the "something new, reconcile" signal in the
 * `notif:sig:{channel}` counter; the client doesn't trust the payload and
 * re-reads the DB as the source of truth from the feed end. That's why Redis
 * being down / erroring → always ChannelResult::skipped, never throw. Channel
 * order is decided by dispatch(); with the default ['inapp','realtime'],
 * inapp (the DB commit) runs BEFORE this signal.
 */
final class RealtimeChannel implements ChannelInterface
{
    private NotificationsConfig $config;
    private SignalStoreInterface $signalStore;

    /**
     * @param NotificationsConfig|null    $config      Resolves the global config if not injected.
     * @param SignalStoreInterface|null   $signalStore Resolves service('signalStore') if not injected.
     */
    public function __construct(?NotificationsConfig $config = null, ?SignalStoreInterface $signalStore = null)
    {
        $this->config = $config ?? config(NotificationsConfig::class);

        if ($signalStore === null) {
            /** @var SignalStoreInterface $signalStore */
            $signalStore = service('signalStore');
        }

        $this->signalStore = $signalStore;
    }

    /**
     * Bumps the Redis signal on the message's authoritative channel; skips if
     * realtime is disabled or the signal fails.
     *
     * @param NotificationMessage $message Sanitized, single-target message.
     *
     * @return ChannelResult ok if the signal was bumped, skipped otherwise (never throws).
     */
    public function send(NotificationMessage $message): ChannelResult
    {
        if (! $this->config->realtimeEnabled) {
            return ChannelResult::skipped('realtime-disabled');
        }

        $channel = Notifier::topicFor($message->targetType, $message->targetValue);

        return $this->signalStore->bump($channel)
            ? ChannelResult::ok(null, ['topic' => $channel])
            : ChannelResult::skipped('signal-failed', ['topic' => $channel]);
    }
}

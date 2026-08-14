<?php

namespace Tests\Support\Notifications;

use Modules\Notifications\Libraries\Channels\ChannelInterface;
use Modules\Notifications\Libraries\Channels\ChannelResult;
use Modules\Notifications\Libraries\NotificationMessage;

/**
 * A delivery channel that keeps every message it was handed, in dispatch order.
 *
 * {@see RecordingChannel} records only the channel slug, which is enough to assert
 * channel ORDERING but says nothing about what was built. The FAZ 2 targeting rules
 * live in the message itself (one message per resolved row, each with its own
 * `excludeUsers` set), so proving them DB-free needs the messages retained rather
 * than counted — that is the one thing this double adds.
 */
final class CapturingChannel implements ChannelInterface
{
    /**
     * Messages handed to send(), in dispatch order (one per resolved target row).
     *
     * @var list<NotificationMessage>
     */
    public array $messages = [];

    /**
     * Stores the message and reports a successful (no-op) delivery.
     *
     * @param NotificationMessage $message The already-sanitised message dispatch built.
     *
     * @return ChannelResult Always ok, so dispatch() treats the row as delivered.
     */
    public function send(NotificationMessage $message): ChannelResult
    {
        $this->messages[] = $message;

        return ChannelResult::ok(count($this->messages));
    }

    /**
     * The stored (union) exclude list of the message built for one target.
     *
     * @param string      $targetType  'broadcast' | 'user' | 'group'.
     * @param string|null $targetValue Recipient id / group name (null for broadcast).
     *
     * @return list<int>|null The captured exclusions, or null when no such row was built.
     */
    public function exclusionsFor(string $targetType, ?string $targetValue): ?array
    {
        return $this->messageFor($targetType, $targetValue)?->excludeUsers;
    }

    /**
     * The whole message built for one target — the way to inspect exclusion provenance.
     *
     * @param string      $targetType  'broadcast' | 'user' | 'group'.
     * @param string|null $targetValue Recipient id / group name (null for broadcast).
     *
     * @return NotificationMessage|null The captured message, or null when no such row was built.
     */
    public function messageFor(string $targetType, ?string $targetValue): ?NotificationMessage
    {
        foreach ($this->messages as $message) {
            if ($message->targetType === $targetType && $message->targetValue === $targetValue) {
                return $message;
            }
        }

        return null;
    }
}

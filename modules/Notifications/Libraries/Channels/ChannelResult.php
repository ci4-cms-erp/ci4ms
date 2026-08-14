<?php

declare(strict_types=1);

namespace Modules\Notifications\Libraries\Channels;

/**
 * The immutable result of a single channel delivery attempt (DTO).
 *
 * `ok` carries whether the delivery happened, `insertId` (if any) the id of the
 * row that was written, and `meta` extra context such as the skip reason.
 *
 * IDENTITY: a channel does not know its own slug WHILE PRODUCING the result
 * (the same class can be bound to more than one slug); the label is attached by
 * {@see \Modules\Notifications\Libraries\NotificationBuilder::dispatch()}, the
 * sole owner of the channel map ({@see forChannel()}). The `durable` flag also
 * comes from there and states whether the result came from a channel that
 * writes a PERSISTENT row — this is the only basis for the delivery decision
 * ("was it actually written"). The shortcut `ok && insertId !== null` is NOT
 * USED: the day a transient channel that returns an insertId gets added, that
 * shortcut would silently give the wrong answer.
 */
final class ChannelResult
{
    /**
     * @param bool                 $ok       True if the delivery succeeded.
     * @param int|null             $insertId Id of the written row (null if none).
     * @param array<string, mixed> $meta     Extra context (e.g. skip reason).
     * @param string|null          $channel  Slug of the channel that produced the result (null if unlabeled).
     * @param bool                 $durable  Whether the result came from a channel that writes PERSISTENTLY.
     */
    private function __construct(
        public readonly bool $ok,
        public readonly ?int $insertId = null,
        public readonly array $meta = [],
        public readonly ?string $channel = null,
        public readonly bool $durable = false
    ) {
    }

    /**
     * Produces a successful delivery result.
     *
     * @param int|null             $insertId Id of the written row.
     * @param array<string, mixed> $meta     Extra context.
     *
     * @return self
     */
    public static function ok(?int $insertId = null, array $meta = []): self
    {
        return new self(true, $insertId, $meta);
    }

    /**
     * Produces a skipped (not delivered) result.
     *
     * @param string               $reason Skip reason ('not-implemented' ...).
     * @param array<string, mixed> $meta   Extra context.
     *
     * @return self
     */
    public static function skipped(string $reason = '', array $meta = []): self
    {
        return new self(false, null, array_merge(['reason' => $reason], $meta));
    }

    /**
     * Returns a copy of the result labeled with the id of the channel that produced it.
     *
     * The DTO is immutable; labeling does not mutate the existing instance, it
     * produces a new one. Channels are NOT EXPECTED to call this from their own
     * `send()` body — dispatch attaches the label, so the channel map remains
     * the sole owner of the slug-to-class mapping.
     *
     * @param string $slug    The channel's map key ('inapp', 'realtime' ...).
     * @param bool   $durable Whether the channel writes a persistent row ({@see DurableChannelInterface}).
     *
     * @return self The newly labeled result.
     */
    public function forChannel(string $slug, bool $durable): self
    {
        return new self($this->ok, $this->insertId, $this->meta, $slug, $durable);
    }
}

<?php

declare(strict_types=1);

namespace Modules\Notifications\Libraries\Channels;

/**
 * Marker interface for channels that deliver PERSISTENTLY.
 *
 * A channel implementing this interface makes the PROMISE that every result
 * that returns `ok` means it left a RECORD that will still be there when the
 * user reconnects. Today only {@see InAppChannel} does this
 * ({@see \Modules\Notifications\Libraries\Notifier} Model B: a global row in
 * the `notifications` table). {@see RealtimeChannel}, on the other hand,
 * produces NO row, only a "re-read" signal — it cannot be counted as delivered.
 *
 * WHY A SEPARATE INTERFACE: the answer to "was it sent" is NOT "did any channel
 * return ok". When the persistent channel drops the row via a SECURITY refusal
 * ({@see InAppChannel::refuseUnenforceableExclusion()}) while the transient
 * channel still bumps its signal, an "any one is ok" rule would tell the
 * administrator "sent" — yet there is no notification, and clients following
 * the signal find an empty feed.
 *
 * Adds NO methods: none of the existing channels (or their test doubles) have
 * to change, while NEW channels are treated as transient (fail-closed) unless
 * they explicitly declare persistence. The decision inside
 * {@see \Modules\Notifications\Libraries\DispatchOutcome} is made by checking
 * this marker.
 */
interface DurableChannelInterface extends ChannelInterface
{
}

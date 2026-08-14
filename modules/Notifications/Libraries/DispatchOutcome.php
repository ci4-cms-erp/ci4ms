<?php

declare(strict_types=1);

namespace Modules\Notifications\Libraries;

use Modules\Notifications\Libraries\Channels\ChannelResult;
use Modules\Notifications\Libraries\Channels\DurableChannelInterface;

/**
 * The immutable answer to whether a broadcast was ACTUALLY delivered.
 *
 * The decision is made here, not by the caller: the only correct measure of
 * "was it sent" is the results of channels that write PERSISTENTLY
 * ({@see DurableChannelInterface}). Transient channels (like the realtime
 * signal) NEVER count toward the total — their `ok` only means "I told the
 * clients to re-read"; if there's no row to read, that isn't a delivery.
 *
 * THREE STATES are surfaced separately because what needs to be told to the
 * user differs:
 *   - COMPLETE ({@see isComplete()}): every attempted persistent row was written.
 *   - PARTIAL  ({@see isPartial()}):  some were written, some were REFUSED. The
 *     broadcast did not reach part of the intended audience; saying "sent" would be a lie.
 *   - NONE     ({@see storedNothing()}): not a single row was written.
 * If no persistent channel ran at all (`attempted() === 0`), the result is
 * NONE — the absence of information does not count as success (fail-closed).
 *
 * Refusal reasons ({@see refusals()}) do NOT DUPLICATE the channel's own log
 * entry; they are only carried to enrich the caller's decision and message.
 */
final class DispatchOutcome
{
    /**
     * @param int          $attempted Number of rows attempted via a persistent channel.
     * @param int          $stored    Number of those actually written.
     * @param list<string> $refusals  Deduplicated reasons for the ones that could not be written.
     */
    private function __construct(
        private readonly int $attempted,
        private readonly int $stored,
        private readonly array $refusals
    ) {
    }

    /**
     * Derives the delivery status from the channel results.
     *
     * @param ChannelResult[] $results Output of {@see NotificationBuilder::dispatch()}
     *                                 (results labeled with the channel id).
     *
     * @return self Delivery summary.
     */
    public static function fromResults(array $results): self
    {
        $durable = array_filter($results, static fn (ChannelResult $result): bool => $result->durable);
        $stored  = array_filter($durable, static fn (ChannelResult $result): bool => $result->ok);

        $refusals = array_map(
            static fn (ChannelResult $result): string => (string) ($result->meta['reason'] ?? 'unknown'),
            array_filter($durable, static fn (ChannelResult $result): bool => ! $result->ok)
        );

        return new self(count($durable), count($stored), array_values(array_unique($refusals)));
    }

    /**
     * How many rows were attempted via a persistent channel.
     *
     * @return int Number of attempted rows (0 if no persistent channel ran).
     */
    public function attempted(): int
    {
        return $this->attempted;
    }

    /**
     * How many rows were actually written.
     *
     * @return int Number of written rows.
     */
    public function stored(): int
    {
        return $this->stored;
    }

    /**
     * Was every attempted row written (and was at least one row attempted).
     *
     * @return bool True if the broadcast was delivered completely.
     */
    public function isComplete(): bool
    {
        return $this->attempted > 0 && $this->stored === $this->attempted;
    }

    /**
     * Was part of the rows written and part refused.
     *
     * @return bool True on partial delivery.
     */
    public function isPartial(): bool
    {
        return $this->stored > 0 && $this->stored < $this->attempted;
    }

    /**
     * Was no persistent row written at all (including the case where nothing was attempted).
     *
     * @return bool True if there is no notification at all.
     */
    public function storedNothing(): bool
    {
        return $this->stored === 0;
    }

    /**
     * Deduplicated refusal reasons for the rows that could not be written.
     *
     * @return list<string> Reasons such as 'exclusion-unsupported', 'insert-failed'.
     */
    public function refusals(): array
    {
        return $this->refusals;
    }
}

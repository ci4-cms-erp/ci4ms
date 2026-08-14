<?php

declare(strict_types=1);

namespace Modules\Notifications\Libraries;

use Modules\Notifications\Libraries\Channels\ChannelResult;
use Modules\Notifications\Libraries\Channels\DurableChannelInterface;

/**
 * Fluent builder for a notification broadcast.
 *
 * Usage:
 *   service('notifier')->notify('comment.new')
 *       ->severity('info')->title('New comment')->body('...')->url('/backend/blog/comments')
 *       ->toUser(5)->via('inapp')->dispatch();
 *
 * Multiple target directives (toUser/toGroup) each produce a separate global row;
 * broadcast() overrides all other directives. Channel discovery is delegated to Notifier.
 * IDs passed to exceptUser() land in each row's `exclude_users` field and are
 * filtered out on the read path (applyRelevance).
 */
final class NotificationBuilder
{
    private Notifier $notifier;
    private string $type;
    private string $severity = 'info';
    private string $title = '';
    private ?string $body = null;
    private ?string $url = null;

    /** @var array<int, array{0:string, 1:?string}> */
    private array $targets = [];

    /** @var list<int> User IDs accumulated via exceptUser() to be excluded from the target. */
    private array $excludeUsers = [];

    /** ID of the user who produced the broadcast; null = produced by the system (event/CLI). */
    private ?int $createdBy = null;

    private bool $broadcastFlag = false;

    /**
     * Default delivery channels: inapp first (durable DB row), then realtime
     * (Redis-backed best-effort SSE emit). Order matters — realtime must run
     * AFTER the inapp commit so the row already exists at emit time (dispatch()
     * processes this array in sequence). `via(...)` overrides this default with
     * an explicit channel selection.
     *
     * @var string[]
     */
    private array $channels = ['inapp', 'realtime'];

    /**
     * @param Notifier $notifier Service that resolves the channel map.
     * @param string   $type     Machine-readable event type.
     */
    public function __construct(Notifier $notifier, string $type)
    {
        $this->notifier = $notifier;
        $this->type     = $type;
    }

    /**
     * Sets the severity level (an invalid value falls back to 'info').
     *
     * @param string $level info|warning|critical.
     *
     * @return self
     */
    public function severity(string $level): self
    {
        $this->severity = NotificationMessage::normalizeSeverity($level);

        return $this;
    }

    /**
     * Sets the title (sanitization happens during message construction).
     *
     * @param string $title Display title.
     *
     * @return self
     */
    public function title(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    /**
     * Sets the body.
     *
     * @param string|null $body Display body or null.
     *
     * @return self
     */
    public function body(?string $body): self
    {
        $this->body = $body;

        return $this;
    }

    /**
     * Sets the click target (validation happens during message construction).
     *
     * @param string|null $url In-site '/...' or http(s) URL.
     *
     * @return self
     */
    public function url(?string $url): self
    {
        $this->url = $url;

        return $this;
    }

    /**
     * Adds a single user to the target (global 'user' row).
     *
     * @param int $userId Recipient user ID.
     *
     * @return self
     */
    public function toUser(int $userId): self
    {
        $this->targets[] = ['user', (string) $userId];

        return $this;
    }

    /**
     * Adds a Shield group to the target (global 'group' row).
     *
     * @param string $group Group name.
     *
     * @return self
     */
    public function toGroup(string $group): self
    {
        $this->targets[] = ['group', $group];

        return $this;
    }

    /**
     * Leaves one or more users OUT of the target (cumulative).
     *
     * Applies regardless of the target (broadcast included) and also overrides
     * a direct target given via toUser(): exclusion ALWAYS wins. IDs are cast
     * to int, 0/negative ones are dropped, and the list is deduplicated and
     * sorted. The filter is applied on the read path inside
     * `Notifier::applyRelevance()`; that's why markRead() also returns 404 for
     * an excluded user.
     *
     * @param int|array<int, mixed> $userIds Single ID or list of IDs.
     *
     * @return self
     */
    public function exceptUser(int|array $userIds): self
    {
        $this->excludeUsers = NotificationMessage::normalizeExcludeUsers(
            array_merge($this->excludeUsers, is_array($userIds) ? $userIds : [$userIds])
        );

        return $this;
    }

    /**
     * Marks the user who PRODUCED the broadcast (an accountability trail, NOT targeting).
     *
     * Only lands in the `notifications.created_by` field; it has no effect on any
     * delivery decision (relevance, exclusion, preferences) — the sender also sees
     * their own broadcast, and {@see exceptUser()} must be called separately if
     * that's not desired. The caller MUST supply the ID from the SERVER
     * (`auth()->id()`); accepting a value from the client here would forge the
     * trail. A 0/negative value is reduced to null inside {@see NotificationMessage}.
     *
     * If the column hasn't been migrated yet, the row is STILL written, only the
     * trail is lost (fail-open — rationale: {@see \Modules\Notifications\Libraries\Channels\InAppChannel::buildRow()}).
     *
     * @param int|null $userId ID of the user who produced the broadcast, or null (system).
     *
     * @return self
     */
    public function createdBy(?int $userId): self
    {
        $this->createdBy = $userId;

        return $this;
    }

    /**
     * Marks the broadcast for all users (a single 'broadcast' row; overrides other targets).
     *
     * @return self
     */
    public function broadcast(): self
    {
        $this->broadcastFlag = true;

        return $this;
    }

    /**
     * Sets the delivery channels (left empty falls back to the default ['inapp','realtime']).
     *
     * @param string ...$channels Channel slugs.
     *
     * @return self
     */
    public function via(string ...$channels): self
    {
        if ($channels !== []) {
            $this->channels = $channels;
        }

        return $this;
    }

    /**
     * Resolves the broadcast, builds a message per target, and delivers it through each channel.
     *
     * If there are no targets at all (and broadcast() wasn't called), nothing is sent.
     *
     * DOUBLE VISIBILITY (dedup): TargetResolver deduplicates identical [type|value]
     * directives, but `toUser(5)` + `toGroup('admin')` (where user 5 is an admin
     * member) produces two SEPARATE rows, and on the read path BOTH are relevant
     * to user 5. This overlap is resolved in a SINGLE query via
     * {@see coveredUserTargets()}, and the user is filtered out by being written
     * into the GROUP row's `exclude_users` field: they see their own 'user' row
     * but not the group row. Because the direct target is preserved, the user
     * doesn't lose the notification even if they later leave the group.
     *
     * This narrowing is carried in a field SEPARATE from the message's explicit
     * exclusions ({@see NotificationMessage::$derivedExcludeUsers}), because the
     * delivery guarantee differs: `exceptUser()` is a GUARANTEE — if it can't be
     * applied, the channel row is never written. The overlap narrowing is only an
     * OPTIMIZATION; when it can't be applied, the row is still written and the
     * user sees the notification twice. Losing the notification entirely is a
     * worse outcome than seeing it twice.
     *
     * LIMIT: the intersection of two GROUP targets (`toGroup('a')` + `toGroup('b')`,
     * a user in both) is not resolved — that would require materializing the
     * entire group membership at send time (the fan-out Model B avoids) and
     * growing the `exclude_users` text with the group size. In that scenario the
     * user sees two rows.
     *
     * @return ChannelResult[] One result per (target x channel); each result is
     *                         tagged with the slug and durability flag of the
     *                         channel that produced it, so the caller can answer
     *                         "how many rows were ACTUALLY written" via
     *                         {@see DispatchOutcome}.
     */
    public function dispatch(): array
    {
        $rows = (new TargetResolver())->resolve($this->targets, $this->broadcastFlag);
        if ($rows === []) {
            return [];
        }

        $covered    = $this->coveredUserTargets($rows);
        $channelMap = $this->notifier->resolveChannels();
        $results    = [];

        foreach ($rows as [$targetType, $targetValue]) {
            $message = new NotificationMessage(
                $this->type,
                $this->severity,
                $this->title,
                $this->body,
                $this->url,
                $targetType,
                $targetValue,
                $this->channels,
                $this->excludeUsers,
                $this->derivedExcludesFor($targetType, $targetValue, $covered),
                $this->createdBy
            );

            foreach ($this->channels as $slug) {
                $channel = $channelMap[$slug] ?? null;
                if ($channel === null) {
                    continue;
                }

                // We tag it HERE: the channel map is the sole owner of the slug-to-class
                // mapping, the channel itself doesn't know which slug it's bound to. An
                // untagged result could only answer "was it really written" via
                // incidental hints like insertId ({@see ChannelResult}).
                $results[] = $channel->send($message)
                    ->forChannel($slug, $channel instanceof DurableChannelInterface);
            }
        }

        return $results;
    }

    /**
     * Groups together, per group, users targeted both directly and via a group in the same broadcast.
     *
     * A single batch query (no N+1), and it only runs when both target types are
     * present; group membership knowledge stays in Notifier (the sole owner of
     * the group query).
     *
     * @param array<int, array{0:string, 1:?string}> $rows Resolved row definitions.
     *
     * @return array<string, list<int>> Group name => IDs in that group that are also targeted directly.
     */
    private function coveredUserTargets(array $rows): array
    {
        $userIds = [];
        $groups  = [];

        foreach ($rows as [$targetType, $targetValue]) {
            if ($targetType === 'user') {
                $userIds[] = (int) $targetValue;
            } elseif ($targetType === 'group' && $targetValue !== null) {
                $groups[] = $targetValue;
            }
        }

        if ($userIds === [] || $groups === []) {
            return [];
        }

        return $this->notifier->groupMembersAmong($userIds, $groups);
    }

    /**
     * The DERIVED exclusion list for a single row: only targets that overlap in group rows.
     *
     * The explicit exceptUser() list is not merged in here; it's passed to the
     * message as a separate field so the channel can distinguish whether an
     * exclusion that couldn't be applied was a guarantee or an optimization
     * ({@see dispatch()}).
     *
     * @param string                   $targetType  'broadcast' | 'user' | 'group'.
     * @param string|null              $targetValue Target value.
     * @param array<string, list<int>> $covered     Output of {@see coveredUserTargets()}.
     *
     * @return list<int> IDs also targeted directly in this row (empty if not a group).
     */
    private function derivedExcludesFor(string $targetType, ?string $targetValue, array $covered): array
    {
        if ($targetType !== 'group' || $targetValue === null) {
            return [];
        }

        return $covered[$targetValue] ?? [];
    }
}

<?php

declare(strict_types=1);

namespace Modules\Notifications\Libraries\Channels;

use ci4commonmodel\CommonModel;
use Modules\Notifications\Config\NotificationsConfig;
use Modules\Notifications\Libraries\NotificationMessage;
use Modules\Notifications\Libraries\SchemaGuard;

/**
 * In-app channel — writes the message to the `notifications` table as a
 * global (Model B) row.
 *
 * The row is opened with `user_id = null`; the target is expressed only via
 * `target_type`/`target_value` and resolved at read time through the relevance
 * query (no fan-out). After the write, the unread-badge cache is dropped via a
 * portable `deleteMatching` guard; on cache handlers that don't support it, a
 * 60s TTL acts as the upper bound instead.
 *
 * Exclusion is handled in this channel BY ITS SOURCE: if an EXPLICIT
 * (`exceptUser()`) exclusion cannot be guaranteed, the row is not written
 * (FAIL-CLOSED); only when a DERIVED (overlap-narrowing) exclusion cannot be
 * applied is the row still written (FAIL-OPEN + warning).
 * Rationale: {@see refuseUnenforceableExclusion()}.
 *
 * PERSISTENCE DECLARATION: this channel implements {@see DurableChannelInterface},
 * meaning every result that returns `ok` can be counted as "the notification
 * was actually written". Whether a broadcast was delivered is decided ONLY
 * from the results of channels marked this way
 * ({@see \Modules\Notifications\Libraries\DispatchOutcome}).
 */
final class InAppChannel implements DurableChannelInterface
{
    /** Global rows have no user-specific value. */
    private const GLOBAL_USER_ID = null;

    private CommonModel $model;

    public function __construct()
    {
        $this->model = new CommonModel();
    }

    /**
     * Writes the message to a `notifications` row and invalidates the unread cache.
     *
     * When an EXPLICIT exclusion request cannot be enforced, the row is NOT
     * WRITTEN; only when a derived narrowing cannot be applied is the row
     * written, with the situation logged as a warning. Rationale:
     * {@see refuseUnenforceableExclusion()}.
     *
     * @param NotificationMessage $message Sanitized, single-target message.
     *
     * @return ChannelResult ok(insertId) if the row was written, skipped otherwise.
     */
    public function send(NotificationMessage $message): ChannelResult
    {
        if (! $this->model->db->tableExists('notifications')) {
            return ChannelResult::skipped('notifications-table-missing');
        }

        $refusal = $this->refuseUnenforceableExclusion($message);
        if ($refusal !== null) {
            return $refusal;
        }

        $insertId = $this->model->create('notifications', $this->buildRow($message));

        if ($insertId <= 0) {
            return ChannelResult::skipped('insert-failed');
        }

        $this->invalidateUnreadCaches();

        return ChannelResult::ok($insertId);
    }

    /**
     * Refuses the write for an exclusion request that cannot be enforced
     * (only for EXPLICIT exclusion).
     *
     * `exceptUser()` is not a delivery PREFERENCE, it is a GUARANTEE of
     * exclusion that the caller explicitly asked for
     * ({@see \Modules\Notifications\Libraries\NotificationBuilder::exceptUser()}
     * contract: "exclusion ALWAYS wins"). In the two situations where the
     * guarantee cannot be given, writing the row would mean the excluded user
     * SEES the notification — a silent leak. So the row is never written and
     * the event is logged at `critical` level:
     *   1. The `exclude_users` column has not been migrated yet (SchemaGuard
     *      returns false); the field cannot be written, and the read-path
     *      filter is never added either.
     *   2. The list exceeds the {@see NotificationsConfig::EXCLUDE_USERS_MAX}
     *      cap; on TEXT overflow the value would be silently truncated and the
     *      sentinel wrapper broken. No TRUNCATION is applied — truncating
     *      would break things in exactly the leaking direction too.
     *
     * SOURCE DISTINCTION: the list DERIVED from overlap narrowing ({@see
     * \Modules\Notifications\Libraries\NotificationBuilder::coveredUserTargets()})
     * is not a guarantee, it is an optimization that prevents double delivery.
     * Behaving fail-closed for it too when the column is missing would mean
     * the GROUP row disappears entirely in a `toUser(5) + toGroup('x')`
     * broadcast where nobody called `exceptUser()` — the entire group would
     * fail to receive the notification. Since that is a heavier loss than the
     * leak being prevented, the row IS WRITTEN only for a derived exclusion
     * (FAIL-OPEN), and the situation is logged as `warning` — the cost being
     * that the directly targeted user sees the notification twice. The cap
     * check, however, doesn't care about the source: an overflowing CSV's leak
     * is evaluated on the combined count regardless of where it came from.
     *
     * Broadcasts that DO NOT WANT exclusion (`excludeUsers === []`) never go
     * through this path at all, so on a module that hasn't been migrated,
     * normal notifications keep being written as before.
     *
     * @param NotificationMessage $message The message about to be delivered.
     *
     * @return ChannelResult|null The skipped result if refused, null otherwise.
     */
    private function refuseUnenforceableExclusion(NotificationMessage $message): ?ChannelResult
    {
        if ($message->excludeUsers === []) {
            return null;
        }

        $where = $message->targetType . '/' . ($message->targetValue ?? '-');

        if (! SchemaGuard::hasExcludeUsers($this->model->db)) {
            if ($message->explicitExcludeUsers === []) {
                log_message('warning', sprintf(
                    'InAppChannel: `%s` bildiriminde örtüşme daraltması UYGULANAMADI — `notifications.exclude_users` kolonu yok (hedef: %s). Satır yazıldı; doğrudan hedeflenen %d kullanıcı bildirimi iki kez görebilir. `php spark migrate --all` çalıştırın.',
                    $message->type,
                    $where,
                    count($message->derivedExcludeUsers)
                ));

                return null;
            }

            log_message('critical', sprintf(
                'InAppChannel: `%s` bildirimi YAZILMADI — %d kullanıcı hariç tutulacaktı ama `notifications.exclude_users` kolonu yok (hedef: %s). `php spark migrate --all` çalıştırın.',
                $message->type,
                count($message->excludeUsers),
                $where
            ));

            return ChannelResult::skipped('exclusion-unsupported');
        }

        if (count($message->excludeUsers) > NotificationsConfig::EXCLUDE_USERS_MAX) {
            log_message('critical', sprintf(
                'InAppChannel: `%s` bildirimi YAZILMADI — hariç tutma listesi %d kullanıcı, tavan %d (hedef: %s). Liste kırpılsaydı hariç tutulan kullanıcılar bildirimi görürdü.',
                $message->type,
                count($message->excludeUsers),
                NotificationsConfig::EXCLUDE_USERS_MAX,
                $where
            ));

            return ChannelResult::skipped('exclusion-too-large');
        }

        return null;
    }

    /**
     * Prepares the field set for a global notification row.
     *
     * `exclude_users` is only written if the column has been migrated (PHASE 2
     * additive column); the format is a sentinel-wrapped CSV
     * ({@see NotificationMessage::encodeExcludeUsers()}), staying NULL for an
     * empty list.
     *
     * `created_by` likewise depends on the column, BUT its contract is the
     * OPPOSITE, and this FAIL-OPEN behavior is deliberate: if the column is
     * missing the row is STILL written, only the trail is dropped. The
     * fail-closed refusal of `exclude_users`
     * ({@see refuseUnenforceableExclusion()}) is NOT CARRIED OVER HERE, because
     * the two protect different things: exclusion is a DELIVERY guarantee — if
     * not enforced, the excluded user sees the notification, i.e. a leak.
     * `created_by` is only an ACCOUNTABILITY trail; failing to apply it shows
     * nobody any wrong content. On an unmigrated install, notifications being
     * lost entirely is a far heavier consequence than losing the "who sent it"
     * information, so the situation is only logged.
     *
     * @param NotificationMessage $message The already-sanitized message.
     *
     * @return array<string, mixed>
     */
    private function buildRow(NotificationMessage $message): array
    {
        $row = [
            'user_id'      => self::GLOBAL_USER_ID,
            'type'         => $message->type,
            'severity'     => $message->severity,
            'target_type'  => $message->targetType,
            'target_value' => $message->targetValue,
            'title'        => $message->title,
            'body'         => $message->body,
            'url'          => $message->url,
            'channel'      => 'inapp',
            'read_at'      => null,
            'created_at'   => date('Y-m-d H:i:s'),
        ];

        if (SchemaGuard::hasExcludeUsers($this->model->db)) {
            $row['exclude_users'] = NotificationMessage::encodeExcludeUsers($message->excludeUsers);
        }

        if ($message->createdBy !== null) {
            if (SchemaGuard::hasCreatedBy($this->model->db)) {
                $row['created_by'] = $message->createdBy;
            } else {
                log_message('warning', sprintf(
                    'InAppChannel: `%s` bildiriminin `created_by` izi YAZILAMADI — `notifications.created_by` kolonu yok (üreten: %d). Satır yine de yazıldı. `php spark migrate --all` çalıştırın.',
                    $message->type,
                    $message->createdBy
                ));
            }
        }

        return $row;
    }

    /**
     * Sweeps every user's unread-badge cache on a global write.
     *
     * `deleteMatching` is defined on CacheInterface, but some handlers
     * (Memcached/Wincache) implement it as `: never` and throw an exception;
     * that's why the portability guard is try/catch rather than method_exists.
     * On an unsupported handler, a 60s TTL acts as the upper bound instead (no
     * fatal is thrown).
     */
    private function invalidateUnreadCaches(): void
    {
        try {
            cache()->deleteMatching('notif_unread_*');
        } catch (\Throwable $e) {
            log_message('debug', 'InAppChannel deleteMatching cache handler tarafından desteklenmiyor: ' . $e->getMessage());
        }
    }
}

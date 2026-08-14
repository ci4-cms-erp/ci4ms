<?php namespace Modules\Notifications\Config;

use Modules\Notifications\Libraries\Channels\EmailChannel;
use Modules\Notifications\Libraries\Channels\InAppChannel;
use Modules\Notifications\Libraries\Channels\RealtimeChannel;
use Modules\Notifications\Libraries\Channels\WebhookChannel;

/**
 * Notification Center module configuration (identical pattern to MenuConfig).
 * - filters: puts all backend/notifications endpoints behind backendGuard.
 * - moduleInfo: the icon shown in the module list.
 * - menus: navigation entry shown in the left menu (label lang('Notifications.notifications')).
 * - channels: base channel map for the Model B publish pipeline (slug => class). Other
 *   modules can add new channels by defining `public array $notificationChannels` in
 *   their own Config; Notifier scans and merges them at dispatch time.
 */
class NotificationsConfig extends \CodeIgniter\Config\BaseConfig
{
    /** Unread-badge cache lifetime (sec) — keeps polling from hammering the DB. */
    public const UNREAD_CACHE_TTL = 60;

    /** Number of rows returned by the top bar bell / feed endpoint. */
    public const FEED_LIMIT = 10;

    /** Number of rows returned by the full list page. */
    public const LIST_LIMIT = 50;

    /** Client polling interval for the top bar bell (ms). */
    public const POLL_MS = 60000;

    /** Max character length of the `type` field (column VARCHAR(64)). */
    public const TYPE_MAX = 64;

    /** Max length of the `title` field (column VARCHAR(255)). */
    public const TITLE_MAX = 255;

    /** Max length of the `url` field (column VARCHAR(255)). */
    public const URL_MAX = 255;

    /**
     * Max CHARACTER length of the `body` field.
     *
     * The column is TEXT (65,535 BYTES) and the project runs with `strictOn = false`:
     * an overflowing value is SILENTLY truncated. The cap is therefore kept well below
     * the column — 4,000 characters, which in the worst case (4-byte UTF-8 characters)
     * is 16 KB, i.e. a quarter of the column; already a generous limit for a
     * notification body. Both validation
     * ({@see \Modules\Notifications\Controllers\ComposerController::validationRules()})
     * and the DTO clamp ({@see \Modules\Notifications\Libraries\NotificationMessage})
     * use the same constant: if the two diverged, one would silently truncate what the
     * other let through.
     */
    public const BODY_MAX = 4000;

    /**
     * Max number of TARGET directives allowed in a single broadcast (users + groups combined).
     *
     * Each directive means ONE row in Model B: one INSERT and — since a global row
     * affects every user's badge — one `cache()->deleteMatching('notif_unread_*')` sweep
     * per row ({@see \Modules\Notifications\Libraries\Channels\InAppChannel::send()}).
     * PHP's default `max_input_vars` is 1000, so without a cap a SINGLE form submission
     * could trigger ~1000 INSERTs + ~1000 cache sweeps; 200 is both plenty for a real
     * announcement and a fifth of that cost. When exceeded, the list is NOT truncated,
     * the submission is rejected (fail-closed): truncating would mean a portion the
     * admin selected silently not receiving the notification.
     *
     * Group names are also bounded by a whitelist ({@see
     * \Modules\Notifications\Controllers\ComposerController::allowedGroups()}), so the
     * axis that can grow unboundedly is user selection; the cap still applies to the SUM
     * of both, because what determines the cost is the row count, not the row type.
     */
    public const TARGETS_MAX = 200;

    /**
     * Max number of users a single row's `exclude_users` list can carry.
     *
     * `exclude_users` is a TEXT column (65,535 bytes) and the project runs with
     * `strictOn = false`: a CSV exceeding the limit is SILENTLY TRUNCATED. A truncated
     * value breaks the sentinel wrapping, the `NOT LIKE '%,{id},%'` pattern on the read
     * path no longer matches, and excluded users WILL SEE the notification — i.e. the
     * overflow fails OPEN. That's why the cap is kept well below the truncation limit
     * (500 ids ≈ 4 KB, not even a tenth of 64 KB) and, when exceeded, the row is NOT
     * truncated, it's not written at all
     * ({@see \Modules\Notifications\Libraries\Channels\InAppChannel::send()}).
     */
    public const EXCLUDE_USERS_MAX = 500;

    /**
     * Fail-closed value used when `$realtimeConnCapDefault` becomes INVALID (0).
     *
     * It's a constant, meaning `.env` cannot override it: the source of a broken
     * configuration cannot be the fallback itself. Must match the value documented for
     * `$realtimeConnCapDefault`.
     */
    public const CONN_CAP_FALLBACK = 6;

    public $csrfExcept = [];

    public $filters = [
        'backendGuard' => ['before' => [
            'backend/notifications', 'backend/notifications/*'
        ]]
    ];

    public $moduleInfo = [
        'icon' => 'fas fa-bell',
    ];

    public $menus = [
        'Notifications.notifications' => [
            'icon'         => 'fas fa-bell',
            'inNavigation' => true,
            'hasChild'     => false,
            'pageSort'     => 9,
            'parent_pk'    => null,
        ],
    ];

    /**
     * Shield group that alerts generated from the `ci4ms.audit` event are sent to.
     */
    public string $auditTargetGroup = 'superadmin';

    /**
     * Whether in-page realtime delivery (our own Redis-backed PHP SSE endpoint) is enabled.
     *
     * When false, RealtimeChannel returns skipped early, the SSE stream() endpoint
     * returns 204, and the bell only does 60s polling (behavior identical to the old
     * default). `.env`: notificationsconfig.realtimeEnabled.
     */
    public bool $realtimeEnabled = false;

    /**
     * Lifetime cap of a single SSE connection, in seconds.
     *
     * When the time expires, stream() returns cleanly; the browser's EventSource
     * reconnects automatically. `.env`: notificationsconfig.realtimeStreamTtl.
     */
    public int $realtimeStreamTtl = 30;

    /**
     * TTL, in seconds, of the Redis `notif:sig:*` signal keys.
     *
     * bump() refreshes this via EXPIRE on every INCR; dead channel counters expire on
     * their own. `.env`: notificationsconfig.realtimeSignalTtl.
     */
    public int $realtimeSignalTtl = 300;

    /**
     * Default number of concurrent SSE connections a user can open.
     *
     * Every open connection occupies a PHP-FPM worker for the duration of its TTL;
     * without a cap, a single identity could exhaust the `pm.max_children` pool. Applied
     * when none of the user's groups match `$realtimeConnCapByGroup`.
     *
     * Default 6: the bell opens its own EventSource in every TAB (see
     * Views/Cells/bell.php), so the cap is a RESOURCE threshold, not a per-identity
     * session limit — a normal admin with a few tabs shouldn't hit the cap, and 6 is
     * still safe for a typical `pm.max_children`.
     *
     * VALUE SEMANTICS:
     * - Positive = the cap to apply.
     * - NEGATIVE (e.g. `-1`) = UNLIMITED and a GLOBAL escape valve: the connection
     *   registry is never touched and the `$realtimeConnCapByGroup` table is never
     *   evaluated either.
     * - 0 = INVALID, NOT unlimited: {@see CONN_CAP_FALLBACK} is applied (fail-closed) and
     *   a `warning` is logged once. Rationale: since the property is declared `int`, CI4
     *   (`BaseConfig::initEnvValue()`) casts every non-numeric value in `.env` — i.e.
     *   every typo — to 0. That's why 0 CANNOT be the "turn off protection" sentinel; a
     *   negative value can never come out of a typo's cast, so it's the safe sentinel.
     * `.env`: notificationsconfig.realtimeConnCapDefault (write `-1` for unlimited).
     */
    public int $realtimeConnCapDefault = self::CONN_CAP_FALLBACK;

    /**
     * SSE connection cap per Shield group name (group => concurrent connection count).
     *
     * If a user matches multiple groups, the HIGHEST cap applies; if none match,
     * `$realtimeConnCapDefault` applies. Input semantics match `$realtimeConnCapDefault`:
     * NEGATIVE (e.g. `-1`) = UNLIMITED and wins among matches; 0 = INVALID, that row is
     * IGNORED (treated as if the group never matched), it does NOT silently become
     * "unlimited". A non-numeric value is ignored the same way — this filter is for call
     * paths that assign directly to the property; on the `.env` path the value is
     * already cast to 0. This table is never read while `$realtimeConnCapDefault` is
     * NEGATIVE.
     *
     * `.env`: notificationsconfig.realtimeConnCapByGroup.<group>. NOTE — `.env` can only
     * override keys that ALREADY EXIST HERE, it CANNOT add a new group:
     * `BaseConfig::initEnvValue()` iterates over `array_keys($property)` for arrays, so
     * if a group other than `superadmin` needs a cap, its row must first be added to
     * this array by hand.
     *
     * @var array<string, int|string>
     */
    public array $realtimeConnCapByGroup = [
        'superadmin' => 10,
    ];

    /**
     * Notification types that can be muted in the preferences screen (whitelist):
     * type/type-prefix => lang key.
     *
     * A key can be a FULL type ('audit.login') or a type PREFIX ('audit'); the
     * read-path condition `p.type = n.type OR n.type LIKE CONCAT(p.type, '.%')` matches
     * both. This array is also the ONLY source PreferenceController::save() accepts —
     * any `type` outside it is silently discarded.
     *
     * The content is derived from the types ACTUALLY published in the repo: the only
     * producer is the `ci4ms.audit` event (app/Config/Events.php →
     * `notify('audit.' . $action)`). The 'test' type written by the `notifications:test`
     * CLI is deliberately left OUT: it's a diagnostic tool, and muting it would defeat
     * its purpose. A module that publishes a new type must add its slug here plus the
     * corresponding key to Language/{en,tr}.
     *
     * @var array<string, string>
     */
    public array $preferenceTypes = [
        'audit' => 'Notifications.prefTypeAudit',
    ];

    /**
     * Channel keys selectable in the preferences screen (whitelist).
     *
     * For now only '*' (all channels) is offered, and this is DELIBERATE: ONLY
     * InAppChannel writes the persistent row (`notifications.channel` is always
     * 'inapp'), while the realtime channel produces a per-TOPIC Redis signal instead of
     * a row. The signal carries no user/type, so a per-user 'realtime' mute can't be
     * enforced on the read path — if exposed in the UI it would be a dead setting that
     * does nothing. When a new row-writing channel is added, it's enough to add its
     * slug here; the `(p.channel = '*' OR p.channel = n.channel)` condition in
     * applyRelevance() already supports the granularity.
     *
     * @var list<string>
     */
    public array $preferenceChannels = ['*'];

    /**
     * Base channel map (slug => ChannelInterface implementation).
     *
     * @var array<string, class-string>
     */
    public array $channels = [
        'inapp'    => InAppChannel::class,
        'email'    => EmailChannel::class,
        'webhook'  => WebhookChannel::class,
        'realtime' => RealtimeChannel::class,
    ];
}

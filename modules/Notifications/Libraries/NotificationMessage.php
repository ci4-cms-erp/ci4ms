<?php

declare(strict_types=1);

namespace Modules\Notifications\Libraries;

use Modules\Notifications\Config\NotificationsConfig;

/**
 * Immutable carrier for a single notification row definition (DTO).
 *
 * All fields are sanitized in the constructor (CHARACTER-based truncation for
 * type/title/body/url, strip_tags for title/body, URL validation, and
 * normalization of severity and excludeUsers). This way a NotificationMessage
 * instance is always well-formed, and channels don't have to deal with
 * sanitizing raw input. `targetType`/`targetValue` carry the Model B relevance
 * contract ('broadcast' | 'user' | 'group'); `excludeUsers` carries the users
 * to be excluded FROM the target (stored as a sentinel-wrapped CSV, see
 * {@see encodeExcludeUsers()}).
 *
 * The PROVENANCE of the exclusion is carried separately, because the delivery
 * guarantees differ:
 *   - `explicitExcludeUsers`: exclusion EXPLICITLY requested by the caller via
 *     {@see NotificationBuilder::exceptUser()}. This is a GUARANTEE; the channel
 *     row must never be written if it can't be applied (FAIL-CLOSED) —
 *     otherwise the excluded user would see the notification.
 *   - `derivedExcludeUsers`: narrowing DERIVED by
 *     {@see NotificationBuilder::dispatch()} for users targeted both directly
 *     and via a group in the same broadcast. This is not a guarantee, it's an
 *     OPTIMIZATION that prevents double delivery; if it can't be applied the
 *     row is still written (FAIL-OPEN) and the user sees the notification twice.
 * `excludeUsers` is the UNION of both and is the single value that's stored;
 * the provenance split is only used to decide "what happens if it can't be applied".
 */
final class NotificationMessage
{
    /** Valid severity levels; any other value falls back to 'info'. */
    public const SEVERITIES = ['info', 'warning', 'critical'];

    public string $type;
    public string $severity;
    public string $title;
    public ?string $body;
    public ?string $url;
    public string $targetType;
    public ?string $targetValue;

    /** @var string[] */
    public array $channels;

    /** @var list<int> The stored MERGED list: explicit ∪ derived (unique + sorted ascending). */
    public array $excludeUsers;

    /** @var list<int> Exclusions EXPLICITLY requested via exceptUser() — a delivery GUARANTEE. */
    public array $explicitExcludeUsers;

    /** @var list<int> Exclusions DERIVED from overlap narrowing — a double-delivery optimization. */
    public array $derivedExcludeUsers;

    /**
     * ID of the user who PRODUCED the broadcast (not the recipient) — an accountability trail.
     *
     * Only populated for broadcasts produced by a human (PHASE 3 composer);
     * stays null for event/CLI-sourced notifications, and this distinction is
     * intentional: null = "produced by the system". The value's source is
     * ALWAYS the server (`auth()->id()`); an ID read from the client must never
     * enter here. It does NOT affect delivery decisions — none of the relevance,
     * exclusion, or preference filters look at this field.
     *
     * @var int|null
     */
    public ?int $createdBy;

    /**
     * @param string   $type        Machine-readable event type ('comment.new' ...).
     * @param string   $severity    info|warning|critical (invalid → info).
     * @param string   $title       Display title (strip_tags + TITLE_MAX character truncation).
     * @param string|null $body      Display body or null (strip_tags + BODY_MAX character truncation).
     * @param string|null $url       Click target; in-site '/...' or http(s), otherwise null.
     * @param string   $targetType  'broadcast' | 'user' | 'group'.
     * @param string|null $targetValue Target value (user id / group name), null for broadcast.
     * @param string[] $channels     Channel slugs this message will be sent through.
     * @param array<int, mixed> $explicitExcludeUsers Exclusions explicitly requested via exceptUser() (guarantee).
     * @param array<int, mixed> $derivedExcludeUsers  Exclusions derived from overlap narrowing (optimization).
     * @param int|null          $createdBy            ID of the user who produced the broadcast; 0/negative → null (system).
     */
    public function __construct(
        string $type,
        string $severity,
        string $title,
        ?string $body,
        ?string $url,
        string $targetType,
        ?string $targetValue,
        array $channels,
        array $explicitExcludeUsers = [],
        array $derivedExcludeUsers = [],
        ?int $createdBy = null
    ) {
        // CHARACTER-based truncation (mb_substr), not BYTE-based: validation's
        // `max_length` rule also counts characters (mb_strlen). If the two
        // diverge, a 255-character Turkish title would pass validation, get cut
        // at 255 BYTES, and split the last multi-byte character in half; since
        // `strictOn = false`, MySQL accepts this silently and a corrupted row
        // stays in storage.
        $this->type         = mb_substr(trim($type), 0, NotificationsConfig::TYPE_MAX, 'UTF-8');
        $this->severity     = self::normalizeSeverity($severity);
        $this->title        = mb_substr(strip_tags(trim($title)), 0, NotificationsConfig::TITLE_MAX, 'UTF-8');
        $this->body         = ($body !== null && trim($body) !== '')
            ? mb_substr(strip_tags(trim($body)), 0, NotificationsConfig::BODY_MAX, 'UTF-8')
            : null;
        $url                = self::sanitizeUrl($url);
        $this->url          = $url !== null ? mb_substr($url, 0, NotificationsConfig::URL_MAX, 'UTF-8') : null;
        $this->targetType   = $targetType;
        $this->targetValue  = $targetValue;
        $this->channels     = $channels;

        $this->explicitExcludeUsers = self::normalizeExcludeUsers($explicitExcludeUsers);
        $this->derivedExcludeUsers  = self::normalizeExcludeUsers($derivedExcludeUsers);
        $this->excludeUsers         = self::normalizeExcludeUsers(
            array_merge($this->explicitExcludeUsers, $this->derivedExcludeUsers)
        );

        // 0 and negative values don't correspond to any user; the distinction
        // between "produced by the system" (null) and "produced by user 0" is
        // collapsed here so it doesn't persist in storage.
        $this->createdBy = ($createdBy !== null && $createdBy > 0) ? $createdBy : null;
    }

    /**
     * Normalizes the exclusion list: cast to int, drop invalid values, deduplicate, sort.
     *
     * 0 and negative IDs (and inputs that can't be converted to a number) are
     * dropped — they don't correspond to any user and would needlessly grow the
     * stored CSV. Sorting makes the stored value deterministic (the same
     * exclusion set always produces the same text).
     *
     * @param array<int, mixed> $userIds Raw user IDs.
     *
     * @return list<int> Unique, ascending-sorted, positive IDs.
     */
    public static function normalizeExcludeUsers(array $userIds): array
    {
        $ids = array_filter(array_map(static fn ($id): int => (int) $id, $userIds), static fn (int $id): bool => $id > 0);
        $ids = array_values(array_unique($ids));
        sort($ids);

        return $ids;
    }

    /**
     * Converts the exclusion list to a SENTINEL-WRAPPED CSV (storage format).
     *
     * The value is always comma-wrapped at both ends (`,5,12,`), so the read
     * path's `NOT LIKE '%,{userId},%'` check can't confuse `,1,` with `,12,`.
     * Returns NULL if the list is empty (the column default). JSON IS NOT USED:
     * plain text + LIKE is preferred to stay independent of MySQL/MariaDB
     * version differences.
     *
     * @param array<int, mixed> $userIds Raw or already-normalized IDs.
     *
     * @return string|null Text in `,5,12,` form, or null if the list is empty.
     */
    public static function encodeExcludeUsers(array $userIds): ?string
    {
        $ids = self::normalizeExcludeUsers($userIds);

        return $ids === [] ? null : ',' . implode(',', $ids) . ',';
    }

    /**
     * The LIKE pattern used on the read path for a single user (the twin of the storage format).
     *
     * Applies the same sentinel contract as {@see encodeExcludeUsers()}; the two
     * must change together. Since the ID is cast to int, the pattern can't carry
     * a wildcard character.
     *
     * @param int $userId ID of the reading user.
     *
     * @return string LIKE pattern in `%,5,%` form.
     */
    public static function excludeMatchPattern(int $userId): string
    {
        return '%,' . $userId . ',%';
    }

    /**
     * Reduces an invalid/empty severity level to the safe default ('info').
     *
     * @param string $severity Raw severity input.
     *
     * @return string One of SEVERITIES.
     */
    public static function normalizeSeverity(string $severity): string
    {
        $severity = strtolower(trim($severity));

        return in_array($severity, self::SEVERITIES, true) ? $severity : 'info';
    }

    /**
     * Only accepts an in-site ('/...') or absolute http(s) URL; returns null otherwise.
     *
     * Filters out open-redirect vectors such as 'javascript:' and
     * protocol-relative '//host' / '/\host' (the WHATWG parser normalizes '\'
     * to '/'), as well as URLs containing internal control characters
     * (CR/LF/TAB/NUL etc.).
     * (Must also be escaped with esc() on the display side — defense in two layers.)
     *
     * @param string|null $url Raw URL input.
     *
     * @return string|null Safe URL or null.
     */
    public static function sanitizeUrl(?string $url): ?string
    {
        if ($url === null) {
            return null;
        }

        $url = trim($url);
        if ($url === '') {
            return null;
        }

        // Internal control characters (CR/LF/TAB/NUL etc.) are a header/DOM
        // injection surface; trim only cleans the ends, characters in the
        // middle are rejected here.
        if (preg_match('/[\x00-\x1F\x7F]/', $url) === 1) {
            return null;
        }

        // '/backend/...' yes; but '//host' and '/\host' (protocol-relative) no.
        if ($url[0] === '/' && (strlen($url) === 1 || ! in_array($url[1], ['/', '\\'], true))) {
            return $url;
        }

        if (preg_match('#^https?://#i', $url) === 1) {
            return $url;
        }

        return null;
    }
}

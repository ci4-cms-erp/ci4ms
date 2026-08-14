<?php

declare(strict_types=1);

namespace Modules\Notifications\Libraries;

use ci4commonmodel\CommonModel;
use CodeIgniter\Database\BaseBuilder;
use Modules\Notifications\Config\NotificationsConfig;
use Modules\Notifications\Libraries\Channels\ChannelInterface;
use Modules\Notifications\Libraries\Channels\ChannelResult;

/**
 * The central service of the Notification Center (Model B: global rows + per-user read status).
 *
 * No part of the application writes directly to the `notifications` table;
 * every write goes through the `notify()` builder or its backward-compat
 * wrappers (toUser/toRole). On the read path, `applyRelevance()` is the SOLE
 * relevance chokepoint (the IDOR gate): a user can only see broadcast
 * notifications, notifications targeting their own 'user', and notifications
 * targeting a 'group' they're a member of; rows excluded by the row itself
 * (`exclude_users`) and rows the user has muted (`notification_preferences`)
 * are also filtered out here. Read status is kept in a separate
 * `notification_reads` table; `notifications` rows are not user-specific
 * (user_id = null).
 *
 * REALTIME: the SSE endpoint only emits a content-free "nudge" (`{"t":...}`),
 * and the client reconciles from the feed endpoint -> feed listFor() ->
 * applyRelevance(). This means exclusion and preference filters automatically
 * apply to realtime delivery too; there is NO separate realtime filter (an
 * excluded user just performs a wasted reconcile, no content leaks).
 *
 * Usage:
 *   service('notifier')->notify('comment.new')->title('New comment')->toUser(5)->dispatch();
 *   service('notifier')->notify('update.available')->severity('warning')->broadcast()->dispatch();
 */
class Notifier
{
    private CommonModel $model;

    public function __construct()
    {
        $this->model = new CommonModel();
    }

    /**
     * Starts a new broadcast builder (fluent builder).
     *
     * @param string $type Machine-readable event type ('comment.new' ...).
     *
     * @return NotificationBuilder
     */
    public function notify(string $type): NotificationBuilder
    {
        return new NotificationBuilder($this, $type);
    }

    /**
     * Merges the base channel map with a module scan and returns it.
     *
     * The base comes from NotificationsConfig::$channels; each module's
     * `Config\{Name}Config::$notificationChannels` property is scanned (using
     * the Filters $csrfExcept scan pattern) via scandir -> class_exists ->
     * property_exists and merged in. A module that later defines the same slug
     * overrides the base.
     *
     * @return array<string, ChannelInterface> slug => channel instance.
     */
    public function resolveChannels(): array
    {
        /** @var NotificationsConfig $config */
        $config = config(NotificationsConfig::class);

        $map = [];
        foreach ($config->channels as $slug => $class) {
            if (class_exists($class)) {
                $map[$slug] = new $class();
            }
        }

        $modulesPath = ROOTPATH . 'modules/';
        $modules     = array_filter(
            scandir($modulesPath) ?: [],
            static fn ($module) => ! in_array($module, ['.', '..', '.DS_Store'], true)
                && is_dir($modulesPath . $module)
        );

        foreach ($modules as $module) {
            $configClass = "Modules\\{$module}\\Config\\{$module}Config";
            if (! class_exists($configClass)) {
                continue;
            }

            $instance = new $configClass();
            if (! property_exists($instance, 'notificationChannels') || ! is_array($instance->notificationChannels)) {
                continue;
            }

            foreach ($instance->notificationChannels as $slug => $class) {
                if (is_string($class) && class_exists($class)) {
                    $map[$slug] = new $class();
                }
            }
        }

        return $map;
    }

    /**
     * Number of unread (relevant + unread) notifications for the user.
     *
     * Cached for NotificationsConfig::UNREAD_CACHE_TTL seconds under the key
     * `notif_unread_{userId}` — so polling doesn't wear out the DB. The badge
     * is 0 if the tables aren't ready.
     *
     * @param int $userId ID of the user in session.
     *
     * @return int Number of unread notifications.
     */
    public function unreadCount(int $userId): int
    {
        if (! $this->tablesReady()) {
            return 0;
        }

        $key   = self::cacheKey($userId);
        $count = cache($key);

        if ($count === null) {
            $groups  = $this->groupsFor($userId);
            $builder = $this->model->db->table('notifications n');
            $builder->join('notification_reads r', 'r.notification_id = n.id AND r.user_id = ' . $this->model->db->escape($userId), 'left');
            $builder->where('r.id', null);
            $this->applyRelevance($builder, $userId, $groups);

            $count = $builder->countAllResults();
            cache()->save($key, $count, NotificationsConfig::UNREAD_CACHE_TTL);
        }

        return (int) $count;
    }

    /**
     * The relevant notification feed for a user (newest first).
     *
     * A NULL `read_at` on a row means unread (the view contract).
     *
     * @param int $userId ID of the user in session.
     * @param int $limit  Maximum number of rows to return.
     *
     * @return list<\stdClass> Rows containing id,type,severity,title,body,url,created_at,read_at.
     */
    public function listFor(int $userId, int $limit): array
    {
        if (! $this->tablesReady()) {
            return [];
        }

        $groups  = $this->groupsFor($userId);
        $builder = $this->model->db->table('notifications n');
        $builder->select('n.id, n.type, n.severity, n.title, n.body, n.url, n.created_at, r.read_at');
        $builder->join('notification_reads r', 'r.notification_id = n.id AND r.user_id = ' . $this->model->db->escape($userId), 'left');
        $this->applyRelevance($builder, $userId, $groups);
        $builder->orderBy('n.created_at', 'DESC');
        $builder->limit($limit);

        return $builder->get()->getResult();
    }

    /**
     * Marks a single notification as read for the user (IDOR-safe + idempotent).
     *
     * Returns false if the record doesn't exist or isn't relevant to the user
     * (controller -> 404). Doesn't rewrite if already read. On success, the
     * unread cache is invalidated.
     *
     * @param int $id     Notification ID.
     * @param int $userId ID of the user in session.
     *
     * @return bool True if it could be marked (or was already marked); false if missing/irrelevant.
     */
    public function markRead(int $id, int $userId): bool
    {
        if (! $this->tablesReady()) {
            return false;
        }

        if ($this->model->selectOne('notifications', ['id' => $id]) === null) {
            return false;
        }

        if (! $this->isRelevant($id, $userId)) {
            return false;
        }

        $already = $this->model->selectOne('notification_reads', ['notification_id' => $id, 'user_id' => $userId]);
        if ($already === null) {
            $this->model->create('notification_reads', [
                'notification_id' => $id,
                'user_id'         => $userId,
                'read_at'         => date('Y-m-d H:i:s'),
            ]);
        }

        cache()->delete(self::cacheKey($userId));

        return true;
    }

    /**
     * Marks all of the user's relevant + unread notifications as read in a single batch.
     *
     * `INSERT IGNORE` skips UNIQUE(notification_id,user_id) collisions; idempotent.
     *
     * @param int $userId ID of the user in session.
     *
     * @return int Number of rows inserted as read.
     */
    public function markAllRead(int $userId): int
    {
        if (! $this->tablesReady()) {
            return 0;
        }

        $groups  = $this->groupsFor($userId);
        $builder = $this->model->db->table('notifications n');
        $builder->select('n.id');
        $builder->join('notification_reads r', 'r.notification_id = n.id AND r.user_id = ' . $this->model->db->escape($userId), 'left');
        $builder->where('r.id', null);
        $this->applyRelevance($builder, $userId, $groups);
        $rows = $builder->get()->getResult();

        if ($rows === []) {
            cache()->delete(self::cacheKey($userId));

            return 0;
        }

        $now  = date('Y-m-d H:i:s');
        $data = array_map(static fn ($row) => [
            'notification_id' => (int) $row->id,
            'user_id'         => $userId,
            'read_at'         => $now,
        ], $rows);

        $this->model->db->table('notification_reads')->ignore(true)->insertBatch($data);

        cache()->delete(self::cacheKey($userId));

        return count($data);
    }

    /**
     * Sends a notification to a single user (backward-compat wrapper; delegates to the builder).
     *
     * Model B semantics: instead of a row with user_id, writes a single global
     * row with target_type='user'.
     *
     * MEANING OF THE RETURN VALUE (a knowingly-recorded technical debt): the
     * return means ANY channel returned ok ({@see anyOk()}), it does NOT mean
     * "a row was written". While the realtime channel is on, even if the
     * durable write was rejected (exclusion couldn't be applied, table
     * missing, insert failed), this method returns true. The correct measure
     * is {@see DispatchOutcome}; the composer path
     * ({@see \Modules\Notifications\Controllers\ComposerController::send()})
     * uses it. This wrapper's behavior (and toRole()'s) is left AS-IS for
     * backward compatibility; callers must not treat the return value as a
     * delivery guarantee.
     *
     * @param int         $userId Recipient user ID.
     * @param string      $type   Event type.
     * @param string      $title  Title.
     * @param string|null $body   Body or null.
     * @param string|null $url    Click target or null.
     *
     * @return bool True if at least one channel returned ok (NOT A GUARANTEE the row was written).
     */
    public function toUser(int $userId, string $type, string $title, ?string $body = null, ?string $url = null): bool
    {
        $results = $this->notify($type)
            ->severity('info')->title($title)->body($body)->url($url)
            ->toUser($userId)
            ->dispatch();

        return $this->anyOk($results);
    }

    /**
     * Sends a notification to a Shield group (backward-compat wrapper; delegates to the builder).
     *
     * Model B semantics: there is NO MORE fan-out — doesn't open a row per
     * member, writes a single global 'group' row, and the return is 1/0 (not
     * a member count, whether the delivery attempt was ok). Group membership
     * is resolved at read time via the relevance query.
     *
     * MEANING OF THE RETURN VALUE: the same technical debt as {@see toUser()}
     * applies — 1 means "any channel returned ok", it does NOT mean "a row
     * was written".
     *
     * @param string      $groupName Group name.
     * @param string      $type      Event type.
     * @param string      $title     Title.
     * @param string|null $body      Body or null.
     * @param string|null $url       Click target or null.
     *
     * @return int 1 if at least one channel returned ok, 0 otherwise (NOT a row guarantee).
     */
    public function toRole(string $groupName, string $type, string $title, ?string $body = null, ?string $url = null): int
    {
        $results = $this->notify($type)
            ->severity('info')->title($title)->body($body)->url($url)
            ->toGroup($groupName)
            ->dispatch();

        return $this->anyOk($results) ? 1 : 0;
    }

    /**
     * The unread-badge cache key.
     *
     * @param int $userId User ID.
     *
     * @return string Cache key ('notif_unread_{userId}').
     */
    public static function cacheKey(int $userId): string
    {
        return 'notif_unread_' . $userId;
    }

    /**
     * The AUTHORIZED channel (Redis-backed SSE) list for the user — the IDOR gate.
     *
     * Must be FULLY consistent with `applyRelevance()`: a user may only listen
     * on 'broadcast', their own 'user/{id}', and the 'group/{name}' channels
     * they're a member of. RealtimeController::stream() derives the channels
     * to listen on ONLY from this server-side topicsFor() read; the client
     * never sends a channel (the IDOR protection lives here).
     *
     * @param int $userId ID of the user in session.
     *
     * @return list<string> Authorized topic names.
     */
    public function topicsFor(int $userId): array
    {
        $topics = [
            self::topicFor('broadcast', null),
            self::topicFor('user', (string) $userId),
        ];

        foreach ($this->groupsFor($userId) as $group) {
            $topics[] = self::topicFor('group', $group);
        }

        return $topics;
    }

    /**
     * Derives the Redis-backed SSE channel name from a target (target_type/target_value) (single source).
     *
     * Lives here so both publishing (bump, RealtimeChannel) and authorization
     * (listening, topicsFor) use the same scheme: 'user' -> user/{id},
     * 'group' -> group/{name}, everything else (including broadcast) -> 'broadcast'.
     *
     * @param string      $targetType  'broadcast' | 'user' | 'group'.
     * @param string|null $targetValue Target value (user id / group name) or null.
     *
     * @return string Channel name.
     */
    public static function topicFor(string $targetType, ?string $targetValue): string
    {
        return match ($targetType) {
            'user'  => 'user/' . (string) $targetValue,
            'group' => 'group/' . (string) $targetValue,
            default => 'broadcast',
        };
    }

    /**
     * Applies the relevance filter to the query builder (the SOLE relevance chokepoint — the IDOR gate).
     *
     * Three layers are applied in sequence, and all FOUR read paths
     * (unreadCount, listFor, markAllRead, isRelevant) go through this single
     * method:
     *   1. TARGET: broadcast + own 'user' target + membered 'group' target.
     *   2. EXCLUSION: a user in the row's `exclude_users` list is filtered out.
     *   3. PREFERENCES: a type/channel the user has muted is filtered out (critical excepted).
     * That's why markRead() also returns 404 for an excluded/muted row (isRelevant).
     *
     * @param BaseBuilder $builder Builder to add WHERE clauses to.
     * @param int         $userId  User ID.
     * @param string[]    $groups  Groups the user is a member of.
     */
    private function applyRelevance(BaseBuilder $builder, int $userId, array $groups): void
    {
        $builder->groupStart()
            ->where('n.target_type', 'broadcast')
            ->orGroupStart()
                ->where('n.target_type', 'user')->where('n.target_value', (string) $userId)
            ->groupEnd();

        if (! empty($groups)) {
            $builder->orGroupStart()
                ->where('n.target_type', 'group')->whereIn('n.target_value', $groups)
            ->groupEnd();
        }

        $builder->groupEnd();

        $this->applyExclusion($builder, $userId);
        $this->applyPreferences($builder, $userId);
    }

    /**
     * Adds the row-level exclusion filter (`exclude_users` sentinel CSV).
     *
     * The stored form is always comma-wrapped (`,5,12,`), so the `,1,` pattern
     * never WRONGLY matches `,12,` ({@see NotificationMessage::encodeExcludeUsers()}).
     * The ID arrives already cast to int in the method, and the pattern is also
     * escaped via `escape()` (two layers). If the column hasn't been migrated
     * yet, the filter isn't added at all.
     *
     * @param BaseBuilder $builder Builder to add a WHERE clause to.
     * @param int         $userId  User ID.
     */
    private function applyExclusion(BaseBuilder $builder, int $userId): void
    {
        if (! SchemaGuard::hasExcludeUsers($this->model->db)) {
            return;
        }

        $pattern = $this->model->db->escape(NotificationMessage::excludeMatchPattern($userId));

        $builder->where("(n.exclude_users IS NULL OR n.exclude_users NOT LIKE {$pattern})", null, false);
    }

    /**
     * Applies user preferences (opt-out) at read time (anti-join).
     *
     * Because Model B rows are global, muting can't be applied at SEND time;
     * the filter is built here via a LEFT JOIN to the `notification_preferences`
     * table + `p.id IS NULL`. Thanks to
     * `p.type = n.type OR n.type LIKE CONCAT(p.type, '.%')`, a preference row
     * can be written as an exact type or a type PREFIX ('audit' -> 'audit.*').
     *
     * CRITICAL: the `n.severity <> 'critical'` condition is deliberately on the
     * JOIN's ON side, NOT in WHERE. This way critical rows are never joined at
     * all, so (a) they can never be muted, and (b) even if multiple preference
     * rows match, listFor() doesn't DUPLICATE the row and unreadCount()'s
     * countAllResults() isn't INFLATED (every surviving row has zero matches,
     * i.e. the output row is single). For the same reason this JOIN doesn't
     * break countAllResults(): no GROUP BY/DISTINCT is added to the query.
     *
     * Performance: the JOIN narrows on the indexed `user_id` — BOTH the
     * `notif_pref_unique` and `notif_pref_lookup` indexes start with `user_id`,
     * so which one gets used is the optimizer's call — and the per-user
     * preference set is small; no extra query, no N+1.
     *
     * @param BaseBuilder $builder Builder to add a JOIN/WHERE to.
     * @param int         $userId  User ID.
     */
    private function applyPreferences(BaseBuilder $builder, int $userId): void
    {
        if (! SchemaGuard::hasPreferences($this->model->db)) {
            return;
        }

        $table = $this->model->db->prefixTable('notification_preferences');
        $owner = $this->model->db->escape($userId);

        $builder->join(
            "`{$table}` p",
            "p.user_id = {$owner}"
                . ' AND p.enabled = 0'
                . " AND (p.channel = '*' OR p.channel = n.channel)"
                . " AND (p.type = n.type OR n.type LIKE CONCAT(p.type, '.%'))"
                . " AND n.severity <> 'critical'",
            'left',
            false
        );

        $builder->where('p.id', null);
    }

    /**
     * Verifies whether a single notification is relevant to the user (ID-scoped relevance).
     *
     * @param int $id     Notification ID.
     * @param int $userId User ID.
     *
     * @return bool True if relevant.
     */
    private function isRelevant(int $id, int $userId): bool
    {
        $groups  = $this->groupsFor($userId);
        $builder = $this->model->db->table('notifications n');
        $builder->select('n.id');
        $builder->where('n.id', $id);
        $this->applyRelevance($builder, $userId, $groups);

        return $builder->get()->getRow() !== null;
    }

    /**
     * Returns the names of the Shield groups the user is a member of (the SOLE group-query source).
     *
     * Used by both in-module relevance/topic derivation (applyRelevance,
     * topicsFor) and out-of-module role-based policies (RealtimeController's
     * SSE connection cap) via this single read; there is NO second group-query
     * implementation.
     *
     * WARNING (authorization): this method does NOT PERFORM AN AUTHORIZATION
     * CHECK — it returns the Shield group membership of whatever userId is
     * given, as-is. The CALLER MUST GUARANTEE that `$userId` belongs to the
     * session owner (`auth()->id()`); calling it with an ID from the client
     * opens up an IDOR for role discovery.
     *
     * @internal For in-module use + RealtimeController cap policy.
     *
     * @param int $userId User ID (verified by the caller to be the session owner).
     *
     * @return string[] Group names.
     */
    public function groupsFor(int $userId): array
    {
        $rows = $this->model->lists('auth_groups_users', 'group', ['user_id' => $userId]) ?: [];

        return array_column(array_map(static fn ($row) => (array) $row, $rows), 'group');
    }

    /**
     * Resolves, in a SINGLE query, which of the given groups each of the given users belongs to.
     *
     * Only used for overlap cleanup at SEND time (see
     * {@see NotificationBuilder::coveredUserTargets()}): when the same
     * broadcast has both `toUser(5)` and `toGroup(...)` for a group 5 is a
     * member of, the user is written into the group row's `exclude_users` list
     * to prevent the user from seeing two rows. NOT fan-out: it's only an
     * intersection read scoped to EXPLICITLY targeted IDs, the full group
     * membership is never materialized.
     *
     * The `auth_groups_users` query — together with {@see groupsFor()} —
     * stays in this class; there is NO second group-query implementation
     * outside the module. Returns an empty map if the Shield tables haven't
     * been migrated yet (doesn't throw).
     *
     * @internal Only for overlap cleanup at send time (NotificationBuilder).
     *
     * @param list<int>    $userIds Explicitly targeted user IDs.
     * @param list<string> $groups  Group names targeted in the same broadcast.
     *
     * @return array<string, list<int>> Group name => targeted IDs that are members of that group.
     */
    public function groupMembersAmong(array $userIds, array $groups): array
    {
        if ($userIds === [] || $groups === [] || ! $this->model->db->tableExists('auth_groups_users')) {
            return [];
        }

        $rows = $this->model->db->table('auth_groups_users')
            ->select('user_id, group')
            ->whereIn('user_id', $userIds)
            ->whereIn('group', $groups)
            ->get()->getResultArray();

        $map = [];
        foreach ($rows as $row) {
            $map[(string) $row['group']][] = (int) $row['user_id'];
        }

        return $map;
    }

    /**
     * Estimates, BEFORE sending, how many PEOPLE a broadcast will reach (composer preview).
     *
     * Because Model B rows are global, the recipient count is never written to
     * disk; this method DERIVES it from the broadcast definition
     * (broadcast / users / groups / exclusions) and writes nothing. The
     * `auth_groups_users` query — together with {@see groupsFor()} and
     * {@see groupMembersAmong()} — stays in this class; the module has NO
     * second owner of the group query.
     *
     * QUERY BUDGET (no N+1, independent of input size):
     *   - broadcast: 1 query (total) + 1 query if there's an exclusion (count existing ones).
     *   - targeted:  if groups are given, a SINGLE
     *     `SELECT DISTINCT user_id ... WHERE group IN (...)`; explicit IDs and
     *     exclusions are merged with a set operation on the PHP side.
     *
     * SCOPE: only ADDRESSABLE accounts are counted ({@see scopeAddressableUsers()})
     * — soft-deleted and banned accounts can never see a notification.
     * Exclusions are subtracted from the broadcast using IDs that ACTUALLY
     * exist; subtracting a nonexistent ID would understate the count.
     *
     * APPROXIMATION (an honesty note): in targeted mode, group members are
     * counted from `auth_groups_users`, with no JOIN to `users`. If a deleted
     * or banned user still has a leftover membership row, the count may come
     * out one person too high. This is a deviation not worth the cost of a
     * second JOIN: the value is a PREVIEW, not a delivery guarantee.
     *
     * Returns 0 if the Shield tables haven't been migrated yet (doesn't throw).
     *
     * @param bool                 $broadcast    Whether the broadcast goes to all users (overrides other targets).
     * @param array<int, mixed>    $userIds      Explicitly targeted user IDs.
     * @param array<int, mixed>    $groups       Targeted Shield group names.
     * @param array<int, mixed>    $excludeUsers User IDs to leave out of the target.
     *
     * @return int Deduplicated estimated recipient count (never negative).
     */
    public function recipientCount(bool $broadcast, array $userIds, array $groups, array $excludeUsers): int
    {
        if (! $this->model->db->tableExists('users')) {
            return 0;
        }

        $excluded = self::normalizeIds($excludeUsers);

        if ($broadcast) {
            return $this->broadcastRecipientCount($excluded);
        }

        $recipients = array_merge(
            self::normalizeIds($userIds),
            $this->groupMemberIds(self::normalizeNames($groups))
        );

        return count(array_diff(array_unique($recipients), $excluded));
    }

    /**
     * Scopes a `users` query to ADDRESSABLE accounts (the SOLE filter source).
     *
     * Addressable = an account that CAN see the notification. Two exclusions apply:
     *   1. `deleted_at IS NULL` — never returns a Shield soft-deleted row on any read path.
     *   2. `status <> 'banned'` — Shield rejects login while banned
     *      ({@see \CodeIgniter\Shield\Authentication\Authenticators\Session::login()}),
     *      so a banned account can never read a notification. Because the column
     *      is nullable, `IS NULL OR <> 'banned'` is written; a plain
     *      `<> 'banned'` would also filter out NULL rows, i.e. ALL regular users.
     *
     * The `active` column is DELIBERATELY not filtered: in this setup the
     * activation flow is disabled
     * (`Modules\Auth\Config\Auth::$actions['register'] === null`) and Shield
     * doesn't check `active` when logging in, so an account with `active = 0`
     * can perfectly well log in and read notifications. Filtering it out would
     * silently drop real recipients.
     *
     * Both the composer's selection/validation queries and this class's counts
     * go through here; the rule isn't duplicated in three places.
     *
     * @param BaseBuilder $builder The `users` builder to add a WHERE clause to.
     *
     * @return BaseBuilder The same builder, chainable.
     */
    public static function scopeAddressableUsers(BaseBuilder $builder): BaseBuilder
    {
        return $builder->where('deleted_at', null)
            ->groupStart()
                ->where('status', null)
                ->orWhere('status !=', 'banned')
            ->groupEnd();
    }

    /**
     * Broadcast recipient count: addressable account count minus existing exclusions.
     *
     * @param list<int> $excluded Normalized exclusion IDs.
     *
     * @return int Recipient count (never negative).
     */
    private function broadcastRecipientCount(array $excluded): int
    {
        $total = (int) self::scopeAddressableUsers($this->model->db->table('users'))->countAllResults();

        if ($excluded === []) {
            return $total;
        }

        $existing = (int) self::scopeAddressableUsers($this->model->db->table('users'))
            ->whereIn('id', $excluded)
            ->countAllResults();

        return max(0, $total - $existing);
    }

    /**
     * Returns member IDs of the given groups, deduplicated, in a SINGLE query.
     *
     * @param list<string> $groups Normalized group names.
     *
     * @return list<int> Member user IDs (unique).
     */
    private function groupMemberIds(array $groups): array
    {
        if ($groups === [] || ! $this->model->db->tableExists('auth_groups_users')) {
            return [];
        }

        $rows = $this->model->db->table('auth_groups_users')
            ->select('user_id')
            ->distinct()
            ->whereIn('group', $groups)
            ->get()->getResultArray();

        return array_values(array_map(static fn (array $row): int => (int) $row['user_id'], $rows));
    }

    /**
     * Prepares a raw ID list for counting: cast to int, drop 0/negatives, deduplicate.
     *
     * Applies the same DROP rule as
     * {@see NotificationMessage::normalizeExcludeUsers()} but doesn't carry its
     * STORAGE contract (sorting); only set semantics are needed here, no
     * stored value is produced.
     *
     * @param array<int, mixed> $ids Raw user IDs.
     *
     * @return list<int> Unique, positive IDs.
     */
    private static function normalizeIds(array $ids): array
    {
        $normalized = [];

        foreach ($ids as $id) {
            // A non-scalar element is dropped: an `(int)` cast on a nested array
            // silently becomes 1 and would count an untargeted user.
            if (! is_scalar($id)) {
                continue;
            }

            $value = (int) $id;

            if ($value > 0) {
                $normalized[] = $value;
            }
        }

        return array_values(array_unique($normalized));
    }

    /**
     * Cleans a raw group name list: cast to string, drop empty ones, deduplicate.
     *
     * @param array<int, mixed> $names Raw group names.
     *
     * @return list<string> Unique, non-empty group names.
     */
    private static function normalizeNames(array $names): array
    {
        $normalized = [];

        foreach ($names as $name) {
            if (! is_scalar($name)) {
                continue;
            }

            $value = trim((string) $name);

            if ($value !== '') {
                $normalized[] = $value;
            }
        }

        return array_values(array_unique($normalized));
    }

    /**
     * Whether the Model B tables (notifications + notification_reads) are ready.
     *
     * @return bool True if both exist.
     */
    private function tablesReady(): bool
    {
        return $this->model->db->tableExists('notifications')
            && $this->model->db->tableExists('notification_reads');
    }

    /**
     * Whether there is at least one successful delivery among the channel results.
     *
     * @param ChannelResult[] $results Delivery results.
     *
     * @return bool True if any is ok.
     */
    private function anyOk(array $results): bool
    {
        return array_filter($results, static fn (ChannelResult $result) => $result->ok) !== [];
    }
}

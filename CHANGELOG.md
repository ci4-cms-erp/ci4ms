# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html) conventions adapted to the existing four-component version numbers.

## [Unreleased]

### Fixed

- **`json_decode()` Flag Constant in the `$associative` Argument Slot (2 Sites):** `modules/Users/Controllers/PermgroupController.php:126` and `modules/Blog/Controllers/Blog.php:376` both passed `JSON_UNESCAPED_UNICODE` as `json_decode()`'s **second** argument. The two JSON functions are not symmetric — `json_encode()` takes `$flags` at position 2, while `json_decode()` takes `$associative` at position 2 and `$flags` at position 4 — so the constant (`int(256)`) landed in the `?bool $associative` slot and was silently coerced to `true`. The result happened to be the desired associative array, which is why both sites worked and never raised a notice; the correctness of the RBAC permission matrix and the blog badword list was resting on an argument-position mistake rather than on anything written intentionally. Both now pass `true` explicitly and drop the constant entirely (`JSON_UNESCAPED_UNICODE` is an *encoding* flag and has no meaning to `json_decode()` in any position). Note that simply deleting the constant would have been a regression: `$associative` would default to `null`, `json_decode()` would return `stdClass`, and both consuming views — which use array access — would fatal with `Cannot use object of type stdClass as array`. The same edit adds a `?? ''` guard on the input, because `auth_groups.permissions` is `LONGTEXT NULL DEFAULT NULL` and a NULL value raises a PHP 8.1+ deprecation on argument 1. Neither site was security-relevant: the application is the sole writer of both values, the decoded result is display-only (checkbox state / list rendering), and RBAC enforcement runs through `auth()->user()->can()` in `Ci4MsAuthFilter` against the group config built in `AuthGroups.php:146`, which already decoded correctly. Reported by **[0xAlchemist](https://github.com/bugmithlegend)**. This is the last of this pattern in the codebase; the security-relevant instances of the same shape were fixed in 0.34.1.0.

- **Sitemap Joined Language Rows on the Wrong Column, Dropping and Mis-Attributing URLs:** `App\Models\PagesModel::sitemapItems()` and `App\Models\BlogModel::sitemapItems()` joined `pages_langs` / `blog_langs` with `ON pages_langs.id = pages.id` — matching the *language row's own primary key* against the content id instead of using the `pages_id` / `blog_id` foreign key. The effect on live data was that the sitemap silently published a subset of URLs and attached some of them to the wrong record: for pages, 2 of 4 localized URLs never appeared at all and `pages.id=2` was advertised under page 1's slug; for blog, 3 of 6 appeared and 2 of those 3 pointed at the wrong post. Both joins now use the real foreign key, and rows whose `seflink` is empty or NULL are skipped rather than emitting a bare `/` or `/blog/` entry. Verified by running both the old and the new join against the live database and comparing the emitted URL sets.

### Changed

- **`declare(strict_types=1)` Extended to 48 Logic Files:** Coverage went from 28 to 76 first-party files. The declaration was added only to `Libraries/`, `Models/`, `Commands/` and `Filters/` — the layers that hold logic rather than presentation — and deliberately **not** to `Views/`, `Language/` or `app/Config/`. The exclusion is not stylistic: `strict_types` governs every function call *made from* the declaring file, and views are precisely where MySQL/MariaDB's all-columns-are-strings behaviour meets typed builtins, so a blanket rollout would convert working pages into `TypeError`s. Language files (`return [...]`) and config property bags gain nothing. Each batch was applied and verified against the test suite separately, and every target's call sites were checked for coercion hazards first — the `$builder->limit()` calls in `Ci4ms`, `AjaxModel` and `UserscrudModel` were confirmed safe because those methods already declare `int $limit` / `int $skip`. The two code-generator templates under `modules/Backend/Commands/Views/` were left untouched so the declaration does not leak into generated modules.

  A follow-up review caught what the green test suite did not: strict mode turned eight previously-silent coercions into fatals on code paths that have no test coverage. Each is now guarded at the point where an untyped value enters a typed call. The consequential ones were `CommonTagsLibrary::checkTags()`, where a non-string tag `value` (an unvalidated `keywords` POST field) crashed *after* the existing tag relations had already been deleted, losing them; `CommonBackendLibrary::buildSeoData()`, whose `?string` return type was violated when `json_encode()` returned `false` on invalid UTF-8, leaving an orphaned row because `create()` runs first; and `Ci4msSetup`, where `CLI::write($text, 'white', false)` passed `false` into the `?string $background` parameter and killed the interactive `php spark ci4ms:setup` path documented as first-time setup — now `CLI::print()`, which is the correct no-newline call. `Ci4msTrait` resolves `--namespace` with an `is_string()` check rather than a string cast, so a valueless flag falls back to `APP_NAMESPACE` instead of silently generating under namespace `1`. `LogViewer`, `BackendMaintenance` and `Notifier` received `false`/type guards on the same principle. Every guard was verified by re-running the exact input that reproduced the crash.

## [0.34.1.0] - 2026-07-26

### Added

- **Backend Cache Management Panel:** The Settings page gained a "Cache Management" section that lets an administrator (with the settings **update** permission) selectively purge cacheable keys. A select2-powered multi-select drives a "Clear Selected" action, while a separate "Clear All" button loops over every clearable key; both actions are SweetAlert-confirmed, run over AJAX (CSRF token in the request body), and report back with a success toast. A new server-side allowlist — `Modules\Settings\Libraries\CacheRegistry` — is the single source of truth for what may be cleared: the client only sends logical ids and the glob patterns are resolved on the server, so glob injection is not possible. Purges are targeted — `cache()->delete()` for exact keys and `cache()->deleteMatching()` for key families (`*_permissions`, `menus_*`, `notif_unread_*`) — and the framework-wide `php spark cache:clear` / `cache()->clean()` is never invoked. The Shield dynamic RBAC config key (`shield_auth_dynamic_config`) is a protected key that can never be cleared through either action. Adds the `backend/settings/clearCache` route (`role=update`) and new English/Turkish language keys; select2 is enqueued only on this view rather than globally.

- **Notifications Module (in-app admin notifications, Model B):** A new `Modules\Notifications` module delivers server-side, in-app notifications for administrators, surfaced through a bell dropdown in the backend header. It follows a **Model B / single-global-row** design: every notification is stored **once** in the `notifications` table as a global row (`user_id = null`) and targeted with `target_type` (`broadcast` | `user` | `group`) + `target_value`, while per-user read state lives in a separate `notification_reads` table (`UNIQUE(notification_id, user_id)`). `Notifier::applyRelevance()` is the single IDOR chokepoint: a user only ever sees broadcast notifications, notifications addressed to their own user id, and notifications for a group they belong to — group membership is resolved at read time, so `toGroup()` / `toRole()` persist exactly one group row and never fan out per recipient (they return `1`/`0`). The unread badge is an anti-join (`notifications LEFT JOIN notification_reads r ON r.notification_id = n.id AND r.user_id = X WHERE r.id IS NULL`) constrained by the same relevance filter and cached for 60 s under `notif_unread_{userId}`. `markRead` is IDOR-safe and idempotent (there is no mark-unread); `markAllRead` batches via `INSERT IGNORE`. Delivery channels are declared in `NotificationsConfig::$channels` (`inapp`, `email`, `webhook`); only `inapp` is active — `email` / `webhook` are no-op stubs. All content sanitisation is centralised in the `NotificationMessage` DTO (title `strip_tags`, `type` clamped to 64 and `title` / `url` clamped to 255 chars, and a `sanitizeUrl` that rejects `javascript:` / `data:` / protocol-relative / CRLF URLs). Each notification carries a `severity` (`info` | `warning` | `critical`) that drives the icon/colour in the bell view. Notifications are **never** produced from a route; the only trigger is the server-side `ci4ms.audit` listener in `app/Config/Events.php` (warning-severity audit events → the `NotificationsConfig::$auditTargetGroup` group, default `superadmin`). Retention is indefinite — cleanup is manual via the `php spark notifications:purge` CLI command (no cron is installed; operators schedule it themselves). Notification text is frozen in the application locale at send time (no per-user re-translation), and there is no per-user opt-out in this MVP. Ships three migrations (batch 7-8): `CreateNotificationsTable`, `AddModelBColumnsToNotifications`, `CreateNotificationReadsTable`. **Caveat:** `notifications:purge` deletes any notification that has been read at least once, so a broadcast/group notification read by a single recipient becomes eligible for deletion — a deliberate MVP simplification (tracked as a code TODO). **Correction (superseded):** the "no per-user opt-out" and "three migrations" statements above describe the initial MVP only; an opt-out screen and two further migrations have since landed — see *Notifications — Rich Targeting & Per-User Preferences (Phase 2)* below.

- **Realtime In-App Notifications via Redis-backed SSE — Phase A:** The Notifications module gained an optional realtime delivery path over Server-Sent Events, so the backend bell badge updates the instant a notification is produced instead of waiting for the 60 s poll. The transport is **self-contained** — no external hub, no JWT, no message broker — reusing the existing Redis + nginx + PHP-FPM stack. It is **disabled by default** (`NotificationsConfig::$realtimeEnabled = false`); with it off, behaviour is byte-for-byte the previous polling model. A new `RealtimeChannel` (a `ChannelInterface`) runs on every notification **only after** `InAppChannel` commits the durable `notifications` row (the single source of truth), so a Redis outage yields a `skipped` `ChannelResult` and never throws. `NotificationBuilder`'s default channel set is now `['inapp', 'realtime']`, so producers get realtime for free. On dispatch `RealtimeChannel` bumps a per-channel Redis counter through the new `RealtimeSignal` store (`INCR notif:sig:{channel}` + `EXPIRE` to `realtimeSignalTtl`, default 300 s); `{channel}` is **always** `Notifier::topicFor()` output (`broadcast`, `user/{id}`, `group/{name}`), derived server-side, so the signal store is IDOR-safe by construction. The browser subscribes same-origin with the native `EventSource` API to a new endpoint, `GET backend/notifications/stream` (`RealtimeController::stream`), guarded by `backendGuard` + `role = read` (permission `notifications.realtimecontroller.read` — the same permission the module already scans; no new permission is introduced). `stream()` reads the session user + groups, then closes the session write lock **immediately** (critical under the `FileHandler` driver, which would otherwise block every other backend request from that user) and the default DB connection, and polls the authorized channels' signals from Redis via a single `MGET` about once a second (never the DB), emitting an `event: notification` nudge on change and a `: ping` heartbeat every ~15 s. Each SSE connection is **lifetime-capped** by `realtimeStreamTtl` (default 30 s, clamped to a 120 s ceiling); on expiry it returns cleanly and `EventSource` auto-reconnects, and `connection_aborted()` exits early. The channels are derived strictly from the session (`topicsFor()` mirrors `applyRelevance()`), so there is no JWT and the client cannot request a channel it is not entitled to. The bell client (`bell.php`) **never trusts the SSE payload** — a message only triggers a reconcile against the `feed` endpoint (the DB stays the source of truth) — and degrades to the existing 60 s polling with a jittered backoff if the stream drops (or on the `204` returned when realtime is disabled), staying in sync across tabs over a `BroadcastChannel`. Adds three `notificationsconfig.*` env overrides (`realtimeEnabled`, `realtimeStreamTtl`, `realtimeSignalTtl`); the Redis connection is reused from `Config\Cache::$redis` (no separate setting), and `ext-redis` (phpredis) is declared under `composer.json`'s `suggest`. **No external hub, JWT, or nginx config change is required** — SSE buffering is disabled per-response via the `X-Accel-Buffering: no` header; because each open stream holds a short-lived php-fpm worker for up to `realtimeStreamTtl` seconds, size `pm.max_children` and set PHP `max_execution_time` / FPM `request_terminate_timeout` above `realtimeStreamTtl` for the concurrent-admin count. **Operator action required:** register the `RealtimeController` route as a permission via the Methods module scan (`notifications.realtimecontroller.read`) before enabling realtime, or the endpoint is fail-closed `403`; see the [Notifications module README](modules/Notifications/README.md) for the full setup. **Correction (superseded):** this entry originally shipped with no cap on concurrent SSE connections per user and listed one as future hardening; that cap has since landed — see *Role-Aware SSE Connection Cap (Realtime — Phase 1)* below.

- **Role-Aware SSE Connection Cap (Realtime — Phase 1):** The realtime SSE endpoint now limits how many streams a single identity may hold open at once, closing the resource-exhaustion gap left by Phase A: because every open stream occupies a PHP-FPM worker for its whole lifetime, one authenticated account could previously open streams in a loop and drain `pm.max_children`, denying the backend to everyone. `RealtimeController::stream()` now reserves a connection slot **before** opening the stream, through the new `ConnectionRegistryInterface` / `RedisConnectionRegistry` (`service('connectionRegistry')`). Open slots live in a Redis sorted set per user — `notif:conn:{userId}`, member = a server-generated random connection id (`bin2hex(random_bytes(8))`, never client-supplied), score = the slot's expiry timestamp — and acquiring one is a **single atomic Lua `EVAL`**: prune expired members (`ZREMRANGEBYSCORE key -inf now`) → reject when `ZCARD >= cap` → `ZADD` → `EXPIRE`. Atomicity is load-bearing rather than an optimisation: with the check and the insert split across two PHP round-trips, 30 simultaneous requests against a cap of 5 were measured admitting **six times** the cap, and a crash between `ZADD` and `EXPIRE` would have left the key without a TTL. An `INCR`/`DECR` counter was deliberately rejected — a client that simply closes the tab never runs the decrement, so the counter would leak until the user was locked out of their own cap. The cap is **role-aware** through two new config keys: `NotificationsConfig::$realtimeConnCapDefault` (default `6`) and `$realtimeConnCapByGroup` (default `['superadmin' => 10]`); the effective limit is resolved from the caller's Shield groups, where the **highest** matching group wins, no match falls back to the default, and non-numeric values are ignored instead of being coerced to `0`. A **negative** cap (e.g. `-1`) means **unlimited** and doubles as a global escape valve: with `$realtimeConnCapDefault < 0` the registry is never touched and the by-group table is not consulted at all. `0` is deliberately **not** the "unlimited" sentinel — because the property is declared `int`, CI4's `BaseConfig::initEnvValue()` casts every non-numeric `.env` value (i.e. every typo) to `0` before the code sees it, so `0` is treated as invalid and falls back **fail-closed** to `NotificationsConfig::CONN_CAP_FALLBACK` (6) with a once-per-process `warning`; a `0` or non-numeric entry in the by-group table is ignored (the group counts as unmatched), while a negative entry there wins as unlimited. Over the cap the endpoint returns `429` with a short `text/plain` body (new `Notifications.realtimeConnLimit` key, English + Turkish), a `Retry-After` equal to the slot lifetime and `X-Content-Type-Options: nosniff`; the body leaks neither the cap value nor a group name, and the rejected request costs a single group query because the channel list is never derived. A slot lives for the clamped stream TTL plus a 5 s buffer (`CONN_TTL_BUFFER_SECONDS`) and is released three ways: the fast `ZREM` path after a normal loop exit, a `register_shutdown_function` hook — mandatory, not belt-and-braces, because under `ignore_user_abort(false)` PHP terminates the script the moment it notices the client disconnect, so the code after the loop never runs — and finally the key's own `EXPIRE`, so a slot cannot leak permanently. **If Redis is unreachable the endpoint fails closed (`429`)**, the deliberate opposite of the best-effort, fail-open sibling `RealtimeSignal`: without Redis the signal store returns `0` for every channel, so the stream has nothing to deliver and holding a worker for the full TTL would burn exactly the resource the cap protects; the client falls back to the 60 s poll with no functional loss. Redis connection setup was consolidated into a shared `RedisConnectionTrait` that now applies a **read** timeout (0.5 s) on top of the connect timeout, so a Redis that accepts a connection but never answers (packet-dropping firewall, `BGSAVE` stall) can no longer pin a worker for `default_socket_timeout` (typically 60 s). On the client, `bell.php` treats the permanent `EventSource` close that follows a `429` (or the `204` when realtime is off) as a signal to drop to polling and retry **once** after 5 minutes — the exponential backoff chain is explicitly not run, since reconnect storms would hammer the very pool the cap protects (`EventSource` never exposes the HTTP status to JavaScript, so the client cannot distinguish `429` from `204`; the correct action is the same for both). **Known limits:** the cap is per **identity**, not global — `N` distinct accounts can still fill the worker pool, so size `pm.max_children` for the admin count — and because CI4's `BaseConfig::initEnvValue()` only walks keys that already exist, `.env` can override the shipped `notificationsconfig.realtimeConnCapByGroup.superadmin` entry but cannot add a *new* group; new groups require editing the config file. **No migration and no new route:** the existing `notifications.realtimecontroller.read` permission is unchanged, so neither a Methods module scan nor `php spark migrate` is required.

- **Notifications — Rich Targeting & Per-User Preferences (Phase 2):** A single dispatch can now address several users **and** several groups at once, and every administrator can mute the notification types they do not want to receive — both without giving up the Model B "one global row, no fan-out" guarantee. **Targeting.** `TargetResolver` deduplicates identical directives, so repeating `toUser(5)` produces one row. Overlapping *kinds* of target no longer double-notify: `toUser(5)` together with `toGroup('admin')` (where user 5 is an admin) still writes two global rows, but user 5 is written into the **group** row's exclusion list, so they see the notification exactly once — through their own `user` row, which survives even if they later leave the group. The overlap is resolved by a single bounded query (`Notifier::groupMembersAmong()`, at most one extra query per dispatch, only when both target kinds are present); full group membership is never materialised, so there is no N+1 and no fan-out. **Known limit (by design):** a group∩group overlap (`toGroup('a')` + `toGroup('b')` with a user in both) is **not** collapsed and shows twice, because collapsing it would require exactly the send-time membership expansion Model B exists to avoid. **Explicit exclusion.** The new builder method `exceptUser(int|array $userIds)` accumulates ids, normalises them (cast to int, drop `<= 0`, unique, sorted) and applies to every row of the dispatch, `broadcast()` included; it wins over `toUser()`. Storage is the new additive column `notifications.exclude_users` (`TEXT NULL`) holding a **sentinel-wrapped CSV** — always comma-wrapped (`,5,12,`), `NULL` when empty — so the read-side `NOT LIKE '%,1,%'` can never collide with `,12,`; JSON was deliberately rejected to stay free of MySQL/MariaDB JSON-function version differences. The filter is applied in `Notifier::applyRelevance()` (`n.exclude_users IS NULL OR n.exclude_users NOT LIKE …`, pattern built from an int-cast id and additionally `db->escape()`d), which is why `markRead()` also returns `404` for an excluded recipient. **Exclusion is fail-closed, deliberately.** An `exceptUser()` call is a guarantee the caller asked for, not a delivery preference, so `InAppChannel` refuses to write a row whose exclusion it cannot enforce: `skipped('exclusion-unsupported')` when the `exclude_users` column has not been migrated yet, and `skipped('exclusion-too-large')` when the list exceeds `NotificationsConfig::EXCLUDE_USERS_MAX` (500 ids ≈ 4 KB). Both are logged at `critical`, and the list is **never truncated** — the project runs `strictOn = false`, so an overflowing `TEXT` value would be cut *silently*, breaking the sentinel and failing **open** (the excluded users would see the notification). The cap is a count kept two orders of magnitude below the 65 535-byte truncation point for the same reason. Dispatches that request no exclusion are untouched by the guard, so a module dropped in without migrations still delivers ordinary notifications; and the guard is **provenance-aware**, because `NotificationMessage` keeps the two sources apart — `explicitExcludeUsers` (asked for with `exceptUser()`), `derivedExcludeUsers` (produced by the overlap narrowing above) and `excludeUsers`, their union and the stored value. Only an explicit exclusion is a guarantee, so on an unmigrated schema only a row carrying one is refused (`skipped('exclusion-unsupported')`, `critical`); a row whose exclusion is **only** derived is still written (fail-open, logged at `warning`), since the narrowing is a double-delivery optimisation whose worst case is the directly targeted user seeing the notification twice — the pre-Phase-2 behaviour — while refusing it would drop the **group** row of a `toUser` + `toGroup` dispatch for every member. The `EXCLUDE_USERS_MAX` ceiling ignores provenance and is evaluated on the union. **Per-user preferences (opt-out).** A new `notification_preferences` table stores one row per `(user_id, type, channel)` — `user_id` is a CASCADE foreign key to `users`, with `UNIQUE notif_pref_unique(user_id, type, channel)` and `KEY notif_pref_lookup(user_id, enabled)`. Because Model B keeps a single global row, there is no per-recipient row to skip at send time, so the preference is enforced at **read time**: `Notifier::applyRelevance()` `LEFT JOIN`s the preference table and anti-joins it (`p.id IS NULL`), dropping muted rows. A preference `type` may be an exact type (`audit.login`) or a **prefix** — `type = 'audit'` mutes `audit` and `audit.login` through `n.type LIKE CONCAT(p.type, '.%')` but never `auditor.x`. The join narrows on the indexed `user_id` (both indexes are `user_id`-leading; which one is used is the optimizer's call) and a per-user preference set is small, so no extra query and no N+1 is introduced. **`critical` notifications cannot be muted**, and the `n.severity <> 'critical'` condition sits on the join's **ON** side rather than in `WHERE` for two reasons: a critical notification is never silenced, and a surviving row can match zero preference rows, so `listFor()` cannot duplicate rows and `unreadCount()`'s `countAllResults()` cannot be inflated by several matching mute rows (no `GROUP BY`/`DISTINCT` needed). **`applyRelevance()` remains the single relevance/IDOR chokepoint** — all four read paths (`unreadCount`, `listFor`, `markAllRead`, `isRelevant`) go through it, and targeting, exclusion and preferences are layered *there*, never spread across controllers, views or channels; this is the architectural rule for anyone adding a read path. Realtime inherits both filters for free: `RealtimeChannel` emits only a contentless per-topic nudge (`data: {"t":<ts>}`), the client reconciles against `feed` → `listFor()` → `applyRelevance()`. The accepted residual surface is that an excluded or muted user may perform one wasted reconcile — no content leaks, only a timing signal. **Preference screen.** `GET`/`POST backend/notifications/preferences` (route names `notifPrefs` / `notifPrefsSave`, `role = read` / `role = update`), served by the new `PreferenceController` and `Views/preferences.php`, reachable from a button on the notification list page. A user can only ever manage their own preferences: `user_id` always comes from `auth()->id()` and is never read from the request; the accepted `type` / `channel` pairs come from the `NotificationsConfig::$preferenceTypes` / `$preferenceChannels` whitelists, and both the POST read and the write **iterate the whitelist, not the payload**, so an unknown key is structurally unable to reach a write (no mass assignment). Whitelist values carrying a LIKE metacharacter (`%`, `_`, `\`) are dropped with a `warning`, since such a value would act as a wildcard in the read-time join and could silence every notification of that row's owner. Rows outside the whitelist (from a seed, an import or an older release) are neither shown nor touched — the form cannot silently un-mute something it never rendered — `setEnabled()` scopes its `UPDATE` by `user_id` as defense in depth, inserts use `INSERT IGNORE` against the unique key so a concurrent double-submit cannot lose the whole batch, global CSRF stays enabled (`$csrfExcept` remains empty), and a save invalidates the owner's `notif_unread_{userId}` badge cache. `$preferenceChannels` ships as `['*']` on purpose: only `InAppChannel` persists a row (`notifications.channel` is always `inapp`), while the realtime channel emits a per-topic signal that carries neither user nor type, so a per-user "realtime" mute could not be enforced on the read path and would have been a dead toggle; a future channel that persists rows only needs its slug added, as the join's `(p.channel = '*' OR p.channel = n.channel)` already supports the granularity. New English and Turkish language keys back the whole screen. **Schema capability guard.** `SchemaGuard` memoises, per request and per connection (keyed by database name + table prefix, so a second DB group cannot inherit the answer), whether `notifications.exclude_users` and `notification_preferences` exist; when they do not, the corresponding SQL is simply not emitted, so the module keeps working on an installation where the folder was dropped in but migrations have not run. **Migrations.** Two additive migrations ship the schema and both are idempotent (`fieldExists()` / `tableExists()` guarded) with **non-destructive** `down()` methods (the manual `ALTER` / `DROP` is documented in a comment instead, because both objects carry data): `2026-07-25-000000_AddTargetingColumnsToNotifications` (adds `exclude_users` after `target_value`) and `2026-07-25-000100_CreateNotificationPreferencesTable` (creates the table; if Shield's `users` table is absent it logs a `warning` and creates it without the foreign key rather than failing). **Operator action required after upgrading:** (1) `php spark migrate --all`; (2) Backend → Methods / Modules → **Module Scan**, to register `notifications.preferencecontroller.read` and `notifications.preferencecontroller.update` in `auth_permissions_pages` and grant them to the appropriate Shield groups — until then the preference screen is fail-closed `403` for non-superadmins; (3) `php spark cache:clear` for the menu and permission caches. No route or permission of the existing endpoints changed. **Test coverage.** The full Notifications suite is green after this phase (195 tests / 557 assertions, up from 116 / 352 before it). Since the project has no separate test database, the new schema is exercised through connection-scoped `CREATE TEMPORARY TABLE` shadows that mask the permanent tables for one connection without altering them — including a pre-migration variant that proves the fail-closed exclusion path; the technique is documented in the developer handbook's testing section.

- **Notifications — Admin Composer & Delivery Accountability (Phase 3):** Administrators can now author and send a notification from the backend instead of waiting for an automatic producer. A new `ComposerController` + `Views/compose.php` serve a "Send notification" screen (title, message, link, severity, audience) reachable from a button on the notification list page, and — because the answer to "did it actually go out?" turned out to be wrong under the old rules — the same phase replaced the delivery-success test across the dispatch path. **Endpoints.** Four routes join the existing `backend/notifications` group, all behind `backendGuard`: `GET compose` (`notifCompose`, `role = read`), `GET compose/users` (`notifComposeUsers`, `role = read`, the select2 remote source), `POST compose/preview` (`notifComposePreview`, **`role = create`**) and `POST compose` (`notifComposeSend`, `role = create`). The preview endpoint is deliberately bound to the **send** permission rather than to `read`: it returns only a recipient count, but that count is a membership/existence oracle — adding an id to a `groups[]=superadmin` selection and watching whether the number moves reveals whether that identity is a superadmin, and whether it exists at all — while `read` is effectively granted to every backend user for the top-bar bell. Permission strings are derived from the route's `as` name by the Methods scanner (`ModuleScanner` writes `pagename = '{Module}.{routeName}'`), so the four new records are `notifications.notifcompose.read`, `notifications.notifcomposeusers.read`, `notifications.notifcomposepreview.create` and `notifications.notifcomposesend.create`. **Targeting.** Several users and several Shield groups can be selected *together* in one publication, or the whole audience with a single "Everyone" (broadcast) choice, plus an optional exclusion list that maps onto the Phase 2 `exceptUser()` contract. Both pickers are fed from the **server** and re-validated on the server at send time: accepted group names come from `config('AuthGroups')->groups` (an unknown name is a validation error, never a silent drop), and user ids are checked against the `users` table in a **single** `whereIn` query — no query inside a loop. Only *addressable* accounts count anywhere: the new `Notifier::scopeAddressableUsers()` is the single filter source that drops soft-deleted (`deleted_at`) and `banned` rows, so a banned identity can neither be picked, nor validated through, nor counted; `active` is deliberately **not** filtered, because Shield does not check it at login in this installation and filtering it would silently drop real recipients. **Delivery path.** Sending goes exclusively through the `service('notifier')` builder — there is **no** direct `INSERT` into `notifications` anywhere in the controller — and sanitisation stays in exactly one layer (the `NotificationMessage` constructor); the controller never repeats `strip_tags` or URL validation. The notification `type` is **not** accepted from the client: every publication from this screen is stamped with the fixed slug `announcement`, so a sender cannot invent a fresh slug per send and thereby route around a user's existing mutes. Mass assignment is structurally impossible — the POST array is never spread; each field is read by name into a server-built payload which is then both the validation input and the publication input. **Accountability.** A new additive migration (`2026-07-25-000200_AddCreatedByToNotifications`) adds a nullable `notifications.created_by`, written by `InAppChannel::buildRow()` from `auth()->id()` and never from the request; it stays `NULL` for programmatic/audit output, so `NULL` reads as "produced by the system". The column carries **no** foreign key on purpose (an audit trail must not be deleted or nulled along with the account it points at), and when the column has not been migrated yet the row is still written and only the trail is lost (**fail-open**, logged at `warning`) — the deliberate opposite of `exclude_users`, whose absence is fail-closed, because a missing exclusion *leaks a notification to someone who was excluded* while a missing `created_by` shows nobody anything wrong. `SchemaGuard::hasCreatedBy()` memoises the capability alongside the existing two. **Recipient preview.** `Notifier::recipientCount()` derives the audience size from the publication definition (broadcast / users / groups / exclusions) without touching the `notifications` table at all and without making any relevance decision; its query budget is flat in the size of the input — a broadcast costs one `countAllResults()` (plus one more when exclusions are present), and a targeted preview resolves every selected group in a **single** `SELECT DISTINCT user_id ... WHERE group IN (...)`, with the id/exclusion arithmetic done in PHP. It is documented as an estimate: group members are counted from `auth_groups_users` without joining `users`, so a stale membership row for a deleted account can overcount by one — a preview, not a delivery guarantee. **Architectural invariants held.** `Notifier::applyRelevance()` is still the single relevance/IDOR chokepoint and this phase added **zero** new reads of the `notifications` table; `Notifier` is still the only owner of the `auth_groups_users` query (`groupsFor()`, `groupMembersAmong()`, and now `recipientCount()`); global CSRF stays enabled (`$csrfExcept` remains empty — the AJAX preview carries the token in the request body); and select2 is enqueued **only** by this view rather than in the global backend layout. **Delivery reporting rebuilt (hardening).** Success was previously "any channel returned ok", which is false under Model B: with the realtime channel enabled, `InAppChannel` could refuse the durable write for a *security* reason (an unenforceable exclusion) while `RealtimeChannel` still bumped its signal, and the administrator was told the notification had been sent — when in fact nobody received anything and the excluded users the sender was protecting were the only outcome achieved. Delivery is now judged **solely** from channels that persist a row: the new marker interface `DurableChannelInterface` (implemented by `InAppChannel`; adding no methods, so new channels are transient unless they opt in — fail-closed) and the new immutable `DispatchOutcome` value object, which `NotificationBuilder::dispatch()` feeds by tagging every `ChannelResult` with its slug and durability via `ChannelResult::forChannel()`. `DispatchOutcome` separates three states because the sentence shown to the operator differs for each: complete (every attempted durable row stored), **partial** (some stored, some refused — reported as its own error, "only N of M rows could be stored", never as success) and stored-nothing, which also covers "no durable channel ran at all" (absence of evidence is not success). `exclusion-*` security refusals therefore can no longer be masked as a silent success. **Further hardening in the same pass.** The composer's link field is restricted to a **site-relative** path (`/...`): an operator holding only the send permission can address `severity = critical` to the superadmin group, criticals cannot be muted by design, and no view shows who sent a notification — so the message reads as coming from "the system", and an external link would have carried that trust straight into a phishing page. The general `NotificationMessage::sanitizeUrl()` contract is unchanged (programmatic producers may still use `http(s)` URLs) and remains the last line of defence for protocol-relative forms; an invalid link is now an explicit validation error instead of being silently dropped, which previously let an administrator send a link-less notification after typing `example.com/announcement` without any warning. `NotificationsConfig::TARGETS_MAX = 200` caps the target directives in one publication (users + groups) — each directive is one INSERT plus one `notif_unread_*` cache sweep, and PHP's default `max_input_vars` of 1000 sets the ceiling an attacker would otherwise reach; over the cap the publication is **refused**, never trimmed, since trimming would silently leave part of the chosen audience uninformed. `NotificationsConfig::BODY_MAX = 4000` caps the message body, which is a `TEXT` column under `strictOn = false` and would otherwise be cut silently; the same constant drives both the `max_length` rule and the DTO clamp. All `NotificationMessage` clamps moved from byte-based `substr` to character-based `mb_substr`, matching the `max_length` validation rule that already counted characters — otherwise a 255-character Turkish title passes validation and is then cut at the 255th **byte**, splitting a multi-byte character that `strictOn = false` stores without complaint. The user-search term is now length-capped and its LIKE metacharacters are neutralised through CI4's `ESCAPE '!'` contract (escaped, not stripped, so searching `john_doe` still finds that user while `a%` no longer matches everything): CI4 binds the value so there was no injection, but the wildcards were an **enumeration amplifier**, letting a caller slide the 20-row window with patterns like `a%` / `_a%` and map the user table. **Operator action required after upgrading:** (1) `php spark migrate --all` (or `php spark migrate -n "Modules\Notifications"`) for the `created_by` column; (2) Backend → Methods / Modules → **Module Scan**, so the four new routes land in `auth_permissions_pages` — until the scan runs the endpoints are fail-closed `403` for **everyone, superadmin included**; (3) `php spark cache:clear` for the `{userId}_permissions` caches; (4) grant `notifications.notifcomposesend.create` **deliberately** in the group matrix rather than by ticking every box in the module — the bell permissions (`notifications.read`, `notiffeed.read`, `notifstream.read`, `notifread.update`, `notifreadall.update`) have to be held by every backend user, whereas the send permission is the right to broadcast to the whole installation. New English and Turkish language keys back the whole screen. **Test coverage.** The Notifications suite is green at **286 tests / 915 assertions**, up from 195 / 557. **Known limits (deliberately not addressed in this phase):** `InAppChannel::invalidateUnreadCaches()` still runs once per stored row rather than once per dispatch; `severity = critical` is not gated behind a separate permission, so anyone who may send may send an unmutable notification; `created_by` is written but no view reads it, so "who sent this" is not yet visible in the UI; the back-compat wrappers `Notifier::toUser()` / `toRole()` and the `notifications:test` CLI still use the old "any channel ok" predicate (their docblocks were corrected to say so, but the behaviour was left alone for back-compat); and the `announcement` type is **not** in `NotificationsConfig::$preferenceTypes`, so composer announcements cannot be muted from the preference screen — left open as a product decision (should an administrator announcement be silenceable?), and technically a one-line addition plus a label. Unrelated to this phase, `php spark migrate --all` aborted on `modules/DashboardWidgets/Database/Migrations/2026-03-24-000000_AddAllowedGroupsToDashboardWidgets.php`, which lacked a `fieldExists` guard while its column already existed; that migration — and four others carrying the same latent defect — have since been guarded (see *Migrations Not Idempotent* under **Fixed**).

### Security

- **Notifications — Identity Hardening (`BellCell`):** The bell view component now derives the current user strictly from `auth()->id()` rather than any caller-supplied identifier, closing a footgun where a mismatched id could have surfaced another user's unread feed.
- **Notifications — Bound `user_id` in Relevance JOINs (defense in depth):** `Notifier` now binds `$userId` through `db->escape()` in the `notification_reads` anti-join and the relevance predicates, hardening the read path against SQL injection even though the value already originates from the authenticated session.

### Changed

- **CodeIgniter Framework Upgraded 4.7.2 → 4.7.4:** The `codeigniter4/framework` dependency was bumped from 4.7.2 to 4.7.4 (commit `653cad7`), previously shipped but undocumented in any release block.
- **`Config\Format::$jsonEncodeDepth` Added for CI 4.7.4 Compatibility:** CI 4.7.4's `JSONFormatter` reads `$jsonEncodeDepth` from the format config **without a fallback**, so the property has to exist or every `respond()` / `setJSON()` response throws. The property was added with the framework default (`512`); this surfaced while building the Notifications AJAX endpoints, which are JSON-only.
- **`cache('settings')` Decode Canonicalized Across Every Warm-Up Path:** The `settings` cache key (24 h TTL) can be warmed by whichever entry point touches it first while it is cold — `app/Config/Filters.php`, the generated `app/Config/Routes.php`, or `modules/Auth/Controllers/BaseController.php`. Over time these fillers had drifted apart and produced structurally different payloads for the same key, so downstream code behaved differently depending on which request happened to prime the cache. All fillers now use byte-identical decode logic — `json_decode($value)` gated on `json_last_error() === JSON_ERROR_NONE && (is_object($decoded) || is_array($decoded))`, matching the canonical `Routes.php` template — so a cold cache always warms to a consistent `stdClass` shape regardless of which entry point (Filters, Routes, or Auth) primed it. The unused `FormatRules` dependency in `Filters.php` was removed. `modules/Backend/Commands/Views/routes.tpl.php` was confirmed already canonical and left unchanged; `app/Config/Routes.php` was regenerated via `php spark create:route`. The new canon and its consumer paths are pinned by `tests/unit/SettingsCacheCanonTest.php` (9 tests covering the decode gate, the object-access view paths, and the `maintenanceMode` / `convertWebp` scalar reads).

### Fixed

- **Maintenance Mode Silently Disabled or Falsely Triggered by Inconsistent Settings Decode:** Two independent defects in the settings-cache fillers could take maintenance mode out of the operator's control. (1) The before-filter gate in `app/Config/Filters.php` used `(object) json_decode($value, JSON_UNESCAPED_UNICODE)`; the `JSON_UNESCAPED_UNICODE` constant (`256`) landed in `json_decode()`'s second (`$associative`) parameter slot instead of in `$flags`, so the payload decoded as an associative array and only the **top** level was cast to an object — every nested level stayed an array, producing the fragile mixed shape the rest of the codebase then tripped over. (2) `modules/Auth/Controllers/BaseController.php` warmed the same cache with the **raw, undecoded** DB row list; when the login flow primed a cold cache, the entire key→value settings map was lost, silently disabling maintenance mode. As a further side effect of the old top-level-cast gate, a stored `maintenanceMode = "0"` decoded to `stdClass{scalar: 0}` and was then evaluated as `(bool) true`, forcing the site **into** maintenance mode while maintenance was actually off. The canonical decode removes all three failure modes.
- **PHP 8 `Cannot use object of type stdClass as array` Fatal in Settings-Driven Views:** With the decode canonicalized to objects, view code that reached into nested settings via array syntax (`->x['y']`) would fatal under PHP 8. Those accessors were converted to object access (`->x->y`) in `app/Views/templates/default/base.php` (fonts / theme assets / display), `widgets/sidebar.php` (`widgets->sidebar`), `blog/list.php`, and `temp-settings.php` (widgets / display / theme assets / footer links / fonts). JSON-array levels remain PHP arrays, so the surrounding `foreach` loops are preserved.
- **`socialNetwork` Settings Rendering:** `app/Controllers/Home.php` (`$sN->link`) and `modules/Settings/Views/settings.php` (`$sn->smName`, `$sn->link`) now read social-network entries through the canonical object shape, so the frontend social links and the backend settings form render correctly.
- **`maintenanceMode` and `convertWebp` Scalar Reads Left on the Old Object-Cast Artifact:** Two scalar consumers still expected the removed `->scalar` wrapper. `app/Controllers/Home.php:116` (`maintenanceMode`) now reads the raw string value, preventing the maintenance page from failing to render (redirect loop / 500), and `modules/Media/Controllers/Media.php:124` (`convertWebp`) now reads the raw value, so WebP conversion is no longer silently disabled while its setting is enabled.
- **Migrations Not Idempotent — `php spark ci4ms:migrate` Aborting on `Duplicate column name 'allowed_groups'`:** `modules/DashboardWidgets/.../AddAllowedGroupsToDashboardWidgets` called `forge->addColumn()` with no guard. On an installation where the column already existed but the matching row in the `migrations` ledger did not — schema and ledger drift apart through a partial dump restore, a ledger reset, or a manual `ALTER` — the runner saw the migration as pending, re-ran it, and MySQL rejected it (error 1060). Because the ledger row could then never be written, **every** run aborted at the same point and every later migration (including the Notifications ones) was blocked. The migration now returns early when the column is present, so the run records it and moves on. Four further schema-altering migrations carried the same latent defect and were guarded the same way: `Auth/…AddLockedAtToUserSessions`, `LanguageManager/…AddIsFrontendToLanguages` and `Users/…UsersAddColumns` via `fieldExists()`, and `Pages/…CreatePagesTable` via CI4's own `createTable($table, true)` (`CREATE TABLE IF NOT EXISTS`). All five are behaviour-neutral on a consistent database; a repository-wide scan confirms no schema-altering migration is left unguarded.
- **Dashboard "Unread Notifications" Widget Querying a Column That Does Not Exist:** `WidgetService` counted unread notifications with `WHERE user_id = X AND is_read = 0` — inherited from a pre-Model B schema. Under Model B there is no `is_read` column (per-user read state lives in `notification_reads`) and `user_id` is `NULL` on every global row, so the widget's counter was structurally incapable of returning a correct value. Both call sites (the batched `getAllStatCounts()` subquery and the `data_unread_notifs()` provider) now delegate to `service('notifier')?->unreadCount($userId) ?? 0`, which is the module's single relevance/IDOR chokepoint and already 60 s-cached — so the widget cannot drift from the bell badge. The null-safe call keeps the dashboard rendering if the Notifications module is removed.
- **Notifications — Stale Unread Badge Race in `markAllRead`:** Cache invalidation of `notif_unread_{userId}` was moved to run **after** the database write instead of before it, closing a window where a concurrent request could re-prime the badge cache from still-unread rows immediately before they were marked read.
- **Notifications — Strict-Mode Insert Failure on Long Titles/URLs:** `NotificationMessage` now clamps `title` and `url` to 255 characters (and `type` to 64), preventing a strict-SQL insert error when an audit message exceeds the column length.
- **Notifications — Misleading `notifications:test` CLI Output:** The `notifications:test` command's messages were updated to Model B semantics, removing leftover wording that implied a per-recipient fan-out that Model B does not perform.

## [0.34.0.0] - 2026-07-05

### Added

- **Local Session Geo Lookup Subsystem (privacy-first, opt-in):** Login sessions can now be enriched with an approximate city / region / country, derived entirely from a **local** DB-IP City Lite database — the visitor's IP address never leaves the server and no third-party request is made. A new `Modules\Auth\Libraries\GeoLocator` library reads the MMDB file via `maxmind-db/reader`, and a new `Modules\Auth\Commands\GeoIpUpdate` command (`php spark ci4ms:geoip-update`) downloads the DB-IP Lite database, gunzips it, verifies it with a test lookup, and atomically renames it into place; the command is `flock`-guarded against concurrent runs and is designed to be scheduled as a monthly cron job. A new setting `Auth.geoLookupEnabled` (config default `false`) gates the whole feature and is exposed in the backend via a Settings → "Session Location Tracking" toggle (route `saveGeoLookup`; AJAX + `role=update` + CSRF + `in_list` validation, with a UI warning when the toggle is enabled while the database file is still missing) and via an opt-in checkbox in the web installer. Adds the `maxmind-db/reader` Composer dependency. New language keys — `Settings.geoLookupEnabled`, `Settings.geoLookupActive`, `Settings.geoLookupDisabled`, `Settings.geoDbMissing`, `Install.geoLookup`, and `Install.geoLookupHint` — were added in both English and Turkish. DB-IP attribution (CC BY 4.0) is surfaced in the README, the installer hint, and the command output.

### Changed

- **Session geo lookup replaces the previous inline `ip-api.com` HTTP call — BEHAVIOR CHANGE:** After upgrading, existing installations **stop collecting geo data** until an administrator enables the new setting and downloads the local database (privacy-by-default). As part of this change, the visitor's IP address is no longer transmitted to a third party over plain HTTP.

### Fixed

- **Login Crash When `ip-api.com` Was Unreachable (DNS-level blocker such as Pi-hole, or an outage):** `UserSessionModel::recordLogin()` performed `json_decode(file_get_contents("http://ip-api.com/..."))`; under `declare(strict_types=1)` a failed fetch returns `false`, and `json_decode(false)` raised an uncaught `TypeError` that turned **every** login into a 500 (the `@` error-suppression operator does not suppress a `TypeError`). This was reported by a user who hit the Pi-hole scenario. Two adjacent bugs in the same block were corrected as well: an operator-precedence bug (`$geo && $geo['status'] ?? '' === 'success'` — because `??` binds looser than `&&`, the status check never actually executed) and a missing `region` entry in `$allowedFields` (the `region` column was being silently dropped).
- **Auto-Updater Could Hang Indefinitely:** `Modules\Settings\Libraries\UpdateService` created its cURL client without a timeout (CI4 defaults: 150 s connect, **unlimited** transfer), so an unreachable or slow GitHub could stall the `checkVersion` AJAX call and the update worker for minutes. Requests are now capped at 15 s transfer / 5 s connect.
- **Auto-Updater 500 on Network Failure:** `fetchAllChangedFiles()` sat outside the surrounding try/catch, so a DNS or connection failure threw an uncaught `HTTPException`. It is now caught and returned as a structured error.
- **Auto-Updater Silent Partial Update:** `autoUpdate` / `downloadPatch` re-invoked `checkVersion()` and, on error, fell back to an empty `changed_files` list — applying an update with no deleted-file list. The file list is now carried over from the same compare result, which also removes one redundant GitHub round-trip.
- **`TypeError` Guards on `file_get_contents()` Reads:** `Modules\LanguageManager\Controllers\Translations::import()` (language import) and `Modules\Methods\Libraries\ModuleInstaller::getModuleTables()` could throw a `TypeError` under `strict_types` when `file_get_contents()` returned `false`; both now have explicit `=== false` guards (returning 422 / continuing respectively).

## [0.33.2.0] - 2026-07-03

### Fixed

- **Web Installer Silently Aborting on a Fresh Install (Empty Database Name / Missing Encryption Key):** A from-scratch web installation (`/install`) could fail mid-flow because `.env` is written *inside* the same request that runs the installation, but the `Config\Database` and `Config\Encryption` singletons were instantiated at process boot — while `.env` still did not exist — and kept their empty boot-time values. This produced two failures: (a) the migration runner connected with an empty database name and issued `SHOW TABLES FROM ` (blank), triggering a SQL syntax error that bounced the operator back to the installer form; and (b) `InstallService::createDefaultData()` threw `EncryptionException: Encrypter needs a starter key`. `Install::dbsetup()` now rebinds the `default` database connection group at runtime with the POST-supplied credentials *before* running migrations, so both the migration runner and `InstallService` connect to the real schema. `Install::generateEncryptionKey()` now writes the freshly generated key back into the live `Config\Encryption` singleton as decoded binary (the `hex2bin:`-prefixed value persisted in `.env` stays consistent for later boots), so the encrypter works within the same request.
- **Fatal Error on Install Nonce Mismatch:** The install nonce-mismatch path called `redirect()->route_to('install')`; `route_to()` is not a valid `RedirectResponse` method and produced a fatal error instead of redirecting back to the installer form. Corrected to `redirect()->route('install')`.
*(Install bug reported by [SIENSIS](https://github.com/SIENSIS))*

## [0.33.1.0] - 2026-06-22

### Security

- **Account Takeover — Defense in Depth (User Profile Password Change):** The profile update endpoint (`backend/users/profile`, `Modules\Users\Controllers\UserController::profile()`) now requires current-password verification before a logged-in user can change their password. Previously the password could be changed without re-entering the existing one, which — chained with an XSS/CSRF entry point — formed part of an account-takeover path (a defense-in-depth gap). When a new password is submitted, the `current_password` field is now mandatory and verified via Shield's `service('passwords')->verify()`; if it does not match, the password is left unchanged. (The Stored XSS layers of the related "Pages Cover Image URL" chain were already closed separately; see the Pages hardening in `[0.33.0.0]`.) Added two new language keys (`Users.currentPassword`, `Users.currentPasswordWrong`) in both English and Turkish.
- **Media Module — Defense-in-Depth Refactor & Hardening (elFinder Access Control):** Hardened the Media module's layered access control around the elFinder connector (`modules/Media/Controllers/Media.php`). The list of elFinder write commands is now defined once as a `WRITE_COMMANDS` class constant, providing a single source of truth shared by the Layer-2 controller 403 gate and the Layer-3 elFinder disabled-commands list, so a "read" permission can never silently grant write/delete access through a drift between the two lists. A pure, unit-testable `isWriteBlocked()` helper now centralizes the write-command decision, and the elFinder `debug` flag was changed from a hardcoded `true` to `(ENVIRONMENT !== 'production')` so connector debug information is no longer leaked in production. Comprehensive PHPDoc was added across the access-control chain (`index`, `elfinderConnection`, `isWriteBlocked`, `elfinderAccess`).
- **Stored XSS — Blog Categories Cover Image URL (`pageimg`):** Patched a Stored Cross-Site Scripting vulnerability in the Blog Categories module that mirrored, byte for byte, the "Stored XSS — Pages Cover Image URL" pattern already closed in `[0.33.0.0]` — this was the residual instance of the same vulnerability class in Blog Categories. The Categories controller's `pageimg` validation rule was too permissive (`regex_match[/^[^<>{}]*$/u]` still allowed `"` and `=`), and three backend views rendered the cover-image `<img src>` output without `esc()`, enabling an attribute breakout that could lead to stored XSS and admin account takeover. The fix applies the same three-layer approach used for Pages (CSP layer out of scope): input validation now enforces a strict URL regex for `pageimg` — accepting only `http(s)://` or `/`-relative URLs ending in a known image extension and rejecting `"`, `=`, whitespace, and `()` — shared between the `new()` and `edit()` flows via a new private `coverImageRules()` helper (`modules/Blog/Controllers/Categories.php`); output encoding now wraps the `<img src>` output in `esc()` in `modules/Blog/Views/categories/form.php`, and the legacy per-action category views `create.php` and `update.php` were removed (consolidated into `form.php`). No new language keys were added; the existing `Backend.coverImgURL` label is retained.
- **Stored XSS — Frontend Blog Tag Template (`tags.php`):** Patched a Stored Cross-Site Scripting vulnerability in the public-facing blog "tags" template (`app/Views/templates/default/blog/tags.php`) where user-controlled and persisted data was rendered without output encoding. The post's SEO `description` (a persisted JSON blob editable in the backend) was echoed raw into the page, allowing stored XSS to fire for every visitor of an affected tag page; it is now guarded with `!empty()` and wrapped in `esc()`, matching the canonical sibling `list.php` rendering. Two additional unescaped user-controlled outputs in the same template were also closed: the post `title` rendered inside an `<img alt>` attribute is now `esc($blog->title)`, and the tag name rendered inside the page `<h1>` is now `esc($tagInfo->tag)`.
- **Stored XSS — Frontend Blog Category Listing Header (`list.php`):** Fixed an unescaped persisted-XSS (LOW) in the public-facing blog category listing template (`app/Views/templates/default/blog/list.php`) where the category title (`$category->title`) was rendered without output encoding in the listing header; the category title is now wrapped in `esc()`, matching the sibling frontend template hardening above.

## [0.33.0.0] - 2026-06-17

### Security

- **Broken Access Control:** Fixed an issue where the Media module's "read" permission unintentionally granted full file write and delete capabilities.
- **Unsafe Reflection:** Secured the Dashboard Widgets `data_source` execution to prevent arbitrary method invocation that could disclose sensitive data like password hashes.
- **Remote Code Execution (RCE):** Prevented RCE vulnerabilities arising from unsafe template-function parsing within Page content.
- **Stored XSS:** Patched a Stored Cross-Site Scripting (XSS) vulnerability via the Pages Cover Image URL that could lead to an admin account takeover.
*(All security issues above reported by [iltosec](https://github.com/iltosec))*

### Added

- **Rate Limiting:** Introduced `ThrottleFilter` and `BackendThrottleFilter` to provide rate limiting (429 Too Many Requests) across the application.
- **Maintenance Mode:** Added `BackendMaintenanceFilter` and `BackendMaintenance` library to elegantly handle 503 Service Unavailable scenarios.
- **Custom Exception Handling:** Implemented `BackendExceptionHandler` for improved presentation of HTTP errors (403, 404, 429, 500, 503).
- **Idle Lock Screen:** Added `LockController` and `lock.php` view, along with updates to `ci4ms.js`, to lock inactive administrative sessions.
- **Widget Security:** Introduced `WidgetDataProviderInterface` to strictly enforce data sourcing contracts for Dashboard Widgets.

### Changed

- **Error Handling:** Removed legacy `Errors.php` controller in favor of the new `BackendExceptionHandler` system.
- **Module Views Unified:** Consolidated `create.php` and `update.php` into a unified `form.php` structure across `Pages` and `Blog` modules for maintainability.
- **Session Tracking:** Updated `SessionTracker` to track locked sessions with a `locked_at` timestamp.

## [0.32.0.0] - 2026-06-03

### Added

- **Pages: Inline Status Toggle:** New `isActive()` AJAX endpoint allows administrators to activate or deactivate pages directly from the DataTables listing without opening the edit form. Deactivating a page automatically removes it from the navigation menu and invalidates the corresponding per-locale menu cache.
- **Pages: Homepage Badge:** The pages list now shows a visual "Home" badge next to the page currently set as the homepage, updating in real time when the homepage selection changes.
- **Sitemap Stylesheet:** Added `public/sitemap.css` to style the XML sitemap with a clean, readable layout for browsers.
- **Users: Superadmin Delete Protection:** `user_del()` now verifies the caller holds the `superadmin` role and prevents deletion of any user belonging to the `superadmin` group, returning a localized error message (`cannotDeleteSuperadmin`) instead.
- **Menu Module: Full Internationalization:** All hardcoded Turkish strings in the Menu module views and JavaScript (stat labels, toast messages, confirm dialogs) have been replaced with `lang()` calls backed by new language keys in both English and Turkish language files.

### Changed

- **elFinder Upgrade to 2.1.67:** Updated all elFinder JS, CSS, and i18n files from 2.1.66 to 2.1.67. Added three new help files (`fr`, `zh_CN`, `zh_TW`). Asset `<script>` tags now include a `?v=2.1.67` cache-buster.
- **elFinder CSRF Bypass:** elFinder's internal CSRF token validation has been disabled via an anonymous class override because CI4 Shield's session-based authentication and the `backendGuard` filter already protect the connector endpoint; the internal CSRF mechanism was causing stale-token 403 errors during multi-request file operations.
- **Frontend: Inactive Pages Hidden:** `App\Controllers\Home` now adds an `isActive = 1` condition when resolving page content, preventing deactivated pages from being rendered on the public site.
- **Sitemap: Single-Language Mode:** `BlogModel::sitemapItems()` and `PagesModel::sitemapItems()` now check `App.siteLanguageMode`; in single-language mode, only records matching the default locale are emitted, eliminating duplicate sitemap entries.
- **Users: DataTables Search Fix:** Removed the erroneous `$like = []` reassignment in `UserController::index()` that was silently discarding the search string parsed from the DataTables request.
- **Users: CSRF Exemptions:** Added `backend/users/removeFromBlacklist`, `backend/users/blackList`, `backend/users/forceResetPassword`, and `backend/users/user_del` to `UsersConfig::$csrfExcept` so AJAX-based user management actions no longer fail on token regeneration.
- **Users: `user_del()` Signature Change:** The method no longer accepts a URL segment parameter; the target user ID is now read exclusively from POST data, matching the AJAX call pattern.
- **Backup Module: AJAX Reliability:** Backup create and delete operations now use the `.done()/.fail()/.always()` promise chain instead of the legacy `$.post(url, data, callback, type)` signature. The DataTables reload is deferred via `setTimeout(0)` to ensure it runs after the CSRF meta tag update.
- **Backup & Users Views: DataTable Scope Fix:** The DataTable instance variable in both Backup and Users list views is now declared at module scope (outside the `$(function(){})` wrapper) so that external functions (e.g. create/delete handlers) can call `table.ajax.reload()` without `ReferenceError`.
- **Filters.php: Template Filter Path Simplification:** The active theme's filter directory is now resolved with a simple `APPPATH` concatenation instead of the `resolve_template_path()` helper, removing an unnecessary abstraction layer and null-check branch.
- **Menu Module: `refreshLeftList()` changed from POST to GET:** The left sidebar panel refresh now uses `$.get()` instead of `$.post()`, matching the idempotent nature of the request and eliminating the need for CSRF token injection on a read-only call.
- **Pages Controller: Code Style Normalization:** Minor formatting changes (alignment, brace style, cast spacing) applied across the Pages controller for consistency with the project's coding standards.

### Fixed

- **Users: Search Broken in DataTables:** The `$like` variable was overwritten with an empty array immediately after being parsed from the DataTables request, making search effectively non-functional. The erroneous reassignment has been removed.
- **Pages: `setHomePage` Client-Side Stale Badge:** After toggling the homepage via AJAX, the JavaScript `homePageId` variable was not updated, causing the "Home" badge to appear on the wrong row until a full page reload. The variable is now updated immediately upon a successful response.

## [0.31.11.0] - 2026-05-24

### Fixed

- **CRITICAL — Web Installer Broken (404 on `/install/dbsetup`) (reported by [spreaderman](https://github.com/spreaderman)):** `Install::index()` redirected the browser to `install/dbsetup` after writing `.env`, but the route was registered as `POST`-only. The 302 redirect issued a `GET` request, producing `404 — Can't find a route for 'GET: install/dbsetup'` and aborting every web installation. The two-step request flow also relied on flashdata that was lost across the redirect on some session drivers. `index()` now invokes the migration + seed pipeline directly in the same request (no HTTP redirect, no flashdata), and the `install/dbsetup` route was removed entirely to eliminate the dead public endpoint.
- **CRITICAL — CLI Installer Migration Failure (`profileIMG` Default Value) (reported by [spreaderman](https://github.com/spreaderman)):** The `users` table migration declared `profileIMG` as `TEXT NOT NULL` with a string `default`. MySQL/MariaDB reject this with `BLOB, TEXT, GEOMETRY or JSON column 'profileIMG' can't have a default value` on every server version that does not silently relax the rule (most Linux distros, all default strict-mode installs), so `php spark ci4ms:setup` aborted at Step 5/6 before the database was usable. Changed the column to `VARCHAR(255) NULL` so the default URL is preserved and the migration succeeds on every supported MySQL/MariaDB version.

### Changed

- **Install Controller Hardening:** `dbsetup()` is now `private` and accepts the installation payload as a typed `array` parameter, removing the externally callable seed endpoint, the flashdata round-trip, and the empty-payload guard. The `install_dbsetup` route alias and its `role=create` permission are gone, shrinking the installer's attack surface to a single endpoint protected by `InstallFilter` (which returns `404` once `writable/install.lock` exists).
- **Version Bump:** `app.version` advanced to `0.31.11.0` in both `Install::index()` and `Ci4msSetup::run()` so freshly written `.env` files report the correct release.

## [0.31.10.0] - 2026-05-23

### Fixed

- **CRITICAL — Single Module Deletion Wiped Entire Database:** `ModuleInstaller::rollbackModuleMigrations()` called `MigrationRunner::regress(0)` under the assumption that `setNamespace()` scoped the rollback to a single module. CI4's `regress()` ignores the namespace filter (it nulls `$this->namespace` internally, and `getBatches()`/`getBatchHistory()` do not filter by namespace), so any uninstall would down every registered module's migrations and drop the entire database. Replaced with a namespace-filtered `getHistory()` walk that calls `force()` per migration, guaranteeing only the target module's tables are dropped and only its `migrations` table rows are removed. Added a `finally` block to reset the shared MigrationRunner singleton's namespace.
- **Sitemap URL Duplication:** `BlogModel::sitemapItems()` and `PagesModel::sitemapItems()` returned fully-qualified URLs via `site_url()`, but `ci4seopro\Libraries\Seo\Search\SitemapBuilder` prepends `Seo::$baseUrl` to every `loc`, producing malformed `<loc>https://host.tldhttps://host.tld/...</loc>` entries. Models now return paths only (`'/' . ltrim($seflink, '/')`), matching the package contract.
- **Sitemap Multilingual Coverage:** Both sitemap models now `LEFT JOIN` their `*_langs` tables so localized records are included instead of being filtered out by the primary-table-only query.
- **Backend CSRF Hidden Input Stale After AJAX:** `setCsrfHash()` in `public/be-assets/js/ci4ms.js` only updated the `<meta name="X-CSRF-TOKEN">` tag after token regeneration, leaving every `csrf_field()` hidden input in the page bound to the previous token. Classic (non-AJAX) form submissions following an AJAX request received `403 Forbidden`. The setter now also writes the new hash to every `input[name="csrf_token_ci4ms"]` on the page.
- **Backend CSRF Empty-Body POST Token Loss:** When an AJAX POST was sent with `data: null` or no `data` at all, `ajaxPrefilter` created a fresh object and assigned the token to it, but jQuery later re-serialized that object into an empty body, stripping the CSRF parameter. The prefilter now writes a pre-encoded URL-encoded string instead, so the token survives to the wire.
- **Frontend Captcha Auto-Fire on All Pages:** `public/templates/default/assets/ci4ms.js` invoked `captchaF()` at file scope, firing a `POST /commentCaptcha` request on every public-side page load regardless of whether a captcha image was rendered. Wrapped the call in a DOM-ready guard that runs only when `.captcha` elements exist.
- **Methods Update View Broken Route:** "Back to list" link in `modules/Methods/Views/update.php` referenced the non-existent route alias `list`; updated to the correct `methodList` alias.
- **Methods Update View Checkbox Active State:** `inNavigation`, `isBackoffice`, and `hasChild` flags are stored as integers (`1` / `0`) but the view used strict `=== true` comparison, never marking checkboxes as active for existing records. Added `(bool)` casts so the `active` class and `checked` attribute apply correctly.

### Changed

- **elFinder Dialog Reuse:** `pageImgelfinderDialog()` and `pageMultipleImgelfinderDialog()` no longer create a fresh dialog on every call. The first invocation builds the dialog and caches the jQuery wrapper in a module-scoped variable (or keyed map for the multi-image variant); subsequent invocations call `dialogelfinder("open")` on the existing instance. Removes the `destroyOnClose: true` flag on these variants, prevents leaked event handlers, and stops repeated `cssAutoLoad` HTTP requests. The `sync` interval is wrapped in `try/catch` so a destroyed instance no longer throws a polling exception every second.
- **Captcha Refresh Button Wiring:** The "New Captcha" button in `app/Helpers/templates/default/funcs_helper.php` switched from inline `onclick="captchaF()"` to a `.captcha-refresh` class that the existing jQuery delegation in `captchaF()` already binds. Cleaner separation between markup and behavior.

### Added

- **`.gitignore` Entries:** Excluded `CLAUDE.md` and `ci4ms-specs/` so per-developer agent tooling artifacts stay out of version control.

## [0.31.9.0] - 2026-05-08

### Security

- **CSRF Architecture Overhaul:** Implemented centralized `ajaxPrefilter` in `ci4ms.js` for automatic CSRF token injection on all AJAX requests. elFinder route exempted from CSRF via `MediaConfig::$csrfExcept` to prevent stale-token 403 errors during multi-request operations.
- **HTMLPurifier Hardening:** Removed `data:` URI scheme from `AllowedSchemes` to block `data:text/html;base64` XSS bypass attacks (Base64 images use a custom placeholder mechanism). Disabled `CSS.Trusted` to filter dangerous CSS properties. Enabled `HTML.TargetBlank` for automatic `rel="noopener noreferrer"` on external links. Ensured Blog and Pages controllers always persist `CustomRules::getClean()` sanitized content to the database.
- **IP Spoofing Fix:** Removed raw `$_SERVER['HTTP_X_FORWARDED_FOR']` and `$_SERVER['HTTP_CLIENT_IP']` reads from `BackendLogFilter`. Now relies solely on CI4's `$request->getIPAddress()` which respects `App.proxyIPs` config for trusted proxy detection.
- **Raw `$_SERVER` Elimination:** Replaced all raw `$_SERVER['HTTP_HOST']`, `$_SERVER['HTTPS']`, and `$_SERVER['SERVER_NAME']` reads with CI4's `base_url()`, `site_url()`, and `parse_url()` helpers across `Email.php`, `Ci4ms.php`, `Install.php`, and `Settings.php`.
- **Fileeditor RCE Prevention:** Added `$dangerousExtensions` blacklist (`.php`, `.phtml`, `.phar`, `.htaccess`, etc.) to block creating, writing, or renaming executable files via the file editor. Added `file_exists()` overwrite protection for `createFile` and `realpath` boundary validation for `renameFile`.
- **SQL Restore Hardening:** Implemented a SQL statement whitelist (`INSERT`, `CREATE TABLE`, `DROP TABLE`, etc.) and dangerous command blacklist (`LOAD_FILE`, `INTO OUTFILE`, `GRANT`, `xp_cmdshell`, etc.) in `DbBackup::restore()`. Added path traversal protection requiring backup files to reside within `WRITEPATH`.
- **Hardcoded Credentials:** Removed plaintext passwords from `DevGate` configuration. Implemented `bcrypt` hashed passwords and enabled `$useHashedPasswords` by default to protect developer credentials.
- **Stored XSS — Blog Content (reported by offset):** The `html_purify` custom validation rule was applied to the Blog content field but did not enforce sanitization during update operations, allowing authenticated authors to persist malicious scripts. Fixed by ensuring `CustomRules::getClean()` is invoked and its output persisted on both `create` and `update` flows in `Blog.php` controller.
- **Stored XSS — Pages Content (reported by offset):** Identical bypass in the Pages module: the `html_purify` rule ran validation but the raw unsanitized value was written to the database on update. Fixed by enforcing `CustomRules::getClean()` output persistence in `Pages.php` controller for both creation and editing endpoints.
- **Fileeditor Destructive Operations Extension Bypass (reported by offset):** The dangerous-extension blacklist was only enforced on `createFile`, `saveFile`, and `renameFile` but not on `deleteFileOrFolder` and `renameFile` when the target was a critical application file (e.g. `.env`, `composer.json`). Added an explicit extension allowlist check to all destructive operations (`deleteFileOrFolder`, `renameFile`) so that renaming or deleting files with critical extensions is blocked regardless of the operation type.

### Changed

- **DevGate CLI Sync:** `php spark ci4ms:setup` now automatically updates `DevGate.php` with the admin credentials provided during installation, matching the web installer's behaviour.
- **Proxy Configuration:** Added comprehensive Cloudflare and Nginx reverse proxy configuration examples as comments in `App.php::$proxyIPs`.
- **URI Schemes:** Removed unused `nntp` and `news` URI schemes from HTMLPurifier configuration.

## [0.31.8.0] - 2026-04-19

### Fixed

- **Security (Session Management):** Re-activated user account status verification in `Ci4MsAuthFilter`. Deactivated or banned users now have their sessions immediately terminated upon their next request, remediating a session bypass flaw.
- **Security (Arbitrary Table Drop):** Implemented migration-based whitelist validation in `Theme::deleteProcess`. This ensures that selectively dropping database tables during theme deletion is restricted exclusively to tables declared within the specific theme's migration files, preventing arbitrary database table deletion.

## [0.31.7.0] - 2026-04-17

### Added

- **UpdateService Library:** Introduced a comprehensive `UpdateService` library (`modules/Settings/Libraries/UpdateService.php`) to centralize all update logic. Features include GitHub Releases API integration (via `releases/latest`), atomic file writing with `rename()`, automatic backup of modified files, concurrency control via `ci4ms_update.lock`, and pagination-aware file comparison (bypassing GitHub's 300-file API limit).
- **Rollback Management:** Added `listBackups()` and `rollbackUpdate()` endpoints with a SweetAlert2-based interactive UI for browsing and restoring system backups from the Settings dashboard.
- **Security Advisory:** Added `security-advisory.md` documenting the authenticated RCE vulnerability via theme upload (GHSA-fw49-9xq4-gmx6).

### Changed

- **Settings Controller:** Refactored `checkVersion()`, `downloadPatch()`, and `autoUpdate()` methods to delegate all logic to the new `UpdateService` library, reducing controller complexity and improving testability.
- **Setup Command:** Updated version reference in `Ci4msSetup.php` to `0.31.7.0`.
- **Settings Routes:** Added new `listBackups` and `rollbackUpdate` POST routes under the `backend/settings` group.
- **Settings UI:** Added "Backups" button to the settings header and integrated rollback confirmation workflow with progress feedback.
- **Localization:** Added 12 new translation keys for backup and rollback features across English and Turkish language files.

## [0.31.6.0] - 2026-04-15

### Added

- **Automatic Update:** Introduced a comprehensive `UpdateService` library in the Settings module. Features include automated GitHub version discovery via `releases/latest` endpoint, secure file-by-file patching (bypassing 300-file API limits), and automatic database migration support.
- **Atomic Operations:** Implemented atomic file writing using temporary storage and `rename()` to prevent partial updates.
- **Backup & Rollback:** Integrated an automatic backup mechanism that captures modified files before patching, with a new manual rollback management interface in the Settings dashboard.
- **Concurrency Control:** Added `ci4ms_update.lock` to prevent concurrent update attempts.
- **Update UI:** Modernized the version check and update workflow with an interactive SweetAlert2-based interface and detailed progress feedback.

### Changed

- **Internationalization (i18n):** Completed full translation support for the Settings module across all 11 supported languages (Arabic, German, English, Spanish, French, Hindi, Japanese, Portuguese, Russian, Turkish, Chinese).
- **Update UI:** Modernized the version check and update workflow with an interactive SweetAlert2-based interface.
- **Setup Flow:** Enhanced the security and reliability of credential propagation from the web installer to the DevGate configuration.

## [0.31.5.0] - 2026-04-14

### Security

- **XSS Protection:** Patched Stored XSS vulnerability in Backup module by mitigating unescaped filename rendering in DataTables.
- **File System Security:** Fixed Arbitrary File Write (Zip Slip RCE) via directory traversal inside ZIP processing during `Theme::upload` and `Backup::restore` handling.
- **Privilege Escalation:** Prevented unauthorized assignment of the `superadmin` role during user creation and update flows within the `UserController`.

### Changed

- **Funding:** Added funding configuration (`.github/FUNDING.yml`) to support project contributions.
- **Logo:** Updated the application's default logo format to optimized WebP.

### Fixed

- **Backup Manager:** Resolved an underlying syntax error in the Backup controller's restore method.

## [0.31.4.0] - 2026-04-06

### Security

- **XSS Protection:** Mitigated Stored XSS vulnerability in `UserController` by wrapping blacklist status notes in `esc()`.
- **Authorization Bypass:** Fortified `Fileeditor` module by implementing `isHiddenPath` validation across all file operations (`readFile`, `saveFile`, `createFile`, `createFolder`, `renameFile`, `deleteFileOrFolder`), preventing unauthorized disclosure and modification of protected system files like `.env` and `composer.json`.
- **Settings Security:** Reformed Google Maps iframe validation (`cMap`) in `Settings` controller to utilize a strict `preg_replace_callback` allowlist, mitigating a sophisticated srcdoc-based Cross-Site Scripting (XSS) exploit.
- **Pages Security:** Appended the stringent `html_purify` validation rule to page creation and update flows to intercept and neutralize injected JavaScript securely.
- **Installation Integrity:** Eliminated a volatile cache-dependent installation guard in favor of a persistent filesystem lock (`install.lock`) verification within both Web (`InstallFilter`) and CLI (`Ci4msSetup.php`) boot lifecycles. This successfully remediates a critical post-installation re-entry bypass.
- **Input Validation:** Patched a CRLF Injection flaw within the initial environment setup by meticulously stripping `\r\n` carriage returns from arbitrary injected payload components inside `Install.php`.

## [0.31.3.0] - 2026-04-02

### Added

- **CLI:** Introduced `php spark ci4ms:setup` command (`Ci4msSetup.php`) to automate
  the full application installation — migrations, seeding, and default data creation —
  from a single command-line call.
- **Install:** Added "Site Slogan" support to both CLI and Web installation flows.

### Changed

- **Install:** Refactored `InstallService.php` and `Install.php` controller to support
  the new `ci4ms:setup` CLI flow alongside the existing web-based installer.
- **DashboardWidgets:** Updated `WidgetService.php` for improved widget handling.
- **CI/CD:** Updated `docker-test.yml` workflow to use `php spark ci4ms:setup` instead
  of separate migrate and seed steps; removed the fragile `Paths.php` patch workaround.
- **Docs:** Synchronized `architecture.html` and `developer-handbook.html` with recent structural changes (Shield, Docker, CLI setup) and improved layout/table styling.

### Fixed

- **Boot:** Added missing `$supportDirectory` property to `app/Config/Paths.php`,
  resolving the `Undefined constant "CodeIgniter\Config\SUPPORTPATH"` fatal error
  that occurred during `php spark` execution in Docker/CI environments on CI4 4.4+.
- **CLI:** Fixed directory creation in `Ci4msSetup.php` by switching from `PUBLICPATH` to `FCPATH` and ensured `filesystem` helper is loaded for route generation.

## [0.31.2.0] - 2026-04-01

### Added

- **Docker Support:** Introduced full Docker environment with `Dockerfile`, `docker-compose.yml`, Apache virtual host configuration, and custom `php.ini` for containerized development and deployment.
- **CI/CD:** Added GitHub Actions workflow (`docker-test.yaml`) to automatically build and test the Docker image on push.
- **Documentation:** Added `DOCKER_SETUP.md` with detailed instructions for running the project via Docker.
- **Localization:** Added complete translation packs for `DashboardWidgets` module in 9 languages: Arabic, German, Spanish, French, Hindi, Japanese, Portuguese, Russian, and Chinese.
- **Localization:** Added complete translation packs for `LanguageManager` module in 9 languages: Arabic, German, Spanish, French, Hindi, Japanese, Portuguese, Russian, and Chinese.
- **Routing:** Introduced `DefaultRoutes.php` config for centralized default route management.

### Changed

- **Auth:** Updated `Auth/Config/Auth.php` and `AuthGroups.php` to refine group and permission configurations.
- **Auth:** Improved `CustomActivationController.php` for Shield-compatible activation flow.
- **Backend:** Updated `AJAX.php` and `BaseController.php` for improved request handling and response consistency.
- **Backend Language:** Refreshed Backend translation files across all 11 supported languages.
- **Blog:** Updated Blog language files across all 11 supported languages; refined comment list and display views; updated post creation view.
- **Backup:** Refined `BackupConfig.php`, `Backup.php` controller, and `DbBackup.php` library for improved reliability.
- **DashboardWidgets:** Updated `WidgetService.php` and `DashboardWidgetsConfig.php`.
- **Fileeditor:** Updated `FileeditorConfig.php` for consistency with new config patterns.
- **Install:** Updated `Install.php` controller and `InstallService.php` for improved setup flow.
- **Media / Menu / Pages / Settings / Theme / Users:** Updated language files across all supported languages and refined module configs, controllers, and views for consistency.
- **Methods:** Updated `ModuleInstaller.php` and `ModuleScanner.php`; refined `Routes.php` and language files across all supported languages.
- **Frontend Language:** Updated `app/Language/en/Frontend.php` with new translation keys.
- **Filters:** Updated `app/Config/Filters.php` for improved filter handling.
- **App Config:** Refined `app/Config/App.php` settings.
- **Git:** Updated `.gitattributes` and `.gitignore` rules.

## [0.31.1.0] - 2026-03-30

### Added

- **Theme Manager:** Added `downloadStarter` feature offering automated, memory-based ZIP creation to provide a standardized starter boilerplate theme directly from the admin panel.
- **Theme Manager:** Smart deletion confirmation GUI that parses theme migration files and allows users to drop associated database tables selectively.
- **Theme Manager:** Added a standalone `remove_theme_files` helper specifically designed to clean up MVC templates and public asset files safely from the project root.
- **Theme Manager:** Automated schema migration implementation inside the Settings module that runs database setups upon theme activation via configuration logic.
- **Core UI:** Integrated multiple message list support into the SweetAlert `_message_block` component for enhanced feedback logs.

### Changed

- **Theme Manager:** Enhanced `install_theme_from_tmp` to support and copy the `Database/Migrations` directory automatically upon extracting a new `.zip` template.
- **Settings UI:** Upgraded backend settings view to elegantly display an extra "Delete Theme" action under inactive template cards.

### Fixed

- **Theme Manager:** Fixed recursive directory deletion method (`deleteFldr`) in `themes_helper.php` to resolve missing directory exceptions (by correcting parameter count based on recent code deprecations) and during legacy theme updates.

## [0.31.0.0] - 2026-03-29

### Security

- **CodeIgniter Shield Integration:** Fully replaced custom authentication migrations with Shield-compatible structures (`auth_groups`, `auth_identities`, `auth_groups_users`). Removed 14 legacy migration files and introduced 6 new Shield-aligned migrations with proper foreign keys.
- **BackendLogFilter:** Added `modules/Backend/Filters/BackendLogFilter.php` to record detailed user activities (IP, user agent, action, module) in the backend for audit trail and security monitoring.
- **XSS Protection:** Implemented global input validation using `regex_match[/^[<>{}]*$/u]` for common fields to prevent HTML/Script injection.
- **CSRF Protection:** Verified and refined CSRF settings. Enhanced `mergeCsrfExcept` method for improved robustness. Added logic to update CSRF tokens in the UI after AJAX operations to prevent token expiration.
- **Improved Validation:** Relaxed `seflink` regex to allow natural characters while strictly forbidding dangerous ones. Added `is_natural_no_zero` and `valid_email` checks where missing.
- Removed 'seunmatt/codeigniter-log-viewer' vendor dependency.
- Implemented `Modules\Logs\Libraries\LogViewer` for better performance and CI4 integration.
- Standardized log deletion with AJAX POST and SweetAlert2 confirmation.
- Improved security by escaping log content and removing external vendor code.
- Updated Logs controller and views to follow internal architecture patterns.

### Added

- **Framework Configurations:** Added `WorkerMode.php` and `Hostnames.php` to support high-performance environments (e.g., Swoole, FrankenPHP).
- **Migration Safeguards:** Implemented `$lock` feature in `app/Config/Migrations.php` to prevent concurrent migration execution conflicts.
- **Dynamic Sidebar:** Implemented auto-configuration for sidebar menus and icons, populated directly from module `Config` parameters.
- **Shared Logic:** Introduced `CommonBackendLibrary` to centralize common backend operational logic across controllers.
- **Template Settings UI:** Comprehensive user-friendly interface for:
  - Dynamically managing theme assets (CSS, JavaScript).
  - Injecting custom CSS and JavaScript code globally.
  - Configuring footer content, including copyright and navigation links.
  - Selecting and previewing Google Fonts.
  - Toggling general display features (breadcrumbs, back-to-top button, dark mode).
  - Controlling sidebar widgets visibility.
- **Version Checker:** Implemented version checking mechanism to notify administrators of available application updates.
- **Development Tools:** Added a custom module generator hook for streamlined backend module creation.
- **Dynamic Confirmation:** Integrated SweetAlert2 for all delete operations across the dashboard.
- **Localization:** Added new translation keys (`areYouSure`, `youWillNotBeAbleToRecoverThis`, `ok`, `success`, `error`) to all 11 supported languages:
  - Turkish (tr), English (en), Arabic (ar), German (de), Spanish (es), French (fr), Hindi (hi), Japanese (ja), Portuguese (pt), Russian (ru), Chinese (zh).
- **Project Hygiene:** Added `CONTRIBUTORS.md` to `.gitignore`.

### Changed

- **System Requirements:** Upgraded minimum PHP requirement to **8.2** across `composer.json`, `public/index.php`, and `spark` to comply with CodeIgniter 4.7.1 standards.
- **Core Dependencies:** Bumped `codeigniter4/framework` to `4.7.1`, `codeigniter4/shield` to `1.3.0`, `codeigniter4/translations` to `4.7.0`, and `claviska/simpleimage` to `4.4.0`.
- **Module Management:** Refined `moduleScan` capabilities and introduced new interactive UI elements for better backend module oversight.
- **Auth System Overhaul:** Refactored user and permission group management to fully leverage CodeIgniter Shield's capabilities. Removed legacy `Backend/Models/UserModel.php` in favour of Shield's built-in user entity.
- **Standardized API Responses:** Unified response formats across backend Settings endpoints using `ResponseTrait`.
- **Cache Invalidation:** Ensured proper sidebar menu cache invalidation upon permission page creation.
- **Asset Optimization:** Migrated heavy frontend dependencies from `node_modules` to standalone `vendor` and `plugins` directories in `be-assets` and `templates`. Drastically reduced repository size (~147MB saved) by removing source maps, unminified files, and unused package logic.
- **Fileeditor Enhancements:**
  - Implemented robust client-side alphabetical sorting (folders first, with Turkish locale character support).
  - Enhanced Monaco Editor integration with accurate language detection (PHP, JS, CSS, MD, ENV) and `vs-dark` theme optimization.
  - Cleaned up redundant logic and syntax issues in the file tree view.
- **AJAX Refactoring:** Converted all "Delete" actions from `GET` routes to secure AJAX `POST` requests.
- **DataTables Improvements:** Fixed dynamic element initialization (Bootstrap Switch) by moving logic to the DataTables `drawCallback`.
- **Module Consistency:** Standardized variable names and status indicators across `Blog` and `Pages` modules.
- **Routes:** Updated `Routes.php` in multiple modules to support `POST` method for sensitive actions.
- **Frontend:** Updated `ci4ms.js` with improved global utilities and event handlers.
- **User Management:** Configured user 'group' assignments in `UserController` to support multiple groups via Select2 array inputs. Refactored validation rules (`group.*`) and role synchronization logic (`syncGroups`) for user creation and updating. Enhanced user statistics counters to dynamically exclude `superadmin` accounts.
- **Template Views:** Refined `base.php`, `pages.php`, `post.php`, and `temp-settings.php` for Shield compatibility and new settings UI.

### Fixed

- **Tags Architecture:** Fixed pivot table insertion logic in `CommonTagsLibrary` to ensure new tags are always correctly linked and persisted for blog posts.
- **Sidebar Navigation:** Fixed the sidebar menu auto-open and active state logic (AdminLTE 3) to accurately reflect the user's current module.
- **Fileeditor Module:** Fixed the path validation regex to correctly allow multi-level folder traversal (`/` character).
- **PHP Logic:** Fixed ternary operator precedence bugs that caused incorrect 'checked' states for status switches.
- **Database Search:** Resolved a linting error in `count()` method calls in controllers.
- **View Cleanup:** Deleted unused `commentList.php` and restructured comment management views.

### Removed

- Deleted 14 legacy authentication/authorization migration files replaced by Shield-compatible schemas.
- Removed `Backend/Models/UserModel.php` (superseded by Shield's user management).

## [0.26.3.4] - 2025-09-27

### Added

- Delivered full translation packs for every module in Spanish, French, German, Chinese, Russian, Japanese, Arabic, Portuguese, and Hindi, including validation to preserve existing placeholders and HTML tokens.

## [0.26.3.3] - 2025-09-26

### Added

- Seed missing default permissions for file editor actions, the backend theme manager, and the WebP toggle during installation.

### Changed

- Build the settings cache once during filter bootstrap to eliminate redundant database lookups.
- Move the WebP conversion toggle from the AJAX controller to the Settings controller so cache invalidation happens automatically after updates.
- Normalize blog `created_at` values to the standard `Y-m-d H:i:s` format before persisting entries.
- Use the correct language keys for blog category headings to resolve localization mismatches.

### Fixed

- Exclude matches that only appear inside HTML comments from frontend autocomplete suggestions and display category labels correctly.
- Remove the unused backend test route and broaden the blog module CSRF exceptions to cover the required endpoints.

## [0.26.3.2] - 2025-09-25

### Added

- Automatically add the Logs module to the admin menu during installation so the log viewer is available from the first run.

### Changed

- Ship the `.gitattributes` file inside distribution packages so attribute rules accompany exported archives.

## [0.26.3.1] - 2025-09-19

### Added

- Started maintaining this changelog to track release highlights.

### Changed

- Marked documentation and auxiliary files with `export-ignore` so Composer dist packages stay lean.
- Refreshed docs to cover the new module generator command, backend log viewer, and changelog access.

## [0.26.3.0] - 2025-09-19

### Added

- Integrated the CI Log Viewer package and exposed a dedicated backend module for reviewing application logs.
- Captured per-action permission flags as structured JSON when creating or updating backend methods.

### Changed

- Refreshed backend method management forms, navigation buttons, and module awareness.
- Loaded SweetAlert assets globally for backend pages and updated in-app documentation links to their GitHub sources.

### Removed

- Dropped the legacy `module:create` CLI command in favour of the composer-driven module generator dependency.

## [0.26.2.0] - 2025-09-17

### Changed

- Updated documentation links to reference the project root correctly.

## [0.26.1.0] - 2025-09-17

### Added

- Published the initial developer documentation set for CI4MS.

## [0.26.0.0] - 2025-09-17

### Added

- Expanded database migrations and introduced new supporting libraries.

[0.32.0.0]: https://github.com/ci4-cms-erp/ci4ms/releases/tag/0.32.0.0
[0.31.11.0]: https://github.com/ci4-cms-erp/ci4ms/releases/tag/0.31.11.0
[0.31.10.0]: https://github.com/ci4-cms-erp/ci4ms/releases/tag/0.31.10.0
[0.31.9.0]: https://github.com/ci4-cms-erp/ci4ms/releases/tag/0.31.9.0
[0.31.8.1]: https://github.com/ci4-cms-erp/ci4ms/releases/tag/0.31.8.1
[0.31.8.0]: https://github.com/ci4-cms-erp/ci4ms/releases/tag/0.31.8.0
[0.31.7.0]: https://github.com/ci4-cms-erp/ci4ms/releases/tag/0.31.7.0
[0.31.6.0]: https://github.com/ci4-cms-erp/ci4ms/releases/tag/0.31.6.0
[0.31.5.0]: https://github.com/ci4-cms-erp/ci4ms/releases/tag/0.31.5.0
[0.31.4.0]: https://github.com/ci4-cms-erp/ci4ms/releases/tag/0.31.4.0
[0.31.3.0]: https://github.com/ci4-cms-erp/ci4ms/releases/tag/0.31.3.0
[0.31.2.0]: https://github.com/ci4-cms-erp/ci4ms/releases/tag/0.31.2.0
[0.31.1.0]: https://github.com/ci4-cms-erp/ci4ms/releases/tag/0.31.1.0
[0.31.0.0]: https://github.com/ci4-cms-erp/ci4ms/releases/tag/0.31.0.0
[0.26.3.4]: https://github.com/ci4-cms-erp/ci4ms/releases/tag/0.26.3.4
[0.26.3.3]: https://github.com/ci4-cms-erp/ci4ms/releases/tag/0.26.3.3
[0.26.3.2]: https://github.com/ci4-cms-erp/ci4ms/releases/tag/0.26.3.2
[0.26.3.1]: https://github.com/ci4-cms-erp/ci4ms/releases/tag/0.26.3.1
[0.26.3.0]: https://github.com/ci4-cms-erp/ci4ms/releases/tag/0.26.3.0
[0.26.2.0]: https://github.com/ci4-cms-erp/ci4ms/releases/tag/0.26.2.0
[0.26.1.0]: https://github.com/ci4-cms-erp/ci4ms/releases/tag/0.26.1.0
[0.26.0.0]: https://github.com/ci4-cms-erp/ci4ms/releases/tag/0.26.0.0

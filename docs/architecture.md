# CI4MS Architecture Guide

This document explains how the components inside `app/` and `modules/` work together, how requests flow through the system, and where you can extend the platform.

## Application Bootstrap & Request Lifecycle

Every request passes through `app/Filters/Ci4ms.php`:

- Redirects to `/install` if `.env` is missing (fresh setup).
- Redirects to `maintenance-mode` if the cached settings flag is enabled.
- On `after()`, caches the menu tree if it is absent (24h TTL).

`app/Config/Filters.php` performs dynamic filter discovery:

- Scans `modules/*/Filters` and the active theme filters to register aliases.
- Merges backend CSRF exceptions from `Modules\Backend\Config\BackendConfig::$csrfExcept`.

`app/Config/Routes.php` preloads settings, loads template routes, then includes each module's routes before defining front-end routes. A default template is shipped as `app/Config/DefaultRoutes.php` — copy it to `Routes.php` during setup.

## CommonModel Abstraction

Almost every module uses `bertugfahriozer/ci4commonmodel`'s `CommonModel` for CRUD. Core helpers: `lists`, `selectOne`, `create`, `createMany`, `edit`, `remove`, `isHave`, `count`. Backend `BaseController` instances instantiate `CommonModel` once and share it via `$this->commonModel`. Module controllers and libraries should go through `CommonModel` rather than calling `db_connect()` directly; a handful of call sites doing raw `db_connect()` were migrated to the shared wrapper for consistency.

## Authentication & Authorization

Authentication is powered by **CodeIgniter Shield** (`codeigniter4/shield`):

- `Modules\Auth\Libraries\AuthLibrary` handles login/logout, remember-me cookies, lockouts, password reset tokens, and email notifications. It sets session keys (`logged_in`, `redirect_url`) and caches user permissions per user ID.
- Shield database tables: `auth_groups`, `auth_identities`, `auth_groups_users`, with proper foreign keys.
- Backend requests pass through `Modules\Backend\Filters\BackendAfterLoginFilter`:
  - Redirects to logout if the user is not authenticated.
  - Validates permissions with `AuthLibrary::has_perm()`; otherwise redirects to `/backend/403` (handled via `BackendExceptionHandler`).
  - Validates theme metadata (`info.xml`, `screenshot.png`) and warms the settings cache.
- `Modules\Backend\Controllers\BaseController` centralizes:
  - Logged-in user lookup via `Modules\Users\Models\UserscrudModel::loggedUser()`.
  - Navigation data (from `AuthLibrary::sidebarNavigation()`), settings, encrypter, mail config, and default view data (`$this->defData`).
- Authorization tables:
  - `auth_permissions_pages` defines module/page permissions (stored as JSON CRUD flags); a DB-level `UNIQUE` constraint on `(className, methodName)` (via a generated column, since MariaDB/MySQL have no partial-index syntax and the table legitimately contains `('', '')` virtual-parent rows) prevents two concurrent `Modules\Methods` writes from registering colliding routes.
  - `auth_users_permissions` stores user-specific overrides.
  - `Modules\Methods` manages these tables, enforces a `className`/`methodName` collision guard on create/update, and can auto-scan routes to populate permissions.
  - Every `{pagename}.{action}` permission string is produced by the single `permission_string(string $pagename, string $action): string` helper in `app/Common.php` rather than being assembled ad hoc per call site.
- Backend activity is logged via `Modules\Backend\Filters\BackendLogFilter` (IP, user agent, action, module) for audit trail purposes.
- Inactive administrative sessions are secured via `Modules\Auth\Controllers\LockController` which locks the session and sets a `locked_at` timestamp.
- **Session geo lookup (local, opt-in):** `Modules\Auth\Models\UserSessionModel::recordLogin()` can enrich a session with approximate city/region/country. Lookups run entirely against a **local** DB-IP City Lite database (MMDB) via `Modules\Auth\Libraries\GeoLocator` (`maxmind-db/reader`) — the IP address never leaves the server. The feature is gated by the `Auth.geoLookupEnabled` setting (default `false`) and returns `null` on any failure, so it never breaks login. The database is downloaded/refreshed with `php spark ci4ms:geoip-update` (atomic swap, `flock`-guarded, monthly cron); the file lives outside the web root under `writable/geoip/`. DB-IP data is CC BY 4.0 and requires attribution.
- Application-wide rate limiting is enforced via `ThrottleFilter` and `Modules\Backend\Filters\BackendThrottleFilter` (HTTP 429).
- Maintenance mode is gracefully handled by `Modules\Backend\Filters\BackendMaintenanceFilter` and the `BackendMaintenance` library (HTTP 503).

## Module Pattern

Each module (under `modules/<Name>/`) includes:

- `Config/Routes.php` — backend routes and metadata (`role`, etc.).
- `Config/*.php` — module-specific configuration.
- `Controllers/` — usually extend the backend base controller.
- `Models/` — data access layer.
- `Views/` — backend UI templates.
- Optional: `Libraries/`, `Helpers/`, `Language/`, `Filters/`, `Database/Migrations/`.

Use `php spark make:module Foo` (provided by `ci4-cms-erp/ext_module_generator`) to scaffold a new module skeleton.

## Installation Flow

### Web Installer (`Modules\Install`)

- Copies `env` to `.env`, updates base settings, triggers migrations, and seeds defaults via `InstallService`.
- Regenerates `app/Config/Routes.php` from the `DefaultRoutes.php` template.
- Because `.env` is written *inside the same request* that runs the installation, the `Config\Database` and `Config\Encryption` singletons — both instantiated at process boot, before `.env` existed — still hold empty boot-time values. The installer therefore rebinds them at runtime: `Install::dbsetup()` merges the POST-supplied credentials into the `default` database group before running migrations (so the runner and `InstallService` connect to the real schema instead of issuing `SHOW TABLES FROM ` with a blank name), and `Install::generateEncryptionKey()` writes the freshly generated key back into the live `Config\Encryption` singleton as decoded binary so the encrypter works within the same request.

### CLI (`php spark ci4ms:setup`)

The `Ci4msSetup` command provides a fully automated installation path:

- Accepts admin account details as command-line arguments.
- Runs all database migrations across every module (`--all`).
- Calls `InstallService::createDefaultData()` to seed modules, permissions, admin user, sample pages/blog entries, and settings.
- Designed for use in CI/CD pipelines (Docker, GitHub Actions) where a browser-based installer is not practical.

## Docker & CI/CD

CI4MS ships with a complete Docker environment:

- `.docker/Dockerfile` — PHP 8.2 + Apache, with all required extensions pre-installed.
- `.docker/apache/000-default.conf` — Apache virtual host pointing to `public/`.
- `.docker/php/php.ini` — PHP configuration tuned for CI4MS.
- `docker-compose.yml` — Orchestrates `app`, `db` (MariaDB), and `phpmyadmin` services.
- `.github/workflows/docker-test.yaml` — GitHub Actions pipeline that builds the image, waits for the database, runs `php spark ci4ms:setup`, performs a PHP syntax check, and validates HTTP responses.

Key `Paths.php` note: CI4 4.4+ requires a `$supportDirectory` property in `app/Config/Paths.php` pointing to the framework's ThirdParty directory. This is pre-configured in the repository:

```php
public string $supportDirectory = __DIR__ . '/../../vendor/codeigniter4/framework/system/ThirdParty';
```

## Caching & Configuration

| Cache key | Contents | TTL |
|---|---|---|
| `settings` | Decoded JSON settings values | 24h |
| `menus_{locale}` | Per-locale frontend menu tree | 24h |
| `notif_unread_{userId}` | Per-user unread notification count | 60s |
| `sidebar_menu` | Backend sidebar items | 24h |
| `shield_auth_dynamic_config` | Shield's dynamic group/permission matrix | 24h |
| `backend_page_info_*` | Per-route `auth_permissions_pages` row | 1h |

`{userId}_permissions` is **not** a live cache: nothing in the codebase ever calls `cache()->save()` with that key format, so a lookup against it is always a miss. A few permission-changing actions (`PermgroupController::user_perms()`, `PermgroupController::update()`, `UserController::update_user()`) still call `cache()->delete("{$id}_permissions")` as a harmless no-op — deleting a key that was never populated. The RBAC caches that actually matter are two, and `Ci4MsAuthFilter` reads **both** on every backend request: `shield_auth_dynamic_config` (Shield's dynamic groups/permissions config, written in `Modules\Auth\Config\AuthGroups`, 24h) and `backend_page_info_*` (the per-route `auth_permissions_pages` row the filter looks up by `className`/`methodName`, 1h). Because a stale half is as wrong as a stale whole, they are always cleared **together** through `rbac_cache_flush()` (`app/Common.php`) — never one on its own. Its call sites are every RBAC write: `PermgroupController::group_create()`/`group_update()` and `Methods::create()`/`update()`/`delete()`/`moduleScan()`. The helper lives in `app/Common.php` rather than `modules/Backend/Helpers/`, because the latter only autoloads through `BaseController::$helpers` (controllers only), while these keys are consumed in a filter. `shield_auth_dynamic_config` is on `CacheRegistry`'s protected list and can never be cleared through the backend Cache Management panel. The `*_permissions` glob family in `CacheRegistry` is kept for forward compatibility with the manual clear panel, not because anything currently populates it.

Clear all caches with `php spark cache:clear` or selectively via `cache()->delete($key)`.

Administrators can also purge caches from the backend Settings page ("Cache Management"). `Modules\Settings\Libraries\CacheRegistry` is a server-side allowlist that maps logical ids to exact keys and glob families (`*_permissions`, `menus_*`, `notif_unread_*`); the `backend/settings/clearCache` route (`role=update`) resolves those ids server-side before calling `cache()->delete()` / `cache()->deleteMatching()`, so the client never supplies a raw pattern. The Shield dynamic RBAC key (`shield_auth_dynamic_config`) is protected and can never be cleared this way, and the framework-wide `cache()->clean()` is never triggered by this panel.

## Theme System

- Themes live under `public/templates/<theme>/` (plus optional app-level template directories).
- `Modules\Theme` handles ZIP uploads to `writable/tmp/`, detects duplicates, installs assets/views/helpers, and copies `Database/Migrations/` if present.
- On theme activation, any bundled database migrations are executed automatically via the Settings module.
- The Theme Manager can generate a downloadable starter boilerplate ZIP directly from the admin panel.
- Backend filter warns administrators if `info.xml` or `screenshot.png` are missing.

## Content & SEO

`App\Controllers\Home` renders front-end pages/blogs:

- Filters out inactive pages (`isActive = 0`) so deactivated content never reaches the frontend.
- Parses inline shortcodes via `CommonLibrary::parseInTextFunctions()`.
- Builds meta tags and JSON-LD with `Ci4msseoLibrary`.
- Loads categories/tags, authors, breadcrumbs, and comment data.

Blog and Pages modules store SEO data as JSON (`coverImage`, `description`, `keywords`). Sitemap models honour the `App.siteLanguageMode` setting: in single-language mode, only the default locale's records are emitted to avoid duplicate sitemap entries.

## Media, File Management & Logs

- `Modules\Media` integrates elFinder (v2.1.67), with a MIME allowlist from settings and optional WebP conversion (`claviska/simpleimage`). elFinder's internal CSRF validation is bypassed because CI4 Shield's session-based auth and `backendGuard` filter already protect the connector endpoint.
- `Modules\Fileeditor` provides project-level file browse/edit operations with `realpath` guardrails.
- `Modules\Logs` implements a custom `LogViewer` library so administrators can securely inspect `writable/logs/` from `/backend/logs` without shell access.
- `Modules\Backup` provides database backup and restore functionality, generating `.zip` archives in `writable/uploads/backups/`.

## Notifications

`Modules\Notifications` provides server-side, in-app notifications for administrators, surfaced through a bell dropdown in the backend header. It uses a **Model B (single global row + per-user read state)** design rather than one row per recipient:

- **Storage.** Each notification is written **once** to the `notifications` table as a global row (`user_id = null`) and targeted via `target_type` (`broadcast` | `user` | `group`) and `target_value`. Per-user read state is kept in a separate `notification_reads` table with `UNIQUE(notification_id, user_id)`; `user_id` is a CASCADE foreign key, so deleting a user clears their read rows automatically.
- **Relevance / access control.** `Notifier::applyRelevance()` is the single IDOR chokepoint. A user sees only: broadcast notifications, notifications whose `user` target is their own id, and notifications whose `group` target is a group they belong to. Group membership is resolved **at read time**, so `toGroup()` / `toRole()` persist exactly one group row and never fan out per recipient (they return `1`/`0`). `$userId` is bound through `db->escape()` in the relevance JOIN and predicates as defense in depth. `applyRelevance()` layers **three** filters in order — target, row-level exclusion, per-user preference (next two bullets) — and all four read paths (`unreadCount`, `listFor`, `markAllRead`, `isRelevant`) go through it. Keep it that way: a new read path must call `applyRelevance()` rather than re-implement any layer in a controller, a view or a channel.
- **Rich targeting & exclusions.** A single dispatch may carry several `toUser()` and `toGroup()` directives; `TargetResolver` deduplicates identical ones, and each surviving directive becomes one global row. A **user↔group overlap is narrowed at send time**: `toUser(5)` + `toGroup('admin')` (user 5 being an admin) writes both rows, but user 5 lands in the group row's exclusion list, so the notification is seen exactly once — via their own `user` row, which survives if they later leave the group. The overlap costs at most **one** extra bounded query per dispatch (`Notifier::groupMembersAmong()`, only when both target kinds are present); group membership is never materialised, so there is still no fan-out and no N+1. A group∩group overlap is deliberately not collapsed (see the limitations below). `NotificationBuilder::exceptUser(int|array)` excludes ids from every row of the dispatch (`broadcast()` included) and wins over `toUser()`. Storage is `notifications.exclude_users` (`TEXT NULL`), a **sentinel-wrapped CSV** — always comma-wrapped (`,5,12,`), `NULL` when empty — so the read-side `NOT LIKE '%,1,%'` cannot collide with `,12,`; JSON functions are avoided for MySQL/MariaDB version portability. **An explicit exclusion is fail-closed:** `NotificationMessage` keeps the provenance apart — `explicitExcludeUsers` (what the caller asked for with `exceptUser()`), `derivedExcludeUsers` (what the overlap narrowing produced) and `excludeUsers`, their union and the stored value. When the column has not been migrated yet, `InAppChannel` refuses to write a row carrying an **explicit** exclusion (`skipped('exclusion-unsupported')`, logged at `critical`), because that exclusion is a guarantee; a row whose exclusion is **only** derived is written anyway (fail-open, logged at `warning`), since the narrowing is a double-delivery optimisation whose worst case is the directly targeted user seeing the notification twice, while refusing it would drop the group's notification entirely. The `NotificationsConfig::EXCLUDE_USERS_MAX` ceiling (500) ignores provenance and is evaluated on the union (`skipped('exclusion-too-large')`, also `critical`). The list is never truncated: the project runs `strictOn = false`, so an overflowing `TEXT` value is cut *silently*, which would break the sentinel and fail **open**. Dispatches without an exclusion are unaffected, so an unmigrated install still delivers ordinary notifications — including the **group** row of a `toUser` + `toGroup` dispatch, whose exclusion is derived rather than requested.
- **Per-user preferences (opt-out).** `notification_preferences` stores one row per `(user_id, type, channel)` (`UNIQUE notif_pref_unique`, `KEY notif_pref_lookup(user_id, enabled)`, `user_id` CASCADE FK to `users`). Because Model B rows are global there is no per-recipient row to skip at send time, so **the preference is applied at read time**: `applyRelevance()` `LEFT JOIN`s the table and anti-joins it (`p.id IS NULL`). A preference `type` is an exact type or a **prefix** — `audit` mutes `audit` and `audit.login` via `n.type LIKE CONCAT(p.type, '.%')`, but not `auditor.x`. The join narrows on the indexed `user_id` (both indexes are `user_id`-leading; the choice is the optimizer's) against a small per-user set, so it adds no query and no N+1. **`critical` notifications cannot be muted**, and `n.severity <> 'critical'` sits on the join's **ON** side rather than in `WHERE` for two reasons: critical rows are never silenced, and a surviving row matches zero preference rows, so `listFor()` cannot duplicate rows and `unreadCount()`'s `countAllResults()` cannot be inflated by multiple matching mute rows (no `GROUP BY`/`DISTINCT` required). Realtime inherits the filter automatically — `RealtimeChannel` only emits a contentless per-topic nudge (`data: {"t":<ts>}`) and the client reconciles through `feed` → `listFor()` → `applyRelevance()`; the accepted residual is a wasted reconcile for a muted/excluded user (a timing signal, never content). Users manage the matrix at `GET`/`POST backend/notifications/preferences` (`notifPrefs` / `notifPrefsSave`, `role = read` / `update`, `PreferenceController` + `Views/preferences.php`, linked from the notification list page): `user_id` always comes from `auth()->id()` and never from the request, the accepted `type` / `channel` pairs come from the `NotificationsConfig::$preferenceTypes` / `$preferenceChannels` whitelists, and both the read and the write **iterate the whitelist rather than the POST body**, so an unknown key cannot reach a write; whitelist values containing a LIKE metacharacter (`%`, `_`, `\`) are dropped with a `warning` because they would act as wildcards in the join. Rows outside the whitelist are neither rendered nor touched, `setEnabled()` scopes its `UPDATE` by `user_id`, inserts use `INSERT IGNORE`, global CSRF stays on (`$csrfExcept` is empty), and a save invalidates `notif_unread_{userId}`. `$preferenceChannels` is `['*']` on purpose: only `InAppChannel` persists a row, and the realtime signal carries neither user nor type, so a per-user realtime mute could not be enforced on the read path; a future row-persisting channel only needs its slug added.
- **Schema capability guard.** `SchemaGuard` memoises per request — keyed by database name + table prefix, so a second DB group cannot inherit the answer — whether `notifications.exclude_users` and `notification_preferences` exist. When they do not, the exclusion / preference SQL is simply not emitted, so a module folder dropped in before `php spark migrate --all` never fatals. Long-running workers must call `SchemaGuard::reset()` after a migration.
- **Unread badge.** The count is an anti-join — `notifications LEFT JOIN notification_reads r ON r.notification_id = n.id AND r.user_id = X WHERE r.id IS NULL` — filtered by the same relevance rules and cached for 60 s under `notif_unread_{userId}`. `markRead` is IDOR-safe and idempotent (there is no mark-unread); `markAllRead` batches with `INSERT IGNORE` and invalidates the cache **after** the write.
- **Content safety.** All sanitisation is centralised in the `NotificationMessage` DTO: `title` is `strip_tags`-ed, `type` / `title` / `url` are length-clamped (`TYPE_MAX = 64`, `TITLE_MAX` / `URL_MAX = 255`), and `sanitizeUrl` rejects `javascript:`, `data:`, protocol-relative and CRLF URLs. Each notification carries a `severity` (`info` | `warning` | `critical`) that maps to an icon/colour in the view. `BellCell` derives the current user strictly from `auth()->id()`.
- **Channels.** `NotificationsConfig::$channels` lists `inapp`, `realtime`, `email`, `webhook`. Only `inapp` persists a row; `realtime` is the optional Redis/SSE signal (see below) and `email` / `webhook` are no-op stubs. Another module can register its own channel by declaring `public array $notificationChannels` on its `Config/{Name}Config.php` — `Notifier::resolveChannels()` scans for it the same way `Filters.php` scans `$csrfExcept`.
- **Trigger.** There is exactly one **automatic** producer: the server-side `ci4ms.audit` listener in `app/Config/Events.php`, which fires only for `warning`-severity audit events and dispatches to the group named by `NotificationsConfig::$auditTargetGroup` (default `superadmin`). The listener is null-safe (`service('notifier')?->…`) and wrapped in try/catch, so a notifier failure can never break the audited request. The only other producer is the deliberate, permission-gated admin composer described in the next bullet; nothing else in the application creates a notification as a side effect of a request.
- **Admin composer (human-authored notifications).** `ComposerController` + `Views/compose.php` serve a "Send notification" screen at `backend/notifications/compose`, linked from the notification list page, where an administrator writes a title/message/link/severity and picks an audience. Four routes join the existing group, all behind `backendGuard`: `notifCompose` (`read`), `notifComposeUsers` (`read`, the select2 remote source, max 20 rows per request), `notifComposePreview` (**`create`**) and `notifComposeSend` (`create`). Permission strings are derived from the route's `as` name — `ModuleScanner` stores `pagename = '{Module}.{route name}'` and `Ci4MsAuthFilter` checks `strtolower(pagename) . '.{action}'` — so the four records are `notifications.notifcompose.read`, `notifications.notifcomposeusers.read`, `notifications.notifcomposepreview.create` and `notifications.notifcomposesend.create`. **Preview is bound to the send permission on purpose:** it returns only a headcount, but that headcount is a membership/existence oracle (add an id to a `groups[]=superadmin` selection, watch whether the number moves), and `read` is in practice held by every backend user so the bell works. **Targeting:** several users *and* several groups may be selected together (each directive is one global row; the user↔group overlap narrowing applies unchanged), or a single broadcast, plus an optional exclusion list that maps onto `exceptUser()`. Both pickers are server-fed and server-revalidated — group names against `config('AuthGroups')->groups` (an unknown name is a validation error, not a silent drop), user ids against the `users` table in a single `whereIn` query, never one per loop iteration — and `NotificationsConfig::TARGETS_MAX` (200) caps users + groups per publication, checked **before** the existence query and **fail-closed** (refused, never trimmed, since trimming would leave part of the chosen audience uninformed). `Notifier::scopeAddressableUsers()` is the single filter source for "an account that could actually read this": it drops soft-deleted and `banned` rows from the picker, the validation and the counting alike, while deliberately not filtering `active` (Shield does not check it at login here, so filtering it would drop real recipients). **Recipient preview:** `Notifier::recipientCount()` derives the audience size from the publication definition only — it never touches the `notifications` table, makes no relevance decision, and its query budget is flat in the input size (one `SELECT DISTINCT` for all selected groups, set arithmetic in PHP, no N+1). It is documented as an estimate, since group members are counted from `auth_groups_users` without joining `users`. **Security contract:** sender identity is always `auth()->id()` (a POSTed `created_by`/`user_id` is read nowhere); there is no mass assignment (each field is read by name into a server-built payload that is both the validation and the publication input); the notification `type` is not client-supplied but stamped as the fixed slug `announcement`, so a sender cannot mint a new slug per send and step around existing mutes; delivery goes exclusively through the `service('notifier')` builder with **no** direct `INSERT`, and sanitisation stays in the single `NotificationMessage` layer; global CSRF stays on (`$csrfExcept` is empty, the AJAX preview carries the token in the body); and select2 is enqueued by this view alone rather than in the global backend layout. The link field is narrowed **here** to a site-relative path — an operator holding only the send permission can address an unmutable `critical` notification to the superadmin group and no view shows who sent it, so the message reads as "the system" and an external link would carry that trust into a phishing page; the general `NotificationMessage::sanitizeUrl()` contract (programmatic producers may use `http(s)`) is untouched and remains the last line of defence for protocol-relative forms. An invalid link is now an explicit error rather than a silent drop. The user-search term is length-capped and its LIKE metacharacters are escaped via CI4's `ESCAPE '!'` contract (escaped, not stripped) — the value was always bound, but unescaped wildcards let a caller slide the 20-row window and enumerate the user table. **Crucially, this phase added zero new reads of the `notifications` table**, so `applyRelevance()` remains the single relevance/IDOR chokepoint, and `Notifier` remains the sole owner of the `auth_groups_users` query (`groupsFor()`, `groupMembersAmong()`, `recipientCount()`).
- **Delivery accountability (`created_by`) and what "sent" means.** `notifications.created_by` (nullable, added by the additive `AddCreatedByToNotifications` migration) records the user who **produced** the row, not a recipient; it is written only by `InAppChannel::buildRow()` from `auth()->id()` and stays `NULL` for programmatic/audit output, so `NULL` reads as "produced by the system". It carries **no foreign key** deliberately — a CASCADE would delete an account's notifications with the account and a SET NULL would erase the trail, both defeating an audit column — and no read path consults it, so it adds no JOIN cost. Its schema guard (`SchemaGuard::hasCreatedBy()`) is **fail-open**: on an unmigrated schema the row is still written and only the trail is lost (logged at `warning`), the deliberate opposite of `exclude_users`, because a missing trail misleads nobody while an unenforced exclusion leaks the notification to someone who was excluded. Separately, the delivery-success test was rebuilt: "any channel returned `ok`" is wrong under Model B, because `InAppChannel` can refuse the durable write for a *security* reason (an unenforceable exclusion) while `RealtimeChannel` still bumps its signal — the administrator would be told the notification was sent when nobody received anything. Delivery is now judged solely from channels that persist a row, marked by the new `DurableChannelInterface` (implemented by `InAppChannel`; it adds no methods, so existing channels and test doubles were untouched and any new channel is transient until it opts in — fail-closed). `NotificationBuilder::dispatch()` tags every `ChannelResult` with its slug and durability (`ChannelResult::forChannel()`), and the immutable `DispatchOutcome` derives three separately reportable states — complete, **partial** (some rows stored, some refused: the audience was only partly reached, so it is surfaced as its own error rather than as success) and stored-nothing, which also covers "no durable channel ran at all", since absence of evidence is not delivery. `exclusion-*` refusals can therefore no longer be masked as a silent success. The back-compat wrappers `Notifier::toUser()` / `toRole()` and the `notifications:test` CLI still use the old predicate; their docblocks now say so, and new call sites should use `DispatchOutcome`.
- **Retention.** Retention is indefinite; there is no automatic deletion. Cleanup is manual via `php spark notifications:purge` — **no cron is installed**, operators schedule it themselves.
- **Realtime delivery (Redis-backed SSE) — optional, disabled by default.** When `NotificationsConfig::$realtimeEnabled` is on, notifications also push over Server-Sent Events for instant badge updates; with it off the module behaves exactly like the previous 60 s polling model. The transport is **self-contained** — no external hub, no JWT, no message broker — reusing the existing Redis + nginx + PHP-FPM stack. `NotificationBuilder`'s default channels are `['inapp', 'realtime']`. `RealtimeChannel` (a `ChannelInterface`) emits **after** `InAppChannel` has committed the durable row (the DB stays the source of truth) and is best-effort — a Redis outage yields a `skipped` result, never a throw. On dispatch it bumps a per-channel Redis counter through the `RealtimeSignal` store (`INCR notif:sig:{channel}` + `EXPIRE` to `realtimeSignalTtl`, default 300 s); `{channel}` is **always** `Notifier::topicFor()` output — `broadcast`, `user/{id}`, `group/{name}` — derived server-side, so the signal store is IDOR-safe by construction. The browser subscribes same-origin with `EventSource` to `GET backend/notifications/stream` (`RealtimeController::stream`, `backendGuard` + `role = read`, permission `notifications.realtimecontroller.read`). `stream()` reads the session user + groups, reserves a connection slot (next bullet), then **immediately** closes the session write lock (critical under the `FileHandler` driver, which would otherwise block every other backend request from that user) and the default DB connection, and polls the authorized channels' signals from Redis via a single `MGET` about once a second (never the DB), emitting an `event: notification` nudge on change and a `: ping` heartbeat every ~15 s. Each connection is **lifetime-capped** by `realtimeStreamTtl` (default 30 s, clamped to a 120 s ceiling); on expiry it returns cleanly and `EventSource` auto-reconnects, and `connection_aborted()` exits early. The channels are derived strictly from the session (`topicsFor()` mirrors `applyRelevance()`), so there is no JWT and the client cannot request a channel it is not entitled to. The bell client treats each message as a *nudge* — it reconciles against the `feed` endpoint rather than trusting the payload — falling back to 60 s polling with a jittered backoff if the stream drops (or on the `204` returned when realtime is disabled) and staying in sync across tabs via `BroadcastChannel`. **No nginx config change is required** (buffering is disabled per-response via the `X-Accel-Buffering: no` header) and **no new permission is introduced** — the stream reuses the read permission, which must be registered via the Methods module scan (`notifications.realtimecontroller.read`) or the endpoint is fail-closed `403`. Because each open stream holds a short-lived php-fpm worker, size `pm.max_children` / `request_terminate_timeout` for the concurrent-admin count **times their connection cap** (see the next bullet). Full setup is documented in the [Notifications module README](../modules/Notifications/README.md).
- **Realtime connection cap (per identity, role-aware).** Since every open stream occupies a php-fpm worker for its whole lifetime, `stream()` reserves a connection slot before opening the stream and returns `429` when the caller has none left — one identity can no longer drain `pm.max_children`. Slots live in a Redis sorted set per user, `notif:conn:{userId}` (member = a server-generated random connection id `bin2hex(random_bytes(8))`, score = the slot's expiry timestamp), reached only through `ConnectionRegistryInterface` / `RedisConnectionRegistry` (`service('connectionRegistry')`). Acquiring a slot is a **single atomic Lua `EVAL`** — prune expired (`ZREMRANGEBYSCORE key -inf now`) → reject if `ZCARD >= cap` → `ZADD` → `EXPIRE` — because a split check/insert lets concurrent requests race past the limit (a measured 30-request burst against `cap = 5` admitted 6× the cap) and a crash between `ZADD` and `EXPIRE` would leave the key without a TTL; an `INCR`/`DECR` counter is deliberately avoided, as a disconnecting client never runs the decrement and the counter would leak until the user was locked out of their own cap. The effective cap is resolved from Shield groups: `$realtimeConnCapByGroup` (default `['superadmin' => 10]`) with the **highest** matching value winning, otherwise `$realtimeConnCapDefault` (default `6`); non-numeric values are ignored rather than coerced to `0`. A **negative** value means **unlimited** — with `$realtimeConnCapDefault < 0` the registry is never touched and the by-group table is not consulted at all (global escape valve) — while `0` is treated as **invalid, not unlimited**: because the property is declared `int`, CI4 casts every non-numeric `.env` value (every typo) to `0` before the code sees it, so the resolver falls back **fail-closed** to `NotificationsConfig::CONN_CAP_FALLBACK` (6) and logs a once-per-process `warning`; a `0` entry in the by-group table is likewise ignored (the group counts as unmatched), whereas a negative entry there wins as unlimited. A rejection is a `429` with a short `text/plain` body (`lang('Notifications.realtimeConnLimit')`), `Retry-After` = the slot lifetime and `X-Content-Type-Options: nosniff`, leaking neither the cap value nor a group name. Slots live for the clamped stream TTL + 5 s and are released by the fast `ZREM` path after a normal loop exit, by a `register_shutdown_function` hook (**mandatory** — under `ignore_user_abort(false)` PHP ends the script when it notices the disconnect, so the post-loop code never runs), and ultimately by the key's own `EXPIRE`. **If Redis is unreachable the endpoint fails closed (`429`)** — the deliberate inverse of the best-effort, fail-open `RealtimeSignal`: without Redis the signal store returns `0` for every channel, so the stream could not deliver anything and holding a worker for the full TTL would waste exactly the resource being protected; the client falls back to polling. Both Redis users share `RedisConnectionTrait`, which applies a 0.5 s **read** timeout on top of the connect timeout so an accepting-but-unresponsive Redis cannot pin a worker for `default_socket_timeout` (typically 60 s). On the client, the permanent `EventSource` close that follows a `429` (or the `204` when realtime is off) drops the bell to polling and triggers exactly **one** retry after 5 minutes — the backoff chain is deliberately not run, since a reconnect storm would hammer the pool the cap protects.

Config constants live in `NotificationsConfig`: `UNREAD_CACHE_TTL = 60`, `TYPE_MAX = 64`, `TITLE_MAX = 255`, `URL_MAX = 255`, `BODY_MAX = 4000` (characters; the column is `TEXT` and the project runs `strictOn = false`, so an unbounded body would be cut silently — the same constant drives the composer's `max_length` rule and the DTO clamp), `FEED_LIMIT = 10`, `LIST_LIMIT = 50`, `TARGETS_MAX = 200` (target directives per publication, fail-closed), `EXCLUDE_USERS_MAX = 500` (ids per `exclude_users` list), `CONN_CAP_FALLBACK = 6`, and `$auditTargetGroup = 'superadmin'`. All `NotificationMessage` length clamps are **character**-based (`mb_substr`), matching the `max_length` validation rule which also counts characters: a byte-based clamp would pass validation and then split a multi-byte character at the boundary, which `strictOn = false` stores without complaint. The opt-out screen is driven by `$preferenceTypes` (mutable type / type-prefix => language key, default `['audit' => 'Notifications.prefTypeAudit']`) and `$preferenceChannels` (default `['*']`); a module that publishes a new notification type adds its slug there plus the label in `Language/{en,tr}/Notifications.php`. Realtime is configured on the same config through `notificationsconfig.*` env overrides — `realtimeEnabled`, `realtimeStreamTtl` (SSE connection lifetime, default 30 s, 120 s ceiling), `realtimeSignalTtl` (Redis `notif:sig:*` key TTL, default 300 s), `realtimeConnCapDefault` (concurrent SSE connections per identity, default 6) and `realtimeConnCapByGroup` (per-Shield-group override, default `['superadmin' => 10]`); the Redis connection is reused from `Config\Cache::$redis` and `ext-redis` (phpredis) is required (declared under `composer.json`'s `suggest`). CI4 env overrides key off the lowercased short class name, so the prefix is `notificationsconfig.`, not `notifications.`. Six migrations ship the schema: `CreateNotificationsTable`, `AddModelBColumnsToNotifications`, `CreateNotificationReadsTable` (batch 7-8), plus `AddTargetingColumnsToNotifications` (`notifications.exclude_users`), `CreateNotificationPreferencesTable` and `AddCreatedByToNotifications` (`notifications.created_by`). The last three are additive and idempotent (`fieldExists()` / `tableExists()` guards) and their `down()` methods are intentionally **non-destructive** — every one of those objects carries data, so the reverse `ALTER` / `DROP` is documented as a manual step in a comment instead. `CreateNotificationPreferencesTable` creates the table without the foreign key (logging a `warning`) when Shield's `users` table is not present, and `AddCreatedByToNotifications` supplies its `after` clause only when the anchor column (`exclude_users`) actually exists, since MySQL rejects the whole `ALTER` for an unknown anchor — so the module folder can be dropped onto a database that has not run the targeting migration yet.

**Upgrading an existing installation** to the targeting / preferences schema: (1) `php spark migrate --all`; (2) Backend → Methods / Modules → **Module Scan**, so `notifications.preferencecontroller.read` and `notifications.preferencecontroller.update` land in `auth_permissions_pages`, then grant them to the appropriate groups (until then the screen is fail-closed `403` for non-superadmins); (3) `php spark cache:clear`.

**Upgrading to the admin composer:** (1) `php spark migrate --all`, or `php spark migrate -n "Modules\Notifications"` to stay inside the module namespace, for the `created_by` column; (2) Backend → Methods / Modules → **Module Scan**, so the four new routes land in `auth_permissions_pages` — note that until a route has a record there, `Ci4MsAuthFilter` returns `403` for **everyone including superadmin**, since the superadmin bypass only applies after the page record exists; (3) `php spark cache:clear` for the `{userId}_permissions` caches; (4) grant `notifications.notifcomposesend.create` **deliberately** in the group matrix instead of ticking every box in the module — the bell permissions (`notifications.read`, `notiffeed.read`, `notifstream.read`, `notifread.update`, `notifreadall.update`) must be held by every backend user, whereas the send permission is the right to broadcast to the whole installation. As of this phase, `php spark migrate --all` aborts on an unrelated module — `modules/DashboardWidgets/Database/Migrations/2026-03-24-000000_AddAllowedGroupsToDashboardWidgets.php` lacks a `fieldExists` guard while its column already exists — so the namespace-scoped form is the reliable one until that migration is fixed.

**Notes & current limitations.**

- Notification text is frozen in the application locale at send time — there is no per-user re-translation.
- A group∩group overlap is not collapsed: `toGroup('a')` + `toGroup('b')` shows the notification twice to a user who belongs to both, because collapsing it would require the send-time membership expansion Model B exists to avoid. A user↔group overlap *is* collapsed.
- Opt-out is per type, not per channel: the schema and the read-time join already understand a `channel` value, but only `'*'` is offered while `inapp` is the sole channel that persists a row.
- `notifications:purge` deletes any notification that has been read **at least once**, so a broadcast/group notification read by a single recipient becomes eligible for deletion. This is a deliberate MVP simplification (tracked as a code TODO).
- The legacy `read_at` column and its `(user_id, read_at)` index on the `notifications` table are dead under Model B (read state now lives in `notification_reads`); they can be dropped later via an additive corrective migration.
- The realtime connection cap is per **identity**, not global — `N` distinct accounts can still saturate the php-fpm worker pool, so `pm.max_children` must be sized for the admin count rather than for a single admin.
- `.env` can override an **existing** `$realtimeConnCapByGroup` entry (`notificationsconfig.realtimeConnCapByGroup.superadmin = 20`) but cannot add a new group: CI4's `BaseConfig::initEnvValue()` only walks keys that already exist. New groups require editing `Config\NotificationsConfig`.
- Composer publications are stamped with the `announcement` type, which is **not** in `$preferenceTypes`, so they cannot be muted from the opt-out screen. Left open as a product decision; technically one entry plus a language label.
- `severity = critical` is not gated behind its own permission — whoever may send may send an unmutable notification — and `created_by` is written but read by no view, so "who sent this" is not surfaced in the UI.
- `InAppChannel::invalidateUnreadCaches()` runs once per stored row rather than once per dispatch, so a maximum-size publication performs 200 `deleteMatching('notif_unread_*')` calls. This is the main cost `TARGETS_MAX` exists to bound; collapsing it to one sweep per dispatch is the obvious follow-up.
- `Notifier::toUser()` / `toRole()` and `php spark notifications:test` still use the pre-`DispatchOutcome` "any channel returned ok" predicate. Their docblocks were corrected to state this; the behaviour was left unchanged for back-compat, and new call sites should use `DispatchOutcome`.

## Auto-Update & Release Signing

`Modules\Settings\Libraries\UpdateService` drives the backend one-click updater. It is **fail-closed**: every file it writes must appear in a `manifest.json` carrying a detached **Ed25519** signature made by a key the installation already trusts. The threat model this is built for is a full supply-chain compromise — the GitHub account, the release, and the CDN serving its assets all in hostile hands. Under that model the updater still writes **zero bytes** of code, because the one thing the attacker does not have is the publisher's **offline** private key.

### Verification pipeline

1. `GET /releases/latest` — the release `tag_name` is validated against `/^v?\d+(?:\.\d+){1,3}$/` before it is used anywhere.
2. `manifest.json` and `manifest.json.sig` are fetched through that release's `assets[].browser_download_url`.
3. `Modules\Settings\Libraries\ManifestVerifier` checks the detached signature with `sodium_crypto_sign_verify_detached()` against every `active` key in the keyring.
4. Version binding (below) is enforced.
5. Every downloaded file is checked against its **SHA-256** entry in the manifest.
6. Only then does `applyUpdate()` touch the filesystem.

**Verification runs on the raw HTTP response body.** Re-serialising the payload — `json_encode(json_decode($body))` — before checking the signature is forbidden: canonicalization drift in key order, slash escaping, unicode escaping or a trailing newline will silently invalidate a valid signature, and can normalise away an attacker's edit. `ManifestVerifierTest::testReserialisedManifestFailsSignature()` pins this as a regression test, because it is the kind of "harmless cleanup" a future refactor invites.

### The gates

All of the following must hold; none is advisory and there is **no "continue anyway" escape hatch** — not in the backend view, not in the API, not in the CLI.

- The signature verifies under at least one `active` keyring entry.
- `manifest.version` equals both the requested version **and** the release `tag_name`.
- `manifest.repo` equals the configured repository.
- `version_compare(manifest.version, app.version, '>')` — **the updater cannot downgrade an installation**. The supported way back is the existing `rollback()` path, which restores the automatic pre-update backup.
- A file listed as changed by the compare API but **absent from the manifest aborts the whole update**. There is no fallback to the compare API's SHA-1 blob hash, which is unsigned and therefore worthless as an integrity check here.
- A file the compare API reports as `removed` while the signed manifest still lists it is a contradiction and aborts (`removed_but_signed`).
- An empty apply set aborts (`empty_apply_set`).
- `.env` is raised to the new version **only** when every expected file was actually written.

The last three exist because the compare response is *not* covered by the manifest signature. Without them, an attacker able to alter that response could serve a genuine signed manifest, mark every file `removed`, apply an update that changes nothing, bump `.env`, and thereby pin the installation to its vulnerable build forever — it would consider itself up to date and never fetch that release again.

### Failure classification

Failures are separated rather than collapsed into one message, because "GitHub is unreachable" and "someone tampered with this release" demand opposite reactions from an operator. All four abort.

| Condition | Message key | Log level |
|---|---|---|
| Asset unreachable / network failure | `Settings.updateManifestUnreachable` | `warning` |
| Signature or version/repo binding failure | `Settings.updateSignatureInvalid` | `critical` |
| Keyring empty — nothing to trust | `Settings.updateNoTrustedKeys` | `critical` |
| Asset over the 8 MB download ceiling | `Settings.updateAssetTooLarge` | `critical` |

### Trusted keyring

`Modules\Settings\Config\UpdateKeys` holds a set keyed by `key_id`; each entry carries `public_key` (base64), `status` (`active` | `revoked`), `added`, and `fingerprint` (hex SHA-256 of the public key). The surrounding rules are what make rotation and compromise survivable:

- At least one **`active`** key must verify the signature.
- If the signature carrier references a **`revoked`** `key_id`, the **entire manifest is rejected** — even if another signature on the same carrier is valid and made by an active key. A revoked key appearing on a release is evidence that the release was produced by, or passed through, a compromised signer, so no partial trust is extended.
- An **unknown** `key_id` is silently ignored. This is precisely what makes **dual signing** work: during a rotation window the publisher signs with both the old and the new key, old installations verify through the old one, updated installations through the new one, and nobody is stranded.
- A repeated `key_id` within one carrier is rejected.

The repository **ships with `$keys = []`**, so **auto-update is disabled out of the box**. This is a deliberate fail-closed default rather than an oversight: a keyring shipped with a key that no operator ever verified out of band would be trust theatre. Until the publisher pastes in their own public key block, the updater reports `Settings.updateNoTrustedKeys` and applies nothing.

### Key management (entirely offline)

The private key **never enters GitHub or CI in any form**. `php spark ci4ms:release:keygen` seals it into a keyfile with `sodium_crypto_pwhash` (argon2id) + `sodium_crypto_secretbox`, created via `fopen('xb')` inside a `umask(0077)` window — so it is never even briefly world-readable — and left at mode `0600`. The keyfile must live **outside `ROOTPATH` and outside `public/`**; the command refuses to write inside either, rejecting symlinks and comparing paths case-insensitively so a case-flipped path cannot slip past on a case-insensitive filesystem. The password is read only through a hidden terminal prompt (`stty -echo`), never from argv, an environment variable, or shell history, and is wiped with `sodium_memzero()` after use. The command prints the public key, its fingerprint, and a ready-to-paste `UpdateKeys.php` block — never the private key.

### Manifest format

`manifest.json` is **the exact byte string that gets signed**: `json_encode(..., JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)`, **no trailing newline**, with `files` ordered by `ksort(..., SORT_STRING)` for determinism.

```json
{"schema":1,"repo":"ci4-cms-erp/ci4ms","version":"0.35.0.0","generated_at":"2026-07-27T11:36:12+00:00","algo":"sha256","files":{"app/Config/App.php":"<64 hex>"}}
```

`manifest.json.sig` is only a carrier — it is **not** part of the signed payload:

```json
{"schema":1,"signatures":[{"key_id":"ci4ms-2026-a","alg":"ed25519","sig":"<base64 64-byte detached sig>"}]}
```

### Publishing a release (the order is load-bearing)

1. Tag the release and `git checkout v<x.y.z.w>`. The working tree must be clean — `ci4ms:release:manifest` refuses to run on a dirty tree, since a manifest generated from uncommitted state would describe a build nobody can reproduce.
2. `php spark ci4ms:release:manifest --keyfile <path outside ROOTPATH> --version <x.y.z.w>` — builds **and signs** in one step, so an unsigned manifest never exists as an intermediate artifact. It enumerates tracked files via `git ls-files`, serialises deterministically, signs, then **self-checks the output with its own `ManifestVerifier`** and deletes both files if that check fails. Output: `writable/release/manifest.json` + `manifest.json.sig`.
3. Create a **draft** release on GitHub and upload both assets under exactly the names `manifest.json` and `manifest.json.sig`.
4. `php spark ci4ms:release:verify --remote --tag v<x.y.z.w>` — downloads the published assets and verifies them from the outside, exactly as an installation would.
5. Only now publish (`--draft=false`).

**Step 3 is mandatory, not tidiness.** `checkVersion()` reads `/releases/latest`, which does not see drafts. Publishing first and uploading the assets afterwards would open a window in which every installation sees a new version whose signature assets do not yet exist, and all of them report `updateManifestUnreachable`.

### Network layer note

CI4's `CURLRequest` **does not follow redirects by default** — when the `allow_redirects` key is absent from the options, `CURLOPT_FOLLOWLOCATION` is never set at all. Since `browser_download_url` answers with a `302` to a CDN host, the manifest and signature downloads must pass `'allow_redirects' => ['protocols' => ['https'], 'max' => 3, 'strict' => true]` **explicitly**; without it the response body is simply empty and the failure looks like a missing asset. The GitHub `Authorization` token is deliberately **not** attached to asset (CDN) requests.

### Cost

The manifest for the current tree covers **4,677 tracked files** at **627,193 bytes (612.5 KB)**, of which **1,969 entries (42%) sit under `public/be-assets/`**. That directory is **deliberately in scope**: it is the JS/CSS served into an authenticated administrator's browser, so excluding it to shrink the manifest would leave an unsigned XSS / browser-RCE surface. The download is gzip-compressed in transit (`decode_content => true`).

### Contract & permissions

No new route and no new `Modules\Methods` permission record were introduced — `autoUpdate`, `downloadPatch`, `listBackups` and `rollbackUpdate` are already registered with `role => 'update'`, so **no module scan is needed after upgrading**. The flat array contract (`['result' => bool, 'message' => …]`) was kept rather than replaced with a DTO, and `checkVersion()`'s ten existing keys are unchanged; it only gained a `signed` flag, which reports the **presence** of the signature assets on the release and never their validity — the real verification happens at download time.

## CLI & Automation

| Command | Purpose |
|---|---|
| `php spark ci4ms:setup` | Full automated installation (migrations + seeding) |
| `php spark make:module <n>` | Scaffold a new module skeleton |
| `php spark make:abview <n>` | Generate a backend view from the AdminLTE template |
| `php spark create:route` | Rebuild `app/Config/Routes.php` from the template |
| `php spark migrate --all` | Run all pending migrations |
| `php spark cache:clear` | Clear all application caches |
| `php spark ci4ms:geoip-update` | Download/refresh the local DB-IP City Lite database for session geo lookup (monthly cron) |
| `php spark notifications:purge` | Manually delete read notifications (retention cleanup; no cron installed) |
| `php spark notifications:test` | Emit a test notification to verify the Model B dispatch path |
| `php spark ci4ms:release:keygen` | Generate an Ed25519 release signing keypair into a password-sealed keyfile (publisher only) |
| `php spark ci4ms:release:manifest` | Build **and** sign `writable/release/manifest.json` + `.sig` from the tracked tree (clean tree required) |
| `php spark ci4ms:release:verify` | Verify a local manifest/signature pair, or the published assets with `--remote --tag v<x.y.z.w>` |

`Modules\Methods::moduleScan()` inspects the router to align routes with permission records.

## Development Tips

- Extend the backend base controller for consistent session and view data.
- Update `Modules\Methods` when adding new secured routes.
- Clear caches (`php spark cache:clear`) after updating settings, menus, or permissions.
- Keep theme directories synchronized with required assets (`info.xml`, `screenshot.png`).
- Captcha auto-disables in development via the environment check in the login controller.
- Use `php spark ci4ms:setup` in CI pipelines instead of chaining multiple spark commands.

## Common Data Tables

`users`, `auth_groups`, `auth_identities`, `auth_groups_users`, `auth_permissions_pages`, `auth_users_permissions`, `modules`, `pages`, `blog`, `blog_categories_pivot`, `tags`, `tags_pivot`, `menu`, `settings`, `login_rules`, `notifications`, `notification_reads`, `notification_preferences`, etc.

This document should help you navigate, extend, and maintain CI4MS safely. For clarification or enhancements, use the project issue tracker.

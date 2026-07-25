# Notifications Module

`Modules\Notifications` provides server-side, in-app notifications for the backend. It renders as a bell dropdown in the admin header and a full list page under `backend/notifications`. Producing modules never write to the database directly — every notification flows through a single service (`service('notifier')`), and every read passes through a single relevance chokepoint that enforces access control.

The module is a drop-in HMVC module: dropping the folder under `modules/` registers the `Modules\Notifications` namespace and auto-discovers its config, routes, filters, service, cell, commands, and migrations.

## How it works (Model B)

The module uses a **Model B** design: a **single global row + per-user read state**, rather than one row per recipient.

- **One row per notification.** Each notification is written **once** to the `notifications` table as a *global* row (`user_id = null`). The audience is expressed only through `target_type` (`broadcast` | `user` | `group`) and `target_value`. There is **no fan-out** — sending to a 50-member group still writes exactly one row.
- **Per-user read state.** Whether a given user has read a notification lives in a separate `notification_reads` table (`UNIQUE(notification_id, user_id)`). "Unread" is an anti-join:

  ```sql
  SELECT ... FROM notifications n
  LEFT JOIN notification_reads r
         ON r.notification_id = n.id AND r.user_id = :userId
  WHERE r.id IS NULL          -- not yet read by this user
    AND (<relevance predicate for :userId>)
  ```

- **Single relevance chokepoint.** `Notifier::applyRelevance()` is the one place that decides what a user may see. A user sees only: `broadcast` rows, `user` rows whose `target_value` equals their own id, and `group` rows for a Shield group they belong to. Group membership is resolved **at read time** (from `auth_groups_users`), so `toGroup()` / `toRole()` persist exactly one row and never expand per member. `$userId` is bound through `db->escape()` in the join and predicates as defense in depth. This is the IDOR guard for both the feed and `markRead`.

## Installation

The module ships inside `modules/` and is auto-discovered. To activate it on an existing install:

```bash
# 1. Run the module's migrations (creates notifications + notification_reads)
php spark migrate -n "Modules\Notifications"

# 2. Register the secured routes as permissions (see note below)

# 3. Clear caches
php spark cache:clear
```

**Permission registration is mandatory.** The controller endpoints (`index`, `feed`, `markRead`, `markAllRead`) are guarded by role flags, so until their permissions exist in `auth_permissions_pages` every request returns **403**. Registration is **not** a spark command — run the **Module Scan** action in the backend:

> Backend → **Methods / Modules** management → **Module Scan** (`POST backend/methods/moduleScan`, `Modules\Methods::moduleScan()`).

This scans the router and inserts `notifications.notificationcontroller.read`, `notifications.notificationcontroller.update`, `notifications.realtimecontroller.read` (the realtime SSE stream endpoint), `notifications.preferencecontroller.read` and `notifications.preferencecontroller.update` (the opt-out screen). Grant those to the appropriate Shield groups, then `php spark cache:clear`. A role that lacks `notifications.realtimecontroller.read` has its SSE stream request denied (fail-closed 403) and the bell silently stays on polling (superadmin bypasses the check).

**How a permission string is built.** `Modules\Methods\Libraries\ModuleScanner` stores `pagename = '{Module}.{route name}'` — the route's `as` option, not the controller class — and `Modules\Auth\Filters\Ci4MsAuthFilter` then checks `strtolower(pagename) . '.{action}'`. So each route's `as` name determines its own permission record, and the [composer screen](#sending-a-notification-from-the-backend-composer) adds four of them: `notifications.notifcompose.read`, `notifications.notifcomposeusers.read`, `notifications.notifcomposepreview.create` and `notifications.notifcomposesend.create`. Until the scan has run these endpoints are fail-closed `403` for **everyone, superadmin included** — the superadmin bypass only applies once an `auth_permissions_pages` record exists for the route.

## Usage — sending notifications from another module

Resolve the shared service with `service('notifier')` and use the fluent builder. Nothing else is required; the in-app channel writes the row and invalidates the unread-badge caches.

### Fluent builder

```php
// Notify a single user
service('notifier')
    ->notify('comment.new')                 // machine-readable event type
    ->title('New comment awaiting moderation')
    ->body('A visitor commented on "Hello World".')
    ->url('/backend/blog/comments')          // click target (see URL rules)
    ->severity('info')                       // info | warning | critical
    ->toUser(5)
    ->dispatch();

// Notify every member of a Shield group (one row; membership resolved on read)
service('notifier')
    ->notify('update.available')
    ->title('A new platform update is available')
    ->severity('warning')
    ->toGroup('superadmin')
    ->dispatch();

// Notify everyone (broadcast dominates any toUser/toGroup on the same builder)
service('notifier')
    ->notify('maintenance.scheduled')
    ->title('Scheduled maintenance tonight at 02:00')
    ->severity('critical')
    ->broadcast()
    ->dispatch();
```

Multiple target directives on one builder produce multiple global rows (deduplicated). Calling `broadcast()` overrides all other targets and produces a single `broadcast` row. `dispatch()` returns a `ChannelResult[]` (one per target × channel); with no target directive and no `broadcast()`, nothing is sent.

**Overlapping targets never double-notify.** `toUser(5)` together with `toGroup('admin')` (where user 5 is an admin) still writes two rows, but user 5 is written into the group row's `exclude_users` list, so they see the notification exactly once — through their own `user` row, which survives even if they later leave the group. The overlap is resolved with a single bounded query (`Notifier::groupMembersAmong()`), never by expanding group membership. Two *group* targets that overlap (`toGroup('a')` + `toGroup('b')`, user in both) are **not** collapsed — see [Known limitations](#known-limitations).

**An explicit exclusion is fail-closed.** `NotificationMessage` tracks where an exclusion came from: `explicitExcludeUsers` (requested by the caller through `exceptUser()`), `derivedExcludeUsers` (produced by the overlap narrowing above) and `excludeUsers` — their union, which is the value actually stored. An `exceptUser()` call is a guarantee, not a delivery preference, so when the `exclude_users` column has not been migrated yet `InAppChannel` refuses to write a row carrying one: `skipped('exclusion-unsupported')`, logged at `critical`, because writing it would make the excluded users *see* the notification. A row whose exclusion is **only** derived is written anyway (fail-open, logged at `warning`): the narrowing is a double-delivery optimization rather than a guarantee, so its worst case is the directly targeted user seeing the notification twice — the behaviour before rich targeting existed — whereas refusing the row would drop the `group` row of a `toUser` + `toGroup` dispatch for every member. The `NotificationsConfig::EXCLUDE_USERS_MAX` (500) ceiling ignores provenance and is checked on the union: over it the row is skipped (`skipped('exclusion-too-large')`, `critical`) and the list is never truncated, since a cut-off sentinel CSV would leak the notification just as writing the row would. Dispatches without any exclusion never reach the guard, so an unmigrated module still delivers normal notifications.

### Builder reference

| Method | Effect |
| --- | --- |
| `notify(string $type)` | Starts a builder for the given event type. |
| `title(string)` | Display title (sanitized at build time). |
| `body(?string)` | Optional body (sanitized; `null`/empty stays `null`). |
| `url(?string)` | Optional click target (validated; see URL rules). |
| `severity(string)` | `info` \| `warning` \| `critical`; anything else falls back to `info`. |
| `toUser(int $userId)` | Adds a `user` target row. |
| `toGroup(string $group)` | Adds a `group` target row (Shield group name). |
| `exceptUser(int\|array $userIds)` | Excludes user ids from every row of this dispatch (accumulates; wins over `toUser`). |
| `createdBy(?int $userId)` | Records **who produced** the notification in `notifications.created_by` (an audit trail, not a target). Pass a server-derived id (`auth()->id()`); it changes no delivery decision, so the sender still sees their own notification unless you also `exceptUser()` them. |
| `broadcast()` | Marks the notification for everyone (dominant). |
| `via(string ...$channels)` | Delivery channels (default `['inapp', 'realtime']`). |
| `dispatch()` | Resolves targets, builds messages, delivers on each channel. |

### Was it actually delivered?

`dispatch()` returns a `ChannelResult[]`, one per target × channel, and each result is tagged by `NotificationBuilder::dispatch()` with the slug of the channel that produced it and with a `durable` flag. **Do not read that array as "any result is `ok`, therefore it was sent."** Under Model B only a channel that persists a row has actually delivered anything: `InAppChannel` implements the marker interface `DurableChannelInterface`, while `RealtimeChannel` merely tells connected clients to re-read — if there is no row to read, that is not a delivery. The interface adds no methods on purpose, so existing channels (and test doubles) needed no change and any **new** channel counts as transient until it explicitly declares durability (fail-closed).

Wrap the results in `DispatchOutcome` to get the answer:

```php
$outcome = DispatchOutcome::fromResults(
    service('notifier')->notify('announcement')
        ->title('Scheduled maintenance')
        ->toGroup('superadmin')
        ->dispatch()
);

$outcome->attempted();     // durable rows attempted
$outcome->stored();        // durable rows actually written
$outcome->isComplete();    // every attempted row stored (and at least one was attempted)
$outcome->isPartial();     // some stored, some refused — the audience was only partly reached
$outcome->storedNothing(); // nothing stored, including "no durable channel ran at all"
$outcome->refusals();      // ['exclusion-unsupported', 'insert-failed', ...]
```

The three states are kept apart because the sentence you owe the operator differs for each; in particular a **partial** result must not be reported as success, and `storedNothing()` deliberately covers the "no durable channel ran" case as well, since absence of evidence is not delivery. This matters most for the security refusals: when `InAppChannel` drops a row because it cannot enforce an exclusion, an "any channel ok" test would report success to an administrator while nobody received the notification.

The back-compat wrappers `Notifier::toUser()` / `toRole()` and the `notifications:test` command still use the old "any channel ok" predicate and are documented as such — treat their return value as "a delivery was attempted", never as "a row exists".

### Back-compat wrappers

Thin wrappers over the builder for terse call sites:

```php
// Returns bool: any channel reported ok — NOT a guarantee that a row was written.
// Severity is fixed to 'info'.
service('notifier')->toUser(5, 'comment.new', 'New comment', 'Optional body', '/backend/blog/comments');

// Returns 1 or 0 on the same "any channel ok" predicate — NOT a recipient count. No fan-out.
service('notifier')->toRole('superadmin', 'update.available', 'Update available');
```

Read-side helpers (used by the controller and cell, but public):

| Method | Returns |
| --- | --- |
| `unreadCount(int $userId): int` | Relevant + unread count (cached ~60s). |
| `listFor(int $userId, int $limit): array` | Relevant rows, newest first (`stdClass` list). |
| `markRead(int $id, int $userId): bool` | Marks one notification read; `false` if missing or not relevant. |
| `markAllRead(int $userId): int` | Marks all relevant unread as read; returns affected count. |

`markRead` / `markAllRead` are IDOR-safe and idempotent (there is no mark-unread). `markAllRead` uses `INSERT IGNORE` against the `UNIQUE(notification_id, user_id)` constraint.

### Severity

`severity` accepts `info`, `warning`, or `critical`; any other value is normalized to `info`. The view maps severity to an icon and color:

| Severity | Icon | Color |
| --- | --- | --- |
| `info` | `far fa-bell` | `text-primary` |
| `warning` | `fas fa-exclamation-triangle` | `text-warning` |
| `critical` | `fas fa-exclamation-circle` | `text-danger` |

### URL rules (content safety)

All sanitization is centralized in the `NotificationMessage` DTO, so channels never handle raw input:

- `title` and `body` are `strip_tags`-ed and trimmed.
- `type` is clamped to `TYPE_MAX` (64), `title` and `url` to `TITLE_MAX` / `URL_MAX` (255), `body` to `BODY_MAX` (4000). Clamping counts **characters** (`mb_substr`), matching the `max_length` validation rule that guards the same fields — a byte-based cut would pass validation and then slice a multi-byte character in half, which `strictOn = false` stores without complaint.
- `url` is accepted **only** if it is site-relative (`/...`) or an absolute `http(s)://` URL. Everything else becomes `null`:
  - `javascript:` and `data:` schemes → rejected (they are neither `/...` nor `http(s)://`).
  - Protocol-relative `//host` and back-slash variant `/\host` → rejected.
  - URLs containing control characters (`\x00`–`\x1F`, `\x7F`, i.e. CR/LF/TAB/NUL) → rejected (header/DOM-injection surface).

The view additionally escapes URLs with `esc(..., 'attr')`, so sanitization is two-layered.

## The bell UI & HTTP endpoints

`Cells\BellCell` renders the top-bar bell dropdown. It is called from `Modules\Backend\Views\base.php` on every backend page:

```php
<?= view_cell('Modules\Notifications\Cells\BellCell::render', ['user_id' => auth()->id()]) ?>
```

The `user_id` param is only a *render gate* (so the bell is skipped on the login page without touching the DB); the identity used for queries is derived strictly from `auth()->id()` inside the cell. If the Model B tables have not been migrated yet, the cell renders nothing (a backend page never fatals because the module was dropped but not migrated). After the initial render, a small script polls the `feed` endpoint every `POLL_MS` (60s) to refresh the badge and list. When realtime is enabled, a same-origin `EventSource` connection to the SSE stream nudges that same refresh the instant a notification is produced; see [Realtime notifications (Redis-backed SSE)](#realtime-notifications-redis-backed-sse).

Routes are grouped under `backend/notifications` (all behind the `backendGuard` filter):

| Method | Path | Route name | Permission | Purpose |
| --- | --- | --- | --- | --- |
| GET | `backend/notifications` | `notifications` | `read` | Full list page (up to `LIST_LIMIT`). |
| GET | `backend/notifications/feed` | `notifFeed` | `read` | AJAX JSON: `{status, unread, items}` for the dropdown. |
| GET | `backend/notifications/preferences` | `notifPrefs` | `read` | Per-user mute matrix (opt-out screen). |
| POST | `backend/notifications/preferences` | `notifPrefsSave` | `update` | Save the mute matrix (form POST, CSRF field). |
| GET | `backend/notifications/compose` | `notifCompose` | `read` | Composer form (see [below](#sending-a-notification-from-the-backend-composer)). |
| GET | `backend/notifications/compose/users` | `notifComposeUsers` | `read` | AJAX JSON: select2 remote user source (max 20 rows). |
| POST | `backend/notifications/compose/preview` | `notifComposePreview` | `create` | AJAX JSON: `{status, count}` recipient estimate. Bound to `create`, **not** `read` — see below. |
| POST | `backend/notifications/compose` | `notifComposeSend` | `create` | Send the notification (form POST, CSRF field). |
| POST | `backend/notifications/read/(:num)` | `notifRead` | `update` | Mark one notification read (AJAX). |
| POST | `backend/notifications/readAll` | `notifReadAll` | `update` | Mark all relevant unread as read (AJAX). |

The write endpoints accept AJAX only (`failForbidden()` otherwise) and return `failNotFound()` when a notification is missing or not relevant to the caller. Global CSRF stays enabled — `$csrfExcept` is empty; the shared `be-assets/js/ci4ms.js` AJAX layer injects the CSRF token automatically, so no per-module CSRF exception is needed.

## Sending a notification from the backend (composer)

Everything above is about notifications a *producer* emits. `ComposerController` + `Views/compose.php` add the human path: a "Send notification" screen at `backend/notifications/compose`, reachable from a button on the notification list page, where an administrator writes a title, a message, an optional link and a severity, picks an audience, and sends. It is a permission-sensitive feature — the right to use it is the right to put text in front of every administrator in the installation — so the contract below is deliberate rather than incidental.

### Choosing the audience

Two modes: **Everyone** (a single `broadcast` row) or **Selected users and groups**. In the targeted mode several users **and** several groups may be chosen *together* — each directive becomes one global row, and the Phase 2 user↔group overlap narrowing applies unchanged, so someone who is both directly targeted and a member of a targeted group still sees the notification once. A separate "Excluded users" picker maps onto `exceptUser()` and applies to every row of the publication, broadcast included.

Both pickers are populated **from the server** and re-validated **on the server** at send time; the browser's selection is treated as a request, never as a fact:

- **Groups** are whitelisted against `config('AuthGroups')->groups`. The same array renders the form and validates the submission, so a name that is not in it is not merely ignored — it is returned as a validation error. (`setting('AuthGroups.groups')` is deliberately not used: it opens a query against the settings store and throws when that store is unreachable, and in this project `Modules\Auth\Config\AuthGroups` is a static config class with no settings row, so the two resolve to the same array anyway. If groups ever become settings-overridable, this is the line to move.)
- **Users** are verified against the `users` table in a **single** `whereIn` query — there is no query inside a loop. Only *addressable* accounts exist as far as this screen is concerned: `Notifier::scopeAddressableUsers()` is the one filter source that drops soft-deleted (`deleted_at`) and `banned` rows, and it is applied to the picker, to the validation and to the recipient count alike. A banned or deleted id therefore cannot be picked, and pasting one into the payload fails the send with a flat "unknown user" (fail-closed, and without disclosing the account's actual state). `active` is deliberately **not** filtered: registration activation is disabled in this installation and Shield does not check `active` at login, so an `active = 0` account can log in and read notifications — excluding it would silently drop real recipients.
- **Target ceiling.** `NotificationsConfig::TARGETS_MAX` (200) caps users + groups in one publication, and it is checked **before** the existence query so a thousand-id body cannot buy a thousand-element `IN` before being rejected. Over the cap the publication is refused, never trimmed — trimming would leave part of the chosen audience silently uninformed. The rationale is cost: each directive is one INSERT plus one `notif_unread_*` cache sweep, and PHP's default `max_input_vars` of 1000 is the ceiling an attacker would otherwise aim at.

### Recipient preview

The "Preview recipients" button POSTs the current selection to `compose/preview` and shows an estimated headcount. The count is produced by `Notifier::recipientCount()`, which derives it from the publication definition alone: it never touches the `notifications` table, makes no relevance decision, and writes nothing. Its query budget is flat in the size of the input — a broadcast costs one `countAllResults()` (plus one more when exclusions are present, to subtract only ids that really exist), and a targeted preview resolves every selected group in a **single** `SELECT DISTINCT user_id ... WHERE group IN (...)`, with the id and exclusion arithmetic done in PHP. No N+1.

It is an **estimate**, honestly: in targeted mode group members are counted from `auth_groups_users` without joining `users`, so a leftover membership row for a deleted or banned account can overcount by one. A second join was judged not worth it for a number that is a preview, not a delivery guarantee.

**Why preview requires the send permission.** The route is `role = create`, not `read`. The endpoint returns only a number, but that number is a membership/existence oracle: add an id to a `groups[]=superadmin` selection, watch whether the count moves, and you learn whether that identity is a superadmin — and whether it exists at all. Since `read` is in practice granted to every backend user so the top-bar bell works, the preview is put in the same bucket as sending: whoever cannot preview cannot send anyway.

### What the server guarantees on send

1. **Sender identity is always `auth()->id()`.** A `created_by` or `user_id` field in the POST body is read nowhere and therefore does nothing.
2. **No mass assignment.** The POST array is never spread. Each field is read by name into a server-built payload, and validation runs against *that* array, so a key that is not on the form can reach neither validation nor publication.
3. **The notification type is not client-supplied.** Every publication from this screen is stamped with the fixed slug `announcement`. A free-form type would let a sender mint a new slug on each send and thereby step around the mutes users have already set, since the preference whitelist keys off fixed slugs.
4. **One delivery path.** Sending goes exclusively through the `service('notifier')` builder; the controller contains no `INSERT` into `notifications`. Sanitisation stays in a single layer — the `NotificationMessage` constructor — and is not repeated in the controller, so there is only ever one place where the rules can change.
5. **Accountability.** `createdBy(auth()->id())` is attached, landing in `notifications.created_by`.
6. **CSRF stays global.** These routes are *not* added to `$csrfExcept`; the AJAX preview carries the token in the request body.
7. **Authorization is fail-closed at the route** (`backendGuard` + `role`). The composer button on the list page is rendered without a permission check — the project has no in-view permission helper — so an unauthorized user sees the link and gets a `403` from the endpoint.

### Delivery reporting

The success message reflects what was actually **stored**, via `DispatchOutcome` (see [Was it actually delivered?](#was-it-actually-delivered)): "sent" is claimed only when every attempted durable row was written; a partial write is reported as its own error ("only N of M rows could be stored"), and a publication where nothing was stored is a plain failure. This is why the composer does not use the older "any channel returned ok" test: when `InAppChannel` refuses a row for a security reason — an exclusion it cannot enforce — while the realtime channel still emits its signal, that test would tell the administrator the notification had been sent when in fact nobody received it.

### Link field: site-relative only

The composer's link is restricted by validation to a **site-relative** path (`/...`); an absolute `https://…`, a scheme-less `example.com/x` or a `javascript:` value is rejected with an explicit error rather than being silently dropped (previously, typing `example.com/announcement` sent a link-less notification with no warning). This is narrower than the general `NotificationMessage::sanitizeUrl()` contract, which still accepts `http(s)` for programmatic producers and remains in place as the last line of defence for protocol-relative forms (`//host`, `/\host`).

The reason is authority, not URL hygiene: an operator holding only the send permission can address a `critical` notification to the superadmin group; criticals cannot be muted, and no view currently shows who sent a notification, so the message reads as coming from "the system". An external link would carry that borrowed trust straight into a phishing page.

### Assets and language

select2 is enqueued **by this view only** (`head` / `javascript` sections, via `link_tag()` / `script_tag()` so a subdirectory install resolves correctly) rather than in the global backend layout — it is the only screen with a remote-sourced multi-select, and ~100 KB on every backend page would be a poor trade. The user search term is length-capped and its LIKE metacharacters are escaped through CI4's `ESCAPE '!'` contract (escaped, not stripped: searching `john_doe` still finds that user, while `a%` no longer matches everything) — CI4 binds the value so there was never an injection, but unescaped wildcards let a caller slide the 20-row result window with patterns like `a%` / `_a%` and map the user table. All strings come from `Language/{en,tr}/Notifications.php` (`compose*` keys).

## Realtime notifications (Redis-backed SSE)

**Optional and disabled by default.** Out of the box the bell polls `feed` every 60 s (`POLL_MS`). Phase A adds an optional realtime push path over Server-Sent Events so the badge updates the instant a notification is produced. The transport is **self-contained**: there is **no external hub, no JWT, and no message broker** — it reuses the stack you already run (Redis + nginx + PHP-FPM). With `realtimeEnabled = false` (the default) the behaviour is byte-for-byte the polling model above — no stream, no `EventSource`.

### How it works

- **In-app stays the source of truth.** `NotificationBuilder`'s default channels are `['inapp', 'realtime']`. `InAppChannel` writes the durable `notifications` row **first**; `RealtimeChannel` emits **only after** that commit. Realtime is best-effort: if Redis is down or a command fails, `RealtimeChannel` returns `ChannelResult::skipped(...)` and `RealtimeSignal` degrades to a no-op — neither throws, so a missing Redis cannot break notification delivery or the audited request that triggered it. The client never trusts the pushed payload: every event is a *nudge* that triggers a reconcile against the `feed` endpoint, which reads the DB as the authoritative source.
- **Signal path.** When a notification is produced, `RealtimeChannel` bumps a Redis counter for the target channel: `INCR notif:sig:{channel}` followed by `EXPIRE` to `realtimeSignalTtl` (default 300 s), so dead-channel counters self-evict. `{channel}` is **always** the output of `Notifier::topicFor()` — one of `broadcast`, `user/{id}`, `group/{name}` — derived entirely on the server. The client never supplies a channel name, so the signal store is IDOR-safe by construction. `RealtimeSignal` is the single access point (`bump()` writes, `read()` reads) and connects to Redis lazily through the shared `RedisConnectionTrait` (0.5 s connect **and** read timeout).
- **SSE endpoint.** `RealtimeController::stream()` serves `GET backend/notifications/stream` behind the `backendGuard` filter with `role = read`, i.e. the permission `notifications.realtimecontroller.read` — the **same** permission the module already scans (it was the old token endpoint's permission; the stream introduces no new permission). The loop:
  1. Reads `user_id` and the session's group memberships, **reserves a connection slot** (see [Connection cap](#connection-cap-per-identity-role-aware) — a rejected request returns `429` right here, before any channel is derived), then derives the authorized channels via `Notifier::topicsFor($userId)` (the transport mirror of `applyRelevance()`) and calls `session()->close()` **immediately** to release the session write lock. This is critical: with the `FileHandler` session driver an open lock would block **every** other backend request from the same user for the whole life of the connection.
  2. Closes the default DB connection, so an idle worker holds no DB handle for the connection's lifetime — the loop only touches Redis.
  3. Sends the SSE headers: `Content-Type: text/event-stream`, `Cache-Control: no-cache`, `X-Accel-Buffering: no`, `Connection: keep-alive`.
  4. Roughly once a second reads the signal counters of the authorized channels with a single Redis `MGET` (never the DB — Redis reads are sub-millisecond). If a counter changed it emits `event: notification` with a minimal `data:` nudge; roughly every 15 s it emits a `: ping` heartbeat so idle proxies/browsers keep the connection open.
  5. **Caps the connection lifetime** at `realtimeStreamTtl` (default 30 s; the code clamps the value to a 120 s hard ceiling). When the TTL elapses the stream returns cleanly and the browser's `EventSource` auto-reconnects. `connection_aborted()` breaks the loop early when the client goes away.
- **Connection cap.** Because an open stream occupies a php-fpm worker for its whole lifetime, the number of *concurrent* streams per identity is capped — role-aware, enforced atomically in Redis, resolved **before** the stream opens. Over the cap the endpoint answers `429` and never enters the worker-holding loop. See [Connection cap](#connection-cap-per-identity-role-aware).
- **Disabled path.** With `realtimeEnabled = false`, `stream()` returns an empty `204` and `RealtimeChannel` returns `skipped('realtime-disabled')`, so the bell stays on the 60 s poll — behaviour identical to before Phase A.
- **IDOR gate.** Subscribed channels are derived strictly from the authenticated session (`topicsFor()` mirrors `applyRelevance()`); there is no JWT and the browser cannot request a channel it is not entitled to — the server has full control over what the connection sees.

Endpoint:

| Method | Path | Route name | Controller | Permission | Purpose |
| --- | --- | --- | --- | --- | --- |
| GET | `backend/notifications/stream` | `notifStream` | `RealtimeController::stream` | `notifications.realtimecontroller.read` | SSE stream that nudges the bell when an authorized channel's Redis signal changes. Returns `429` when the caller's connection cap is full and `204` when realtime is disabled. |

### Connection cap (per identity, role-aware)

Every open stream holds a php-fpm worker for the whole connection lifetime, so without a limit one authenticated identity can open streams in a loop and drain `pm.max_children` — a self-inflicted denial of service against the entire backend. `stream()` therefore reserves a **connection slot** before opening the stream; with no slot available the request is rejected immediately and no worker-holding loop is ever entered.

- **Registry.** Open slots live in a Redis **sorted set** per user, `notif:conn:{userId}`: member = a server-generated random connection id (`bin2hex(random_bytes(8))` — the client never influences it), score = the slot's expiry timestamp. `RedisConnectionRegistry` (behind `ConnectionRegistryInterface`, resolved as `service('connectionRegistry')`) is the single access point. A plain `INCR` / `DECR` counter is deliberately **not** used: a client that simply closes the tab never runs the decrement, so the counter leaks upward until the user is permanently locked out of their own cap. The ZSET is self-healing — expired members are pruned on every acquire — and the key additionally carries its own `EXPIRE`.
- **Atomic acquire.** Prune → count → add → expire runs as a **single Lua `EVAL`**: `ZREMRANGEBYSCORE key -inf now` → reject if `ZCARD >= cap` → `ZADD key now+ttl connId` → `EXPIRE key ttl`. Atomicity is load-bearing, not an optimization: with the check and the insert split across two PHP round-trips concurrent requests race between them (TOCTOU) — a measured burst of 30 simultaneous requests against `cap = 5` admitted **6× the cap**. The single script also closes the window where a crash between `ZADD` and `EXPIRE` would leave the key without a TTL. The prune's lower bound is `-inf`, not `0`, so a slot whose score somehow ended up `<= 0` (clock rollback, hand-written member) is still pruned instead of squatting a slot forever.
- **Cap resolution.** The effective cap comes from the caller's Shield groups: `$realtimeConnCapByGroup` (default `['superadmin' => 10]`) is intersected with those groups and the **highest** matching value wins; with no match, `$realtimeConnCapDefault` (default `6`) applies. A **negative** value means **unlimited**: with `$realtimeConnCapDefault < 0` the registry is never touched, nothing is written to Redis, and the by-group table is not consulted at all — a global escape valve, so an operator who opens it does not get the shipped `superadmin` row applying behind their back. `0` is **invalid, not unlimited**: because the property is declared `int`, CI4's `BaseConfig::initEnvValue()` casts every non-numeric `.env` value (i.e. every typo) to `0` before the code sees it, so the resolver falls back **fail-closed** to `NotificationsConfig::CONN_CAP_FALLBACK` (6) and logs a once-per-process `warning`. In the by-group table a `0` or non-numeric entry is **ignored** — the group counts as unmatched rather than being coerced into a cap — while a negative entry there wins as unlimited.
- **Over the cap.** The endpoint returns `429` with a short `text/plain` body (`lang('Notifications.realtimeConnLimit')`), a `Retry-After` header equal to the slot lifetime (the earliest moment a slot can free itself) and `X-Content-Type-Options: nosniff`. The body deliberately leaks neither the cap value nor a group name. The rejected request is cheap: only the single group query has run — the channel list is never derived.
- **Slot lifetime and release.** A slot lives for the clamped stream TTL plus `CONN_TTL_BUFFER_SECONDS` (5 s). It is returned three ways: (1) the fast path — `release()` (`ZREM`) right after the loop ends normally; (2) a `register_shutdown_function` hook, which is **mandatory** rather than belt-and-braces — under `ignore_user_abort(false)` PHP notices the client disconnect during a write and terminates the script, so the code *after* the loop never runs when the client goes away, while shutdown functions still do; (3) the key's own `EXPIRE` as the final safety net. A slot cannot leak permanently.
- **Redis unreachable → fail-closed (`429`).** This is the deliberate opposite of its sibling `RealtimeSignal`, which is best-effort and fails *open*. The reasoning is operational: without Redis the signal store also returns `0` for every channel, so the stream has nothing to deliver anyway — holding a worker open for the full TTL would burn exactly the resource the cap exists to protect. The client falls back to the 60 s poll, so there is no loss of functionality.
- **Redis connection hardening.** `RealtimeSignal` and `RedisConnectionRegistry` both build their connection through the shared `RedisConnectionTrait`, which applies a **read** timeout (0.5 s) on top of the connect timeout. Without it, a Redis that accepts the connection but never answers (packet-dropping firewall, `BGSAVE` stall) blocks on `default_socket_timeout` — typically 60 s — turning the mechanism that protects the worker pool into the thing that pins it.
- **Client behaviour (`bell.php`).** After a `429` the browser closes the `EventSource` permanently (`readyState === CLOSED`), and the bell client deliberately **does not** run its reconnect backoff chain — retrying while the cap is full would be a request storm against the very pool the cap protects. It drops to the 60 s poll and makes a **single** retry 5 minutes later, so a transient outage does not sentence the tab to polling forever. Note that `EventSource` never exposes the HTTP status code to JavaScript: the client cannot tell a `429` from the `204` returned when realtime is disabled — and the correct action is identical for both.
- **Known limit (by design).** The cap is per **identity**, not global: `N` distinct accounts can still fill the worker pool between them. Size `pm.max_children` for your admin count, not for a single admin.

### Configuration (env)

Realtime is driven by five keys on `NotificationsConfig`, plus the shared Redis connection. **Important:** CI4 env overrides key off the *lowercased short class name*, so the prefix is `notificationsconfig.` — **not** `notifications.`. Add these to your real `.env` (values below are examples):

```dotenv
notificationsconfig.realtimeEnabled = true
notificationsconfig.realtimeStreamTtl = 30
notificationsconfig.realtimeSignalTtl = 300
notificationsconfig.realtimeConnCapDefault = 6
notificationsconfig.realtimeConnCapByGroup.superadmin = 10
```

- `realtimeEnabled` (bool, default `false`) — master switch. `false` is byte-for-byte the old polling model.
- `realtimeStreamTtl` (int seconds, default `30`) — lifetime of a single SSE connection; the code clamps it to a 120 s ceiling. Shorter values recycle php-fpm workers faster; the browser reconnects transparently.
- `realtimeSignalTtl` (int seconds, default `300`) — TTL of the `notif:sig:*` Redis keys.
- `realtimeConnCapDefault` (int, default `6`) — concurrent SSE connections allowed per identity when none of the user's groups match the by-group table. The bell opens one `EventSource` **per tab**, so this is a resource threshold, not a session limit; `6` leaves a multi-tab admin unaffected while staying safe for a typical `pm.max_children`. A **negative** value (e.g. `-1`) = **unlimited**, and it also switches the cap off globally (the by-group table is then never read); `0` is **invalid, not unlimited** — a non-numeric `.env` value is cast to `0` before the code sees it, so it falls back fail-closed to `CONN_CAP_FALLBACK` (6) with a once-per-process `warning`. See [Connection cap](#connection-cap-per-identity-role-aware).
- `realtimeConnCapByGroup` (array `group => int`, default `['superadmin' => 10]`) — per-Shield-group override; the **highest** matching group wins, a `0` or non-numeric entry is ignored (the group counts as unmatched) and a negative entry wins as unlimited.
- **Caveat — `.env` cannot add a new group.** CI4's `BaseConfig::initEnvValue()` only walks keys that already exist, so `.env` can override the shipped entry (`notificationsconfig.realtimeConnCapByGroup.superadmin = 20`) but a group that is not already in the array is not picked up. Adding a new group means editing `Config\NotificationsConfig`.
- **Redis connection.** There is **no separate connection setting** — `RealtimeSignal` and `RedisConnectionRegistry` both build their connection from `Config\Cache::$redis` (host / port / password / database) through the shared `RedisConnectionTrait`. Point that at your Redis and realtime works.
- **Extension.** `ext-redis` (phpredis) is required and declared under `composer.json`'s `suggest`. Without it `RealtimeSignal` degrades to a no-op **and** the connection registry cannot reserve a slot, so the stream answers `429` (fail-closed) — either way the bell stays on polling.

### Deployment

There is **no hub to install** and **no nginx config change** required — that is the whole point of the Redis-backed design.

- **nginx.** SSE response buffering is disabled per-response by the `X-Accel-Buffering: no` header that `stream()` sends, so no `location` block, proxy tuning, or reverse-proxy is needed. The stream is served by the same PHP-FPM path as every other backend route.
- **PHP-FPM tuning.** Every open SSE connection holds a **short-lived** php-fpm worker for up to `realtimeStreamTtl` seconds (≤ 120 s ceiling). Size `pm.max_children` for your peak concurrent admin count with headroom — budget roughly one worker per open backend tab. PHP's `max_execution_time` and FPM's `request_terminate_timeout` **must** exceed `realtimeStreamTtl`, or the worker is killed mid-stream.
  - **Per-identity connection cap.** Concurrent streams per identity are capped — `$realtimeConnCapDefault` (default `6`), raised to `10` for `superadmin` — and the reservation is enforced atomically in Redis. Over the cap the endpoint answers `429` without opening a worker-holding loop and the client drops to polling, so a single account can no longer drain the pool. Budget `pm.max_children` for *(concurrent admins) × (their cap)* in the worst case, plus headroom for ordinary backend requests. **The cap is per identity, not global** — `N` distinct accounts can still fill the pool. If Redis is unreachable the endpoint fails **closed** (`429`); that is intentional, since without Redis the stream could not deliver anything anyway. Full mechanics: [Connection cap](#connection-cap-per-identity-role-aware).
- **Permission (mandatory).** The stream endpoint is guarded by `role = read`, so until `notifications.realtimecontroller.read` exists in `auth_permissions_pages` it is **fail-closed** and returns `403` (superadmin bypasses the check). Run the backend **Module Scan** (Methods / Modules → Module Scan) to insert it, grant it to the roles that should get realtime, then `php spark cache:clear`. This is the **same** permission the read endpoints already use — the stream introduces no new permission.

**Post-deploy checklist.**

1. Ensure `ext-redis` (phpredis) is installed and `Config\Cache::$redis` points at a reachable Redis.
2. Add the `notificationsconfig.*` keys to `.env` (`realtimeEnabled = true`, plus the optional `realtimeStreamTtl` / `realtimeSignalTtl` / `realtimeConnCapDefault` / `realtimeConnCapByGroup.superadmin`).
3. Confirm PHP `max_execution_time` and FPM `request_terminate_timeout` exceed `realtimeStreamTtl`, and size `pm.max_children` for your concurrent admins **times their connection cap**.
4. **Register the permission:** run the backend **Module Scan** so `notifications.realtimecontroller.read` lands in `auth_permissions_pages`, grant it to the appropriate roles, then `php spark cache:clear`. Superadmin bypasses the check; other roles without the scanned permission get a fail-closed 403 and the bell silently stays on polling.
5. **Verify:** open a backend page and, in DevTools → Network, confirm a `backend/notifications/stream` `EventSource` connection stays open (status `200`, type `eventsource`) and recycles every `realtimeStreamTtl` seconds. Then emit a test notification (`php spark notifications:test`) and confirm the bell badge updates within ~1 s instead of on the next 60 s poll. To exercise the cap, open more backend tabs than the account's cap: the surplus streams get `429` and those tabs fall back to polling.

## Extending with channels

A channel is any class implementing `Libraries\Channels\ChannelInterface`:

```php
public function send(NotificationMessage $message): ChannelResult;
```

If your channel **persists** the notification — i.e. an `ok` result means a record will still be there when the user reconnects — implement `Libraries\Channels\DurableChannelInterface` instead (it extends `ChannelInterface` and adds no methods). That marker is what `DispatchOutcome` counts when deciding whether a publication was delivered; a channel that does not declare it is treated as transient, so forgetting the marker under-reports delivery rather than over-reporting it.

Built-in channels:

| Slug | Class | Status |
| --- | --- | --- |
| `inapp` | `InAppChannel` | Implemented, **durable** (`DurableChannelInterface`) — writes the global `notifications` row and invalidates the unread caches. Refuses the write (`skipped('exclusion-unsupported')` / `skipped('exclusion-too-large')`, logged at `critical`) when an **explicit** `exceptUser()` exclusion cannot be enforced; a row whose exclusion is only *derived* from the overlap narrowing is written anyway (fail-open, logged at `warning`). |
| `realtime` | `RealtimeChannel` | Implemented (optional, **disabled by default**) — best-effort Redis SSE signal **after** the in-app row commits; returns `skipped` when `realtimeEnabled` is off or the Redis bump fails. See [Realtime notifications (Redis-backed SSE)](#realtime-notifications-redis-backed-sse). |
| `email` | `EmailChannel` | No-op stub — returns `ChannelResult::skipped('not-implemented')`. |
| `webhook` | `WebhookChannel` | No-op stub — returns `ChannelResult::skipped('not-implemented')`. |

Other modules add a channel the same way `Filters.php` discovers `$csrfExcept`: declare a `public array $notificationChannels` on your own `Config/{Name}Config.php`. `Notifier::resolveChannels()` scans every `modules/*/Config/{Module}Config.php`, and a module-declared slug overrides the base map.

### Example: adding a Slack channel

```php
// modules/MyModule/Libraries/Channels/SlackChannel.php
namespace Modules\MyModule\Libraries\Channels;

use Modules\Notifications\Libraries\Channels\ChannelInterface;
use Modules\Notifications\Libraries\Channels\ChannelResult;
use Modules\Notifications\Libraries\NotificationMessage;

final class SlackChannel implements ChannelInterface
{
    public function send(NotificationMessage $message): ChannelResult
    {
        // $message is already sanitized (title/body/url/type/severity).
        $ok = $this->postToSlack($message->title, $message->body, $message->url);

        return $ok ? ChannelResult::ok() : ChannelResult::skipped('slack-failed');
    }

    private function postToSlack(string $title, ?string $body, ?string $url): bool
    {
        // ... your HTTP call here ...
        return true;
    }
}
```

```php
// modules/MyModule/Config/MyModuleConfig.php
public array $notificationChannels = [
    'slack' => \Modules\MyModule\Libraries\Channels\SlackChannel::class,
];
```

Then opt a notification into the channel:

```php
service('notifier')
    ->notify('deploy.finished')
    ->title('Deployment finished')
    ->broadcast()
    ->via('inapp', 'slack')     // in-app row + Slack
    ->dispatch();
```

## Configuration reference

`Config\NotificationsConfig`:

| Constant / property | Value | Meaning |
| --- | --- | --- |
| `UNREAD_CACHE_TTL` | `60` | Unread-badge cache lifetime (seconds); keeps polling off the DB. |
| `FEED_LIMIT` | `10` | Rows returned by the bell / `feed` endpoint. |
| `LIST_LIMIT` | `50` | Rows returned by the full list page. |
| `POLL_MS` | `60000` | Client poll interval for the bell (milliseconds). |
| `TYPE_MAX` | `64` | Max length of `type` (column `VARCHAR(64)`). |
| `TITLE_MAX` | `255` | Max length of `title` (column `VARCHAR(255)`). |
| `URL_MAX` | `255` | Max length of `url` (column `VARCHAR(255)`). |
| `BODY_MAX` | `4000` | Max **characters** of `body`. The column is `TEXT` (65 535 bytes) and `strictOn = false`, so an unbounded body is cut silently; 4000 characters is at most 16 KB even in 4-byte UTF-8. Enforced twice from this one constant: the composer's `max_length` rule and the DTO clamp. |
| `TARGETS_MAX` | `200` | Max target directives (users + groups) in one publication. Each directive is one INSERT plus one `notif_unread_*` cache sweep, and PHP's default `max_input_vars` (1000) sets the ceiling an attacker would otherwise reach. Over it the composer refuses the publication (fail-closed) rather than trimming the audience. |
| `EXCLUDE_USERS_MAX` | `500` | Max user ids in one row's `exclude_users` list (≈ 4 KB, far below the 65 535-byte `TEXT` limit). Over it `InAppChannel` skips the write (`exclusion-too-large`, `critical`) instead of truncating — with `strictOn = false` an overflowing value is cut silently and a broken sentinel CSV fails open. Checked on the union of explicit and derived exclusions. |
| `CONN_CAP_FALLBACK` | `6` | Fail-closed cap applied when `$realtimeConnCapDefault` holds the invalid value `0`. A constant on purpose, so `.env` cannot be the source of a broken value; must match the shipped `$realtimeConnCapDefault`. |
| `$auditTargetGroup` | `'superadmin'` | Shield group that receives `ci4ms.audit` warnings. |
| `$preferenceTypes` | `['audit' => 'Notifications.prefTypeAudit']` | Types offered on the opt-out screen (exact type **or** type prefix) → language key. Also the only source `PreferenceController::save()` accepts — any other `type` is dropped. A module publishing a new type adds its slug here plus the label in `Language/{en,tr}/Notifications.php`. |
| `$preferenceChannels` | `['*']` | Channel keys offered on the opt-out screen. Only `'*'` for now: `inapp` is the sole channel that persists a row, and the realtime signal carries neither user nor type, so a per-user realtime mute could not be enforced on the read path. |
| `$channels` | `inapp`, `realtime`, `email`, `webhook` | Base slug → channel-class map (`realtime` is gated by `$realtimeEnabled`). |
| `$filters` | `backendGuard` before `backend/notifications`, `backend/notifications/*` | Route protection. |
| `$menus` | `Notifications.notifications` | Sidebar entry (icon `fas fa-bell`, `pageSort` 9). |
| `$csrfExcept` | `[]` | No CSRF opt-out; token added by the global AJAX layer. |
| `$realtimeEnabled` | `false` | Master switch for Redis-backed SSE delivery. Off = polling only (env `notificationsconfig.realtimeEnabled`). |
| `$realtimeStreamTtl` | `30` | Lifetime (seconds) of a single SSE connection; clamped to a 120 s ceiling in code (env `notificationsconfig.realtimeStreamTtl`). |
| `$realtimeSignalTtl` | `300` | TTL (seconds) of the `notif:sig:*` Redis signal keys (env `notificationsconfig.realtimeSignalTtl`). |
| `$realtimeConnCapDefault` | `6` (= `CONN_CAP_FALLBACK`) | Concurrent SSE connections allowed per identity when no group matches. **Negative** = unlimited **and** disables the cap globally, by-group table included; `0` is **invalid, not unlimited** (a non-numeric `.env` value is cast to `0`), so it falls back fail-closed to `CONN_CAP_FALLBACK` (6) with a once-per-process `warning` (env `notificationsconfig.realtimeConnCapDefault`). |
| `$realtimeConnCapByGroup` | `['superadmin' => 10]` | Per-Shield-group connection cap; highest matching group wins, a `0` or non-numeric entry is ignored (group counts as unmatched), a negative entry wins as unlimited. Env overrides existing keys only (`notificationsconfig.realtimeConnCapByGroup.superadmin`). |

The unread cache key is `notif_unread_{userId}` (see `Notifier::cacheKey()`). The in-app channel invalidates all of them on write via `cache()->deleteMatching('notif_unread_*')`, guarded by try/catch for cache handlers that do not support pattern deletion (the 60s TTL is the fallback ceiling).

## CLI commands

Both commands are HMVC-discovered spark commands.

### `notifications:test`

Emits a real notification end-to-end (exercising create → relevance → count → cache invalidation).

```bash
php spark notifications:test                              # user_id = 1
php spark notifications:test 5                            # user_id = 5
php spark notifications:test --role superadmin           # one group row
php spark notifications:test 5 --title "Hi" --body "..." --url /backend/blog
```

| Argument / option | Description |
| --- | --- |
| `user_id` (arg) | Target user id (default `1`). Ignored when `--role` is given. |
| `--role` | Send to a Shield group instead of a single user. |
| `--title` | Notification title. |
| `--body` | Notification body (optional). |
| `--url` | Click target: site-relative `/...` or `http(s)` (optional). |

### `notifications:purge`

Deletes old, read notifications. **Dry-run by default** — nothing is deleted unless `--force` is given. **No cron is installed**; schedule it yourself if you want automatic cleanup.

```bash
php spark notifications:purge                # dry-run, reports candidate count (default 90 days)
php spark notifications:purge --days 30      # dry-run with a 30-day threshold
php spark notifications:purge --days 30 --force   # actually delete
```

| Option | Description |
| --- | --- |
| `--days` | Age threshold in days (default `90`). Candidates are `created_at` older than the threshold. |
| `--force` | Actually delete. Without it the command only reports the candidate count. |

A "read" candidate is any notification older than the threshold that has **at least one** `notification_reads` row. Deletion is a single `whereIn('id', ...)` batch, and the `notification_id` foreign key cascade removes the associated read rows.

## Audit integration (automatic producer)

Apart from the [composer screen](#sending-a-notification-from-the-backend-composer), where an administrator sends deliberately, notifications are not produced from a route. The sole **automatic** trigger is the `ci4ms.audit` listener in `app/Config/Events.php`:

- It fires only for events with `severity === 'warning'`.
- It dispatches via the builder to the group in `NotificationsConfig::$auditTargetGroup` (default `superadmin`):

  ```php
  service('notifier')?->notify('audit.' . $action)
      ->severity('warning')
      ->title($message)
      ->url($url)
      ->toGroup($group)
      ->dispatch();
  ```

- The call is null-safe (`?->`) and wrapped in try/catch, so a notifier failure can never break the audited request (it is logged instead).

## Database schema

Three tables (created across the module's migrations `CreateNotificationsTable`, `AddModelBColumnsToNotifications`, `CreateNotificationReadsTable`, `AddTargetingColumnsToNotifications`, `CreateNotificationPreferencesTable`, `AddCreatedByToNotifications`).

### `notifications` — global rows

Created by `CreateNotificationsTable`; `severity`, `target_type`, `target_value`, and `channel` are added afterwards by `AddModelBColumnsToNotifications` (which also relaxes `user_id` to nullable). The table has **no foreign keys**.

| Column | Type | Notes |
| --- | --- | --- |
| `id` | `INT(11) UNSIGNED`, PK, auto-increment | |
| `user_id` | `INT(11) UNSIGNED`, NULL | Originally NOT NULL; relaxed to nullable by the Model B migration. Global rows store `NULL`. No FK. |
| `type` | `VARCHAR(64)` NOT NULL | Event type, e.g. `comment.new`. |
| `severity` | `VARCHAR(16)` NOT NULL DEFAULT `'info'` | `info` \| `warning` \| `critical` (added after `type`). |
| `target_type` | `VARCHAR(16)` NOT NULL DEFAULT `'broadcast'` | `broadcast` \| `user` \| `group` (added after `severity`). |
| `target_value` | `VARCHAR(255)` NULL DEFAULT NULL | User id or group name; `null` for broadcast (added after `target_type`). |
| `exclude_users` | `TEXT` NULL DEFAULT NULL | Sentinel-wrapped CSV of excluded user ids — always comma-wrapped (`,5,12,`), `NULL` when empty, so `NOT LIKE '%,1,%'` can never match `,12,`. Deliberately not JSON (MySQL/MariaDB portability). Added by `AddTargetingColumnsToNotifications` after `target_value`. Capped at `NotificationsConfig::EXCLUDE_USERS_MAX` (500 ids ≈ 4 KB) far below the 65 535-byte TEXT limit, because `strictOn = false` would truncate an overflowing value *silently* and a truncated sentinel CSV fails open. |
| `created_by` | `INT(11) UNSIGNED` NULL DEFAULT NULL | Id of the user who **produced** the row (not a recipient). Filled only for human-authored publications (the composer, from `auth()->id()`); `NULL` means "produced by the system" (audit listener, CLI). Written solely by `InAppChannel::buildRow()` and read by **no** query path, so it adds no JOIN cost. Deliberately carries **no foreign key**: a CASCADE would delete an account's notifications along with the account and a SET NULL would erase the trail — both defeat the point of an audit column, so an unresolvable id is kept instead. Added by `AddCreatedByToNotifications` after `exclude_users`. If the column is missing the row is still written and only the trail is lost (fail-open, `warning`) — the opposite of `exclude_users`, because a lost trail misleads nobody while an unenforced exclusion leaks the notification. |
| `title` | `VARCHAR(255)` NOT NULL | Sanitized display title. |
| `body` | `TEXT` NULL DEFAULT NULL | Sanitized body. |
| `url` | `VARCHAR(255)` NULL DEFAULT NULL | Validated click target. |
| `channel` | `VARCHAR(32)` NOT NULL DEFAULT `'inapp'` | Origin channel (added after `url`). |
| `read_at` | `DATETIME` NULL DEFAULT NULL | **Dead** under Model B — read state lives in `notification_reads` (kept from the original Model A design). |
| `created_at` | `DATETIME` NOT NULL | Send time. |

Indexes: PRIMARY(`id`); KEY(`user_id`, `read_at`) (legacy Model A index); KEY(`created_at`); INDEX `notif_target`(`target_type`, `target_value`).

### `notification_reads` — per-user read state

Created by `CreateNotificationReadsTable` (ENGINE=InnoDB). Both foreign keys cascade on `DELETE` **and** `UPDATE`.

| Column | Type | Notes |
| --- | --- | --- |
| `id` | `INT(11) UNSIGNED`, PK, auto-increment | |
| `notification_id` | `INT(11) UNSIGNED` NOT NULL | FK → `notifications`(`id`) ON DELETE CASCADE ON UPDATE CASCADE (purge cleans read rows). |
| `user_id` | `INT(11) UNSIGNED` NOT NULL | FK → `users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE (deleting a user clears their read rows). |
| `read_at` | `DATETIME` NOT NULL | When this user read the notification. |

Indexes: PRIMARY(`id`); UNIQUE(`notification_id`, `user_id`) (one read row per notification/user; backs `INSERT IGNORE`); KEY(`user_id`).

### `notification_preferences` — per-user opt-out

Created by `CreateNotificationPreferencesTable` (ENGINE=InnoDB). Because Model B rows are global, a preference cannot be applied at send time; it is applied at **read time** inside `Notifier::applyRelevance()` as a `LEFT JOIN ... WHERE p.id IS NULL` anti-join. Only mute rows (`enabled = 0`) are meaningful — an absent row means "delivered".

| Column | Type | Notes |
| --- | --- | --- |
| `id` | `INT(11) UNSIGNED`, PK, auto-increment | |
| `user_id` | `INT(11) UNSIGNED` NOT NULL | FK → `users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE (skipped, with a warning, if Shield's `users` table is absent). |
| `type` | `VARCHAR(64)` NOT NULL | Exact type (`audit.login`) **or** a prefix (`audit`, matching `audit.*` via `n.type LIKE CONCAT(p.type, '.%')`). |
| `channel` | `VARCHAR(32)` NOT NULL DEFAULT `'*'` | `'*'` = every channel; otherwise matches `notifications.channel`. |
| `enabled` | `TINYINT(1)` NOT NULL DEFAULT `1` | `0` = muted (the only value the read path looks at). |
| `created_at` / `updated_at` | `DATETIME` NULL | |

Indexes: PRIMARY(`id`); UNIQUE `notif_pref_unique`(`user_id`, `type`, `channel`); KEY `notif_pref_lookup`(`user_id`, `enabled`). The read-time join narrows on `user_id`, served by whichever of the two the optimizer picks — both are `user_id`-leading, and on a small per-user preference set MariaDB commonly prefers the shorter `notif_pref_unique`.

`severity = 'critical'` rows are excluded on the **ON** side of the join, not in `WHERE`: critical notifications are therefore unmutable, and a row that survives can never match more than zero preference rows, so `listFor()` cannot duplicate rows and `unreadCount()`'s `countAllResults()` cannot be inflated.

Users manage this from **backend/notifications/preferences** (`PreferenceController`). The screen only offers the type/channel pairs whitelisted in `NotificationsConfig::$preferenceTypes` / `$preferenceChannels`; `user_id` always comes from `auth()->id()`.

## Known limitations

- **No i18n at read time.** Notification text is frozen in the application locale at send time; there is no per-user re-translation.
- **Group-to-group overlap is not collapsed.** `toGroup('a')` + `toGroup('b')` shows the notification twice to a user who belongs to both, because collapsing it would require materializing full group membership at send time (the fan-out Model B avoids). User-to-group overlap *is* collapsed.
- **No per-channel opt-out yet.** The preference schema and the read-time join support a `channel` value, but only `'*'` is offered: the in-app channel is the only one that persists a row, and the realtime channel emits a per-topic Redis signal that carries neither user nor type, so a per-user "realtime" mute cannot be enforced.
- **Purge is coarse.** `notifications:purge` treats a notification as removable once **any** recipient has read it, so a broadcast/group row read by a single user becomes eligible. Full "read by all relevant users" accounting is a tracked TODO.
- **Dead `read_at` column.** The legacy `notifications.read_at` column (and its `(user_id, read_at)` index) is unused under Model B and can be dropped later via an additive corrective migration.
- **Email / webhook are stubs.** Only the in-app channel is implemented; `email` and `webhook` return `skipped('not-implemented')`.
- **Composer announcements cannot be muted.** The composer stamps every publication with the `announcement` type, which is **not** in `NotificationsConfig::$preferenceTypes`, so it does not appear on the opt-out screen. Left open as a product decision (should an administrator announcement be silenceable?); technically it is one entry in `$preferenceTypes` plus a label in `Language/{en,tr}/Notifications.php`.
- **`created_by` is written but never displayed.** No view reads the column yet, so "who sent this" is not visible in the UI — only in the database.
- **`critical` severity is not separately permissioned.** Anyone who may send may send a `critical` notification, and criticals cannot be muted by design.
- **The unread-cache sweep runs per row.** `InAppChannel::invalidateUnreadCaches()` fires once per stored row rather than once per dispatch, so a 200-target publication performs 200 `deleteMatching('notif_unread_*')` calls. This is the main reason `TARGETS_MAX` exists.
- **The old "any channel ok" predicate survives in three places.** `Notifier::toUser()`, `Notifier::toRole()` and `php spark notifications:test` still report success when any channel returned `ok`. Their docblocks say so; the behaviour was left unchanged for back-compat. New call sites should use `DispatchOutcome`.

<?php

namespace Modules\Notifications\Controllers;

use CodeIgniter\Shield\Config\AuthGroups;
use Modules\Notifications\Config\NotificationsConfig;
use Modules\Notifications\Libraries\Channels\ChannelResult;
use Modules\Notifications\Libraries\DispatchOutcome;
use Modules\Notifications\Libraries\NotificationMessage;
use Modules\Notifications\Libraries\Notifier;

/**
 * Notification composer — the backend controller the admin uses to manually send notifications.
 *
 * Endpoints (see Config/Routes.php):
 *   GET  backend/notifications/compose         index()   — send form (role: read)
 *   GET  backend/notifications/compose/users   users()   — AJAX: select2 user source (role: read)
 *   POST backend/notifications/compose/preview preview() — AJAX: recipient count preview (role: create)
 *   POST backend/notifications/compose         send()    — sends the broadcast (role: create)
 *
 * WHY PREVIEW IS 'create': preview() returns a count, but that count is a
 * MEMBERSHIP/EXISTENCE leak — someone who adds an identity to a `groups[]=superadmin`
 * selection and watches whether the count changes learns whether that identity is a
 * superadmin (and whether it exists at all). Since the 'read' permission is granted to
 * EVERYONE for the top bar bell, this endpoint is bucketed with the send permission:
 * whoever can't preview can't send either.
 *
 * SECURITY — this is a permission-sensitive feature, the following are the contract:
 *   1. SENDER IDENTITY: always `auth()->id()`. A `created_by` or `user_id` field posted
 *      by the client is NEVER read anywhere, so it has no effect.
 *   2. NO MASS-ASSIGNMENT: the POST array is never spread anywhere; every field is read
 *      ONE BY ONE, by name ({@see payload()}), and validation is also performed on that
 *      server-built array ({@see \CodeIgniter\Controller::validateData()}). This way a
 *      key that isn't in the form can enter neither validation nor the broadcast.
 *   3. GROUP WHITELIST: accepted group names come from the keys of Shield's `AuthGroups`
 *      configuration ({@see allowedGroups()}); a name not in the list is NOT silently
 *      swallowed, it comes back as a validation error.
 *   4. USER IDENTITIES: validated against the `users` table in a SINGLE query
 *      ({@see existingUserIds()}); rejects the request for any identity that is not
 *      ADDRESSABLE (nonexistent, soft-deleted, or banned). There is NO query inside a
 *      loop. Target count is also bounded by {@see NotificationsConfig::TARGETS_MAX}.
 *   5. SEND PATH: only the `service('notifier')` builder. There is NO direct INSERT
 *      into the `notifications` table; sanitization (strip_tags, URL validation,
 *      severity and exclude normalization) happens in ONE place, the
 *      {@see NotificationMessage} constructor, and is NOT repeated here.
 *   6. CSRF: global protection is on; these endpoints are NOT added to
 *      `NotificationsConfig::$csrfExcept` — AJAX POSTs carry the token in the body
 *      (see Views/compose.php).
 *   7. AUTHORIZATION: routes sit behind `backendGuard` + the `role` flag (fail-closed);
 *      the permission record is dropped by the Methods scan.
 *
 * The notification TYPE is NEVER TAKEN from the client: every broadcast coming out of
 * the composer is stamped with {@see TYPE}, so the preference/mute whitelist operates
 * on a fixed slug and the client can't invent its own type to bypass existing mutes.
 */
class ComposerController extends \Modules\Backend\Controllers\BaseController
{
    /**
     * Fixed type of broadcasts coming out of the composer.
     *
     * NEVER TAKEN from the client: if a free-form type were accepted, the sender could
     * dodge users' EXISTING mutes by inventing a new slug on every send, polluting the
     * type space without bound. The mute whitelist
     * ({@see NotificationsConfig::$preferenceTypes}) operates on fixed slugs.
     *
     * HONESTY NOTE: this slug is currently NOT in that whitelist, meaning composer
     * output cannot be muted from the preferences screen. This needs to be revisited as
     * a product decision (should an admin announcement be mutable?); technically all
     * that's needed is adding the slug to `$preferenceTypes` and its label to
     * Language/{en,tr}.
     */
    private const TYPE = 'announcement';

    /** Max number of users the select2 remote source returns in a single request. */
    private const USER_PICKER_LIMIT = 20;

    /**
     * Max character length of the user search term.
     *
     * The longest searched column is 255 characters (`firstname`/`surname`); a longer
     * term can never match any row, it would just bind an unnecessarily large query on
     * every keystroke.
     */
    private const USER_PICKER_TERM_MAX = 255;

    /** Target mode: all users (a single 'broadcast' row). */
    private const MODE_BROADCAST = 'broadcast';

    /** Target mode: selected users and/or groups. */
    private const MODE_TARGETED = 'targeted';

    /**
     * Severity slug => lang key of the displayed label.
     *
     * The single source of which severities EXIST is {@see NotificationMessage::SEVERITIES};
     * this array only attaches labels to them ({@see severityChoices()}).
     *
     * @var array<string, string>
     */
    private const SEVERITY_LABELS = [
        'info'     => 'Notifications.severityInfo',
        'warning'  => 'Notifications.severityWarning',
        'critical' => 'Notifications.severityCritical',
    ];

    /**
     * Renders the send form (user and group sources come from the server).
     *
     * The group list is directly the whitelist itself; the user list isn't embedded in
     * the form since it can grow large, it's fetched page by page from the {@see users()}
     * remote source.
     *
     * @return string The rendered composer view.
     */
    public function index(): string
    {
        $this->defData = array_merge($this->defData, [
            'composeGroups'     => $this->allowedGroups(),
            'composeSeverities' => self::severityChoices(),
            'composeType'       => self::TYPE,
            'composeOldUsers'   => $this->oldUserLabels(),
        ]);

        return view('Modules\Notifications\Views\compose', $this->defData);
    }

    /**
     * Select2 remote source: user list filtered by the search term.
     *
     * Accepts AJAX only and opens a SINGLE query; the result is bounded by
     * {@see USER_PICKER_LIMIT}, meaning the entire user table is never dumped to the
     * client. Only ADDRESSABLE accounts are visible ({@see Notifier::scopeAddressableUsers()}):
     * soft-deleted and banned identities never leak to the operator.
     *
     * SEARCH TERM: CI4's `like()` rule BINDS the value (no SQL injection risk), but it
     * does NOT TOUCH LIKE wildcards — `%`/`_` continue to work as patterns. On its own
     * this isn't an injection but an ENUMERATION amplifier: patterns like `a%`, `_a%`
     * can slide the {@see USER_PICKER_LIMIT} window and map out the user list. That's
     * why the term is neutralized in {@see searchTerm()} and its length is capped.
     *
     * @return \CodeIgniter\HTTP\ResponseInterface `{status, results: [{id, text}]}`.
     */
    public function users()
    {
        if (! $this->request->isAJAX()) {
            return $this->failForbidden();
        }

        if (! $this->commonModel->db->tableExists('users')) {
            return $this->respond(['status' => true, 'results' => []]);
        }

        $term    = self::searchTerm($this->request->getGet('q'));
        $builder = Notifier::scopeAddressableUsers(
            $this->commonModel->db->table('users')->select('id, username, firstname, surname')
        );

        if ($term !== '') {
            $builder->groupStart()
                ->like('username', $term)
                ->orLike('firstname', $term)
                ->orLike('surname', $term)
                ->groupEnd();
        }

        /** @var list<\stdClass> $rows */
        $rows = $builder->orderBy('username', 'ASC')->limit(self::USER_PICKER_LIMIT)->get()->getResult();

        return $this->respond([
            'status'  => true,
            'results' => array_map(static fn (\stdClass $row): array => [
                'id'   => (int) $row->id,
                'text' => self::userLabel($row),
            ], $rows),
        ]);
    }

    /**
     * Recipient count preview before sending (writes nothing).
     *
     * Counting is delegated to {@see Notifier::recipientCount()} — it's the sole owner
     * of the group query. Invalid selections do NOT produce an ERROR here, they simply
     * aren't counted: preview is not a validation endpoint, the real decision is made
     * in {@see send()}.
     *
     * @return \CodeIgniter\HTTP\ResponseInterface `{status, count}`.
     */
    public function preview()
    {
        if (! $this->request->isAJAX()) {
            return $this->failForbidden();
        }

        $userIds    = $this->postedIds('users');
        $excludeIds = $this->postedIds('exclude_users');
        $existing   = $this->existingUserIds(array_merge($userIds, $excludeIds));

        $count = $this->notifier()->recipientCount(
            $this->postedString('mode') === self::MODE_BROADCAST,
            array_intersect($userIds, $existing),
            array_intersect($this->postedNames('groups'), array_keys($this->allowedGroups())),
            array_intersect($excludeIds, $existing)
        );

        return $this->respond(['status' => true, 'count' => $count]);
    }

    /**
     * Sends the broadcast: validates, builds the target on the server, and hands off to the builder.
     *
     * Target selections are validated REGARDLESS OF MODE (even if unused in broadcast):
     * letting an invalid selection through with "it isn't read anyway" would leave a
     * silent acceptance surface in a form where the mode can change afterwards.
     *
     * The SUCCESS REPORT is based on the ACTUAL delivery outcome ({@see DispatchOutcome}):
     * "sent" is only said when ALL of the persistent rows were written; a partial
     * delivery is reported as a separate, explicit error. When a persistent write is
     * rejected for a security reason (an exclusion that couldn't be applied), telling
     * the admin "sent" would mean the excluded users were believed to be protected while
     * nobody received the notification at all.
     *
     * @return \CodeIgniter\HTTP\RedirectResponse
     */
    public function send()
    {
        $payload = $this->payload();

        if (! $this->validateData($payload, $this->validationRules(), $this->validationMessages())) {
            return $this->rejected($this->validator->getErrors());
        }

        $groups = $this->postedNames('groups');
        if (array_diff($groups, array_keys($this->allowedGroups())) !== []) {
            return $this->rejected(['groups' => self::text('Notifications.composeUnknownGroup')]);
        }

        $userIds    = $this->postedIds('users');
        $excludeIds = $this->postedIds('exclude_users');

        // Cap check BEFORE the existence check: otherwise a thousand-identity body,
        // even though it would be rejected, would first open a thousand-item IN query.
        if (count($userIds) + count($groups) > NotificationsConfig::TARGETS_MAX) {
            return $this->rejected([
                'users' => self::text('Notifications.composeTooManyTargets', [NotificationsConfig::TARGETS_MAX]),
            ]);
        }

        $requested = array_unique(array_merge($userIds, $excludeIds));

        if (count($this->existingUserIds($requested)) !== count($requested)) {
            return $this->rejected(['users' => self::text('Notifications.composeUnknownUser')]);
        }

        $broadcast = $payload['mode'] === self::MODE_BROADCAST;
        if (! $broadcast && $userIds === [] && $groups === []) {
            return $this->rejected(['mode' => self::text('Notifications.composeNoTarget')]);
        }

        $outcome = DispatchOutcome::fromResults(
            $this->publish($payload, $broadcast, $userIds, $groups, $excludeIds)
        );

        if ($outcome->storedNothing()) {
            return $this->rejected(null, self::text('Notifications.composeFailed'));
        }

        if ($outcome->isPartial()) {
            return redirect()->route('notifCompose')->with(
                'error',
                self::text('Notifications.composePartial', [$outcome->stored(), $outcome->attempted()])
            );
        }

        $count = $this->notifier()->recipientCount($broadcast, $userIds, $groups, $excludeIds);

        return redirect()->route('notifCompose')->with('message', self::text('Notifications.composeSent', [$count]));
    }

    /**
     * Shared Notifier service instance.
     *
     * @return Notifier
     */
    private function notifier(): Notifier
    {
        /** @var Notifier $notifier */
        $notifier = service('notifier');

        return $notifier;
    }

    /**
     * Reads the form's individual fields on the server, one by one, by name (mass-assignment is off).
     *
     * The returned array is the SOLE input for both validation and the broadcast; a
     * POST key not in here can never enter the system. Values are left raw: sanitizing
     * is the job of the {@see NotificationMessage} constructor and is not done in two places.
     *
     * @return array{title: string, body: string, url: string, severity: string, mode: string}
     */
    private function payload(): array
    {
        return [
            'title'    => $this->postedString('title'),
            'body'     => $this->postedString('body'),
            'url'      => $this->postedString('url'),
            'severity' => $this->postedString('severity'),
            'mode'     => $this->postedString('mode'),
        ];
    }

    /**
     * Delivers the validated broadcast through the builder (the single send path).
     *
     * The sender identity is ALWAYS `auth()->id()`. The target loops only add
     * directives to the builder — there is NO query inside them, overlap/group
     * resolution happens as a single batch inside
     * {@see \Modules\Notifications\Libraries\NotificationBuilder::dispatch()}.
     *
     * @param array{title: string, body: string, url: string, severity: string, mode: string} $payload   Validated fields.
     * @param bool                                                                            $broadcast Whether it targets all users.
     * @param list<int>                                                                       $userIds   Validated target ids.
     * @param list<string>                                                                    $groups    Group names that passed the whitelist.
     * @param list<int>                                                                       $excludeIds Validated exclusion ids.
     *
     * @return ChannelResult[] One result per (target × channel); the delivery decision
     *                         is derived from this list via {@see DispatchOutcome}.
     */
    private function publish(array $payload, bool $broadcast, array $userIds, array $groups, array $excludeIds): array
    {
        $builder = $this->notifier()->notify(self::TYPE)
            ->severity($payload['severity'])
            ->title($payload['title'])
            ->body(self::nullableText($payload['body']))
            ->url(self::nullableText($payload['url']))
            ->createdBy((int) auth()->id());

        if ($broadcast) {
            $builder->broadcast();
        } else {
            foreach ($userIds as $userId) {
                $builder->toUser($userId);
            }

            foreach ($groups as $group) {
                $builder->toGroup($group);
            }
        }

        if ($excludeIds !== []) {
            $builder->exceptUser($excludeIds);
        }

        return $builder->dispatch();
    }

    /**
     * Sends the form back with an error message while preserving the input.
     *
     * @param array<string, string>|null $errors  Field-level validation errors, or null.
     * @param string|null                $message A single non-field-level error message, or null.
     *
     * @return \CodeIgniter\HTTP\RedirectResponse
     */
    private function rejected(?array $errors, ?string $message = null)
    {
        $redirect = redirect()->route('notifCompose')->withInput();

        return $errors !== null
            ? $redirect->with('errors', $errors)
            : $redirect->with('error', (string) $message);
    }

    /**
     * Accepted Shield groups: group name => displayed title (the ONE whitelist source).
     *
     * The source is Shield's `AuthGroups` configuration; since the project populates
     * this configuration from the `auth_groups` table and caches it, the list matches
     * the setup's actual groups. Both the UI and {@see send()} validation are fed from
     * the same array: a name not in here is neither shown nor accepted.
     *
     * WHY NOT `setting('AuthGroups.groups')` (which Shield's GroupModel uses): a
     * `setting()` read opens a query against the settings STORE and THROWS AN EXCEPTION
     * if the store is unreachable — in this setup that's exactly the case in the unit
     * test context (the settings table only exists on the `default` connection). Letting
     * the whitelist be able to fatal is a heavier cost than the problem it solves: in
     * this project `Modules\Auth\Config\AuthGroups` is a static configuration class and
     * there is no `AuthGroups.groups` row in the settings store, meaning `setting()`
     * already falls back to this same array today — just with an extra query on every
     * call. If groups become overridable via settings in the future, this should be
     * moved to that source too.
     *
     * @return array<string, string> Group name => title (the name itself if the title is empty).
     */
    private function allowedGroups(): array
    {
        /** @var AuthGroups|null $config */
        $config = config('AuthGroups');

        if ($config === null) {
            return [];
        }

        $groups = [];

        foreach ($config->groups as $name => $info) {
            $name          = (string) $name;
            $title         = is_array($info) ? trim((string) ($info['title'] ?? '')) : '';
            $groups[$name] = $title !== '' ? $title : $name;
        }

        return $groups;
    }

    /**
     * Returns the ADDRESSABLE ones among the given ids, in a SINGLE query.
     *
     * Soft-deleted and banned rows are NOT counted ({@see
     * Notifier::scopeAddressableUsers()}): neither can ever see the notification, so
     * they aren't a meaningful target. That's why the send is rejected with "unknown
     * user" (fail-closed) when such an identity is selected — no further information
     * about the identity's status is given.
     *
     * @param array<int, int> $ids Ids to validate.
     *
     * @return list<int> Addressable ids (unique).
     */
    private function existingUserIds(array $ids): array
    {
        if ($ids === [] || ! $this->commonModel->db->tableExists('users')) {
            return [];
        }

        $rows = Notifier::scopeAddressableUsers($this->commonModel->db->table('users')->select('id'))
            ->whereIn('id', $ids)
            ->get()->getResultArray();

        return array_values(array_map(static fn (array $row): int => (int) $row['id'], $rows));
    }

    /**
     * Makes the raw search term safe for LIKE (length cap + wildcard neutralization).
     *
     * CI4's `like()` rule binds the value, so there's no injection risk; but `%` and `_`
     * keep working as PATTERNS. The term is escaped following the `ESCAPE '!'`
     * convention CI4 adds to the query ({@see \CodeIgniter\Database\BaseBuilder::_like()}),
     * meaning wildcard CHARACTERS ARE NOT STRIPPED, they're literalized: a search for
     * `john_doe` still finds that user, while `a%` no longer matches everything. The
     * escape character itself ('!') is also doubled, otherwise someone typing '!' in the
     * term could escape the following character.
     *
     * @param mixed $raw Raw value from the query string.
     *
     * @return string Term ready to be bound to the query ('' = no filter).
     */
    private static function searchTerm($raw): string
    {
        if (! is_scalar($raw)) {
            return '';
        }

        $term = trim((string) $raw);

        if ($term === '') {
            return '';
        }

        return str_replace(
            ['!', '%', '_'],
            ['!!', '!%', '!_'],
            mb_substr($term, 0, self::USER_PICKER_TERM_MAX, 'UTF-8')
        );
    }

    /**
     * Reads a POST field as text; any non-scalar value becomes ''.
     *
     * Array injections like `title[]=x` stop here: the value that reaches validation
     * and the broadcast is always text.
     *
     * @param string $field POST field name.
     *
     * @return string The field's text value, or ''.
     */
    private function postedString(string $field): string
    {
        $value = $this->request->getPost($field);

        return is_scalar($value) ? (string) $value : '';
    }

    /**
     * Converts a multi-select POST field into a list of user ids.
     *
     * @param string $field POST field name.
     *
     * @return list<int> Unique, positive ids.
     */
    private function postedIds(string $field): array
    {
        return self::normalizeIds($this->request->getPost($field));
    }

    /**
     * Converts a multi-select POST field into a list of group names (whitelist checking is the CALLER's job).
     *
     * @param string $field POST field name.
     *
     * @return list<string> Unique, non-empty names.
     */
    private function postedNames(string $field): array
    {
        return self::normalizeNames($this->request->getPost($field));
    }

    /**
     * Reduces a raw multi-select value to a list of user ids.
     *
     * Non-scalar items are DROPPED: a nested array like `users[][]` would silently
     * become 1 on `(int)` cast and point at an UNINTENDED user. 0/negative ids are
     * eliminated, the list is deduplicated.
     *
     * WHY THIS DOESN'T DELEGATE to {@see NotificationMessage::normalizeExcludeUsers()}
     * (a deliberate split of three similar normalizers): that method enforces the
     * STORAGE contract and differs from this one in two points —
     *   1. It does NOT drop a non-scalar item, it casts it directly to `(int)`; a `[[5]]`
     *      input becomes 1, meaning it would target the account with id 1 (the setup's
     *      first account, often superadmin). The dropping here stops exactly that hijack.
     *   2. It SORTS the list (so the same exclusion set always produces the same CSV).
     *      Order in the target list is the order the rows are written in; sorting would
     *      silently change the directive order the admin gave.
     *
     * @param mixed $raw Raw value from POST or old input.
     *
     * @return list<int> Unique, positive ids (in input order).
     */
    private static function normalizeIds($raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $ids = array_map(
            static fn ($value): int => (int) $value,
            array_filter($raw, static fn ($value): bool => is_scalar($value))
        );

        return array_values(array_unique(array_filter($ids, static fn (int $id): bool => $id > 0)));
    }

    /**
     * Reduces a raw multi-select value to a list of names (non-scalars are dropped).
     *
     * @param mixed $raw Raw value from POST or old input.
     *
     * @return list<string> Unique, non-empty names.
     */
    private static function normalizeNames($raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $names = array_map(
            static fn ($value): string => trim((string) $value),
            array_filter($raw, static fn ($value): bool => is_scalar($value))
        );

        return array_values(array_unique(array_filter($names, static fn (string $name): bool => $name !== '')));
    }

    /**
     * User labels to repopulate the select2 boxes after a failed submission.
     *
     * Since the user boxes are fed from a remote (AJAX) source, the browser doesn't
     * know the LABEL of the ids coming back via `withInput()`; an unlabeled selection
     * would disappear from the screen, and the admin would have to manually rebuild the
     * entire target list after a single validation error. That's why labels are
     * resolved on the server, in a SINGLE query — and only IF old input EXISTS, so no
     * query is opened at all on the happy path.
     *
     * @return array<int, string> User id => displayed label.
     */
    private function oldUserLabels(): array
    {
        $ids = array_values(array_unique(array_merge(
            self::normalizeIds(old('users')),
            self::normalizeIds(old('exclude_users'))
        )));

        if ($ids === [] || ! $this->commonModel->db->tableExists('users')) {
            return [];
        }

        /** @var list<\stdClass> $rows */
        $rows = Notifier::scopeAddressableUsers(
            $this->commonModel->db->table('users')->select('id, username, firstname, surname')
        )->whereIn('id', $ids)->get()->getResult();

        $labels = [];

        foreach ($rows as $row) {
            $labels[(int) $row->id] = self::userLabel($row);
        }

        return $labels;
    }

    /**
     * Reads a language line as GUARANTEED text.
     *
     * `lang()` can return a list for pluralized lines; the call sites here (field
     * label, validation message, error text) always expect a SINGLE line. Type
     * narrowing is done in one place so it doesn't need to be repeated at every call site.
     *
     * @param string            $key  '{Module}.{key}' language key.
     * @param array<int, mixed> $args Values for the {0}, {1}, ... placeholders in the line.
     *
     * @return string The language line (joined with spaces if a list comes back).
     */
    private static function text(string $key, array $args = []): string
    {
        $line = lang($key, $args);

        return is_string($line) ? $line : implode(' ', $line);
    }

    /**
     * Reduces empty text to null (the nullable field contract).
     *
     * `strip_tags` is NOT REPEATED here: the sole owner of sanitizing is the
     * {@see NotificationMessage} constructor; doing it in two places would make it
     * untraceable which layer changes the rule.
     *
     * @param string $value Raw field value.
     *
     * @return string|null The filled-in text, or null.
     */
    private static function nullableText(string $value): ?string
    {
        return trim($value) ?: null;
    }

    /**
     * The label of a user row as it will appear in the selection box.
     *
     * @param \stdClass $row Row carrying id, username, firstname, surname.
     *
     * @return string 'First Last (username)', or whichever part is filled in.
     */
    private static function userLabel(\stdClass $row): string
    {
        $name     = trim(((string) ($row->firstname ?? '')) . ' ' . ((string) ($row->surname ?? '')));
        $username = trim((string) ($row->username ?? ''));

        if ($name === '') {
            return $username;
        }

        return $username === '' ? $name : $name . ' (' . $username . ')';
    }

    /**
     * Selectable severity levels: slug => lang key of the displayed label.
     *
     * @return array<string, string> Severity slug => lang key.
     */
    private static function severityChoices(): array
    {
        // Intersection: a severity without a label NEVER shows up in the form
        // (fail-closed), and a slug that has a label but no longer exists is also
        // dropped — if the two constants diverge, the UI won't silently show a wrong option.
        return array_intersect_key(self::SEVERITY_LABELS, array_flip(NotificationMessage::SEVERITIES));
    }

    /**
     * Validation rules for the send form (following the project's regex convention).
     *
     * The URL is narrowed HERE, SPECIFICALLY for the composer, to a site-relative path:
     * any value not starting with '/' (an absolute `https://...`, a schemeless
     * `example.com/x`, `javascript:` ...) is REJECTED. The rationale is authorization: a
     * lower-level operator with `compose.create` permission can send a
     * `severity=critical` notification to the superadmin group; because critical
     * notifications can't be muted and the sender isn't shown in the UI, the message is
     * perceived as coming from "the system". An external link would carry that trust
     * straight to a phishing page. The GENERAL contract of
     * {@see NotificationMessage::sanitizeUrl()} (programmatic producers may use http(s))
     * is NOT broken; that layer remains in place as the last line of defense for
     * protocol-relative forms not filtered out here ('//host', '/\host').
     *
     * An invalid URL no longer SILENTLY drops: previously an admin who typed
     * `example.com/duyuru` would send a notification without a link and get no warning at all.
     *
     * `body` is also bounded by {@see NotificationsConfig::BODY_MAX}: since the column
     * is TEXT and `strictOn = false`, an unbounded body would be silently truncated.
     *
     * @return array<string, array<string, string>> CI4 validation rule definition.
     */
    private function validationRules(): array
    {
        return [
            'title' => [
                'label' => self::text('Notifications.composeFieldTitle'),
                'rules' => 'required|max_length[' . NotificationsConfig::TITLE_MAX . ']|regex_match[/^[^<>{}=]+$/u]',
            ],
            'body' => [
                'label' => self::text('Notifications.composeFieldBody'),
                'rules' => 'permit_empty|max_length[' . NotificationsConfig::BODY_MAX . ']|regex_match[/^[^<>{}=]*$/u]',
            ],
            'url' => [
                'label' => self::text('Notifications.composeFieldUrl'),
                // Starts with '/' and carries no control character (CR/LF/TAB/NUL).
                'rules' => 'permit_empty|max_length[' . NotificationsConfig::URL_MAX . ']|regex_match[/^\/[^\x00-\x1F\x7F]*$/]',
            ],
            'severity' => [
                'label' => self::text('Notifications.composeFieldSeverity'),
                'rules' => 'required|in_list[' . implode(',', NotificationMessage::SEVERITIES) . ']',
            ],
            'mode' => [
                'label' => self::text('Notifications.composeFieldMode'),
                'rules' => 'required|in_list[' . self::MODE_BROADCAST . ',' . self::MODE_TARGETED . ']',
            ],
        ];
    }

    /**
     * User-visible texts for validation errors.
     *
     * @return array<string, array<string, string>> Field => rule => message.
     */
    private function validationMessages(): array
    {
        $invalidText = self::text('Notifications.composeInvalidText');

        return [
            'title' => [
                'required'    => self::text('Notifications.composeTitleRequired'),
                'regex_match' => $invalidText,
            ],
            'body' => [
                'regex_match' => $invalidText,
            ],
            'url' => [
                'regex_match' => self::text('Notifications.composeInvalidUrl'),
            ],
            'severity' => [
                'required' => self::text('Notifications.composeInvalidSeverity'),
                'in_list'  => self::text('Notifications.composeInvalidSeverity'),
            ],
            'mode' => [
                'required' => self::text('Notifications.composeInvalidMode'),
                'in_list'  => self::text('Notifications.composeInvalidMode'),
            ],
        ];
    }
}

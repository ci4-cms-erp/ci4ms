<?php

namespace Modules\Notifications\Controllers;

use Modules\Notifications\Config\NotificationsConfig;
use Modules\Notifications\Libraries\Notifier;
use Modules\Notifications\Libraries\SchemaGuard;

/**
 * Notification preferences (opt-out) — backend controller.
 *
 * Endpoints (see Config/Routes.php):
 *   GET  backend/notifications/preferences  index() — mute matrix (role: read)
 *   POST backend/notifications/preferences  save()  — save the matrix (role: update)
 *
 * SECURITY: `user_id` is ALWAYS read from `auth()->id()`, NEVER taken from the client.
 * Accepted `type`/`channel` values come from the NotificationsConfig::$preferenceTypes
 * and $preferenceChannels whitelists; iteration happens over the whitelist, not the
 * POST array — this way an unknown key can never enter the row setup (mass-assignment
 * is off). The whitelist matrix is built in ONE place ({@see whitelistKeys()}) and
 * reading, writing, and the UI all use the same matrix: a row not in the matrix is
 * neither shown nor TOUCHED. Global CSRF is active; these endpoints are NOT added to $csrfExcept.
 *
 * Per Model B, preferences are applied at READ time, not SEND time
 * ({@see Notifier::applyRelevance()}); only mute rows are kept here.
 */
class PreferenceController extends \Modules\Backend\Controllers\BaseController
{
    /** The table preferences are kept in. */
    private const TABLE = 'notification_preferences';

    /**
     * FORBIDDEN characters in whitelist values: LIKE wildcards and the escape character.
     *
     * The read path compares the preference type with `n.type LIKE CONCAT(p.type, '.%')`;
     * a `type` carrying `%` or `_` would behave as a wildcard there and could mute ALL
     * of the row OWNER's notifications. Today such a value can't be written because of
     * the whitelist; this guard also guarantees that for the whitelist ITSELF (so that a
     * future configuration mistake coming via seed/import/restore doesn't silently
     * become a weapon).
     */
    private const LIKE_WILDCARDS = '%_\\';

    /**
     * Renders the mute matrix (row: type, column: channel).
     *
     * @return string The rendered preferences view.
     */
    public function index(): string
    {
        $ready              = $this->tableReady();
        [$types, $channels] = $this->safeWhitelist();
        $whitelist          = $this->whitelistKeys();

        $muted = [];
        if ($ready) {
            foreach ($this->existingRows((int) auth()->id()) as $key => $row) {
                // A row outside the matrix DOES NOT EXIST in the UI; if shown it would
                // be a dead checkbox that can't be unchecked (and that save() also never touches).
                if (isset($whitelist[$key]) && (int) $row->enabled === 0) {
                    $muted[] = $key;
                }
            }
        }

        $this->defData = array_merge($this->defData, [
            'prefReady'    => $ready,
            'prefTypes'    => $types,
            'prefChannels' => $channels,
            'prefMuted'    => $muted,
        ]);

        return view('Modules\Notifications\Views\preferences', $this->defData);
    }

    /**
     * Saves the mute matrix (the session owner's own preferences).
     *
     * A checked box = MUTE (`enabled = 0`); unchecked = default (`enabled = 1`). After
     * writing, the user's unread-badge cache is cleared, because muting is a read-time
     * filter and changes the count instantly.
     *
     * @return \CodeIgniter\HTTP\RedirectResponse
     */
    public function save()
    {
        if (! $this->tableReady()) {
            return redirect()->route('notifPrefs')->with('error', lang('Notifications.prefTableMissing'));
        }

        $userId = (int) auth()->id();

        $this->persist($userId, $this->existingRows($userId), $this->postedMutes());

        cache()->delete(Notifier::cacheKey($userId));

        return redirect()->route('notifPrefs')->with('message', lang('Notifications.prefSaved'));
    }

    /**
     * Whether the preferences table has been migrated (memoized for the request's lifetime).
     *
     * @return bool True if the table exists.
     */
    private function tableReady(): bool
    {
        return SchemaGuard::hasPreferences($this->commonModel->db);
    }

    /**
     * Reads the user's existing preference rows in a SINGLE query.
     *
     * @param int $userId Session owner's id.
     *
     * @return array<string, \stdClass> '{type}|{channel}' => row (id, type, channel, enabled).
     */
    private function existingRows(int $userId): array
    {
        $rows = $this->commonModel->lists(self::TABLE, 'id, type, channel, enabled', ['user_id' => $userId]) ?: [];

        $map = [];
        foreach ($rows as $row) {
            $map[$row->type . '|' . $row->channel] = $row;
        }

        return $map;
    }

    /**
     * The configuration's type and channel whitelists, stripped of any carrying a LIKE wildcard.
     *
     * A `warning` is logged instead of staying silent for a filtered-out value: a type
     * not shown in the UI is a configuration mistake that goes unnoticed once it disappears.
     *
     * @return array{0: array<string, string>, 1: list<string>} [type => lang key, channel list].
     */
    private function safeWhitelist(): array
    {
        /** @var NotificationsConfig $config */
        $config = config(NotificationsConfig::class);

        $types    = array_filter($config->preferenceTypes, static fn ($type): bool => self::isSafeKeyPart((string) $type), ARRAY_FILTER_USE_KEY);
        $channels = array_values(array_filter($config->preferenceChannels, static fn (string $channel): bool => self::isSafeKeyPart($channel)));

        return [$types, $channels];
    }

    /**
     * Whether a whitelist value carries a LIKE wildcard / escape character.
     *
     * @param string $value Type or channel coming from configuration.
     *
     * @return bool True if safe; false (and logged) if it carries a wildcard.
     */
    private static function isSafeKeyPart(string $value): bool
    {
        if (strpbrk($value, self::LIKE_WILDCARDS) === false) {
            return true;
        }

        log_message('warning', sprintf(
            'PreferenceController: `%s` tercih anahtarı YOK SAYILDI — LIKE joker\'i (%s) taşıyan bir değer okuma yolunda tüm bildirimleri susturabilir.',
            $value,
            self::LIKE_WILDCARDS
        ));

        return false;
    }

    /**
     * The matrix of accepted '{type}|{channel}' keys (the ONE whitelist source).
     *
     * POST reading ({@see postedMutes()}), writing ({@see persist()}), and the UI
     * ({@see index()}) are all fed from this same matrix; a key not being here means
     * that row is neither read NOR written on any path.
     *
     * @return array<string, array{0:string, 1:string}> '{type}|{channel}' => [type, channel].
     */
    private function whitelistKeys(): array
    {
        [$types, $channels] = $this->safeWhitelist();

        $keys = [];
        foreach (array_keys($types) as $type) {
            foreach ($channels as $channel) {
                $keys[$type . '|' . $channel] = [(string) $type, $channel];
            }
        }

        return $keys;
    }

    /**
     * Derives the requested mute set from POST via the whitelist.
     *
     * Iteration happens over the whitelist matrix, NOT the POST array: a type/channel
     * outside the whitelist is silently ignored and never reaches any write path.
     *
     * @return array<string, array{0:string, 1:string}> '{type}|{channel}' => [type, channel].
     */
    private function postedMutes(): array
    {
        $posted = $this->request->getPost('mute');
        if (! is_array($posted)) {
            return [];
        }

        $desired = [];

        foreach ($this->whitelistKeys() as $key => [$type, $channel]) {
            if (! empty($posted[$type][$channel])) {
                $desired[$key] = [$type, $channel];
            }
        }

        return $desired;
    }

    /**
     * Writes the requested state to disk: new mutes as a single batch, changes one by one.
     *
     * The loops are BOUNDED by the whitelist matrix (type × channel) and the user's own
     * rows — it's not N+1 over a growing dataset; also a query is only opened for a row
     * that CHANGED (0-2 queries in a typical run).
     *
     * The mute-REMOVAL loop only touches keys within the matrix: a row of the user's
     * that is OUTSIDE the whitelist (left over from an old version, or written manually
     * or via seed) never appears in the UI, so it can't be interpreted as "the user
     * unchecked the box" — if it were, an owner's never-requested mute would silently be TURNED ON.
     *
     * @param int                                      $userId   Session owner's id.
     * @param array<string, \stdClass>                 $existing Output of {@see existingRows()}.
     * @param array<string, array{0:string, 1:string}> $desired  Output of {@see postedMutes()}.
     *
     * @return void
     */
    private function persist(int $userId, array $existing, array $desired): void
    {
        $now       = date('Y-m-d H:i:s');
        $whitelist = $this->whitelistKeys();
        $inserts   = [];

        foreach ($desired as $key => [$type, $channel]) {
            if (! isset($existing[$key])) {
                $inserts[] = [
                    'user_id'    => $userId,
                    'type'       => $type,
                    'channel'    => $channel,
                    'enabled'    => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            } elseif ((int) $existing[$key]->enabled === 1) {
                $this->setEnabled((int) $existing[$key]->id, $userId, 0, $now);
            }
        }

        foreach ($existing as $key => $row) {
            if (! isset($whitelist[$key])) {
                continue;
            }

            if (! isset($desired[$key]) && (int) $row->enabled === 0) {
                $this->setEnabled((int) $row->id, $userId, 1, $now);
            }
        }

        if ($inserts !== []) {
            // INSERT IGNORE: UNIQUE(user_id,type,channel) can conflict on two concurrent
            // requests; without ignore, a DatabaseException would lose the ENTIRE batch
            // (including the non-conflicting rows). Same pattern as Notifier::markAllRead().
            $this->commonModel->db->table(self::TABLE)->ignore(true)->insertBatch($inserts);
        }
    }

    /**
     * Updates a single preference row's enabled/disabled state (ownership is in the WHERE).
     *
     * The `user_id` condition is defense in depth: `$id` only comes from rows
     * {@see existingRows()} read for the session owner, but if ownership also lives IN
     * THE QUERY ITSELF, a one-line regression added to that read can't turn this into a
     * full IDOR.
     *
     * @param int    $id      Preference row id.
     * @param int    $userId  User the row must belong to (the session owner).
     * @param int    $enabled 0 = muted, 1 = default.
     * @param string $now     Write timestamp (Y-m-d H:i:s).
     *
     * @return void
     */
    private function setEnabled(int $id, int $userId, int $enabled, string $now): void
    {
        $this->commonModel->edit(
            self::TABLE,
            ['enabled' => $enabled, 'updated_at' => $now],
            ['id' => $id, 'user_id' => $userId]
        );
    }
}

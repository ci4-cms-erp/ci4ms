<?php

namespace Tests\Modules\Notifications;

use ci4commonmodel\CommonModel;
use CodeIgniter\Config\Factories;
use CodeIgniter\Config\Services;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\Mock\MockCache;
use Modules\Notifications\Config\NotificationsConfig;
use Modules\Notifications\Controllers\PreferenceController;
use Modules\Notifications\Libraries\Notifier;
use ReflectionClass;
use Tests\Support\Notifications\FakeAuth;
use Tests\Support\Notifications\ShadowSchemaTrait;

/**
 * PreferenceController write path: ownership (IDOR) and whitelist enforcement.
 *
 * save() is the only place a user's notification preferences can be written, so
 * two properties matter more than the feature itself: the row owner must come from
 * the session and nothing else, and the accepted type/channel pairs must come from
 * the config whitelist rather than from the POST body. Both are asserted against
 * the actual stored rows — a hostile `user_id` in the payload must leave a foreign
 * row byte-for-byte unchanged, which is a claim only real rows can settle.
 *
 * The preferences table does not exist on this dev database (the FAZ 2 migration
 * has not run there), so the tests write into a connection-scoped TEMPORARY-table
 * shadow of it; see {@see ShadowSchemaTrait} for why that is non-destructive.
 * index() is not exercised here: it renders the full backend view (sidebar,
 * defData, layout) and is not reachable in isolation.
 *
 * @internal
 */
final class PreferenceControllerTest extends CIUnitTestCase
{
    use ShadowSchemaTrait;

    /** The logged-in user every save() in this class acts as. */
    private const OWNER = 900101;

    /** A foreign account the payload will try, and fail, to write to. */
    private const VICTIM = 900102;

    /** The single type registered in NotificationsConfig::$preferenceTypes. */
    private const ALLOWED_TYPE = 'audit';

    /** The single channel registered in NotificationsConfig::$preferenceChannels. */
    private const ALLOWED_CHANNEL = '*';

    /** A type the screen never offers — only a seed/import or an older release writes it. */
    private const FOREIGN_TYPE = 'settings.maintenance';

    private MockCache $cache;

    /**
     * Shadows the preferences table and logs the owner in.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Canary: never allowed to run against anything but the throwaway
        // test schema. Matches tests/Modules/Users/PermgroupPrivilegeEscalationTest.php
        // :76-80 and tests/Modules/Methods/ModuleInstallerNamespaceTest.php:84-88.
        $this->assertStringContainsString(
            'ci4ms_test',
            (string) \Config\Database::connect()->database,
            'Refusing to run: the active connection is not pointed at the test database.',
        );

        $this->cache = new MockCache();
        Services::injectMock('cache', $this->cache);
        Services::injectMock('auth', new FakeAuth(self::OWNER));

        // save() always ends in redirect()->route('notifPrefs'). Nothing loads the
        // route files in a unit-test container, and the module's own Config/Routes.php
        // is only reached through app/Config/Routes.php, so discover them here or every
        // save() dies on an unknown route name.
        service('routes')->loadRoutes();

        $this->createShadowTables();
    }

    /**
     * Drops the shadows and releases the injected services.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        $this->dropShadowTables();

        // reset() drops every shared instance (BaseService.php:366-375),
        // including 'routes' -- it is discovered exactly once per PHPUnit
        // process (vendor/codeigniter4/framework/system/Test/bootstrap.php
        // :90) and nothing besides FeatureTestTrait::call()
        // (FeatureTestTrait.php:216) reloads it afterwards. setUp() above
        // reloads it for THIS class's own use (:73), but that does not
        // help any LATER test in the same process that drives a controller
        // directly (not through FeatureTestTrait) and hits
        // redirect()->route(...) -- it would otherwise fail with
        // HTTPException "The route for '...' cannot be found" (see
        // tests/Modules/Users/PermgroupPrivilegeEscalationTest.php:106-123
        // and context.md FAZ 4 / F-2), so it is reloaded below.
        //
        // reset(true) -- not reset(false) -- is required for that reload to
        // succeed: $initAutoloader=false skips re-running
        // Autoloader::initialize() (BaseService.php:372-374), and
        // Services::autoloader(true) hands out a bare `new Autoloader()`
        // with no PSR-4 map at all once the old shared instance is gone
        // (BaseService.php:276-287). loadRoutes() re-executes every
        // Config/Routes.php, including modules/Auth/Config/Routes.php:4's
        // service('auth')->routes(...), which resolves CodeIgniter\Shield\
        // Config\Auth/AuthRoutes through that same Autoloader-backed
        // FileLocator -- with an empty map those classes are not found and
        // config('Auth') returns null, throwing "Auth::__construct():
        // Argument #1 ($config) must be of type ...Auth, null given"
        // (reproduced with reset(false) before this fix; see context.md).
        // loadRoutes() itself is a no-op on an already-discovered
        // collection (RouteCollection.php:312-314), so this costs nothing
        // in a clean process.
        Services::reset(true);
        service('routes')->loadRoutes();

        Factories::reset('config');

        parent::tearDown();
    }

    /**
     * Builds a PreferenceController with a real CommonModel and a stubbed request.
     *
     * @param array<string, mixed> $post Values the stub request reports for getPost().
     *
     * @return PreferenceController The wired-up controller.
     */
    private function makeController(array $post = []): PreferenceController
    {
        /** @var PreferenceController $controller */
        $controller              = (new ReflectionClass(PreferenceController::class))->newInstanceWithoutConstructor();
        $controller->commonModel = new CommonModel();

        $request = new class ($post) {
            /**
             * @param array<string, mixed> $post The canned POST body.
             */
            public function __construct(private array $post) {}

            /**
             * Reports a single POST value, mirroring IncomingRequest::getPost().
             *
             * @param string|null $key Field name.
             *
             * @return mixed The canned value, or null when absent.
             */
            public function getPost(?string $key = null)
            {
                return $key === null ? $this->post : ($this->post[$key] ?? null);
            }
        };

        $this->setPrivateProperty($controller, 'request', $request);

        return $controller;
    }

    /**
     * Inserts a preference row directly, bypassing the controller.
     *
     * @param int    $userId  Row owner.
     * @param int    $enabled 0 = muted, 1 = default.
     * @param string $type    Type or type prefix.
     *
     * @return void
     */
    private function seedPreference(int $userId, int $enabled = 0, string $type = self::ALLOWED_TYPE): void
    {
        $this->shadowDb->table('notification_preferences')->insert([
            'user_id'    => $userId,
            'type'       => $type,
            'channel'    => self::ALLOWED_CHANNEL,
            'enabled'    => $enabled,
            'created_at' => '2026-01-01 00:00:00',
            'updated_at' => '2026-01-01 00:00:00',
        ]);
    }

    /**
     * All stored preference rows for a user.
     *
     * @param int $userId Row owner.
     *
     * @return list<\stdClass> Rows in insertion order.
     */
    private function rowsFor(int $userId): array
    {
        return array_values($this->shadowDb->table('notification_preferences')
            ->where('user_id', $userId)
            ->orderBy('id', 'ASC')
            ->get()->getResult());
    }

    /**
     * A checked box stores a mute owned by the session user.
     *
     * @return void
     */
    public function testSaveStoresAMuteOwnedByTheSessionUser(): void
    {
        $this->makeController(['mute' => [self::ALLOWED_TYPE => [self::ALLOWED_CHANNEL => '1']]])->save();

        $rows = $this->rowsFor(self::OWNER);

        $this->assertCount(1, $rows);
        $this->assertSame(self::ALLOWED_TYPE, $rows[0]->type);
        $this->assertSame(self::ALLOWED_CHANNEL, $rows[0]->channel);
        $this->assertSame(0, (int) $rows[0]->enabled, 'a checked box means muted');
    }

    /**
     * A posted user_id is ignored: the row is still written for the session owner.
     *
     * @return void
     */
    public function testSaveIgnoresAPostedUserIdWhenChoosingTheRowOwner(): void
    {
        $this->makeController([
            'user_id' => self::VICTIM,
            'mute'    => [self::ALLOWED_TYPE => [self::ALLOWED_CHANNEL => '1']],
        ])->save();

        $this->assertCount(1, $this->rowsFor(self::OWNER), 'the session owner got the row');
        $this->assertSame([], $this->rowsFor(self::VICTIM), 'the posted id never became a row owner');
    }

    /**
     * A hostile payload cannot flip another account's existing preference.
     *
     * @return void
     */
    public function testSaveLeavesAForeignUsersExistingRowUntouched(): void
    {
        $this->seedPreference(self::VICTIM, 0);

        $this->makeController([
            'user_id' => self::VICTIM,
            'mute'    => [],
        ])->save();

        $victim = $this->rowsFor(self::VICTIM);

        $this->assertCount(1, $victim, 'the foreign row is neither removed nor duplicated');
        $this->assertSame(0, (int) $victim[0]->enabled, 'the foreign mute is still in force');
        $this->assertSame('2026-01-01 00:00:00', $victim[0]->updated_at, 'the foreign row was not even re-stamped');
    }

    /**
     * A type outside the whitelist is dropped silently, never stored.
     *
     * @return void
     */
    public function testSaveDropsTypesOutsideTheWhitelist(): void
    {
        $this->makeController(['mute' => [
            'evil'                 => [self::ALLOWED_CHANNEL => '1'],
            'settings.maintenance' => [self::ALLOWED_CHANNEL => '1'],
        ]])->save();

        $this->assertSame([], $this->rowsFor(self::OWNER), 'only whitelisted types can reach a write');
    }

    /**
     * A channel outside the whitelist is dropped silently, never stored.
     *
     * @return void
     */
    public function testSaveDropsChannelsOutsideTheWhitelist(): void
    {
        $this->makeController(['mute' => [self::ALLOWED_TYPE => ['email' => '1', 'sms' => '1']]])->save();

        $this->assertSame([], $this->rowsFor(self::OWNER), 'only whitelisted channels can reach a write');
    }

    /**
     * Clearing the box re-enables the owner's existing mute rather than deleting it.
     *
     * @return void
     */
    public function testSaveUnmutesByReenablingTheOwnersExistingRow(): void
    {
        $this->seedPreference(self::OWNER, 0);

        $this->makeController(['mute' => []])->save();

        $rows = $this->rowsFor(self::OWNER);

        $this->assertCount(1, $rows);
        $this->assertSame(1, (int) $rows[0]->enabled, 'an unchecked box restores the default');
    }

    /**
     * Unmuting touches only the owner's rows, never a foreign account's.
     *
     * @return void
     */
    public function testSaveUnmutesOnlyTheOwnersRows(): void
    {
        $this->seedPreference(self::OWNER, 0);
        $this->seedPreference(self::VICTIM, 0);

        $this->makeController(['mute' => []])->save();

        $this->assertSame(1, (int) $this->rowsFor(self::OWNER)[0]->enabled, 'the owner was unmuted');
        $this->assertSame(0, (int) $this->rowsFor(self::VICTIM)[0]->enabled, 'the foreign account stayed muted');
    }

    /**
     * Re-saving an already muted type neither duplicates nor changes the row.
     *
     * @return void
     */
    public function testSaveIsIdempotentForAnAlreadyMutedType(): void
    {
        $this->seedPreference(self::OWNER, 0);

        $this->makeController(['mute' => [self::ALLOWED_TYPE => [self::ALLOWED_CHANNEL => '1']]])->save();

        $rows = $this->rowsFor(self::OWNER);

        $this->assertCount(1, $rows, 'the unique (user, type, channel) row is reused, not re-inserted');
        $this->assertSame(0, (int) $rows[0]->enabled);
    }

    /**
     * Saving drops the owner's unread badge cache, since muting changes the count.
     *
     * @return void
     */
    public function testSaveInvalidatesTheOwnersUnreadBadgeCache(): void
    {
        cache()->save(Notifier::cacheKey(self::OWNER), 42, 60);
        cache()->save(Notifier::cacheKey(self::VICTIM), 7, 60);

        $this->makeController(['mute' => [self::ALLOWED_TYPE => [self::ALLOWED_CHANNEL => '1']]])->save();

        $this->assertNull(cache()->get(Notifier::cacheKey(self::OWNER)), 'the owner badge is recomputed on next read');
        $this->assertSame(7, cache()->get(Notifier::cacheKey(self::VICTIM)), 'no badge belonging to anyone else is disturbed');
    }

    /**
     * With the table not yet migrated, save() reports the error and writes nothing.
     *
     * @return void
     */
    public function testSaveRefusesWhenThePreferencesTableIsMissing(): void
    {
        $this->dropShadowTables();

        $response = $this->makeController(['mute' => [self::ALLOWED_TYPE => [self::ALLOWED_CHANNEL => '1']]])->save();

        $this->assertStringContainsString('preferences', $response->getHeaderLine('Location'), 'the user is sent back to the form');
    }

    /**
     * postedMutes() treats a non-array payload as "nothing requested".
     *
     * @return void
     */
    public function testPostedMutesIgnoresANonArrayPayload(): void
    {
        $invoker = $this->getPrivateMethodInvoker($this->makeController(['mute' => 'not-an-array']), 'postedMutes');

        $this->assertSame([], $invoker());
    }

    /**
     * postedMutes() walks the whitelist, so unknown keys never enter the result.
     *
     * The loop is over the config matrix rather than over the POST body, which is
     * what makes an unexpected key structurally unable to reach a write.
     *
     * @return void
     */
    public function testPostedMutesWalksTheWhitelistNotThePayload(): void
    {
        $controller = $this->makeController(['mute' => [
            self::ALLOWED_TYPE => [self::ALLOWED_CHANNEL => '1', 'email' => '1'],
            'evil'             => [self::ALLOWED_CHANNEL => '1'],
        ]]);

        $this->assertSame(
            [self::ALLOWED_TYPE . '|' . self::ALLOWED_CHANNEL => [self::ALLOWED_TYPE, self::ALLOWED_CHANNEL]],
            $this->getPrivateMethodInvoker($controller, 'postedMutes')(),
            'exactly one whitelisted pair survives; the unknown type and channel are gone'
        );
    }

    /**
     * A mute the screen never offered survives an unrelated save, still muted.
     *
     * The re-enable loop walks the user's stored rows, so a row outside the whitelist
     * used to be read as "the box was cleared" — even though no box for it was ever
     * rendered — and was silently switched back on. Nothing the form cannot express
     * may be changed by submitting the form.
     *
     * @return void
     */
    public function testSaveLeavesANonWhitelistedMuteUntouched(): void
    {
        $this->seedPreference(self::OWNER, 0, self::FOREIGN_TYPE);

        $this->makeController(['mute' => []])->save();

        $rows = $this->rowsFor(self::OWNER);

        $this->assertCount(1, $rows);
        $this->assertSame(0, (int) $rows[0]->enabled, 'a mute the form cannot express is not unmuted by the form');
        $this->assertSame('2026-01-01 00:00:00', $rows[0]->updated_at, 'the row was not even re-stamped');
    }

    /**
     * A whitelisted mute and a foreign-type mute coexist: only the first is managed.
     *
     * @return void
     */
    public function testSaveManagesWhitelistedRowsWhileIgnoringTheRest(): void
    {
        $this->seedPreference(self::OWNER, 0, self::ALLOWED_TYPE);
        $this->seedPreference(self::OWNER, 0, self::FOREIGN_TYPE);

        $this->makeController(['mute' => []])->save();

        $rows = [];
        foreach ($this->rowsFor(self::OWNER) as $row) {
            $rows[$row->type] = (int) $row->enabled;
        }

        $this->assertSame(1, $rows[self::ALLOWED_TYPE], 'the whitelisted row follows the cleared box');
        $this->assertSame(0, $rows[self::FOREIGN_TYPE], 'the row outside the matrix is left exactly as it was');
    }

    /**
     * setEnabled() scopes its UPDATE by owner, so a foreign row id changes nothing.
     *
     * Today `$id` can only come from the session user's own rows, which makes this
     * defence in depth: the ownership lives in the statement itself, so a future
     * change to how ids are gathered cannot turn this into an IDOR on its own.
     *
     * @return void
     */
    public function testSetEnabledRefusesToTouchARowOwnedBySomeoneElse(): void
    {
        $this->seedPreference(self::VICTIM, 0);
        $victimRowId = (int) $this->rowsFor(self::VICTIM)[0]->id;

        $this->getPrivateMethodInvoker($this->makeController(), 'setEnabled')(
            $victimRowId,
            self::OWNER,
            1,
            '2026-06-06 06:06:06'
        );

        $victim = $this->rowsFor(self::VICTIM);

        $this->assertSame(0, (int) $victim[0]->enabled, 'the foreign row keeps its state');
        $this->assertSame('2026-01-01 00:00:00', $victim[0]->updated_at, 'and its timestamp');
    }

    /**
     * A mute inserted between the read and the write is absorbed, not fatal.
     *
     * persist() decides what to insert from a snapshot taken earlier in the request,
     * so a second tab (or a double submit) can create the same UNIQUE (user, type,
     * channel) triple in between. Calling persist() with a stale "nothing exists yet"
     * snapshot reproduces exactly that; without INSERT IGNORE the duplicate raises a
     * DatabaseException that loses the whole batch, including the rows that had no
     * conflict at all.
     *
     * @return void
     */
    public function testPersistAbsorbsARowInsertedAfterTheSnapshotWasRead(): void
    {
        $this->seedPreference(self::OWNER, 0);

        $desired = [self::ALLOWED_TYPE . '|' . self::ALLOWED_CHANNEL => [self::ALLOWED_TYPE, self::ALLOWED_CHANNEL]];

        $this->getPrivateMethodInvoker($this->makeController(), 'persist')(self::OWNER, [], $desired);

        $rows = $this->rowsFor(self::OWNER);

        $this->assertCount(1, $rows, 'the conflicting insert is ignored rather than duplicated or fatal');
        $this->assertSame(0, (int) $rows[0]->enabled, 'the existing mute stays in force');
    }

    /**
     * Whitelist values carrying a LIKE wildcard are dropped from the matrix.
     *
     * The read path compares a stored preference with `n.type LIKE CONCAT(p.type, '.%')`,
     * so a `%` or `_` inside a type would act as a wildcard and could silence every
     * notification of that row's owner. The whitelist is the only source such a value
     * could enter from, so it is filtered at the source.
     *
     * @return void
     */
    public function testWhitelistDropsTypesAndChannelsCarryingLikeWildcards(): void
    {
        $config                     = new NotificationsConfig();
        $config->preferenceTypes    = [
            self::ALLOWED_TYPE => 'Notifications.prefTypeAudit',
            'aud_it'           => 'Notifications.prefTypeAudit',
            'aud%it'           => 'Notifications.prefTypeAudit',
            'aud\\it'          => 'Notifications.prefTypeAudit',
        ];
        $config->preferenceChannels = [self::ALLOWED_CHANNEL, 'e_mail', 'ema%l'];

        Factories::injectMock('config', NotificationsConfig::class, $config);

        $this->assertSame(
            [self::ALLOWED_TYPE . '|' . self::ALLOWED_CHANNEL => [self::ALLOWED_TYPE, self::ALLOWED_CHANNEL]],
            $this->getPrivateMethodInvoker($this->makeController(), 'whitelistKeys')(),
            'only the pair free of LIKE metacharacters survives'
        );
    }

    /**
     * A wildcard-carrying type cannot be muted even when it is posted back.
     *
     * @return void
     */
    public function testSaveRefusesAWildcardTypeEvenWhenItIsWhitelisted(): void
    {
        $config                     = new NotificationsConfig();
        $config->preferenceTypes    = ['aud_it' => 'Notifications.prefTypeAudit'];
        $config->preferenceChannels = [self::ALLOWED_CHANNEL];

        Factories::injectMock('config', NotificationsConfig::class, $config);

        $this->makeController(['mute' => ['aud_it' => [self::ALLOWED_CHANNEL => '1']]])->save();

        $this->assertSame([], $this->rowsFor(self::OWNER), 'no row is written for a type the read path could not match safely');
    }
}

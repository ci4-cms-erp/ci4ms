<?php

namespace Tests\Modules\Notifications;

use CodeIgniter\Database\Migration;
use CodeIgniter\Test\CIUnitTestCase;
use Modules\Notifications\Database\Migrations\AddTargetingColumnsToNotifications;
use Modules\Notifications\Database\Migrations\CreateNotificationPreferencesTable;
use ReflectionClass;
use Tests\Support\Notifications\FakeSchema;
use Tests\Support\Notifications\SpyForge;

/**
 * FAZ 2 migration guards: replaying `up()` on an already-migrated schema is a no-op.
 *
 * Both migrations are additive and self-guarding, because a module folder can be
 * dropped into a site whose database is at any point on the timeline. The property
 * under test is therefore not "the DDL is correct" but "the DDL is issued exactly
 * once", which is decided entirely by `fieldExists()` / `tableExists()`.
 *
 * These tests never run DDL. Executing the real migration would permanently alter
 * the shared dev database, and a TEMPORARY-table shadow cannot substitute for it
 * either, since Forge would still resolve the permanent table by name. Instead each
 * migration is built without its constructor and its protected `$db` / `$forge` are
 * replaced with {@see FakeSchema} and {@see SpyForge}: the guard branch runs for
 * real while the DDL is only recorded. The replay tests flip the fake schema
 * between the two `up()` calls, reproducing exactly what a second `spark migrate`
 * would see.
 *
 * @internal
 */
final class NotificationMigrationGuardTest extends CIUnitTestCase
{
    /** Table the targeting column is added to. */
    private const NOTIFICATIONS = 'notifications';

    /** The additive FAZ 2 column. */
    private const COLUMN = 'exclude_users';

    /** Table the preferences migration creates. */
    private const PREFERENCES = 'notification_preferences';

    /** Shield table whose presence decides the foreign key branch. */
    private const USERS = 'users';

    /**
     * Includes both migration files by path, the way the migration runner does.
     *
     * Migration file names carry the timestamp prefix CodeIgniter sorts the timeline
     * on ('2026-07-25-000000_AddTargetingColumns...'), so they do not match their
     * class names and are not PSR-4 resolvable. MigrationRunner include_once's them
     * by path before instantiating, and so must this test.
     *
     * @return void
     */
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $path = ROOTPATH . 'modules/Notifications/Database/Migrations/';

        require_once $path . '2026-07-25-000000_AddTargetingColumnsToNotifications.php';
        require_once $path . '2026-07-25-000100_CreateNotificationPreferencesTable.php';
    }

    /**
     * Builds a migration with a fake schema and a recording forge.
     *
     * @param class-string<Migration> $migration Migration class to instantiate.
     * @param FakeSchema              $schema    Schema answers the guard will read.
     *
     * @return array{0: Migration, 1: SpyForge} The migration and its forge spy.
     */
    private function make(string $migration, FakeSchema $schema): array
    {
        /** @var Migration $instance */
        $instance = (new ReflectionClass($migration))->newInstanceWithoutConstructor();
        $forge    = new SpyForge();

        $this->setPrivateProperty($instance, 'db', $schema);
        $this->setPrivateProperty($instance, 'forge', $forge);

        return [$instance, $forge];
    }

    /**
     * The column is added when the schema does not have it yet.
     *
     * @return void
     */
    public function testTargetingMigrationAddsTheColumnWhenItIsMissing(): void
    {
        [$migration, $forge] = $this->make(AddTargetingColumnsToNotifications::class, new FakeSchema([self::NOTIFICATIONS]));

        $migration->up();

        $this->assertCount(1, $forge->addedColumns);
        $this->assertSame(self::NOTIFICATIONS, $forge->addedColumns[0]['table']);

        $definition = $forge->addedColumns[0]['fields'][self::COLUMN];
        $this->assertIsArray($definition);

        $this->assertSame('TEXT', $definition['type'], 'the sentinel CSV needs an unbounded text column');
        $this->assertTrue($definition['null'], 'no exclusions must be storable as NULL');
        $this->assertNull($definition['default'], 'the default is NULL, not an empty string');
    }

    /**
     * The column is not re-added when the schema already has it.
     *
     * @return void
     */
    public function testTargetingMigrationSkipsWhenTheColumnAlreadyExists(): void
    {
        [$migration, $forge] = $this->make(
            AddTargetingColumnsToNotifications::class,
            new FakeSchema([self::NOTIFICATIONS], [self::NOTIFICATIONS . '.' . self::COLUMN])
        );

        $migration->up();

        $this->assertSame([], $forge->addedColumns, 'an already-migrated schema is left alone');
    }

    /**
     * Replaying the targeting migration issues the ALTER exactly once.
     *
     * The fake schema starts without the column and gains it after the first run,
     * which is what a second `spark migrate` against the same database would see.
     *
     * @return void
     */
    public function testTargetingMigrationIsIdempotentAcrossRepeatedRuns(): void
    {
        $schema              = new FakeSchema([self::NOTIFICATIONS]);
        [$migration, $forge] = $this->make(AddTargetingColumnsToNotifications::class, $schema);

        $migration->up();
        $schema->addField(self::COLUMN, self::NOTIFICATIONS);
        $migration->up();

        $this->assertCount(1, $forge->addedColumns, 'the second run adds nothing');
    }

    /**
     * The preferences table is created with its unique and lookup keys.
     *
     * @return void
     */
    public function testPreferencesMigrationCreatesTheTableWithBothKeys(): void
    {
        [$migration, $forge] = $this->make(CreateNotificationPreferencesTable::class, new FakeSchema([self::USERS]));

        $migration->up();

        $this->assertCount(1, $forge->createdTables);
        $this->assertSame(self::PREFERENCES, $forge->createdTables[0]['table']);
        $this->assertSame(['ENGINE' => 'InnoDB'], $forge->createdTables[0]['attributes']);

        $keys = [];
        foreach ($forge->addedKeys as $key) {
            $keys[$key['name']] = $key;
        }

        $this->assertArrayHasKey('notif_pref_unique', $keys, 'the triple must be unique');
        $this->assertTrue($keys['notif_pref_unique']['unique']);
        $this->assertSame(['user_id', 'type', 'channel'], $keys['notif_pref_unique']['key']);

        $this->assertArrayHasKey('notif_pref_lookup', $keys, 'the read path narrows on user_id first');
        $this->assertFalse($keys['notif_pref_lookup']['unique']);
        $this->assertSame(['user_id', 'enabled'], $keys['notif_pref_lookup']['key']);
    }

    /**
     * The users foreign key is added, with CASCADE, when Shield is already migrated.
     *
     * @return void
     */
    public function testPreferencesMigrationAddsTheUsersForeignKeyWhenShieldIsPresent(): void
    {
        [$migration, $forge] = $this->make(CreateNotificationPreferencesTable::class, new FakeSchema([self::USERS]));

        $migration->up();

        $this->assertCount(1, $forge->addedForeignKeys);
        $this->assertSame('user_id', $forge->addedForeignKeys[0]['field']);
        $this->assertSame(self::USERS, $forge->addedForeignKeys[0]['table']);
        $this->assertSame('CASCADE', $forge->addedForeignKeys[0]['onDelete'], 'deleting a user removes their preferences');
    }

    /**
     * The table is still created, without the foreign key, when Shield is absent.
     *
     * @return void
     */
    public function testPreferencesMigrationSkipsTheForeignKeyWhenUsersTableIsMissing(): void
    {
        [$migration, $forge] = $this->make(CreateNotificationPreferencesTable::class, new FakeSchema());

        $migration->up();

        $this->assertSame([], $forge->addedForeignKeys, 'a standalone module must not reference a table that does not exist');
        $this->assertCount(1, $forge->createdTables, 'the table itself is still created');
    }

    /**
     * The table is not recreated when it already exists.
     *
     * @return void
     */
    public function testPreferencesMigrationSkipsWhenTheTableAlreadyExists(): void
    {
        [$migration, $forge] = $this->make(
            CreateNotificationPreferencesTable::class,
            new FakeSchema([self::USERS, self::PREFERENCES])
        );

        $migration->up();

        $this->assertSame([], $forge->createdTables, 'an existing table is left alone');
        $this->assertSame([], $forge->addedFields, 'the guard returns before any field is staged');
    }

    /**
     * Replaying the preferences migration issues the CREATE exactly once.
     *
     * @return void
     */
    public function testPreferencesMigrationIsIdempotentAcrossRepeatedRuns(): void
    {
        $schema              = new FakeSchema([self::USERS]);
        [$migration, $forge] = $this->make(CreateNotificationPreferencesTable::class, $schema);

        $migration->up();
        $schema->addTable(self::PREFERENCES);
        $migration->up();

        $this->assertCount(1, $forge->createdTables, 'the second run creates nothing');
    }

    /**
     * Neither down() drops anything: both objects carry data, so rollback is manual.
     *
     * @return void
     */
    public function testNeitherDownDropsDataCarryingObjects(): void
    {
        [$targeting, $targetingForge]     = $this->make(AddTargetingColumnsToNotifications::class, new FakeSchema());
        [$preferences, $preferencesForge] = $this->make(CreateNotificationPreferencesTable::class, new FakeSchema());

        $targeting->down();
        $preferences->down();

        $this->assertSame([], $targetingForge->createdTables);
        $this->assertSame([], $targetingForge->addedColumns);
        $this->assertSame([], $preferencesForge->createdTables);
        $this->assertSame([], $preferencesForge->addedFields);
    }
}

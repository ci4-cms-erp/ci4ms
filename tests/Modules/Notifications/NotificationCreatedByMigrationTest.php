<?php

namespace Tests\Modules\Notifications;

use CodeIgniter\Database\Migration;
use CodeIgniter\Test\CIUnitTestCase;
use Modules\Notifications\Database\Migrations\AddCreatedByToNotifications;
use ReflectionClass;
use Tests\Support\Notifications\FakeSchema;
use Tests\Support\Notifications\SpyForge;

/**
 * FAZ 3 migration guard: the `created_by` ALTER is issued once, and only when it fits.
 *
 * A module folder can be dropped into a site whose database sits anywhere on the
 * timeline, so this migration has two decisions to make before touching anything:
 * whether the column is already there, and whether the column it wants to be placed
 * AFTER exists at all. The second one is not cosmetic — MySQL rejects the whole
 * statement with "Unknown column in 'notifications'" when `AFTER` names a column the
 * table does not have, which would take down a migration run on any site that has not
 * applied the FAZ 2 targeting migration yet.
 *
 * No DDL is executed here. Running the real migration would permanently alter the
 * shared dev database, and a TEMPORARY-table shadow cannot stand in for it either
 * (Forge resolves the permanent table by name). Instead the migration is built without
 * its constructor and its protected `$db` / `$forge` are replaced with {@see FakeSchema}
 * and {@see SpyForge}: the guard branch runs for real while the DDL is only recorded.
 * Same shape as {@see NotificationMigrationGuardTest}, which covers the FAZ 2 pair.
 *
 * @internal
 */
final class NotificationCreatedByMigrationTest extends CIUnitTestCase
{
    /** Table the accountability column is added to. */
    private const NOTIFICATIONS = 'notifications';

    /** The additive FAZ 3 column. */
    private const COLUMN = 'created_by';

    /** The FAZ 2 column the new one is positioned after, when it is present. */
    private const ANCHOR = 'exclude_users';

    /**
     * Includes the migration file by path, the way the migration runner does.
     *
     * Migration file names carry the timestamp prefix CodeIgniter sorts the timeline
     * on ('2026-07-25-000200_AddCreatedBy...'), so they do not match their class names
     * and are not PSR-4 resolvable.
     *
     * @return void
     */
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        require_once ROOTPATH . 'modules/Notifications/Database/Migrations/2026-07-25-000200_AddCreatedByToNotifications.php';
    }

    /**
     * Builds the migration with a fake schema and a recording forge.
     *
     * @param FakeSchema $schema Schema answers the guard will read.
     *
     * @return array{0: Migration, 1: SpyForge} The migration and its forge spy.
     */
    private function make(FakeSchema $schema): array
    {
        /** @var Migration $instance */
        $instance = (new ReflectionClass(AddCreatedByToNotifications::class))->newInstanceWithoutConstructor();
        $forge    = new SpyForge();

        $this->setPrivateProperty($instance, 'db', $schema);
        $this->setPrivateProperty($instance, 'forge', $forge);

        return [$instance, $forge];
    }

    /**
     * The column is added, nullable and unsigned, when the schema does not have it.
     *
     * @return void
     */
    public function testMigrationAddsTheColumnWhenItIsMissing(): void
    {
        [$migration, $forge] = $this->make(new FakeSchema([self::NOTIFICATIONS]));

        $migration->up();

        $this->assertCount(1, $forge->addedColumns);
        $this->assertSame(self::NOTIFICATIONS, $forge->addedColumns[0]['table']);

        $definition = $forge->addedColumns[0]['fields'][self::COLUMN];
        $this->assertIsArray($definition);

        $this->assertSame('INT', $definition['type']);
        $this->assertTrue($definition['unsigned'], 'user ids are unsigned, like the column it references');
        $this->assertTrue($definition['null'], 'an event-produced notification has no creator');
        $this->assertNull($definition['default'], 'the default is NULL, meaning "the system produced it"');
    }

    /**
     * The column is positioned after the targeting column when that column exists.
     *
     * @return void
     */
    public function testMigrationPlacesTheColumnAfterTheAnchorWhenItExists(): void
    {
        [$migration, $forge] = $this->make(
            new FakeSchema([self::NOTIFICATIONS], [self::NOTIFICATIONS . '.' . self::ANCHOR])
        );

        $migration->up();

        $this->assertSame(self::ANCHOR, $forge->addedColumns[0]['fields'][self::COLUMN]['after']);
    }

    /**
     * The AFTER clause is omitted when the anchor column is not there yet.
     *
     * Naming a missing column in AFTER makes MySQL reject the entire ALTER, so a site
     * that has not run the FAZ 2 migration would fail the whole migration run instead
     * of just placing the column at the end of the table.
     *
     * @return void
     */
    public function testMigrationOmitsTheAfterClauseWhenTheAnchorIsAbsent(): void
    {
        [$migration, $forge] = $this->make(new FakeSchema([self::NOTIFICATIONS]));

        $migration->up();

        $this->assertArrayNotHasKey(
            'after',
            $forge->addedColumns[0]['fields'][self::COLUMN],
            'column ORDER is cosmetic; a failed migration run is not'
        );
    }

    /**
     * The column is not re-added when the schema already has it.
     *
     * @return void
     */
    public function testMigrationSkipsWhenTheColumnAlreadyExists(): void
    {
        [$migration, $forge] = $this->make(
            new FakeSchema([self::NOTIFICATIONS], [self::NOTIFICATIONS . '.' . self::COLUMN])
        );

        $migration->up();

        $this->assertSame([], $forge->addedColumns, 'an already-migrated schema is left alone');
    }

    /**
     * Replaying the migration issues the ALTER exactly once.
     *
     * The fake schema starts without the column and gains it after the first run,
     * which is what a second `spark migrate` against the same database would see.
     *
     * @return void
     */
    public function testMigrationIsIdempotentAcrossRepeatedRuns(): void
    {
        $schema              = new FakeSchema([self::NOTIFICATIONS]);
        [$migration, $forge] = $this->make($schema);

        $migration->up();
        $schema->addField(self::COLUMN, self::NOTIFICATIONS);
        $migration->up();

        $this->assertCount(1, $forge->addedColumns, 'the second run adds nothing');
    }

    /**
     * No foreign key is created: an audit trace must outlive the account it names.
     *
     * A CASCADE would delete a removed user's notifications along with them and a SET
     * NULL would erase the trace itself; both defeat the reason the column exists.
     *
     * @return void
     */
    public function testMigrationAddsNoForeignKeyToTheAccountTable(): void
    {
        [$migration, $forge] = $this->make(new FakeSchema([self::NOTIFICATIONS, 'users']));

        $migration->up();

        $this->assertSame([], $forge->addedForeignKeys);
        $this->assertSame([], $forge->createdTables, 'this migration only ever alters');
    }

    /**
     * down() drops nothing: the column carries audit data, so rollback is manual.
     *
     * @return void
     */
    public function testDownDropsNothing(): void
    {
        [$migration, $forge] = $this->make(new FakeSchema([self::NOTIFICATIONS], [self::NOTIFICATIONS . '.' . self::COLUMN]));

        $migration->down();

        $this->assertSame([], $forge->addedColumns);
        $this->assertSame([], $forge->createdTables);
        $this->assertSame([], $forge->addedFields);
    }
}

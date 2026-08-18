<?php

declare(strict_types=1);

namespace Tests\Modules\Backup;

use Closure;
use CodeIgniter\Events\Events;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Services;
use Modules\Backup\Libraries\DbBackup;
use Tests\Support\Notifications\CapturingChannel;
use Tests\Support\Notifications\FakeDispatchNotifier;

/**
 * Regression suite for F2 (DbBackup::restore() missing protection for RBAC
 * tables) and for the aggregate `ci4ms.audit` event that reports the skips.
 *
 * Before the fix, any otherwise-allowed statement prefix (INSERT/UPDATE/...)
 * in a restored SQL file could write directly to auth_groups,
 * auth_groups_users, auth_permissions_users, auth_permissions_pages or
 * auth_groups_permissions -- e.g. an INSERT into auth_groups_users granting
 * the attacker's own user_id membership in the "superadmin" group. This
 * locks targetsProtectedRbacTable(): the statement must be skipped
 * (non-fatally, matching the existing "Unrecognized SQL skipped" pattern),
 * not applied, and the rest of the file must still be processed.
 *
 * DbBackup instantiates its own CommonModel (project style: no constructor
 * DI), so makeDbBackup() reflection-points that CommonModel's connection at
 * this test's transaction connection ($this->db). Every write DbBackup then
 * makes participates in this test's transaction and rolls back in tearDown();
 * a bare `new DbBackup()` would resolve its own connection independently and
 * would not share this test's transaction state.
 *
 * The skip itself is silent apart from a log line, so DbBackup.php:232-239
 * raises ONE aggregate `ci4ms.audit` event per restore carrying the number of
 * statements dropped. "One per restore" is a hard constraint, not a style
 * choice: app/Config/Events.php turns every such event into a stored superadmin
 * notification, so a per-statement event would let a crafted dump carrying
 * thousands of RBAC statements flood that table. The aggregate test below is
 * the regression lock for it.
 *
 * A FakeDispatchNotifier is injected for the same reason
 * PermgroupUserPermsWipeAuditTest injects one: the application's own listener
 * fires too, and the real Notifier builds `new CommonModel()` against the
 * autocommitting 'default' group. Measured, not assumed — before the injection
 * a single run of this file left one extra `ci4ms_notifications` row behind
 * (809 -> 810) that no transaction could roll back.
 *
 * @internal
 */
final class DbBackupRbacProtectionTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $refresh = false;

    protected $migrateOnce = true;

    protected $namespace = null;

    private string $tmpSqlPath;

    /**
     * Every `ci4ms.audit` payload seen during the test, in trigger order.
     *
     * @var list<array<string, mixed>>
     */
    private array $auditEvents = [];

    /**
     * The exact closure registered on `ci4ms.audit`, kept so tearDown() can pass
     * it to Events::removeListener(), which matches listeners by identity.
     * Events::removeAllListeners('ci4ms.audit') is deliberately NOT used: it
     * would drop the application's own listener for the rest of the process.
     */
    private ?Closure $auditListener = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertStringContainsString(
            'ci4ms_test',
            (string) \Config\Database::connect()->database,
            'Refusing to run: the active connection is not pointed at the test database.',
        );

        Services::injectMock('notifier', new FakeDispatchNotifier(new CapturingChannel()));

        $this->auditEvents   = [];
        $this->auditListener = function (array $event): void {
            $this->auditEvents[] = $event;
        };
        Events::on('ci4ms.audit', $this->auditListener);

        $this->db->transStart();
    }

    protected function tearDown(): void
    {
        $this->db->transRollback();

        if ($this->auditListener !== null) {
            Events::removeListener('ci4ms.audit', $this->auditListener);
            $this->auditListener = null;
        }

        Services::resetSingle('notifier');

        if (isset($this->tmpSqlPath) && is_file($this->tmpSqlPath)) {
            @unlink($this->tmpSqlPath);
        }

        parent::tearDown();
    }

    /**
     * Every captured `ci4ms.audit` payload whose action is the restore-skip one.
     *
     * Filtered rather than taken wholesale so an unrelated producer firing on
     * the same channel can never be miscounted as a second skip event.
     *
     * @return list<array<string, mixed>>
     */
    private function skipEvents(): array
    {
        return array_values(array_filter(
            $this->auditEvents,
            static fn (array $event): bool => ($event['action'] ?? null) === 'rbac.backupRestoreStatementsSkipped',
        ));
    }

    /**
     * DbBackup takes no injected connection (project convention:
     * `new CommonModel()` in the constructor), so bind its CommonModel to this
     * test's transaction connection via reflection instead of passing it in.
     */
    private function makeDbBackup(): DbBackup
    {
        $dbBackup = new DbBackup();
        $ref = new \ReflectionProperty(DbBackup::class, 'commonModel');
        $ref->setAccessible(true);
        $ref->getValue($dbBackup)->db = $this->db;

        return $dbBackup;
    }

    /**
     * A restore file containing one statement that targets a protected RBAC
     * table (auth_groups_users) alongside one benign statement must: (1) not
     * fail the whole restore, (2) skip only the RBAC statement, (3) still
     * apply the benign statement.
     */
    public function testRestoreSkipsStatementTargetingProtectedRbacTableWithoutFailingWholeFile(): void
    {
        $maliciousUserId = 999999999;
        $benignFilename   = 'f2_dbbackup_regression_' . bin2hex(random_bytes(4)) . '.zip';

        $beforeCount = (int) $this->db->table('auth_groups_users')->countAllResults();

        $sql = "INSERT INTO ci4ms_auth_groups_users (user_id, `group`, created_at) VALUES ({$maliciousUserId}, 'superadmin', '2026-01-01 00:00:00');\n"
            . "INSERT INTO ci4ms_db_backups (filename, file_size, created_at) VALUES ('{$benignFilename}', 123, NOW());\n";

        $this->tmpSqlPath = $this->writeTmpSqlFile($sql);

        $dbBackup = $this->makeDbBackup();
        $result   = $dbBackup->restore($this->tmpSqlPath);

        $this->assertTrue($result, 'restore() must not fail the whole file just because one statement targets a protected RBAC table.');

        $afterCount = (int) $this->db->table('auth_groups_users')->countAllResults();
        $this->assertSame($beforeCount, $afterCount, 'A statement writing to a protected RBAC table must be skipped, not applied (row count must not change).');

        $injected = (int) $this->db->table('auth_groups_users')->where('user_id', $maliciousUserId)->countAllResults();
        $this->assertSame(0, $injected, 'The malicious INSERT targeting auth_groups_users must never have been executed.');

        $benign = (int) $this->db->table('db_backups')->where('filename', $benignFilename)->countAllResults();
        $this->assertSame(1, $benign, 'A benign statement in the same file must still be applied -- proves only the RBAC statement was skipped, not the whole file.');
    }

    /**
     * Permanent regression guard (must stay green): a restore file made up
     * entirely of benign, non-RBAC statements must still restore
     * successfully and apply every statement -- the RBAC guard must not
     * become a false positive that blocks ordinary restores.
     */
    public function testRestoreStillAppliesAllStatementsWhenNoneTargetRbacTables(): void
    {
        $filenameA = 'f2_dbbackup_benign_a_' . bin2hex(random_bytes(4)) . '.zip';
        $filenameB = 'f2_dbbackup_benign_b_' . bin2hex(random_bytes(4)) . '.zip';

        $sql = "INSERT INTO ci4ms_db_backups (filename, file_size, created_at) VALUES ('{$filenameA}', 111, NOW());\n"
            . "INSERT INTO ci4ms_db_backups (filename, file_size, created_at) VALUES ('{$filenameB}', 222, NOW());\n";

        $this->tmpSqlPath = $this->writeTmpSqlFile($sql);

        $dbBackup = $this->makeDbBackup();
        $result   = $dbBackup->restore($this->tmpSqlPath);

        $this->assertTrue($result);

        $this->assertSame(1, (int) $this->db->table('db_backups')->where('filename', $filenameA)->countAllResults());
        $this->assertSame(1, (int) $this->db->table('db_backups')->where('filename', $filenameB)->countAllResults());
    }

    /**
     * A restore that dropped an RBAC statement must announce it exactly once,
     * with the payload shape app/Config/Events.php consumes
     * (DbBackup.php:232-239).
     */
    public function testRestoreRaisesOneWarningAuditEventWhenAnRbacStatementWasSkipped(): void
    {
        $sql = "INSERT INTO ci4ms_auth_groups_users (user_id, `group`, created_at) VALUES (999999998, 'superadmin', '2026-01-01 00:00:00');\n"
            . "INSERT INTO ci4ms_db_backups (filename, file_size, created_at) VALUES ('g3_audit_" . bin2hex(random_bytes(4)) . ".zip', 123, NOW());\n";

        $this->tmpSqlPath = $this->writeTmpSqlFile($sql);

        $this->assertTrue($this->makeDbBackup()->restore($this->tmpSqlPath));

        $events = $this->skipEvents();
        $this->assertCount(1, $events, 'A restore that skipped an RBAC statement must raise exactly one audit event.');
        $this->assertSame(
            'warning',
            $events[0]['severity'] ?? null,
            "severity must stay 'warning': this system's own backups dump the auth_* tables, so skips are routine, and "
                . "'critical' bypasses per-user mute preferences in Notifier::applyPreferences().",
        );
        $this->assertSame(base_url('backend/backup'), $events[0]['url'] ?? null);
        $this->assertSame(lang('Backup.auditRestoreRbacStatementsSkipped', [1]), $events[0]['message'] ?? null);
    }

    /**
     * The DoS guard: N skipped statements produce ONE event carrying N, never N
     * events. app/Config/Events.php stores a superadmin notification per event,
     * so a crafted dump full of RBAC statements must not translate into a
     * notification per statement.
     */
    public function testRestoreAggregatesEverySkippedRbacStatementIntoASingleEvent(): void
    {
        $benignFilename = 'g3_aggregate_' . bin2hex(random_bytes(4)) . '.zip';

        $sql = "INSERT INTO ci4ms_auth_groups_users (user_id, `group`, created_at) VALUES (999999997, 'superadmin', '2026-01-01 00:00:00');\n"
            . "INSERT INTO ci4ms_auth_permissions_users (user_id, permission) VALUES (999999997, 'users.delete');\n"
            . "UPDATE ci4ms_auth_groups SET description = 'hijacked' WHERE `group` = 'superadmin';\n"
            . "INSERT INTO ci4ms_db_backups (filename, file_size, created_at) VALUES ('{$benignFilename}', 321, NOW());\n";

        $this->tmpSqlPath = $this->writeTmpSqlFile($sql);

        $this->assertTrue($this->makeDbBackup()->restore($this->tmpSqlPath));

        $this->assertSame(
            1,
            (int) $this->db->table('db_backups')->where('filename', $benignFilename)->countAllResults(),
            'Setup invariant: the benign statement must still have been applied, otherwise the file was aborted and '
                . 'the event count below would be measuring the wrong thing.',
        );

        $events = $this->skipEvents();
        $this->assertCount(
            1,
            $events,
            'Three skipped RBAC statements must produce ONE aggregate event, not one event per statement.',
        );
        $this->assertSame(
            lang('Backup.auditRestoreRbacStatementsSkipped', [3]),
            $events[0]['message'] ?? null,
            'The single event must report how many statements were dropped, not a fixed number.',
        );
        $this->assertStringContainsString(
            '3',
            (string) ($events[0]['message'] ?? ''),
            'The count must survive into the rendered message — a lang key that drops its placeholder would make the '
                . 'assertion above pass while telling the superadmin nothing.',
        );
    }

    /**
     * Negative case: an ordinary restore must stay silent. Without this, an
     * event fired unconditionally would still satisfy the tests above.
     */
    public function testRestoreRaisesNoAuditEventWhenNothingTargetedAnRbacTable(): void
    {
        $sql = "INSERT INTO ci4ms_db_backups (filename, file_size, created_at) VALUES ('g3_silent_" . bin2hex(random_bytes(4)) . ".zip', 111, NOW());\n";

        $this->tmpSqlPath = $this->writeTmpSqlFile($sql);

        $this->assertTrue($this->makeDbBackup()->restore($this->tmpSqlPath));

        $this->assertSame([], $this->skipEvents(), 'A restore with no skipped RBAC statement must raise no audit event.');
    }

    /**
     * A restore aborted by the dangerous-SQL guard returns from inside the loop
     * (DbBackup.php:187), before the aggregate event is reached — even though
     * statements were already skipped. Locks that the event is a report of a
     * COMPLETED restore, not a running tally.
     */
    public function testAbortedRestoreRaisesNoAuditEventEvenAfterSkippingRbacStatements(): void
    {
        $sql = "INSERT INTO ci4ms_auth_groups_users (user_id, `group`, created_at) VALUES (999999996, 'superadmin', '2026-01-01 00:00:00');\n"
            . "SELECT LOAD_FILE('/etc/passwd');\n";

        $this->tmpSqlPath = $this->writeTmpSqlFile($sql);

        $this->assertFalse(
            $this->makeDbBackup()->restore($this->tmpSqlPath),
            'Setup invariant: the dangerous-SQL guard must abort this file.',
        );

        $this->assertSame([], $this->skipEvents(), 'An aborted restore must not report a partial skip count.');
    }

    private function writeTmpSqlFile(string $sql): string
    {
        $dir = WRITEPATH . 'uploads/';
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $path = $dir . 'dbbackup_rbac_test_' . bin2hex(random_bytes(4)) . '.sql';
        file_put_contents($path, $sql);

        return $path;
    }
}

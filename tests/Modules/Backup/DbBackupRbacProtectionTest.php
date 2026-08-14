<?php

declare(strict_types=1);

namespace Tests\Modules\Backup;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Modules\Backup\Libraries\DbBackup;

/**
 * Regression suite for F2 (DbBackup::restore() missing protection for RBAC
 * tables).
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
 * DbBackup is constructed with $this->db explicitly (DatabaseTestTrait's own
 * connection) so every write it performs participates in this test's own
 * transaction and rolls back in tearDown() -- a bare `new DbBackup()` would
 * resolve its own connection independently and, while still pointed at
 * ci4ms_test under ENVIRONMENT === 'testing', would not share this test's
 * transaction state.
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

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertStringContainsString(
            'ci4ms_test',
            (string) \Config\Database::connect()->database,
            'Refusing to run: the active connection is not pointed at the test database.',
        );

        $this->db->transStart();
    }

    protected function tearDown(): void
    {
        $this->db->transRollback();

        if (isset($this->tmpSqlPath) && is_file($this->tmpSqlPath)) {
            @unlink($this->tmpSqlPath);
        }

        parent::tearDown();
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

        $dbBackup = new DbBackup($this->db);
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

        $dbBackup = new DbBackup($this->db);
        $result   = $dbBackup->restore($this->tmpSqlPath);

        $this->assertTrue($result);

        $this->assertSame(1, (int) $this->db->table('db_backups')->where('filename', $filenameA)->countAllResults());
        $this->assertSame(1, (int) $this->db->table('db_backups')->where('filename', $filenameB)->countAllResults());
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

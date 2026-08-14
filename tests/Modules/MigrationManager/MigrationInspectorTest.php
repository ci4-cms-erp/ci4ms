<?php

declare(strict_types=1);

namespace Tests\Modules\MigrationManager;

use CodeIgniter\Database\MigrationRunner;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Modules\MigrationManager\Libraries\MigrationInspector;

/**
 * `MigrationInspector::buildStatusReport()` / `getHistoryByNamespace()` testleri.
 *
 * Görev 18'in geçici probe testinin (bkz. context.md "FAZ 5 — [ci4ms-build-lead]
 * Görev 18 tamamlandı") kalıcı hali. Rapor DAİMA diskten (App + `modules/*` +
 * sabit vendor listesi) inşa edilir; DB yalnızca zenginleştirme amaçlı okunur,
 * bu yüzden `ci4ms_test`'teki bilinen 8 "yetim" namespace kaydı ile bozuk
 * `\Modules\Notifications` anahtarı raporda hiç görünmemeli (bkz. context.md
 * "DOĞRULANMIŞ BULGU-B").
 *
 * @internal
 */
final class MigrationInspectorTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $refresh = false;

    protected $migrateOnce = true;

    protected $namespace = null;

    /**
     * `ci4ms_test`'te dosya sisteminde karşılığı olmayan, bilinen "yetim"
     * migration namespace kayıtları (context.md "DOĞRULANMIŞ BULGU-B").
     *
     * @var list<string>
     */
    private const ORPHAN_NAMESPACES = [
        'Modules\ActivityLog',
        'Modules\Crm',
        'Modules\Cronjobs',
        'Modules\DocumentManager',
        'Modules\EmailManagement',
        'Modules\FormBuilder',
        'Modules\LicenseServer',
        'Modules\TaskManager',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertStringContainsString(
            'ci4ms_test',
            (string) \Config\Database::connect()->database,
            'Refusing to run: the active connection is not pointed at the test database.',
        );
    }

    /**
     * buildStatusReport() hatasız çalışır ve App + Modules\* + iki vendor
     * namespace'ini (readOnly=true) içerir.
     */
    public function testBuildStatusReportRunsCleanAgainstDiscoveredNamespaces(): void
    {
        $report = (new MigrationInspector())->buildStatusReport();

        $this->assertNotSame([], $report);

        $namespaces = array_column($report, 'namespace');
        $this->assertContains('App', $namespaces);
        $this->assertContains('Modules\MigrationManager', $namespaces);

        foreach (MigrationInspector::VENDOR_NAMESPACES as $vendorNamespace) {
            $this->assertContains($vendorNamespace, $namespaces, "Vendor namespace {$vendorNamespace} must be listed.");
        }

        $rowsByNamespace = [];
        foreach ($report as $row) {
            $rowsByNamespace[$row['namespace']] = $row;
        }

        foreach (MigrationInspector::VENDOR_NAMESPACES as $vendorNamespace) {
            $this->assertTrue($rowsByNamespace[$vendorNamespace]['readOnly'], "{$vendorNamespace} must be readOnly=true.");
        }
        $this->assertFalse($rowsByNamespace['App']['readOnly'], 'App must be readOnly=false.');
        $this->assertFalse($rowsByNamespace['Modules\MigrationManager']['readOnly'], 'Modules\MigrationManager must be readOnly=false.');
    }

    /**
     * DB'de kayıtlı ama diskte karşılığı olmayan 8 yetim namespace VE bozuk
     * `\Modules\Notifications` anahtarı rapora hiç girmez; `Modules\Notifications`
     * doğru normalize edilip tam olarak bir kez görünür.
     */
    public function testOrphanAndMalformedNamespacesAreExcludedFromTheReport(): void
    {
        $report     = (new MigrationInspector())->buildStatusReport();
        $namespaces = array_column($report, 'namespace');

        foreach (self::ORPHAN_NAMESPACES as $orphan) {
            $this->assertNotContains($orphan, $namespaces, "Orphan namespace {$orphan} must not appear in the disk-derived report.");
        }

        $this->assertNotContains('\Modules\Notifications', $namespaces, 'The malformed leading-backslash key must never appear as a separate entry.');

        $matches = array_keys($namespaces, 'Modules\Notifications', true);
        $this->assertCount(1, $matches, 'Modules\Notifications must appear exactly once (normalized, backslash stripped).');
    }

    /**
     * N+1 guard: getHistoryByNamespace() çağrıldığında `MigrationRunner::getHistory()`
     * tam olarak 1 kez çağrılır (namespace başına ayrı sorgu YOK).
     */
    public function testGetHistoryByNamespaceCallsGetHistoryExactlyOnce(): void
    {
        $mock = $this->createMock(MigrationRunner::class);
        $mock->method('setNamespace')->willReturnSelf();
        $mock->expects($this->once())->method('getHistory')->willReturn([]);

        $inspector = new MigrationInspector($mock);
        $inspector->getHistoryByNamespace();
    }

    /**
     * N+1 guard: buildStatusReport() de (getHistoryByNamespace() üzerinden)
     * `getHistory()`'yi tam olarak 1 kez çağırır — keşfedilen namespace sayısı
     * kaç olursa olsun.
     */
    public function testBuildStatusReportCallsGetHistoryExactlyOnce(): void
    {
        $mock = $this->createMock(MigrationRunner::class);
        $mock->method('setNamespace')->willReturnSelf();
        $mock->expects($this->once())->method('getHistory')->willReturn([]);
        $mock->method('findNamespaceMigrations')->willReturn([]);

        $inspector = new MigrationInspector($mock);
        $inspector->buildStatusReport();
    }
}

<?php

declare(strict_types=1);

namespace Tests\Modules\MigrationManager;

use ci4commonmodel\CommonModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Modules\Methods\Libraries\ModuleScanner;

/**
 * `ModuleScanner::runScan()` sonrası MigrationManager için `auth_permissions_pages`
 * kayıtlarının varlığını, sayısını ve `typeOfPermissions`'ının route'taki
 * `role` ile eşleştiğini doğrular.
 *
 * TASARIM KARARI (kalıcılık — bilinçli, KENDİ kararım, gerekçeli):
 * Bu testin `runScan()` ile eklediği satırlar tearDown()'da SİLİNMEZ / bir
 * transaction'la geri ALINMAZ; `ci4ms_test`'te KALICI olarak bırakılır.
 * Sebep: `Modules\Auth\Filters\Ci4MsAuthFilter.php:48-57` fail-CLOSED
 * çalışır — `auth_permissions_pages`'te route'a karşılık gelen satır YOKSA
 * superadmin dahi 403 alır (context.md "Katman 2" ve "Erişim: ÜÇ KATMAN").
 * `MigrationManagerViewRenderTest` gibi GERÇEK HTTP routing üzerinden geçen
 * (backendGuard'ı bypass ETMEYEN) testler bu satırlar olmadan doğru kodla
 * bile 403 alır. `ci4ms_test` zaten $refresh=false, kalıcı bir temel
 * veritabanıdır (proje genelinde emsal: kalıcı superadmin grubu, kalıcı
 * migration geçmişi, `Modules\MigrationManager`'ın kendi migration tablosu)
 * — bu 4 satır da aynı kategoridedir: bir kere kurulup KALMASI gereken
 * yapısal veri, her testte yeniden yaratılıp silinen geçici bir fixture
 * değil. `runScan()` idempotent olduğu için (aşağıdaki ikinci test)
 * bu dosyanın tekrar tekrar çalıştırılması güvenlidir ve tekilleşmeyi
 * bozmaz. `MigrationManagerViewRenderTest::setUp()` de kendi çalışma
 * sırasından bağımsız olmak için AYRICA `runScan()` çağırır (idempotent,
 * zararsız).
 *
 * Açık yan etki notu: `ModuleScanner::runScan()` yalnızca MigrationManager'ı
 * değil, TÜM route tablosunu tarar; henüz `modules` tablosuna kayıtlı
 * olmayan başka modüller varsa onlar için de satır ekleyebilir/migration
 * tetikleyebilir ve fiziksel klasörü silinmiş modüllerin kayıtlarını
 * temizleyebilir (`ModuleScanner.php:183-195`). Bu, sınıfın MEVCUT,
 * kendi başına test edilmemiş genel davranışıdır — bu dosyanın kapsamı
 * dışıdır, yalnızca MigrationManager'a özgü satırlar doğrulanır.
 *
 * @internal
 */
final class MigrationManagerPermissionScanTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $refresh = false;

    protected $migrateOnce = true;

    protected $namespace = null;

    /**
     * `ModuleScanner`'ın route handler'ından türettiği className formatı:
     * `str_replace('\\','-', preg_replace('/::.*$/','',$handler))`
     * (bkz. `ModuleScanner.php:53`, kod izi ile doğrulandı — tahmin değil).
     */
    private const EXPECTED_CLASS_NAME = '-Modules-MigrationManager-Controllers-MigrationManager';

    private CommonModel $commonModel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertStringContainsString(
            'ci4ms_test',
            (string) \Config\Database::connect()->database,
            'Refusing to run: the active connection is not pointed at the test database.',
        );

        $this->commonModel = new CommonModel('tests');
    }

    /**
     * `runScan()` sonrası MigrationManager için tam olarak 4 satır bulunur
     * (index/runMigration/runSeed/history — GET+POST `history` aynı handler'a
     * sahip olduğundan ModuleScanner'ın handler-bazlı dedup'ı ile tek satıra
     * düşer) ve her birinin `typeOfPermissions`'ı route'taki `role` ile
     * eşleşir.
     */
    public function testRunScanRegistersExactlyFourPermissionRowsWithMatchingRoles(): void
    {
        (new ModuleScanner())->runScan();

        $rows = $this->commonModel->lists(
            'auth_permissions_pages',
            '*',
            ['className' => self::EXPECTED_CLASS_NAME],
        );

        $this->assertCount(4, $rows, 'Expected exactly 4 auth_permissions_pages rows for MigrationManager.');

        $byMethod = [];
        foreach ($rows as $row) {
            $byMethod[$row->methodName] = $row;
        }

        $this->assertSame(['history', 'index', 'runMigration', 'runSeed'], $this->sortedMethodNames($rows));

        $this->assertRoleMatches($byMethod['index'], true, false);
        $this->assertRoleMatches($byMethod['runMigration'], false, true);
        $this->assertRoleMatches($byMethod['runSeed'], false, true);
        $this->assertRoleMatches($byMethod['history'], true, false);
    }

    /**
     * `runScan()`'ı ikinci kez çağırmak MigrationManager için duplicate
     * satır YARATMAZ (idempotent).
     */
    public function testRunScanIsIdempotentAndDoesNotDuplicateRows(): void
    {
        (new ModuleScanner())->runScan();
        $before = $this->commonModel->count('auth_permissions_pages', ['className' => self::EXPECTED_CLASS_NAME]);

        (new ModuleScanner())->runScan();
        $after = $this->commonModel->count('auth_permissions_pages', ['className' => self::EXPECTED_CLASS_NAME]);

        $this->assertSame(4, $before);
        $this->assertSame($before, $after, 'A second runScan() call must not create duplicate MigrationManager permission rows.');
    }

    /**
     * @param list<object> $rows
     *
     * @return list<string>
     */
    private function sortedMethodNames(array $rows): array
    {
        $names = array_map(static fn (object $row): string => (string) $row->methodName, $rows);
        sort($names);

        return $names;
    }

    private function assertRoleMatches(object $row, bool $expectRead, bool $expectUpdate): void
    {
        $perms = json_decode((string) $row->typeOfPermissions, true);
        $this->assertIsArray($perms, "typeOfPermissions for {$row->methodName} did not decode to an array.");
        $this->assertSame($expectRead, (bool) $perms['read_r'], "read_r mismatch for {$row->methodName}.");
        $this->assertSame($expectUpdate, (bool) $perms['update_r'], "update_r mismatch for {$row->methodName}.");
        $this->assertFalse((bool) $perms['create_r'], "create_r must be false for {$row->methodName}.");
        $this->assertFalse((bool) $perms['delete_r'], "delete_r must be false for {$row->methodName}.");
    }
}

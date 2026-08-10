<?php

declare(strict_types=1);

namespace Modules\MigrationManager\Libraries;

use CodeIgniter\Database\MigrationRunner;
use Config\Database;
use Config\Services;

/**
 * Diskteki migration namespace'lerini keşfeder ve DB geçmişiyle birleştirip
 * bir durum raporu üretir.
 *
 * Kaynak DAİMA diskir, `findMigrations()` DEĞİL: `findMigrations()`
 * (`vendor/codeigniter4/framework/system/Database/MigrationRunner.php:445`)
 * namespace=null'da composer'ın kayıtlı TÜM PSR-4 önek listesini
 * (`service('autoloader')->getNamespace()`, 50-100+ paket) gezer. Bunun
 * yerine bilinen namespace listesi üzerinde döngüyle `findNamespaceMigrations()`
 * (public, `:469`) çağrılır. Bu tasarım aynı zamanda DB'de kayıtlı ama
 * dosyası artık var olmayan "yetim" namespace'lerin (ör. `Modules\Crm`)
 * rapora hiç girmemesini garanti eder — ayrı bir gizleme filtresi YOKTUR,
 * çünkü rapor listesi diskten inşa edilir, DB yalnızca zenginleştirme
 * amaçlı okunur.
 *
 * `getHistory()` (`:697-714`) namespace parametresi ALMAZ; çağrıldığı
 * `MigrationRunner` örneğinin `$namespace` iç durumuna bakar. Namespace
 * başına ayrı `getHistory()` çağırmak N+1'e düşer (`system/Commands/
 * Database/MigrateStatus.php`'nin yaptığı hata, `:114-115`) — bunun yerine
 * `setNamespace(null)` ile bu filtre kapatılır, TÜM geçmiş TEK sorguda
 * çekilir ve PHP tarafında namespace'e göre gruplanır.
 */
class MigrationInspector
{
    /**
     * Diskte karşılığı olmayan, salt-okunur listelenen vendor migration
     * namespace'leri.
     *
     * @var list<string>
     */
    public const VENDOR_NAMESPACES = ['CodeIgniter\Settings', 'CodeIgniter\Shield'];

    private MigrationRunner $runner;

    /**
     * @param MigrationRunner|null $runner Test edilebilirlik için enjekte
     *                                     edilebilir (ör. çağrı sayısını
     *                                     sayan bir spy/mock). `null` ise
     *                                     `Services::migrations(null, null, false)`
     *                                     (non-shared) kullanılır — paylaşımlı
     *                                     örneğe `setNamespace()` state
     *                                     sızıntısı bırakılmaz
     *                                     (`Settings.php:238`'in düştüğü
     *                                     hataya düşülmez).
     */
    public function __construct(?MigrationRunner $runner = null)
    {
        $this->runner = $runner ?? Services::migrations(null, null, false);
    }

    /**
     * Diskte bulunan migration namespace'lerini keşfeder.
     *
     * `App` + her `modules/*` dizini (`Modules\{Basename}` önekiyle,
     * `app/Config/Autoload.php:106` konvansiyonuyla birebir) + sabit
     * `VENDOR_NAMESPACES` sırasıyla döner. Vendor namespace'leri
     * `readOnly=true` bayrağıyla işaretlenir; geri kalanı `false`.
     *
     * @return list<array{namespace: string, readOnly: bool}>
     */
    public function getDiscoveredNamespaces(): array
    {
        $namespaces = [['namespace' => 'App', 'readOnly' => false]];

        foreach (glob(ROOTPATH . 'modules/*', GLOB_ONLYDIR) ?: [] as $moduleDir) {
            $namespaces[] = ['namespace' => 'Modules\\' . basename($moduleDir), 'readOnly' => false];
        }

        foreach (self::VENDOR_NAMESPACES as $vendorNamespace) {
            $namespaces[] = ['namespace' => $vendorNamespace, 'readOnly' => true];
        }

        return $namespaces;
    }

    /**
     * Tüm migration geçmişini TEK `getHistory()` çağrısıyla çeker ve
     * normalize edilmiş namespace'e göre gruplar.
     *
     * `\Modules\Notifications` (baştaki ters bölü ile) DB'de bozuk, ayrı bir
     * anahtar altında kayıtlı (kök neden: `Autoloader.php:254` PSR-4
     * eşlemesinde `trim($prefix,'\\')` uygulanırken `MigrationRunner`'ın
     * kendisi geçmiş satırına namespace'i olduğu gibi yazıyor, `:656`, ve
     * `getHistory()` bunu literal string eşleştiriyor, `:709-710`) —
     * `ltrim($namespace, '\\')` ile normalize edilip doğru gruba katılır.
     *
     * @return array<string, list<object>> Anahtar normalize edilmiş
     *                                     namespace, değer `getHistory()`
     *                                     satırları (`version`, `class`,
     *                                     `namespace`, `time`, `batch`
     *                                     alanlı `stdClass` nesneleri).
     */
    public function getHistoryByNamespace(): array
    {
        $this->runner->setNamespace(null);

        $grouped = [];

        foreach ($this->runner->getHistory($this->historyGroup()) as $row) {
            $grouped[ltrim($row->namespace, '\\')][] = $row;
        }

        return $grouped;
    }

    /**
     * `getDiscoveredNamespaces()` ile `getHistoryByNamespace()`'i birleştirip
     * her namespace için bir durum satırı üretir.
     *
     * DB'de kayıtlı ama diskte karşılığı olmayan "yetim" namespace'ler
     * (`getDiscoveredNamespaces()`'de yer almadıkları için) rapora hiç
     * girmez.
     *
     * @return list<array{
     *     namespace: string,
     *     readOnly: bool,
     *     total_count: int,
     *     applied_count: int,
     *     last_batch: int|null,
     *     last_date: string|null
     * }>
     */
    public function buildStatusReport(): array
    {
        $historyByNamespace = $this->getHistoryByNamespace();
        $report             = [];

        foreach ($this->getDiscoveredNamespaces() as $entry) {
            $history               = $historyByNamespace[$entry['namespace']] ?? [];
            [$lastBatch, $lastDate] = $this->lastRun($history);

            $report[] = [
                'namespace'     => $entry['namespace'],
                'readOnly'      => $entry['readOnly'],
                'total_count'   => count($this->runner->findNamespaceMigrations($entry['namespace'])),
                'applied_count' => count($history),
                'last_batch'    => $lastBatch,
                'last_date'     => $lastDate,
            ];
        }

        return $report;
    }

    /**
     * Bir namespace'in geçmiş satırları arasından en son çalıştırılan
     * batch'i ve tarihini belirler.
     *
     * @param list<object> $history `getHistoryByNamespace()`'ten tek bir grup.
     *
     * @return array{0: int|null, 1: string|null} `[lastBatch, lastDate]`;
     *                                             `$history` boşsa `[null, null]`.
     */
    private function lastRun(array $history): array
    {
        if ($history === []) {
            return [null, null];
        }

        $lastBatch = null;
        $lastTime  = null;

        foreach ($history as $row) {
            $batch = (int) $row->batch;
            $time  = (int) $row->time;

            if ($lastBatch === null || $batch > $lastBatch || ($batch === $lastBatch && $time > $lastTime)) {
                $lastBatch = $batch;
                $lastTime  = $time;
            }
        }

        return [$lastBatch, date('Y-m-d H:i:s', $lastTime)];
    }

    /**
     * `getHistory()`'ye geçirilecek `group` değerini çalışma zamanı DB
     * yapılandırmasından çözer (literal `'default'` yerine).
     *
     * SAPMA GEREKÇESİ: bu proje `ENVIRONMENT==='testing'` altında (PHPUnit,
     * `vendor/codeigniter4/framework/system/Test/bootstrap.php:29`)
     * `Config\Database::$defaultGroup`'u `'tests'`e çeviriyor
     * (`app/Config/Database.php:200-201`) ve migration geçmişi satırlarının
     * `group` sütunu tam olarak bu değerle yazılıyor
     * (`MigrationRunner::__construct()` `:154`, `addHistory()` `:655`).
     * Kanıt (bu oturumda ölçüldü, salt-okunur `SELECT`):
     * canlı `ci4ms_migrations` → `group='default'` (51 satır);
     * `ci4ms_test.ci4ms_migrations` → `group='tests'` (40 satır).
     * Literal `getHistory('default')` PHPUnit altında HER ZAMAN 0 satır
     * dönerdi (yanlış-negatif, hata fırlatmaz ama rapor içeriği hatalı
     * olurdu). Bu metot `MigrationRunner::__construct()`'ın grubu
     * hesapladığı AYNI kaynağı (`config(Database::class)->defaultGroup`)
     * kullanarak doğru filtreyi garanti eder.
     */
    private function historyGroup(): string
    {
        return (string) config(Database::class)->defaultGroup;
    }
}

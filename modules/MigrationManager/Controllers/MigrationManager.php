<?php

declare(strict_types=1);

namespace Modules\MigrationManager\Controllers;

use CodeIgniter\Events\Events;
use Config\Database;
use Config\Services;
use Modules\Backend\Controllers\BaseController;
use Modules\MigrationManager\Libraries\MigrationInspector;
use Modules\MigrationManager\Libraries\RunLock;
use Modules\MigrationManager\Libraries\SeederScanner;

/**
 * Migration ve seed'lerin superadmin tarafından web'den keşfedilip
 * çalıştırılmasını sağlayan backend controller'ı.
 *
 * Erişim üç katmanlıdır: route filtresi (`backendGuard`,
 * `Modules\MigrationManager\Config\MigrationManagerConfig::$filters`),
 * `Modules\Methods` permission kaydı (fail-closed) ve bu sınıfın her public
 * metodunun ilk satırındaki `auth()->user()->inGroup('superadmin')`
 * kontrolü. Üçüncü katman filtre yapılandırması bozulsa bile tutar; hiçbir
 * metottan çıkarılmaz (`Methods::update()` izin matrisini gevşetebildiği
 * için filtre/permission katmanlarına tek başına güvenilmez).
 */
class MigrationManager extends BaseController
{
    /**
     * Migration/seed durum panosunu render eder.
     *
     * Diskteki namespace'lerin DB geçmişiyle birleştirilmiş durumunu
     * (`MigrationInspector::buildStatusReport()`), web'den çalıştırılabilir
     * seed listesini (`SeederScanner::discover()` — bugün henüz hiçbir
     * seeder `WebRunnableSeeder` implement etmediği için BOŞ dizi döner, bu
     * beklenen davranıştır) ve son 50 çalıştırma kaydını (`Backup.php:14`
     * deseni, `ORDER BY migration_runs.id DESC LIMIT 50`) view'a geçirir.
     * `users` tablosuyla `LEFT JOIN` yapılıp her satıra `run_by_username`
     * eklenir (BUG-2: ham `run_by` kullanıcı ID'si yerine kullanıcı adı);
     * kullanıcı silinmişse (`run_by` FK `ON DELETE SET NULL`) veya kayıt
     * sistem/otomasyon kaynaklıysa `run_by_username` `null` döner, view
     * bunu `MigrationManager.deletedUser` ile karşılar. `ORDER BY` join
     * sonrası `migration_runs.id` olarak NİTELENİR — `id` iki tabloda da
     * var, nitelenmezse MySQL/MariaDB'de belirsiz-kolon hatası riski taşır.
     *
     * @return \CodeIgniter\HTTP\ResponseInterface
     */
    public function index()
    {
        if (!auth()->user()->inGroup('superadmin')) {
            return $this->failForbidden();
        }

        $this->defData['statusReport'] = (new MigrationInspector())->buildStatusReport();
        $this->defData['seeders']      = (new SeederScanner())->discover();
        $this->defData['recentRuns']   = $this->commonModel->lists('migration_runs', 'migration_runs.*, users.username AS run_by_username', [], 'migration_runs.id DESC', 50, 0, [], [], [
            ['table' => 'users', 'cond' => 'users.id = migration_runs.run_by', 'type' => 'left'],
        ]);

        return view('Modules\MigrationManager\Views\list', $this->defData);
    }

    /**
     * POST edilen namespace'i sunucu-taraflı allowlist'e karşı doğrulayıp
     * `MigrationRunner::latest()` ile çalıştırır ve sonucu `migration_runs`'a
     * kaydeder.
     *
     * POST edilen değer hiçbir yola/namespace'e birleştirilmez
     * (concatenate edilmez); yalnızca `MigrationInspector::
     * getDiscoveredNamespaces()`'ten türetilen allowlist'e
     * `in_array(..., true)` ile eşleştirilir. Vendor namespace'ler
     * (`readOnly=true`, ör. `CodeIgniter\Settings`) bu listede YOKTUR,
     * dolayısıyla POST edilseler bile reddedilir — istemci tarafındaki
     * `disabled` attribute'una güvenilmez.
     *
     * @return \CodeIgniter\HTTP\ResponseInterface
     */
    public function runMigration()
    {
        if (!$this->request->isAJAX()) {
            return $this->failForbidden();
        }

        if (!auth()->user()->inGroup('superadmin')) {
            return $this->failForbidden();
        }

        $namespace = trim((string) $this->request->getPost('namespace'));

        if ($namespace === '' || !in_array($namespace, $this->allowedMigrationNamespaces(), true)) {
            return $this->respond(['success' => false, 'error' => lang('MigrationManager.invalidNamespaceSelection')], 400);
        }

        try {
            $lock = new RunLock();
        } catch (\RuntimeException $e) {
            log_message('error', '[MigrationManager] ' . $e->getMessage());

            return $this->respond(['success' => false, 'error' => lang('MigrationManager.runLockUnavailable')], 500);
        }

        if (!$lock->acquire()) {
            return $this->respond(['success' => false, 'error' => lang('MigrationManager.runAlreadyInProgress')], 409);
        }

        try {
            return $this->executeMigration($namespace);
        } finally {
            $lock->release();
        }
    }

    /**
     * POST edilen seeder FQCN'ini `SeederScanner::discover()` allowlist'ine
     * karşı doğrulayıp `Config\Database::seeder()->call()` ile çalıştırır ve
     * sonucu `migration_runs`'a kaydeder.
     *
     * Bugün `SeederScanner::discover()` BOŞ dizi döndüğü için (henüz hiçbir
     * seeder `WebRunnableSeeder` implement etmiyor) bu uç PRODUCTION'DA HER
     * ZAMAN reddeder — bu beklenen davranıştır, hata değildir.
     *
     * @return \CodeIgniter\HTTP\ResponseInterface
     */
    public function runSeed()
    {
        if (!$this->request->isAJAX()) {
            return $this->failForbidden();
        }

        if (!auth()->user()->inGroup('superadmin')) {
            return $this->failForbidden();
        }

        $seederClass = trim((string) $this->request->getPost('seeder'));
        $entry       = $this->findSeederEntry((new SeederScanner())->discover(), $seederClass);

        if ($entry === null) {
            return $this->respond(['success' => false, 'error' => lang('MigrationManager.invalidSeederSelection')], 400);
        }

        try {
            $lock = new RunLock();
        } catch (\RuntimeException $e) {
            log_message('error', '[MigrationManager] ' . $e->getMessage());

            return $this->respond(['success' => false, 'error' => lang('MigrationManager.runLockUnavailable')], 500);
        }

        if (!$lock->acquire()) {
            return $this->respond(['success' => false, 'error' => lang('MigrationManager.runAlreadyInProgress')], 409);
        }

        try {
            return $this->executeSeed($entry);
        } finally {
            $lock->release();
        }
    }

    /**
     * `migration_runs` tablosunun DataTables uyumlu sayfalı listesini döner.
     *
     * `modules/Backup/Controllers/Backup.php:9-33`'teki AJAX-datatables
     * deseninin birebir uygulamasıdır. `users` tablosuyla `LEFT JOIN`
     * yapılıp her satıra `run_by_username` eklenir (BUG-2); `run_by` `null`
     * ise (sistem/otomasyon kaynaklı ya da kullanıcı silinmiş) `run_by_username`
     * da `null` döner. Yanıt HAM veri taşır — HTML kaçışlaması yalnız render
     * sınırında, `Views/list.php`'nin kolon `render`'larındaki `escapeHtml()`
     * ile yapılır. Burada ayrıca `esc()` uygulanırsa istemci ikinci kez
     * kaçışlar (içinde `'` geçen kullanıcı adı `O&#039;Brien` görünür).
     * `$total` sayımı join GEREKTİRMEZ, `count('migration_runs', ...)` değişmedi.
     *
     * @return \CodeIgniter\HTTP\ResponseInterface
     */
    public function history()
    {
        if (!$this->request->isAJAX()) {
            return $this->failForbidden();
        }

        if (!auth()->user()->inGroup('superadmin')) {
            return $this->failForbidden();
        }

        $parsed = $this->commonBackendLibrary->getDatatablesPagination($this->request->getPost());
        $like   = $parsed['searchString'] !== '' ? ['target' => $parsed['searchString']] : [];

        $results = $this->commonModel->lists('migration_runs', 'migration_runs.*, users.username AS run_by_username', [], 'migration_runs.id DESC', $parsed['length'], $parsed['start'], $like, [], [
            ['table' => 'users', 'cond' => 'users.id = migration_runs.run_by', 'type' => 'left'],
        ]);
        $total   = $this->commonModel->count('migration_runs', [], $like);

        // Kaçışlama YALNIZ render sınırında yapılır (Views/list.php'nin her
        // DataTables kolonundaki escapeHtml()). Burada esc() uygulanırsa
        // istemci ikinci kez kaçışlar ve içinde ' veya & geçen bir kullanıcı
        // adı tabloda "O&#039;Brien" diye görünür. Tek kural: sunucu ham veri
        // döner, kolon render'ı kaçışlar — yeni kolon eklerken de bu geçerli.
        return $this->respond([
            'draw'                 => $parsed['draw'],
            'iTotalRecords'        => $total,
            'iTotalDisplayRecords' => $total,
            'aaData'               => $results,
        ]);
    }

    /**
     * `MigrationInspector::getDiscoveredNamespaces()`'ten salt-okunur
     * OLMAYAN (vendor dışı) namespace isimlerini çıkarır.
     *
     * @return list<string>
     */
    private function allowedMigrationNamespaces(): array
    {
        $writable = array_filter(
            (new MigrationInspector())->getDiscoveredNamespaces(),
            static fn (array $entry): bool => $entry['readOnly'] === false
        );

        return array_column($writable, 'namespace');
    }

    /**
     * Kilit alındıktan sonra asıl migration çalıştırma-ve-kaydetme işini
     * yapar. `RunLock::acquire()` başarılı olduğu VARSAYILARAK çağrılır.
     *
     * `Services::migrations(null, null, false)` ile non-shared bir
     * `MigrationRunner` alınır (`Settings.php:238`'in shared instance'a
     * `setNamespace()` sızıntısı bırakma hatasına düşülmez).
     * `getCliMessages()` web bağlamında her zaman boş döndüğü için
     * (`MigrationRunner.php:661,683` `is_cli()` guard'lı) "ne çalıştı"
     * bilgisi `latest()` öncesi/sonrası `getHistory()` diff'inden türetilir.
     *
     * @param string $namespace Allowlist'ten doğrulanmış namespace.
     *
     * @return \CodeIgniter\HTTP\ResponseInterface
     */
    private function executeMigration(string $namespace)
    {
        @set_time_limit(0);
        ignore_user_abort(true);

        $group  = $this->historyGroup();
        $runner = Services::migrations(null, null, false);
        $runner->setNamespace($namespace);

        $before    = $runner->getHistory($group);
        $startedAt = microtime(true);
        $regressed = false;
        $error     = null;

        try {
            if ($runner->latest() === false) {
                $regressed = true;
            }
        } catch (\Throwable $e) {
            $regressed = true;
            $error     = $e->getMessage();
        }

        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
        $after      = $runner->getHistory($group);
        $applied    = $this->diffAppliedMigrations($before, $after);

        return $this->finishMigrationRun($namespace, $applied, $after, $regressed, $error, $durationMs);
    }

    /**
     * `latest()` öncesi/sonrası `getHistory()` anlık görüntülerini
     * `version` alanına göre karşılaştırıp yeni uygulanan satırları çıkarır.
     *
     * @param list<object> $before `latest()` öncesi `getHistory()` satırları.
     * @param list<object> $after  `latest()` sonrası `getHistory()` satırları.
     *
     * @return list<object> `$after` içinde olup `$before`'da olmayan satırlar.
     */
    private function diffAppliedMigrations(array $before, array $after): array
    {
        $beforeVersions = array_map(static fn (object $row): string => $row->version, $before);

        return array_values(array_filter(
            $after,
            static fn (object $row): bool => !in_array($row->version, $beforeVersions, true)
        ));
    }

    /**
     * Diff sonucuna göre DB'ye kaydedilecek/kullanıcıya dönülecek mesajları
     * üretir, `migration_runs`'a satırı yazar, audit event'ini tetikler ve
     * JSON yanıtı döner.
     *
     * `latest()` `false` dönerse VEYA exception fırlatırsa (`$regressed`)
     * framework otomatik olarak `regress(-1)` çağırmış olur
     * (`vendor/codeigniter4/framework/system/Database/MigrationRunner.php:210-211`)
     * — bu, `migrationRunFailedRegressed` mesajıyla yanıtta AÇIKÇA belirtilir.
     *
     * @param string       $namespace  Çalıştırılan namespace.
     * @param list<object> $applied    Yeni uygulanan migration satırları.
     * @param list<object> $after      `latest()` sonrası tüm geçmiş satırları.
     * @param bool         $regressed  Framework'ün otomatik `regress(-1)` çağırdığı durum.
     * @param string|null  $error      Yakalanan exception mesajı (varsa).
     * @param int          $durationMs Çalıştırma süresi (milisaniye).
     *
     * @return \CodeIgniter\HTTP\ResponseInterface
     */
    private function finishMigrationRun(string $namespace, array $applied, array $after, bool $regressed, ?string $error, int $durationMs)
    {
        $appliedCount = count($applied);
        $batch        = $this->resolveBatch($applied, $after);
        $status       = $regressed ? 'failed' : 'success';

        if ($regressed) {
            $dbMessage       = $error ?? lang('MigrationManager.migrationRunFailedRegressed', [$namespace]);
            $responseMessage = lang('MigrationManager.migrationRunFailedRegressed', [$namespace]);
        } else {
            $dbMessage = (string) json_encode([
                'applied' => array_map(
                    static fn (object $row): array => ['version' => $row->version, 'class' => $row->class],
                    $applied
                ),
            ], JSON_UNESCAPED_UNICODE);
            $responseMessage = $appliedCount > 0
                ? lang('MigrationManager.migrationRunSuccess', [$appliedCount, $namespace])
                : lang('MigrationManager.migrationRunNoChange', [$namespace]);
        }

        $this->recordRun('migration', $namespace, $status, $appliedCount, $batch, $dbMessage, $durationMs);
        $this->auditRun('runMigration', $namespace, $status);

        return $this->respond([
            'success'      => $status === 'success',
            'message'      => $responseMessage,
            'appliedCount' => $appliedCount,
            'regressed'    => $regressed,
        ], $status === 'success' ? 200 : 500);
    }

    /**
     * `applied_count>0` ise yeni uygulanan satırların batch'ini, aksi halde
     * (namespace zaten güncelse) mevcut son batch'i döner.
     *
     * @param list<object> $applied Yeni uygulanan migration satırları.
     * @param list<object> $after   `latest()` sonrası tüm geçmiş satırları.
     *
     * @return int|null Hiç geçmiş yoksa `null`.
     */
    private function resolveBatch(array $applied, array $after): ?int
    {
        if ($applied !== []) {
            return (int) $applied[0]->batch;
        }

        if ($after === []) {
            return null;
        }

        return (int) end($after)->batch;
    }

    /**
     * `getHistory()`'ye geçirilecek `group` değerini çalışma zamanı DB
     * yapılandırmasından çözer (`MigrationInspector::historyGroup()` ile
     * aynı kaynak — literal `'default'` KULLANILMAZ; `ENVIRONMENT===
     * 'testing'` altında `Config\Database::$defaultGroup` `'tests'`e döner,
     * bkz. `app/Config/Database.php:200-201`).
     *
     * @return string
     */
    private function historyGroup(): string
    {
        return (string) config(Database::class)->defaultGroup;
    }

    /**
     * POST edilen FQCN'i keşfedilen seeder listesinde arar.
     *
     * @param list<array{class: class-string, label: string, repeatable: bool}> $seeders
     * @param string                                                            $seederClass
     *
     * @return array{class: class-string, label: string, repeatable: bool}|null
     */
    private function findSeederEntry(array $seeders, string $seederClass): ?array
    {
        if ($seederClass === '') {
            return null;
        }

        foreach ($seeders as $entry) {
            if ($entry['class'] === $seederClass) {
                return $entry;
            }
        }

        return null;
    }

    /**
     * Kilit alındıktan sonra asıl seed çalıştırma-ve-kaydetme işini yapar.
     *
     * @param array{class: class-string, label: string, repeatable: bool} $entry Allowlist'ten doğrulanmış seeder girdisi.
     *
     * @return \CodeIgniter\HTTP\ResponseInterface
     */
    private function executeSeed(array $entry)
    {
        @set_time_limit(0);
        ignore_user_abort(true);

        $label            = lang('MigrationManager.' . $entry['label']);
        $startedAt        = microtime(true);
        $status           = 'success';
        $dbMessage        = (string) json_encode(['seeded' => $entry['class']], JSON_UNESCAPED_UNICODE);
        $responseMessage  = lang('MigrationManager.seedRunSuccess', [$label]);

        try {
            Database::seeder()->call($entry['class']);
        } catch (\Throwable $e) {
            $status          = 'failed';
            $dbMessage       = $e->getMessage();
            $responseMessage = lang('MigrationManager.seedRunFailed', [$label, $e->getMessage()]);
        }

        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

        $this->recordRun('seed', $entry['class'], $status, $status === 'success' ? 1 : 0, null, $dbMessage, $durationMs);
        $this->auditRun('runSeed', $entry['class'], $status);

        return $this->respond([
            'success' => $status === 'success',
            'message' => $responseMessage,
        ], $status === 'success' ? 200 : 500);
    }

    /**
     * `migration_runs` tablosuna bir çalıştırma satırı ekler.
     *
     * @param 'migration'|'seed'  $kind
     * @param string              $target       Migration namespace'i veya seed FQCN'i.
     * @param 'success'|'failed'  $status
     * @param int                 $appliedCount
     * @param int|null            $batch
     * @param string|null         $message      JSON veya hata metni.
     * @param int                 $durationMs
     *
     * @return void
     */
    private function recordRun(string $kind, string $target, string $status, int $appliedCount, ?int $batch, ?string $message, int $durationMs): void
    {
        $this->commonModel->create('migration_runs', [
            'kind'          => $kind,
            'target'        => $target,
            'status'        => $status,
            'applied_count' => $appliedCount,
            'batch'         => $batch,
            'message'       => $message,
            'duration_ms'   => $durationMs,
            'run_by'        => auth()->id(),
            'ip'            => $this->request->getIPAddress(),
        ]);
    }

    /**
     * `ci4ms.audit` event'ini tetikler (`Fileeditor::triggerFileevent()`
     * deseni, `modules/Fileeditor/Controllers/Fileeditor.php:97-105`).
     *
     * @param string $action Örn. `'runMigration'`, `'runSeed'`.
     * @param string $target Migration namespace'i veya seed FQCN'i.
     * @param string $status `'success'` veya `'failed'`.
     *
     * @return void
     */
    private function auditRun(string $action, string $target, string $status): void
    {
        Events::trigger('ci4ms.audit', [
            'severity' => 'warning',
            'action'   => 'migrationManager.' . $action,
            'message'  => sprintf('%s (%s) %s tarafından çalıştırıldı: %s', $target, $action, auth()->user()->username, $status),
            'url'      => base_url('backend/migration-manager'),
        ]);
    }
}

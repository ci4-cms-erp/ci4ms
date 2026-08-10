<?php

declare(strict_types=1);

namespace Modules\MigrationManager\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * `migration_runs.run_source` — CLI'dan mı web panelinden mi başlatıldığını
 * ayırt eder (FAZ 2 — "KRİTİK KISIT" kararı, `ci4ms:migrate` komutunun
 * `migration_runs`'a yazacağı audit satırı için).
 *
 * SORUN: `run_by` NULLABLE bir FK'dir
 * (`2026-08-02-151717_CreateMigrationRunsTable.php:73-79`) ve bugün UI
 * `run_by IS NULL` satırını HER ZAMAN "Silinmiş kullanıcı" olarak render
 * ediyor (`Views/list.php:225` sunucu-taraflı satır, `:517` DataTables JS
 * render'ı; dil anahtarı `MigrationManager.deletedUser`,
 * `Language/en/MigrationManager.php:52`, `Language/tr/MigrationManager.php:52`).
 * `php spark ci4ms:migrate` CLI'dan koşarken oturum olmadığı için `run_by`
 * da null olacak — ama bu "silinmiş kullanıcı" DEĞİL, "komut satırından
 * çalıştırıldı" demek. `run_by IS NULL` tek başına bu iki durumu AYIRT
 * EDEMEZ; bu yüzden ayrı bir kolon eklenir.
 *
 * `DEFAULT 'web'` KASITLI: mevcut
 * `Modules\MigrationManager\Controllers\MigrationManager::recordRun()`
 * (`Controllers/MigrationManager.php:432-445`) bu kolonu hiç bilmeden
 * `$this->commonModel->create('migration_runs', [...])` çağırmaya devam
 * eder — INSERT'te `run_source` verilmeyince MySQL varsayılanı uygular ve
 * satır otomatik `'web'` alır. Web akışı SIFIR kod değişikliğiyle doğru
 * davranmaya devam eder. Yeni CLI audit yazımı (kapsam dışı,
 * `modules/Backend/Commands/Ci4msMigrate.php`) `run_source => 'cli'`i
 * açıkça geçecek.
 *
 * Mevcut `2026-08-02-151717_CreateMigrationRunsTable.php` DEĞİŞTİRİLMEDİ —
 * o migration başka ortamlarda zaten uygulanmış olabilir; bu yüzden ALTER
 * ayrı bir migration'da yapılıyor. Stil/guard referansı:
 * `modules/Auth/Database/Migrations/2026-06-08-000001_AddLockedAtToUserSessions.php`
 * (fieldExists guard + resetDataCache + `after` konumlandırma).
 */
class AddRunSourceToMigrationRuns extends Migration
{
    public function up(): void
    {
        // fieldExists() sonucu bağlantıda önbelleklenir ve şema bu süreç
        // içinde değişince bayat kalır (migrate:refresh / rollback+migrate).
        // Guard yalan söylemesin diye önce sıfırlanır.
        $this->db->resetDataCache();

        if ($this->db->fieldExists('run_source', 'migration_runs')) {
            return;
        }

        $this->forge->addColumn('migration_runs', [
            'run_source' => [
                'type'       => 'ENUM',
                'constraint' => ['web', 'cli'],
                'null'       => false,
                'default'    => 'web',
                'after'      => 'run_by',
            ],
        ]);
    }

    public function down(): void
    {
        $this->db->resetDataCache();

        $this->forge->dropColumn('migration_runs', 'run_source');
    }
}

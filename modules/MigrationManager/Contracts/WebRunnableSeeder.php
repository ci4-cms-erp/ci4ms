<?php

declare(strict_types=1);

namespace Modules\MigrationManager\Contracts;

/**
 * Web'den tetiklenebilir seed'ler için işaretleyici (marker) arayüz.
 *
 * `vendor/codeigniter4/framework/system/Database/Seeder.php:154`'teki
 * `Seeder::call()` seeder sınıf adını hiç doğrulamadan `new $class(...)`
 * yapar. Bu arayüzü implement etmek, `SeederScanner::discover()`'ın
 * kullandığı yapısal bir allowlist'e girmenin ÖN KOŞULUDUR — implement
 * etmeyen seeder'lar (ör. `Ci4msDefaultsSeeder`) tarama sonucunda hiç
 * görünmez, ayrı bir denylist'e ihtiyaç yoktur.
 */
interface WebRunnableSeeder
{
    /**
     * Backend arayüzünde gösterilecek etiketin `lang()` anahtarını döner.
     *
     * Çevrilmiş metni DEĞİL, `Language/{en,tr}/MigrationManager.php`
     * içindeki anahtarı döner — çeviri çağrı yerinde (view/controller)
     * `lang('MigrationManager.' . $key)` ile yapılır.
     *
     * @return string lang() anahtarı (namespace/nokta içermez).
     */
    public static function seederLabel(): string;

    /**
     * Seeder'ın aynı veri üzerinde güvenle birden çok kez çalıştırılıp
     * çalıştırılamayacağını belirtir.
     *
     * `false` dönerse UI, seeder zaten bir `migration_runs` kaydına sahipse
     * tekrar çalıştırma aksiyonunu gizlemeli/engellemelidir; asıl
     * idempotency garantisi yine de seeder'ın kendi `run()` implementasyonuna
     * (ör. skip-gate) aittir — bu bayrak yalnızca UI/politika sinyalidir.
     *
     * @return bool Tekrar çalıştırmaya güvenli ise `true`.
     */
    public static function isRepeatable(): bool;
}

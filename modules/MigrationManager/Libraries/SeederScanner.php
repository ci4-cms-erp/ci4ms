<?php

declare(strict_types=1);

namespace Modules\MigrationManager\Libraries;

use CodeIgniter\Database\Seeder;
use Modules\MigrationManager\Contracts\WebRunnableSeeder;

/**
 * `app/Database/Seeds/` ve her modülün `Database/Seeds/` dizini altındaki
 * seed dosyalarını tarar ve yalnızca `WebRunnableSeeder` implement eden,
 * `Seeder` alt sınıfı olan sınıfları döner.
 *
 * Bu allowlist, `Seeder::call()`'daki doğrulamasız `new $class(...)` sinkini
 * (bkz. `WebRunnableSeeder` docblock'u) yapısal olarak kapatır: arayüzü
 * implement etmeyen bir seeder (ör. `Ci4msDefaultsSeeder`) tarama sonucuna
 * hiçbir zaman girmez — ayrı bir denylist gerekmez.
 *
 * FQCN türetme, kullanıcı girdisi almadan salt dosya sistemi taraması ile
 * yapılır: `app/Config/Autoload.php`'in her modül dizini için otomatik
 * kaydettiği `Modules\{Folder}` PSR-4 önekiyle birebir örtüşür.
 */
class SeederScanner
{
    /**
     * `WebRunnableSeeder` implement eden geçerli seed sınıflarını keşfeder.
     *
     * Üretim ortamında `app/Database/Seeds/Ci4msDefaultsSeeder.php` bu
     * arayüzü implement etmediği için dönen dizi, henüz `WebRunnableSeeder`
     * implement eden hiçbir seeder eklenmemişse **boş** olur — bu beklenen
     * davranıştır, hata değildir.
     *
     * @return list<array{class: class-string<Seeder>, label: string, repeatable: bool}>
     */
    public function discover(): array
    {
        $discovered = [];

        foreach ($this->candidateClasses() as $fqcn) {
            if (!class_exists($fqcn) || !is_subclass_of($fqcn, Seeder::class)) {
                continue;
            }

            if (!in_array(WebRunnableSeeder::class, class_implements($fqcn) ?: [], true)) {
                continue;
            }

            /** @var class-string<Seeder&WebRunnableSeeder> $fqcn */
            $discovered[] = [
                'class'      => $fqcn,
                'label'      => $fqcn::seederLabel(),
                'repeatable' => $fqcn::isRepeatable(),
            ];
        }

        return $discovered;
    }

    /**
     * Disk üzerindeki seed dosyalarından, allowlist kontrolünden önceki
     * aday FQCN listesini türetir.
     *
     * `app/Database/Seeds/{Basename}.php` -> `App\Database\Seeds\{Basename}`
     * `modules/{Module}/Database/Seeds/{Basename}.php` -> `Modules\{Module}\Database\Seeds\{Basename}`
     *
     * @return list<string> Var olması garanti edilmeyen aday sınıf adları.
     */
    private function candidateClasses(): array
    {
        $candidates = [];

        foreach (glob(ROOTPATH . 'app/Database/Seeds/*.php') ?: [] as $file) {
            $candidates[] = 'App\\Database\\Seeds\\' . pathinfo($file, PATHINFO_FILENAME);
        }

        foreach (glob(ROOTPATH . 'modules/*/Database/Seeds/*.php') ?: [] as $file) {
            $moduleName    = basename(dirname($file, 3));
            $candidates[] = 'Modules\\' . $moduleName . '\\Database\\Seeds\\' . pathinfo($file, PATHINFO_FILENAME);
        }

        return $candidates;
    }
}

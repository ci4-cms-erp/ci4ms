<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * ARTIK NO-OP: bu seeder eskiden `CLI::prompt()` ile isim/e-posta/parola
 * isteyip `InstallService::createDefaultData()`'yı çağırarak superadmin
 * hesabı oluşturuyordu. Web bağlamında STDIN yoktur
 * (`vendor/codeigniter4/framework/system/CLI/CLI.php` -- `fgets(STDIN)`
 * okunamadığında `false`, `CLI::prompt()` bunu `''`'e çevirir), bu yüzden
 * `php spark db:seed Ci4msDefaultsSeeder` web'den (ör. bu görevle eklenen
 * `MigrationManager` seed ekranından) tetiklenirse boş isim/e-posta/parola
 * ile bir superadmin hesabı yaratıyordu -- sessiz veri bozulması. Hesap
 * yaratma artık hiçbir web-runnable seeder'da yok: bu sınıf
 * `Modules\MigrationManager\Contracts\WebRunnableSeeder` implement ETMEZ,
 * bu yüzden `Modules\MigrationManager\Libraries\SeederScanner::discover()`
 * tarafından hiç keşfedilmez ve backend arayüzünde hiç görünmez.
 *
 * Sınıf üçüncü parti doküman/muscle-memory riskine karşı SİLİNMEDİ; `run()`
 * gövdesiz bir no-op'tur, `php spark db:seed Ci4msDefaultsSeeder` hâlâ
 * hatasız/asılı kalmadan döner, yalnızca hiçbir şey yapmaz.
 */
class Ci4msDefaultsSeeder extends Seeder
{
    /**
     * No-op. Eski CLI-prompt + hesap oluşturma davranışı kaldırıldı (bkz.
     * sınıf docblock'u).
     *
     * @return void
     */
    public function run()
    {
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Database\Seeds\Ci4msReferenceDataSeeder;
use ci4commonmodel\CommonModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * `Ci4msReferenceDataSeeder`'ın idempotency ve (ortam izin veriyorsa) taze
 * kurulum davranışını doğrular.
 *
 * `$refresh=false` + açık `$namespace=null` kullanılır: `DatabaseTestTrait`'in
 * `$refresh=true` davranışı `regressDatabase()` üzerinden `setNamespace()`'i
 * yok sayıp paylaşımlı `ci4ms_test` şemasındaki TÜM namespace'leri düşürür
 * (`vendor/codeigniter4/framework/system/Database/MigrationRunner.php:
 * 289-291`) — aynı kök neden `tests/Feature/InstallTest.php` ve
 * `tests/Modules/Users/PermgroupPrivilegeEscalationTest.php`'de de belgelendi.
 *
 * Bu suite'te transaction sarmalama YOKTUR: `Ci4msReferenceDataSeeder` de,
 * bu testin okuma sorguları da `CommonModel`'in DEFAULT grup bağlantısını
 * kullanır; kalıcı yazımlar (varsa) bilerek kalıcıdır, tıpkı InstallTest gibi.
 *
 * @internal
 */
final class Ci4msReferenceDataSeederTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $refresh = false;

    protected $namespace = null;

    protected $migrateOnce = true;

    protected function setUp(): void
    {
        parent::setUp();

        // Risk-1 kanaryası: bu suite yanlışlıkla canlı `ci4ms` şemasına karşı
        // koşarsa (ör. .env/phpunit.xml.dist yanlış yapılandırılmışsa) burada
        // durmalı, sessizce canlı veriye yazmamalı.
        $this->assertStringContainsString(
            'ci4ms_test',
            (string) \Config\Database::connect()->database,
            'Bu suite yalnızca atılabilir ci4ms_test şemasına karşı koşmalı, canlı veritabanına asla.',
        );
    }

    /**
     * Seeder'ı art arda iki kez çalıştırır: ikinci çağrı ne exception fırlatır
     * (PK/unique çakışması yok) ne de `pages` satır sayısını değiştirir
     * (gerçek no-op). `pages` bu testin başında boş ya da dolu olsun fark
     * etmez -- ikisinde de geçerlidir.
     *
     * Seeder'ın yazdıkları `testFreshRunPopulatesDynamicIds` ile AYNI
     * snapshot/restore disipliniyle geri alınır. Bu metod eskiden hiçbir şeyi
     * geri almıyordu ve paylaşımlı `ci4ms_test` şemasında alfabetik olarak
     * `InstallTest`/`InstallerHardeningTest`'ten ÖNCE koştuğu için ikisini de
     * düşürüyordu: `settings.siteName` seeder'ın sabit `'CI4MS'` değerinde
     * kalıyordu (`createDefaultData()` settings dolu olduğu için no-op'lar), ve
     * `Auth.captchaBypassInDevelopment` satırı var olduğu için config-default
     * fallback testi ölçmek istediği yolu artık ölçemiyordu.
     *
     * @return void
     */
    public function testSeederIsIdempotent(): void
    {
        $commonModel = new CommonModel();
        $db          = \Config\Database::connect();

        $tables = ['languages', 'pages', 'pages_langs', 'blog', 'blog_langs', 'menu', 'settings'];

        $snapshot = [];
        foreach ($tables as $table) {
            $result           = $db->table($table)->get();
            $snapshot[$table] = $result === false ? [] : $result->getResultArray();
        }

        $db->disableForeignKeyChecks();

        try {
            \Config\Database::seeder()->call(Ci4msReferenceDataSeeder::class);
            $afterFirst = $commonModel->count('pages');

            \Config\Database::seeder()->call(Ci4msReferenceDataSeeder::class);
            $afterSecond = $commonModel->count('pages');

            $this->assertSame(
                $afterFirst,
                $afterSecond,
                'İkinci seeder çalıştırması no-op olmalı ve pages satır sayısını değiştirmemeli.',
            );
        } finally {
            foreach ($tables as $table) {
                $db->table($table)->truncate();
            }
            foreach ($tables as $table) {
                if ($snapshot[$table] !== []) {
                    $db->table($table)->insertBatch($snapshot[$table]);
                }
            }

            $db->enableForeignKeyChecks();
        }

        // Restore sağlaması: her tablo testten önceki satır sayısına dönmüş olmalı.
        foreach ($tables as $table) {
            $this->assertSame(
                count($snapshot[$table]),
                $commonModel->count($table),
                sprintf('"%s" tablosu testten önceki satır sayısına dönmedi.', $table),
            );
        }
    }

    /**
     * Seeder'ın taze bir kurulumda referans veriyi dinamik `pages_id`'lerle
     * (sabit `1`/`2` DEĞİL) doğru doldurduğunu doğrular.
     *
     * `ci4ms_test` paylaşımlı olduğu ve `pages` bu oturumda genelde dolu
     * kaldığı için (sınıf docblock'undaki gerekçe), taze senaryo PASİF bir
     * gözlemle (skip) değil AKTİF fixture hazırlığıyla kurulur: seeder'ın
     * yazdığı 7 tablonun (`languages`, `pages`, `pages_langs`, `blog`,
     * `blog_langs`, `menu`, `settings`) TAM bir anlık görüntüsü alınır,
     * tablolar `truncate()` ile boşaltılır, seeder çalıştırılır, iddialar
     * doğrulanır ve `finally` içinde orijinal satırlar AYNI `id`
     * değerleriyle geri yazılır.
     *
     * `disableForeignKeyChecks()`/`enableForeignKeyChecks()` (session-scoped
     * `SET FOREIGN_KEY_CHECKS`, `vendor/codeigniter4/framework/system/
     * Database/BaseConnection.php:1900-1924`) TRUNCATE penceresi boyunca
     * açıktır -- `pages_langs.pages_id`/`blog_langs.blog_id` CASCADE FK'ları
     * (`modules/Pages/Database/Migrations/2026-03-12-001000_
     * CreatePagesLangsTable.php:59`, `modules/Blog/Database/Migrations/
     * 2026-03-12-001000_CreateBlogLangsTable.php:59`) ve `menu.parent`
     * öz-referanslı FK'ı (`modules/Backend/Database/Migrations/
     * 2026-02-25-062806_AddForeignKeys.php:23`) aksi halde TRUNCATE'i
     * engeller. `blog_categories_pivot`/`comments`/`tags_pivot` gibi bu
     * testin snapshot'ı DIŞINDAKİ tablolar `blog.id`'ye FK'lıdır -- restore
     * adımında orijinal `id` korunduğu için o referanslar kopmaz.
     *
     * `DatabaseTestTrait` PHPUnit çekirdeğinde OTOMATİK transaction/rollback
     * SAĞLAMAZ -- kaynakta doğrulandı: `vendor/codeigniter4/framework/
     * system/Test/DatabaseTestTrait.php` (`setUpDatabase()`/
     * `tearDownDatabase()`, satır 65-80) hiçbir `transStart()`/
     * `transRollback()` çağrısı içermiyor; bu yüzden temizlik manuel
     * `finally` bloğuyla yapılır, sınıfın diğer testleriyle
     * (`InstallServiceIdempotencyTest`) aynı desen.
     *
     * "Gerçek insertId'ye eşit" iddiası `CommonModel::create()`'in
     * fırlattığı `model:afterCreate` olayını (`vendor/bertugfahriozer/
     * ci4commonmodel/src/CommonModel.php:156-163`) dinleyerek yakalanır --
     * olay `$this->db->insertID()`'nin döndürdüğü DEĞERİ taşır; böylece
     * iddia sabit bir sayıya EŞİT OLMADIĞINI değil, DB'nin gerçekten
     * ürettiği değere EŞİT OLDUĞUNU kanıtlar.
     *
     * @return void
     */
    public function testFreshRunPopulatesDynamicIds(): void
    {
        $commonModel = new CommonModel();
        $db          = \Config\Database::connect();

        $tables = ['languages', 'pages', 'pages_langs', 'blog', 'blog_langs', 'menu', 'settings'];

        $snapshot = [];
        foreach ($tables as $table) {
            $result            = $db->table($table)->get();
            $snapshot[$table]  = $result === false ? [] : $result->getResultArray();
        }

        $capturedPageIds = [];
        $listener        = static function (mixed $event) use (&$capturedPageIds): void {
            if (!is_array($event) || ($event['table'] ?? null) !== 'pages') {
                return;
            }

            $insertId = $event['insertId'] ?? null;
            if (is_int($insertId) || is_string($insertId)) {
                $capturedPageIds[] = (int) $insertId;
            }
        };
        \CodeIgniter\Events\Events::on('model:afterCreate', $listener);

        $db->disableForeignKeyChecks();

        try {
            foreach ($tables as $table) {
                $db->table($table)->truncate();
            }

            $this->assertSame(
                0,
                $commonModel->count('pages'),
                'Fixture hazırlığı: "pages" tablosu boşaltılamadı.',
            );

            \Config\Database::seeder()->call(Ci4msReferenceDataSeeder::class);

            $this->assertCount(
                2,
                $capturedPageIds,
                'Seeder taze kurulumda tam olarak 2 "pages" satırı oluşturmalı'
                . ' ("model:afterCreate" olayı 2 kez tetiklenmeli).',
            );
            [$homePageId, $contactPageId] = $capturedPageIds;

            // İddia 1: pages.id gerçekten auto-increment'ten gelen insertId'ye eşit
            // -- sabit bir değere eşit OLMADIĞI değil, insertID()'nin döndürdüğü
            // gerçek değere eşit OLDUĞU doğrulanıyor.
            $this->assertSame(
                1,
                $commonModel->count('pages', ['id' => $homePageId]),
                'İlk create() çağrısının döndürdüğü insertId ile eşleşen "pages" satırı bulunamadı.',
            );
            $this->assertSame(
                1,
                $commonModel->count('pages', ['id' => $contactPageId]),
                'İkinci create() çağrısının döndürdüğü insertId ile eşleşen "pages" satırı bulunamadı.',
            );

            // İddia 2: settings.homePage gerçek pages.id'ye (insertId'ye) eşit.
            $homePageSetting = $commonModel->selectOne('settings', ['class' => 'Config\\App', 'key' => 'homePage']);
            $this->assertNotNull($homePageSetting, 'settings.homePage satırı bulunamadı.');
            $this->assertSame((string) $homePageId, (string) $homePageSetting->value);

            // İddia 3: menu.pages_id değerleri gerçek pages.id'lere (insertId'lere) eşit.
            $homeMenu = $commonModel->selectOne('menu', ['title' => 'Frontend.home']);
            $this->assertNotNull($homeMenu, 'menu "Frontend.home" satırı bulunamadı.');
            $this->assertSame($homePageId, (int) $homeMenu->pages_id);

            $contactMenu = $commonModel->selectOne('menu', ['title' => 'Frontend.contact']);
            $this->assertNotNull($contactMenu, 'menu "Frontend.contact" satırı bulunamadı.');
            $this->assertSame($contactPageId, (int) $contactMenu->pages_id);

            // İddia 4: pages_langs.pages_id değerleri gerçek pages.id'lere (insertId'lere) eşit.
            $homePageLangEn = $commonModel->selectOne('pages_langs', ['seflink' => 'homepage']);
            $this->assertNotNull($homePageLangEn, 'pages_langs "homepage" satırı bulunamadı.');
            $this->assertSame($homePageId, (int) $homePageLangEn->pages_id);

            $homePageLangTr = $commonModel->selectOne('pages_langs', ['seflink' => 'anasayfa']);
            $this->assertNotNull($homePageLangTr, 'pages_langs "anasayfa" satırı bulunamadı.');
            $this->assertSame($homePageId, (int) $homePageLangTr->pages_id);

            $contactPageLangEn = $commonModel->selectOne('pages_langs', ['seflink' => 'contact']);
            $this->assertNotNull($contactPageLangEn, 'pages_langs "contact" satırı bulunamadı.');
            $this->assertSame($contactPageId, (int) $contactPageLangEn->pages_id);

            $contactPageLangTr = $commonModel->selectOne('pages_langs', ['seflink' => 'iletisim']);
            $this->assertNotNull($contactPageLangTr, 'pages_langs "iletisim" satırı bulunamadı.');
            $this->assertSame($contactPageId, (int) $contactPageLangTr->pages_id);
        } finally {
            \CodeIgniter\Events\Events::removeAllListeners('model:afterCreate');

            foreach ($tables as $table) {
                $db->table($table)->truncate();
            }
            foreach ($tables as $table) {
                if ($snapshot[$table] !== []) {
                    $db->table($table)->insertBatch($snapshot[$table]);
                }
            }

            $db->enableForeignKeyChecks();
        }

        // Restore sağlaması: her tablo testten önceki satır sayısına dönmüş olmalı.
        foreach ($tables as $table) {
            $this->assertSame(
                count($snapshot[$table]),
                $commonModel->count($table),
                sprintf('"%s" tablosu testten sonra orijinal satır sayısına dönmedi.', $table),
            );
        }
    }
}

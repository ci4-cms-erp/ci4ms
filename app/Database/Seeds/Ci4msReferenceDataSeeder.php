<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use ci4commonmodel\CommonModel;
use Modules\MigrationManager\Contracts\WebRunnableSeeder;

/**
 * Web'den tetiklenebilir, parametresiz referans veri seeder'ı.
 *
 * `Modules\Install\Services\InstallService::createDefaultData()`'nın referans
 * veri (diller, sayfalar, örnek blog, menü, ayarlar) kısmının BAĞIMSIZ bir
 * kopyasıdır -- o metodu ÇAĞIRMAZ. Gerekçe: `createDefaultData()` ayrıca
 * `auth_groups`/`auth_groups_users` satırları oluşturup
 * `Modules\Methods\Libraries\ModuleScanner::runScan()`'ı tetikliyor; bu
 * tarama `auth_permissions_pages` tablosunu SİLİP yeniden dolduruyor ve
 * tarama sırasında `Ci4MsAuthFilter` (fail-closed) sayfa satırı bulamayan
 * hiçbir isteğe -- superadmin dahil -- izin vermiyor. Web'den tetiklenirse
 * toplu kilitlenme riski taşır. Bu yüzden bu seeder yalnızca içerik verisini
 * yazar; kullanıcı/grup/izin verisine hiç dokunmaz.
 *
 * Bu seeder'ın tek `count('pages') > 0` kapısı KASITLI ve DOĞRUDUR --
 * yalnız içerik yazdığı için "içerik var mı" sorusu tek bir `pages`
 * kontrolüyle eksiksiz cevaplanır. Bu, `InstallService::createDefaultData()`'nın
 * çok-bloklu (her tablo kendi guard'ına bakan) tasarımıyla KARIŞTIRILMAMALI:
 * o metot hem içerik hem kimlik (`auth_groups`/`auth_groups_users`) yazdığı
 * için tek bir `pages` kapısı yanlıştı (içerik varken kimliği de atlıyordu).
 */
class Ci4msReferenceDataSeeder extends Seeder implements WebRunnableSeeder
{
    /**
     * Backend arayüzünde gösterilecek etiketin `lang()` anahtarını döner.
     *
     * @return string
     */
    public static function seederLabel(): string
    {
        return 'seederReferenceData';
    }

    /**
     * Bu seeder tekrar tetiklendiğinde veri bozulmasına yol açmaz: `run()`
     * en başında `pages` tablosu doluysa no-op döner (skip-gate).
     *
     * @return bool
     */
    public static function isRepeatable(): bool
    {
        return true;
    }

    /**
     * Referans veriyi (diller, sayfalar, örnek blog, menü, ayarlar) yazar.
     *
     * İdempotent: `pages` tablosunda zaten satır varsa hiçbir şey yazmadan
     * döner. Bu no-op SESSİZ değildir -- `log_message('info', ...)` ile
     * işaretlenir; ancak bu mesaj bugün backend UI'ına YANSIMAZ, çünkü
     * `Modules\MigrationManager\Controllers\MigrationManager::executeSeed()`
     * (satır ~388-416) `Config\Database::seeder()->call()`'ın dönüş
     * değerine bakmadan, exception fırlatılmadığı sürece her zaman
     * `seedRunSuccess` mesajını döner.
     *
     * @return void
     */
    public function run(): void
    {
        $commonModel = new CommonModel();

        if ($commonModel->count('pages') > 0) {
            log_message('info', lang('MigrationManager.seedAlreadyApplied'));

            return;
        }

        $this->seedLanguages($commonModel);

        [$homePageId, $contactPageId] = $this->seedPages($commonModel);

        $this->seedPagesLangs($commonModel, $homePageId, $contactPageId);
        $this->seedBlog($commonModel);
        $this->seedMenu($commonModel, $homePageId, $contactPageId);
        $this->seedSettings($commonModel, $homePageId);
    }

    /**
     * `languages` tablosuna varsayılan tr/en satırlarını yazar.
     *
     * @param CommonModel $commonModel
     *
     * @return void
     */
    private function seedLanguages(CommonModel $commonModel): void
    {
        $commonModel->createMany('languages', [
            [
                'code'        => 'tr',
                'name'        => 'Türkçe',
                'native_name' => 'tr',
                'flag'        => 'fi fi-tr',
                'direction'   => 'ltr',
                'is_default'  => 0,
                'is_active'   => 1,
                'is_frontend' => 1,
                'sort_order'  => 1,
            ],
            [
                'code'        => 'en',
                'name'        => 'English',
                'native_name' => 'gb',
                'flag'        => 'fi fi-gb',
                'direction'   => 'ltr',
                'is_default'  => 1,
                'is_active'   => 1,
                'is_frontend' => 1,
                'sort_order'  => 0,
            ],
        ]);
    }

    /**
     * `pages` tablosuna ana sayfa + iletişim sayfası satırlarını tekil
     * `create()` çağrılarıyla yazar ve gerçek insert ID'lerini döner
     * (`createMany()` insert ID döndürmez, `CommonModel.php:200-204`;
     * `create()` döner, `CommonModel.php:151`).
     *
     * @param CommonModel $commonModel
     *
     * @return array{0: int, 1: int} [$homePageId, $contactPageId]
     */
    private function seedPages(CommonModel $commonModel): array
    {
        $homePageId    = $commonModel->create('pages', ['isActive' => 1, 'inMenu' => 1]);
        $contactPageId = $commonModel->create('pages', ['isActive' => 1, 'inMenu' => 1]);

        return [$homePageId, $contactPageId];
    }

    /**
     * `pages_langs` tablosuna ana sayfa/iletişim sayfasının tr/en içeriğini
     * yazar; `pages_id` değerleri `seedPages()`'ten dinamik olarak alınır.
     *
     * @param CommonModel $commonModel
     * @param int         $homePageId
     * @param int         $contactPageId
     *
     * @return void
     */
    private function seedPagesLangs(CommonModel $commonModel, int $homePageId, int $contactPageId): void
    {
        $commonModel->createMany('pages_langs', [
            [
                'pages_id' => $homePageId,
                'lang'     => 'en',
                'title'    => 'The Future of Modular Management',
                'seflink'  => 'homepage',
                'content'  => '
                <section class="hero-section text-center">
                    <div class="container py-3">
                        <img src="/templates/default/assets/hero_banner.png" class="img-fluid rounded-4 shadow-lg mb-5" alt="CI4MS Hero">
                        <h1 class="display-3 mb-4">CodeIgniter 4 Power, Modular Freedom</h1>
                        <p class="lead mb-5 max-width-700 mx-auto">CI4MS is the ultimate hybrid engine for building CMS and ERP systems. Designed for PHP 8.1+, it gives you the modularity you need without the bloat.</p>
                        <div class="d-flex justify-content-center gap-3">
                            <a href="/en/blog" class="btn btn-primary btn-lg px-5">Read Our Blog</a>
                            <a href="/en/contact" class="btn btn-outline-light btn-lg px-5">Contact Sales</a>
                        </div>
                    </div>
                </section>
                <section id="features" class="container">
                    <div class="text-center mb-5"><h2 class="display-5">Core Capabilities</h2></div>
                    <div class="row g-4">
                        <div class="col-md-4"><div class="card p-4 h-100 border-0 shadow-sm">
                            <i class="bi bi-cpu fs-1 text-primary mb-3"></i><h3>Modular Core</h3><p>Every feature from Auth to SEO is a discrete module that can be swapped or customized.</p>
                        </div></div>
                        <div class="col-md-4"><div class="card p-4 h-100 border-0 shadow-sm">
                            <i class="bi bi-shield-check fs-1 text-primary mb-3"></i><h3>Shield Security</h3><p>Integrates natively with CodeIgniter Shield for multi-group RBAC and session security.</p>
                        </div></div>
                        <div class="col-md-4"><div class="card p-4 h-100 border-0 shadow-sm">
                            <i class="bi bi-globe fs-1 text-primary mb-3"></i><h3>Global Ready</h3><p>Full multi-language support with locale-prefixed URLs and translation management.</p>
                        </div></div>
                    </div>
                </section>',
                'seo' => json_encode(['description' => 'CI4MS Homepage']),
            ],
            [
                'pages_id' => $homePageId,
                'lang'     => 'tr',
                'title'    => 'Modüler Yönetimin Geleceği',
                'seflink'  => 'anasayfa',
                'content'  => '
                <section class="hero-section text-center">
                    <div class="container py-3">
                        <img src="/templates/default/assets/hero_banner.png" class="img-fluid rounded-4 shadow-lg mb-5" alt="CI4MS Hero">
                        <h1 class="display-3 mb-4">CodeIgniter 4 Gücü, Modüler Özgürlük</h1>
                        <p class="lead mb-5 max-width-700 mx-auto">CI4MS, CMS ve ERP sistemleri oluşturmak için nihai hibrit motorudur. PHP 8.1+ için tasarlanmış olup ihtiyacınız olan modülerliği karmaşıklık olmadan sunar.</p>
                        <div class="d-flex justify-content-center gap-3">
                            <a href="/tr/blog" class="btn btn-primary btn-lg px-5">Blogumuzu Oku</a>
                            <a href="/tr/iletisim" class="btn btn-outline-light btn-lg px-5">Satışla İletişime Geç</a>
                        </div>
                    </div>
                </section>
                <section id="features" class="container">
                    <div class="text-center mb-5"><h2 class="display-5">Temel Yetenekler</h2></div>
                    <div class="row g-4">
                        <div class="col-md-4"><div class="card p-4 h-100 border-0 shadow-sm">
                            <i class="bi bi-cpu fs-1 text-primary mb-3"></i><h3>Modüler Çekirdek</h3><p>Auth\'tan SEO\'ya kadar her özellik, değiştirilebilen veya özelleştirilebilen ayrı bir modüldür.</p>
                        </div></div>
                        <div class="col-md-4"><div class="card p-4 h-100 border-0 shadow-sm">
                            <i class="bi bi-shield-check fs-1 text-primary mb-3"></i><h3>Shield Güvenliği</h3><p>Çok gruplu RBAC ve oturum güvenliği için CodeIgniter Shield ile yerel olarak entegre olur.</p>
                        </div></div>
                        <div class="col-md-4"><div class="card p-4 h-100 border-0 shadow-sm">
                            <i class="bi bi-globe fs-1 text-primary mb-3"></i><h3>Küresel Hazırlık</h3><p>Locale önekli URL\'ler ve çeviri yönetimi ile tam çoklu dil desteği sunar.</p>
                        </div></div>
                    </div>
                </section>',
                'seo' => json_encode(['description' => 'CI4MS Anasayfa']),
            ],
            [
                'pages_id' => $contactPageId,
                'lang'     => 'en',
                'title'    => 'Contact Us',
                'seflink'  => 'contact',
                'content'  => '
                <section class="container py-5">
                    <div class="row align-items-center">
                        <div class="col-md-6"><h2 class="display-5 mb-4">Connect with CI4MS Experts</h2><p class="lead mb-5">Have questions about integrating CI4MS into your infrastructure? Our team is ready to assist.</p>
                        <div class="mb-4 d-flex gap-3"><i class="bi bi-envelope-at text-primary fs-3"></i> <div><h5>Email</h5><p>experts@ci4ms.pro</p></div></div>
                        <div class="d-flex gap-3"><i class="bi bi-telephone text-primary fs-3"></i> <div><h5>Call Us</h5><p>+1 (800) CI4-CORE</p></div></div></div>
                        <div class="col-md-6"><div class="card p-5 border-0 shadow-lg"><h3>Inquiry Form</h3><p>Coming soon...</p></div></div>
                    </div>
                </section>',
                'seo' => json_encode(['description' => 'Contact CI4MS']),
            ],
            [
                'pages_id' => $contactPageId,
                'lang'     => 'tr',
                'title'    => 'İletişim',
                'seflink'  => 'iletisim',
                'content'  => '
                <section class="container py-5">
                    <div class="row align-items-center">
                        <div class="col-md-6"><h2 class="display-5 mb-4">CI4MS Uzmanlarıyla Bağlantı Kurun</h2><p class="lead mb-5">CI4MS\'i altyapınıza entegre etme konusunda sorularınız mı var? Ekibimiz yardıma hazır.</p>
                        <div class="mb-4 d-flex gap-3"><i class="bi bi-envelope-at text-primary fs-3"></i> <div><h5>E-posta</h5><p>uzman@ci4ms.pro</p></div></div>
                        <div class="d-flex gap-3"><i class="bi bi-telephone text-primary fs-3"></i> <div><h5>Bize Ulaşın</h5><p>+90 (850) CI4-MS00</p></div></div></div>
                        <div class="col-md-6"><div class="card p-5 border-0 shadow-lg"><h3>Talep Formu</h3><p>Yakında...</p></div></div>
                    </div>
                </section>',
                'seo' => json_encode(['description' => 'CI4MS İletişim']),
            ],
        ]);
    }

    /**
     * `blog` + `blog_langs` tablolarına 3 örnek yazı ekler (`blog_langs.blog_id`
     * FK'lı olduğu için önce `blog` satırı yazılıp insert ID alınır).
     *
     * @param CommonModel $commonModel
     *
     * @return void
     */
    private function seedBlog(CommonModel $commonModel): void
    {
        $blogs = [
            [
                'en' => ['title' => 'Why Modular Architecture is the Future', 'slug' => 'modular-architecture-future', 'summary' => 'Explore the benefits of modular design in software development.'],
                'tr' => ['title' => 'Neden Modüler Mimari Gelecek?', 'slug' => 'moduler-mimari-gelecek', 'summary' => 'Yazılım geliştirmede modüler tasarımın avantajlarını keşfedin.'],
            ],
            [
                'en' => ['title' => 'Optimizing CodeIgniter 4 for Scale', 'slug' => 'optimizing-ci4-scale', 'summary' => 'Advanced tips for high-traffic CI4 applications.'],
                'tr' => ['title' => 'Hız ve Ölçek için CodeIgniter 4 Optimizasyonu', 'slug' => 'ci4-optimizasyon', 'summary' => 'Yüksek trafikli CI4 uygulamaları için ileri düzey ipuçları.'],
            ],
            [
                'en' => ['title' => 'Building Custom Modules for CI4MS', 'slug' => 'building-modules', 'summary' => 'A step-by-step guide to building your first module.'],
                'tr' => ['title' => 'CI4MS İçin Özel Modül Geliştirme', 'slug' => 'modul-gelistirme', 'summary' => 'İlk modülünüzü oluşturmak için adım adım kılavuz.'],
            ],
        ];

        foreach ($blogs as $b) {
            $blogId = $commonModel->create('blog', ['isActive' => 1, 'author' => 1]);

            $commonModel->create('blog_langs', [
                'blog_id' => $blogId,
                'lang'    => 'en',
                'title'   => $b['en']['title'],
                'seflink' => $b['en']['slug'],
                'content' => '<p>Modular architecture allows developers to separate concerns effectively. In CI4MS, each module has its own routes, controllers, and views...</p>',
                'seo'     => json_encode(['description' => $b['en']['summary']]),
            ]);
            $commonModel->create('blog_langs', [
                'blog_id' => $blogId,
                'lang'    => 'tr',
                'title'   => $b['tr']['title'],
                'seflink' => $b['tr']['slug'],
                'content' => '<p>Modüler mimari, geliştiricilerin sorumlulukları etkili bir şekilde ayırmasını sağlar. CI4MS\'de her modülün kendi rotaları, denetleyicileri ve görünümleri vardır...</p>',
                'seo'     => json_encode(['description' => $b['tr']['summary']]),
            ]);
        }
    }

    /**
     * `menu` tablosuna anasayfa/blog/iletişim satırlarını yazar; `pages_id`
     * değerleri `seedPages()`'ten dinamik olarak alınır (`blog` satırı
     * `urlType='url'`, `pages_id=null` -- `InstallService.php:209` deseninin
     * aynısı, dokunulmaz).
     *
     * @param CommonModel $commonModel
     * @param int         $homePageId
     * @param int         $contactPageId
     *
     * @return void
     */
    private function seedMenu(CommonModel $commonModel, int $homePageId, int $contactPageId): void
    {
        $commonModel->createMany('menu', [
            ['title' => 'Frontend.home', 'seflink' => '/', 'queue' => 1, 'urlType' => 'pages', 'pages_id' => $homePageId],
            ['title' => 'Frontend.blog', 'seflink' => 'blog', 'queue' => 2, 'urlType' => 'url', 'pages_id' => null],
            ['title' => 'Frontend.contact', 'seflink' => 'contact', 'queue' => 3, 'urlType' => 'pages', 'pages_id' => $contactPageId],
        ]);
    }

    /**
     * `settings` tablosuna varsayılan `Config\App`/`Config\Security`/...
     * satırlarını yazar. `siteName` SABİT `'CI4MS'` -- bu seeder parametre
     * almadığı için `InstallService.php:217`'deki `??`'siz `$args['siteName']`
     * erişimi burada tekrarlanmaz. `homePage` değeri `seedPages()`'ten
     * dinamik olarak alınır (sabit `1` DEĞİL).
     *
     * @param CommonModel $commonModel
     * @param int         $homePageId
     *
     * @return void
     */
    private function seedSettings(CommonModel $commonModel, int $homePageId): void
    {
        $encrypter = \Config\Services::encrypter();
        $now       = date('Y-m-d H:i:s');

        $settings = [
            ['class' => 'Config\\App', 'key' => 'templateInfos', 'value' => '{"path":"default","name":null,"widgets":{"sidebar":{"searchWidget":"true","categoriesWidget":"true"}}}', 'type' => 'string', 'context' => null],
            ['class' => 'Config\\App', 'key' => 'siteName', 'value' => 'CI4MS', 'type' => 'string', 'context' => null],
            ['class' => 'Config\\App', 'key' => 'logo', 'value' => '/media/logo.webp', 'type' => 'string', 'context' => null],
            ['class' => 'Config\\App', 'key' => 'socialNetwork', 'value' => '[{"smName":"facebook","link":"https:\\/\\/facebook.com\\/bertugfahriozer"},{"smName":"twitter","link":"https:\\/\\/twitter.com\\/bertugfahriozer"},{"smName":"github","link":"https:\\/\\/github.com\\/bertugfahriozer"}]', 'type' => 'string', 'context' => null],
            ['class' => 'Config\\App', 'key' => 'contact', 'value' => '{"address":"Bal\\u0131kesir \\/ Turkey","phone":"+905000000000","email":"info@ci4ms.com"}', 'type' => 'string', 'context' => null],
            ['class' => 'Config\\App', 'key' => 'mail', 'value' => '{"server": "mail.ci4ms.com","port": "26","address": "simple@ci4ms.com","password": "' . base64_encode($encrypter->encrypt('123456789')) . '","protocol": "smtp","tls": false}', 'type' => 'string', 'context' => null],
            ['class' => 'Gmap', 'key' => 'map_iframe', 'value' => null, 'type' => 'NULL', 'context' => null],
            ['class' => 'Config\\App', 'key' => 'slogan', 'value' => 'My First Ci4MS Project', 'type' => 'string', 'context' => null],
            ['class' => 'Config\\App', 'key' => 'maintenanceMode', 'value' => '0', 'type' => 'boolean', 'context' => null],
            ['class' => 'Config\\App', 'key' => 'homePage', 'value' => $homePageId, 'type' => 'integer', 'context' => null],
            ['class' => 'Config\\App', 'key' => 'siteLanguageMode', 'value' => 'single', 'type' => 'string', 'context' => null],
            ['class' => 'Config\\Security', 'key' => 'allowedFiles', 'value' => '["image\\/x-ms-bmp","image\\/gif","image\\/jpeg","image\\/png","image\\/x-icon","text\\/plain","image\\/webp"]', 'type' => 'string', 'context' => null],
            ['class' => 'Config\\Security', 'key' => 'badwords', 'value' => '{"status": 1, "autoReject": 0, "autoAccept": 1, "list": []}', 'type' => 'string', 'context' => null],
            ['class' => 'Elfinder', 'key' => 'convertWebp', 'value' => '1', 'type' => 'boolean', 'context' => null],
            ['class' => 'Config\\App', 'key' => 'defaultLocale', 'value' => 'en', 'type' => 'string', 'context' => null],
            ['class' => 'Modules\\Auth\\Config\\Auth', 'key' => 'geoLookupEnabled', 'value' => '0', 'type' => 'boolean', 'context' => null],
        ];

        // settings.created_at/updated_at NOT NULL ve default'suz gelir
        // (codeigniter4/settings migration'ı). InstallService.php:234-237'deki
        // aynı gerekçe.
        $commonModel->createMany('settings', array_map(
            static fn (array $row): array => $row + ['created_at' => $now, 'updated_at' => $now],
            $settings
        ));
    }
}

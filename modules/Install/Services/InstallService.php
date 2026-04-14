<?php

namespace Modules\Install\Services;

use CodeIgniter\Shield\Entities\User;

class InstallService
{
    public function createDefaultData(array $args)
    {
        $commonModel = new \ci4commonmodel\CommonModel();
        $commonModel->create('auth_groups', [
            "group" => "superadmin",
            "description" => "superadmin"
        ]);

        $users = auth()->getProvider();
        $user = new User([
            'firstname' => $args['fname'],
            'surname'   => $args['sname'],
            'username'  => $args['username'],
            'email'     => $args['email'],
            'password'  => $args['password'],
            'active'    => 1
        ]);

        if ($users->save($user)) {
            $userId = $users->getInsertID();
            $commonModel->create('auth_groups_users', [
                'user_id'    => $userId,
                'group'      => 'superadmin',
                'created_at' => date('Y-m-d H:i:s')
            ]);
        }

        // Modülleri ve sistem rotalarını dinamik olarak klasör ve Config yapılarından tara
        $scanner = new \Modules\Methods\Libraries\ModuleScanner();
        $scanner->runScan();

        $commonModel->createMany('languages',[
            [
                'code'        => 'tr',
                'name'        => 'Türkçe',
                'native_name' => 'tr',
                'flag'        => 'fi fi-tr',
                'direction'   => 'ltr',
                'is_default'  => 0,
                'is_active'   => 1,
                'is_frontend' => 1,
                'sort_order'  => 1
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
                'sort_order'  => 0
            ],
        ]);

        $commonModel->createMany('pages', [
            ['id' => 1, 'isActive' => 1, 'inMenu' => 1],
            ['id' => 2, 'isActive' => 1, 'inMenu' => 1],
        ]);

        $commonModel->createMany('pages_langs', [
            [
                'pages_id' => 1,
                'lang' => 'en',
                'title' => 'The Future of Modular Management',
                'seflink' => 'homepage',
                'content' => '
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
                'seo' => json_encode(['description' => 'CI4MS Homepage'])
            ],
            [
                'pages_id' => 1,
                'lang' => 'tr',
                'title' => 'Modüler Yönetimin Geleceği',
                'seflink' => 'anasayfa',
                'content' => '
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
                'seo' => json_encode(['description' => 'CI4MS Anasayfa'])
            ],
            [
                'pages_id' => 2,
                'lang' => 'en',
                'title' => 'Contact Us',
                'seflink' => 'contact',
                'content' => '
                <section class="container py-5">
                    <div class="row align-items-center">
                        <div class="col-md-6"><h2 class="display-5 mb-4">Connect with CI4MS Experts</h2><p class="lead mb-5">Have questions about integrating CI4MS into your infrastructure? Our team is ready to assist.</p>
                        <div class="mb-4 d-flex gap-3"><i class="bi bi-envelope-at text-primary fs-3"></i> <div><h5>Email</h5><p>experts@ci4ms.pro</p></div></div>
                        <div class="d-flex gap-3"><i class="bi bi-telephone text-primary fs-3"></i> <div><h5>Call Us</h5><p>+1 (800) CI4-CORE</p></div></div></div>
                        <div class="col-md-6"><div class="card p-5 border-0 shadow-lg"><h3>Inquiry Form</h3><p>Coming soon...</p></div></div>
                    </div>
                </section>',
                'seo' => json_encode(['description' => 'Contact CI4MS'])
            ],
            [
                'pages_id' => 2,
                'lang' => 'tr',
                'title' => 'İletişim',
                'seflink' => 'iletisim',
                'content' => '
                <section class="container py-5">
                    <div class="row align-items-center">
                        <div class="col-md-6"><h2 class="display-5 mb-4">CI4MS Uzmanlarıyla Bağlantı Kurun</h2><p class="lead mb-5">CI4MS\'i altyapınıza entegre etme konusunda sorularınız mı var? Ekibimiz yardıma hazır.</p>
                        <div class="mb-4 d-flex gap-3"><i class="bi bi-envelope-at text-primary fs-3"></i> <div><h5>E-posta</h5><p>uzman@ci4ms.pro</p></div></div>
                        <div class="d-flex gap-3"><i class="bi bi-telephone text-primary fs-3"></i> <div><h5>Bize Ulaşın</h5><p>+90 (850) CI4-MS00</p></div></div></div>
                        <div class="col-md-6"><div class="card p-5 border-0 shadow-lg"><h3>Talep Formu</h3><p>Yakında...</p></div></div>
                    </div>
                </section>',
                'seo' => json_encode(['description' => 'CI4MS İletişim'])
            ]
        ]);

        // 5. Blog Posts
        $blogs = [
            [
                'en' => ['title' => 'Why Modular Architecture is the Future', 'slug' => 'modular-architecture-future', 'summary' => 'Explore the benefits of modular design in software development.'],
                'tr' => ['title' => 'Neden Modüler Mimari Gelecek?', 'slug' => 'moduler-mimari-gelecek', 'summary' => 'Yazılım geliştirmede modüler tasarımın avantajlarını keşfedin.']
            ],
            [
                'en' => ['title' => 'Optimizing CodeIgniter 4 for Scale', 'slug' => 'optimizing-ci4-scale', 'summary' => 'Advanced tips for high-traffic CI4 applications.'],
                'tr' => ['title' => 'Hız ve Ölçek için CodeIgniter 4 Optimizasyonu', 'slug' => 'ci4-optimizasyon', 'summary' => 'Yüksek trafikli CI4 uygulamaları için ileri düzey ipuçları.']
            ],
            [
                'en' => ['title' => 'Building Custom Modules for CI4MS', 'slug' => 'building-modules', 'summary' => 'A step-by-step guide to building your first module.'],
                'tr' => ['title' => 'CI4MS İçin Özel Modül Geliştirme', 'slug' => 'modul-gelistirme', 'summary' => 'İlk modülünüzü oluşturmak için adım adım kılavuz.']
            ]
        ];

        foreach ($blogs as $b) {
            $blogId = $commonModel->create('blog', ['isActive' => 1, 'author' => 1]);
            $commonModel->create('blog_langs', [
                'blog_id' => $blogId,
                'lang' => 'en',
                'title' => $b['en']['title'],
                'seflink' => $b['en']['slug'],
                'content' => '<p>Modular architecture allows developers to separate concerns effectively. In CI4MS, each module has its own routes, controllers, and views...</p>',
                'seo' => json_encode(['description' => $b['en']['summary']])
            ]);
            $commonModel->create('blog_langs', [
                'blog_id' => $blogId,
                'lang' => 'tr',
                'title' => $b['tr']['title'],
                'seflink' => $b['tr']['slug'],
                'content' => '<p>Modüler mimari, geliştiricilerin sorumlulukları etkili bir şekilde ayırmasını sağlar. CI4MS\'de her modülün kendi rotaları, denetleyicileri ve görünümleri vardır...</p>',
                'seo' => json_encode(['description' => $b['tr']['summary']])
            ]);
        }

        $commonModel->createMany('menu', [
            ['title' => 'Frontend.home', 'seflink' => '/',       'queue' => 1, 'urlType' => 'pages', 'pages_id' => 1],
            ['title' => 'Frontend.blog', 'seflink' => 'blog',    'queue' => 2, 'urlType' => 'url',   'pages_id' => null],
            ['title' => 'Frontend.contact', 'seflink' => 'contact', 'queue' => 3, 'urlType' => 'pages', 'pages_id' => 2]
        ]);

        $encrypter = \Config\Services::encrypter();
        $commonModel->createMany(
            'settings',
            array(
                array('class' => 'Config\\App', 'key' => 'templateInfos', 'value' => '{"path":"default","name":null,"widgets":{"sidebar":{"searchWidget":"true","categoriesWidget":"true"}}}', 'type' => 'string', 'context' => NULL),
                array('class' => 'Config\\App', 'key' => 'siteName', 'value' => $args['siteName'], 'type' => 'string', 'context' => NULL),
                array('class' => 'Config\\App', 'key' => 'logo', 'value' => '/media/logo.webp', 'type' => 'string', 'context' => NULL),
                array('class' => 'Config\\App', 'key' => 'socialNetwork', 'value' => '[{"smName":"facebook","link":"https:\\/\\/facebook.com\\/bertugfahriozer"},{"smName":"twitter","link":"https:\\/\\/twitter.com\\/bertugfahriozer"},{"smName":"github","link":"https:\\/\\/github.com\\/bertugfahriozer"}]', 'type' => 'string', 'context' => NULL),
                array('class' => 'Config\\App', 'key' => 'contact', 'value' => '{"address":"Bal\\u0131kesir \\/ Turkey","phone":"+905000000000","email":"info@ci4ms.com"}', 'type' => 'string', 'context' => NULL),
                array('class' => 'Config\\App', 'key' => 'mail', 'value' => '{"server": "mail.ci4ms.com","port": "26","address": "simple@ci4ms.com","password": "' . base64_encode($encrypter->encrypt('123456789')) . '","protocol": "smtp","tls": false}', 'type' => 'string', 'context' => NULL),
                array('class' => 'Gmap', 'key' => 'map_iframe', 'value' => NULL, 'type' => 'NULL', 'context' => NULL),
                array('class' => 'Config\\App', 'key' => 'slogan', 'value' => $args['slogan']??'My First Ci4MS Project', 'type' => 'string', 'context' => NULL),
                array('class' => 'Config\\App', 'key' => 'maintenanceMode', 'value' => '0', 'type' => 'boolean', 'context' => NULL),
                array('class' => 'Config\\App', 'key' => 'homePage', 'value' => 1, 'type' => 'integer', 'context' => NULL),
                array('class' => 'Config\\App', 'key' => 'siteLanguageMode', 'value' => 'single', 'type' => 'string', 'context' => NULL),
                array('class' => 'Config\\Security', 'key' => 'allowedFiles', 'value' => '["image\\/x-ms-bmp","image\\/gif","image\\/jpeg","image\\/png","image\\/x-icon","text\\/plain","image\\/webp"]', 'type' => 'string', 'context' => NULL),
                array('class' => 'Config\\Security', 'key' => 'badwords', 'value' => '{"status": 1, "autoReject": 0, "autoAccept": 1, "list": []}', 'type' => 'string', 'context' => NULL),
                array('class' => 'Elfinder', 'key' => 'convertWebp', 'value' => '1', 'type' => 'boolean', 'context' => NULL),
                array('class' => 'Config\\App', 'key' => 'defaultLocale', 'value' => 'en', 'type' => 'string', 'context' => NULL)
            )
        );
    }
}

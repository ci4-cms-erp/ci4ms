<?php

declare(strict_types=1);

namespace Tests\Feature;

use ci4commonmodel\CommonModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Modules\Install\Services\InstallService;

/**
 * `InstallService::createDefaultData()`'nın çok-bloklu idempotency guard
 * tasarımının regresyon kilidi.
 *
 * `$refresh=false` + açık `$namespace=null` kullanılır: `DatabaseTestTrait`'in
 * `$refresh=true` davranışı `regressDatabase()` üzerinden `setNamespace()`'i
 * yok sayıp paylaşımlı `ci4ms_test` şemasındaki TÜM namespace'leri düşürür
 * (`vendor/codeigniter4/framework/system/Database/MigrationRunner.php:
 * 289-291`) — aynı kök neden `tests/Feature/InstallTest.php` ve
 * `tests/Feature/Ci4msReferenceDataSeederTest.php`'de de belgelendi.
 *
 * Bu suite'te transaction sarmalama YOKTUR: `InstallService::createDefaultData()`
 * içindeki `CommonModel`, testin kendi `$this->db`'sinden (grup `tests`) AYRI
 * bir bağlantı üzerinden çalışır (`app/Config/Database.php:200-209` —
 * `ENVIRONMENT === 'testing'` iken `$this->default = $this->tests` atanır,
 * ama `Config\Database::connect('default')` yine de `tests` grubundan FARKLI
 * bir bağlantı NESNESİ açar; aynı şemaya bağlanır, aynı transaction'a
 * KATILMAZ). `$this->db->transStart()/transRollback()` bu yüzden
 * `CommonModel`'in yazımlarını geri almaz — `Ci4msReferenceDataSeederTest`
 * ile aynı gerekçe, aynı desen: kalıcı yazımlar hedefli `remove()`/`edit()`
 * ile testin kendi içinde geri alınır.
 *
 * @internal
 */
final class InstallServiceIdempotencyTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $refresh = false;

    protected $namespace = null;

    protected $migrateOnce = true;

    protected function setUp(): void
    {
        parent::setUp();

        // Risk-1 kanaryası: bu suite yanlışlıkla canlı `ci4ms` şemasına karşı
        // koşarsa burada durmalı, sessizce canlı veriye yazmamalı.
        $this->assertStringContainsString(
            'ci4ms_test',
            (string) \Config\Database::connect()->database,
            'Bu suite yalnızca atılabilir ci4ms_test şemasına karşı koşmalı, canlı veritabanına asla.',
        );
    }

    /**
     * `auth_groups`/`auth_groups_users`'ta superadmin kimliği eksikken
     * `createDefaultData()` çağrıldığında kimliğin tamamlandığını ve içerik
     * tablolarının (`pages`/`pages_langs`/`blog`/`menu`/`languages`)
     * DOKUNULMADAN kaldığını doğrular.
     *
     * `ci4ms_test` paylaşımlı olduğu ve bu oturumda superadmin kimliği zaten
     * kurulu olduğu için (bkz. context.md, ci4ms-developer'ın bir önceki
     * turu), eksik-kimlik senaryosu gerçek `auth_groups`/`auth_groups_users`
     * satırlarının `group` sütununu GEÇİCİ olarak yeniden adlandırarak
     * simüle edilir (DELETE/TRUNCATE YOK — yalnız `edit()` ile rename).
     * Guard'ın yarattığı YENİ satırlar (yeni grup, yeni bağlantı, yeni
     * Shield kullanıcısı + `auth_identities` satırı) `finally` içinde
     * `remove()` ile geri alınır, gerçek superadmin satırları aynı `id` ile
     * orijinal `group='superadmin'` değerine geri döndürülür — metod
     * bittiğinde DB tam olarak başlangıç durumuna eşittir. Eğer ortamda
     * superadmin kimliği zaten doğal olarak eksikse (gelecekteki bir koşum),
     * rename adımı hiç tetiklenmez ve senaryo doğal haliyle gözlemlenir.
     *
     * @return void
     */
    public function testCreateDefaultDataCompletesMissingIdentityWithoutTouchingContent(): void
    {
        $commonModel = new CommonModel();
        $db          = \Config\Database::connect();

        $contentTables = ['pages', 'pages_langs', 'blog', 'menu', 'languages'];
        $before        = [];
        foreach ($contentTables as $table) {
            $before[$table] = $commonModel->count($table);
        }

        $originalGroup = $commonModel->selectOne('auth_groups', ['group' => 'superadmin']);
        $originalLinks = $db->table('auth_groups_users')->where('group', 'superadmin')->get()->getResult();

        $tempGroupName   = 'superadmin_bak_qa_' . time() . '_' . random_int(1000, 9999);
        $groupWasRenamed = false;
        $linkIdsRenamed  = [];

        if ($originalGroup !== null) {
            $groupWasRenamed = $commonModel->edit('auth_groups', ['group' => $tempGroupName], ['id' => $originalGroup->id]);
            $this->assertTrue(
                $groupWasRenamed,
                'Fixture hazırlığı: gerçek superadmin grubu geçici olarak yeniden adlandırılamadı.',
            );

            foreach ($originalLinks as $link) {
                $renamed = $commonModel->edit('auth_groups_users', ['group' => $tempGroupName], ['id' => $link->id]);
                $this->assertTrue(
                    $renamed,
                    'Fixture hazırlığı: gerçek superadmin bağlantısı geçici olarak yeniden adlandırılamadı.',
                );
                $linkIdsRenamed[] = $link->id;
            }
        }

        $newGroupId = null;
        $newLinkId  = null;
        $newUserId  = null;

        try {
            $this->assertSame(
                0,
                $commonModel->count('auth_groups', ['group' => 'superadmin']),
                'Fixture hazırlığı doğrulanamadı: superadmin grubu hâlâ görünür.',
            );
            $this->assertSame(
                0,
                $commonModel->count('auth_groups_users', ['group' => 'superadmin']),
                'Fixture hazırlığı doğrulanamadı: superadmin bağlantısı hâlâ görünür.',
            );

            $marker         = 'qa_installsvc_' . time() . '_' . random_int(1000, 9999);
            $installService = new InstallService();
            $installService->createDefaultData([
                'fname'    => 'QA',
                'sname'    => 'InstallService',
                'username' => $marker,
                'email'    => $marker . '@example.com',
                'password' => 'SuperSecret123!',
                'siteName' => 'CI4MS Test System',
            ]);

            $newGroup = $commonModel->selectOne('auth_groups', ['group' => 'superadmin']);
            $this->assertNotNull($newGroup, 'InstallService eksik superadmin grubunu tamamlamadı.');
            $newGroupId = (int) $newGroup->id;

            $newLink = $commonModel->selectOne('auth_groups_users', ['group' => 'superadmin']);
            $this->assertNotNull($newLink, 'InstallService eksik superadmin kullanıcı bağlantısını tamamlamadı.');
            $newLinkId = (int) $newLink->id;
            $newUserId = (int) $newLink->user_id;

            foreach ($contentTables as $table) {
                $this->assertSame(
                    $before[$table],
                    $commonModel->count($table),
                    sprintf('"%s" tablosu kimlik guard\'ı tarafından yeniden yazılmamalıydı.', $table),
                );
            }
        } finally {
            if ($newLinkId !== null) {
                $commonModel->remove('auth_groups_users', ['id' => $newLinkId]);
            }
            if ($newGroupId !== null) {
                $commonModel->remove('auth_groups', ['id' => $newGroupId]);
            }
            if ($newUserId !== null) {
                $db->table('auth_identities')->where('user_id', $newUserId)->delete();
                $commonModel->remove('users', ['id' => $newUserId]);
            }

            if ($groupWasRenamed && $originalGroup !== null) {
                $commonModel->edit('auth_groups', ['group' => 'superadmin'], ['id' => $originalGroup->id]);
            }
            foreach ($linkIdsRenamed as $id) {
                $commonModel->edit('auth_groups_users', ['group' => 'superadmin'], ['id' => $id]);
            }
        }

        // Fixture kaldırıldıktan sonra gerçek superadmin kimliğinin eksiksiz
        // geri döndüğünü doğrula.
        if ($originalGroup !== null) {
            $restoredGroup = $commonModel->selectOne('auth_groups', ['id' => $originalGroup->id]);
            $this->assertNotNull($restoredGroup, 'Gerçek superadmin grubu geri yüklenemedi.');
            $this->assertSame('superadmin', $restoredGroup->group);
        }
        foreach ($originalLinks as $link) {
            $restoredLink = $commonModel->selectOne('auth_groups_users', ['id' => $link->id]);
            $this->assertNotNull($restoredLink, 'Gerçek superadmin bağlantısı geri yüklenemedi.');
            $this->assertSame('superadmin', $restoredLink->group);
        }
    }

    /**
     * Tüm ilgili tablolar (kimlik + içerik) zaten doluyken `createDefaultData()`
     * ikinci kez çağrıldığında hiçbir tabloya yeni satır eklenmediğini,
     * hiçbir PK/unique çakışması veya exception oluşmadığını doğrular.
     *
     * `ci4ms_test` bu oturumda doğal olarak tüm ilgili tabloları dolu
     * durumda tutuyor (bkz. context.md, ci4ms-build-lead/ci4ms-developer'ın
     * bir önceki turdaki ölçümleri) — bu senaryo yapay fixture gerektirmeden
     * doğal olarak gözlemlenebilir.
     *
     * @return void
     */
    public function testCreateDefaultDataIsNoOpWhenEverythingAlreadyExists(): void
    {
        $commonModel = new CommonModel();

        $tables = [
            'auth_groups', 'auth_groups_users', 'users',
            'pages', 'pages_langs', 'blog', 'blog_langs',
            'menu', 'languages', 'settings',
        ];

        $before = [];
        foreach ($tables as $table) {
            $before[$table] = $commonModel->count($table);
        }

        if ($before['auth_groups'] === 0 || $before['auth_groups_users'] === 0) {
            $this->markTestSkipped(
                'ci4ms_test kimlik tabloları (auth_groups/auth_groups_users) bu ortamda boş,'
                . ' "tam dolu DB" senaryosu gözlemlenemiyor — no-op kanıtı zaten'
                . ' testCreateDefaultDataCompletesMissingIdentityWithoutTouchingContent\'te dolaylı olarak var.',
            );
        }

        $marker         = 'qa_second_call_' . time() . '_' . random_int(1000, 9999);
        $installService = new InstallService();
        $installService->createDefaultData([
            'fname'    => 'Second',
            'sname'    => 'Call',
            'username' => $marker,
            'email'    => $marker . '@example.com',
            'password' => 'SuperSecret123!',
            'siteName' => 'CI4MS Test System',
        ]);

        foreach ($tables as $table) {
            $this->assertSame(
                $before[$table],
                $commonModel->count($table),
                sprintf('"%s" tablosu ikinci çağrıda değişmemeliydi (idempotency ihlali).', $table),
            );
        }
    }
}

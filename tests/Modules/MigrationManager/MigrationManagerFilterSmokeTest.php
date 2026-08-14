<?php

declare(strict_types=1);

namespace Tests\Modules\MigrationManager;

use CodeIgniter\Security\Exceptions\SecurityException;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Test\TestResponse;

/**
 * `backendGuard` filtresinin `backend/migration-manager` route grubuna
 * gerçekten bağlı olduğunun regresyon bekçisi (HIGH risk kabul testi,
 * context.md "modül $filters unutulursa çift fail-open").
 *
 * Config mutasyona uğratılmaz (invasive/fragile); gerçek HTTP isteği
 * gönderilip oturumsuz erişimin engellendiği doğrulanır. `backendGuard`
 * yanlışlıkla `MigrationManagerConfig::$filters`'tan kaldırılırsa bu test
 * KIRMIZI olur.
 *
 * DB gerekmez: `backendGuard` zincirinin ilk elemanı olan
 * `CodeIgniter\Shield\Filters\SessionAuth`, hiç oturum olmadığında DB'ye
 * hiç gitmeden login'e yönlendirir (`app/Config/Filters.php:181-189`).
 *
 * @internal
 */
final class MigrationManagerFilterSmokeTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    /**
     * `actingAs()` çağıran BAŞKA bir test dosyası (bu dizinin dışında da
     * olabilir — bkz. `tests/Feature/SecurityXSSCSRFTest.php`,
     * `MigrationManagerControllerTest.php`, `MigrationManagerViewRenderTest.php`)
     * aynı PHPUnit sürecinde BU testten ÖNCE çalışırsa, Shield'ın paylaşımlı
     * `Auth` singleton'ı "giriş yapılmış" durumda kalabilir — çünkü
     * `actingAs()` süreç-paylaşımlı Session authenticator'a yazıyor ve onu
     * geri almıyor (established kök neden, üç dosyada zaten belgeli).
     * Bu test "oturumsuz" bir isteği doğruladığı için savunmasız kalırsa
     * SESSİZCE yanlış-yeşil (veya bu görevde ölçüldüğü gibi rastgele sırada
     * yanlış-kırmızı: durum 200) verir. `resetSingle('auth')`, hangi test
     * bu testten önce çalışırsa çalışsın, HER ÇALIŞMADA taze/anonim bir Auth
     * durumu garanti eder — yukarı akıştaki her olası sızıntıyı tek tek
     * avlamak yerine (o iş bu görevin kapsamı dışında, ayrı bir takip
     * gerektirir) bu test kendi başına savunmalı hale getirildi.
     */
    protected function setUp(): void
    {
        parent::setUp();

        \Config\Services::resetSingle('auth');
    }

    /**
     * Oturumsuz bir GET isteği index route'una (`backend/migration-manager`)
     * login'e yönlendirilir veya 401/403 döner.
     */
    public function testAnonymousGetToIndexIsNeverServed(): void
    {
        $result = $this->get('backend/migration-manager');

        $this->assertAnonymousRequestWasRejected($result);
    }

    /**
     * Oturumsuz bir POST isteği çalıştırma ucuna (`run-migration`) login'e
     * yönlendirilir veya 401/403 döner — hiçbir koşulda controller'a ulaşmaz.
     *
     * `run-migration` route'unun before-filter sırası `devgate csrf
     * backendGuard throttle:strict`'tir (bkz. Görev 16 kanıtı, context.md) —
     * CSRF, backendGuard'dan ÖNCE devrede. Testing ortamında
     * `Config\Security::$redirect` `false` olduğundan token'sız bir POST
     * `SecurityException` fırlatır; `FeatureTestTrait::call()` bunu bir
     * `TestResponse`'a çevirmeden ham exception olarak yükseltir (established
     * gözlem: `UpdateRollbackRouteTest::setUp()`'ın CSRF'i module'ün kendi
     * opt-out API'siyle atlama gerekçesiyle aynı davranış). Bu exception'ın
     * fırlaması TEK BAŞINA "istek controller'a asla ulaşmadı" iddiasını
     * kanıtlar (backendGuard'a gelmeden reddedildi) — 403/redirect ile aynı
     * güvenlik sonucudur, bu yüzden geçerli bir "reddedildi" hali olarak
     * kabul edilir.
     */
    public function testAnonymousPostToRunMigrationIsNeverServed(): void
    {
        try {
            $result = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
                ->post('backend/migration-manager/run-migration', ['namespace' => 'App']);
        } catch (SecurityException $e) {
            $this->assertStringContainsString('not allowed', strtolower($e->getMessage()));

            return;
        }

        $this->assertAnonymousRequestWasRejected($result);
    }

    private function assertAnonymousRequestWasRejected(TestResponse $result): void
    {
        $status = $result->response()->getStatusCode();

        $this->assertTrue(
            $result->isRedirect() || in_array($status, [401, 403], true),
            "Expected a redirect or a 401/403 response for an anonymous request, got status {$status}.",
        );
    }
}

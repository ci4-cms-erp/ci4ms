<?php

namespace Tests\Feature;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Test\DatabaseTestTrait;
use Modules\Auth\Models\UserModel;
use CodeIgniter\Shield\Test\AuthenticationTesting;

class SecurityXSSCSRFTest extends CIUnitTestCase
{
    use FeatureTestTrait;
    use DatabaseTestTrait;
    use AuthenticationTesting;

    // Same root cause and fix shape as tests/Feature/InstallTest.php:
    // $refresh=true forces DatabaseTestTrait::regressDatabase(), which
    // ignores setNamespace() and drops every namespace's tables in the
    // shared ci4ms_test schema, not just this class's own fixtures
    // (vendor/codeigniter4/framework/system/Database/MigrationRunner.php:
    // 289-291).
    protected $refresh = false;

    protected $namespace = null;

    protected function setUp(): void
    {
        parent::setUp();
        // Bütün modüllerin veritabanını hazırla
        $migrate = \Config\Services::migrations();
        $migrate->setNamespace(null)->latest();

        // Migration'lar yalnız şemayı kurar. auth_permissions_pages (Methods
        // tarayıcısı), languages, settings ve kimlik ise kurulum adımında dolar;
        // bunlar olmadan Ci4MsAuthFilter superadmin'i bile 403'e atar ve
        // LocaleFilter isteği ana sayfaya yönlendirir, yani test yanlış şeyi ölçer.
        //
        // Koşulsuz çağrı güvenli: InstallService::createDefaultData() artık her
        // tabloyu (auth_groups, auth_groups_users, languages, pages, blog, menu,
        // settings) bağımsız kontrol edip yalnız eksik olanı yazıyor -- ikinci
        // çağrı no-op. Eski tek `languages===0` dış guard'ı bu tablo-başına
        // idempotency'den ÖNCEYDİ ve paylaşımlı şemada `languages` başka bir
        // testten dolayı doluysa, `auth_groups`/`auth_groups_users` boş olsa bile
        // bu sınıfın kendi kimlik oluşturmasını kalıcı olarak atlıyordu. Admin
        // aramasının neden username yerine grup üyeliğiyle yapıldığı için aşağıya
        // (testXssValidationOnUserCreation içindeki superadminUser() çağrısı) bak.
        (new \Modules\Install\Services\InstallService())->createDefaultData([
            'fname'    => 'Seed',
            'sname'    => 'Admin',
            'username' => 'seedadmin',
            'email'    => 'seedadmin@example.com',
            'password' => 'SuperSecret123!',
            'siteName' => 'CI4MS Test System',
        ]);

        cache()->clean();
    }

    protected function tearDown(): void
    {
        // testXssValidationOnUserCreation() writes the CSRF hash straight into
        // the superglobal (see below); the token name itself is a fixed config
        // value (app/Config/Security.php: $tokenName = 'csrf_test_name'), not a
        // per-request random, so it can be looked up here without needing the
        // test method's local $tokenName variable.
        unset($_SESSION[\Config\Services::security()->getTokenName()]);

        // testXssValidationOnUserCreation() also injects a mock Filters config
        // (CSRF-exempting this route) via Factories::injectMock('config', ...).
        // That instance is process-shared and otherwise survives into every
        // later test in the same PHPUnit run. Scoped to the 'config' component
        // only, matching the existing precedent in
        // tests/Modules/Settings/UpdateRollbackRouteTest.php:92-97 — a bare
        // Factories::reset() would also drop unrelated cached services/models.
        \CodeIgniter\Config\Factories::reset('config');

        // The two injectMock/$_SESSION cleanups above are not enough on their
        // own (verified empirically): CodeIgniter\Validation\Validation::run()
        // never clears $this->errors — only its own reset() does — so the
        // shared 'validation' service (Config\Services::validation(),
        // getShared=true) keeps testXssValidationOnUserCreation()'s
        // 'firstname'/'own_language' errors after this class finishes. Every
        // later controller in the same PHPUnit process that calls
        // Controller::validate() (e.g. Fileeditor::saveFile()) then inherits
        // those stale errors merged into its own, and Controller::validate()
        // reports failure even though the later request's own fields were
        // valid. resetSingle() is BaseService's own test-only API for
        // discarding one shared/mock service so the next service('validation')
        // call builds a fresh instance, instead of reusing the same polluted
        // singleton or the broader Services::reset()/resetFactories(), which
        // would also drop every other cached service in the process.
        \Config\Services::resetSingle('validation');

        // actingAs() (CodeIgniter\Shield\Test\AuthenticationTesting) logs the
        // user in on the *shared* Session authenticator instance cached inside
        // Shield's Auth facade (vendor/codeigniter4/shield/src/Auth.php:47,60-70
        // -> Authentication::factory(), which caches by alias in
        // vendor/codeigniter4/shield/src/Authentication/Authentication.php:30,52-54).
        // login() sets that instance's private $userState to STATE_LOGGED_IN
        // (Authenticators/Session.php:271) and caches the User entity in
        // $this->user. Because $userState is an *object property*, not derived
        // from $_SESSION, Session::checkUserState() short-circuits on every
        // later call (Authenticators/Session.php:414-419) and never re-reads
        // $_SESSION again — so wiping $_SESSION per request in
        // FeatureTestTrait::call() (vendor/codeigniter4/framework/system/Test/
        // FeatureTestTrait.php:203,413) does not undo the login. The 'auth'
        // service is itself shared for the whole PHPUnit process
        // (CodeIgniter\Shield\Config\Services::auth(), getShared=true), so this
        // stale "still logged in as an admin that this test's own transaction
        // rollback just deleted" state survives into whichever test happens to
        // run next. Verified empirically: with this test class present,
        // `vendor/bin/phpunit --order-by=reverse tests/Feature` makes
        // SecurityAuthTest::testGuestIsRedirectedToLoginFromBackend fail with
        // "Attempt to read property force_reset on null" inside Shield's
        // ForcePasswordResetFilter, because it now finds a logged-in user whose
        // identity row no longer exists. resetSingle() discards the cached Auth
        // instance (and with it the cached Authentication/Session instances),
        // so the next service('auth') call builds everything fresh and derives
        // login state from the then-current (already-wiped) $_SESSION again.
        \Config\Services::resetSingle('auth');

        parent::tearDown();
    }

    public function testCsrfProtectionPreventsPostRequestsWithoutToken()
    {
        $result = $this->withSession()->post('backend/users/create_user', [
            'firstname' => 'Test',
            'surname'   => 'User',
            'email'     => 'test@example.com',
            'username'  => 'testcsrf',
            'password'  => 'Password123!',
            'group'     => [1]
        ]);

        // CI4 CSRF filtresi token olmadığında genellikle 403 HTTP Access Denied 
        // veya güvenlik ayarına göre eski sayfaya (302, 301, vs) redirect döner.
        $status = $result->response()->getStatusCode();
        $this->assertTrue(in_array($status, [403, 302, 303]), 'CSRF eksik olmasına rağmen sistem 403 veya 30x döndürmedi. Mevcut kod: ' . $status);
    }

    public function testXssValidationOnUserCreation()
    {
        // Bu test XSS doğrulamasını ölçer, CSRF'i değil (onu yukarıdaki test
        // kapsıyor). Security.redirect bu ortamda true olduğu için token
        // uyuşmazlığı istisna yerine ana sayfaya redirect üretir ve test hiç
        // controller'a ulaşmadan "yanlış sebeple" düşer; bu yüzden rota
        // modülün kendi opt-out API'siyle CSRF dışında bırakılır.
        $filters = config(\Config\Filters::class);
        $filters->mergeCsrfExcept(['backend/users/create_user']);
        \CodeIgniter\Config\Factories::injectMock('config', 'Filters', $filters);

        // Kurulumun ürettiği admin kullanılır: elle insert edilen kullanıcı
        // active=0 kalır ve Ci4MsAuthFilter onu daha ilk adımda dışarı atar,
        // böylece test XSS doğrulamasını hiç ölçmeden "geçmiş" görünür.
        $admin = $this->superadminUser();
        $this->assertNotNull($admin, 'Kurulum superadmin kimliği oluşturmadı');
        $this->assertTrue($admin->inGroup('superadmin'), 'Seed admin superadmin değil');


        // 2. Doğrudan Config üzerinden CSRF token bypass mekanizması kurguluyoruz.
        $security = \Config\Services::security();
        $tokenName = $security->getTokenName();
        $hash = $security->generateHash();
        
        $payload = [
            'firstname' => '<script>alert("xss")</script>',
            'surname'   => 'User',
            'email'     => 'xss@example.com',
            'username'  => 'xssuser',
            'password'  => 'Password123!',
            'group'     => [1],
            $tokenName  => $hash
        ];

        $_SESSION[$tokenName] = $hash;

        // Shield'ın test trait fonksiyonu sayesinde ilgili kullanıcı yetkisiyle backend route'larına girebiliyorum.
        $result = $this->actingAs($admin)
                       ->withSession([$tokenName => $hash])
                       ->post('backend/users/create_user', $payload);

        // Debug bilgisi alalım
        $statusCode = $result->response()->getStatusCode();
        $location = $result->response()->getHeaderLine('Location');

        // Validation hatası durumunda Shield/CI4 genellikle bir önceki sayfaya (create_user) yönlendirir.
        // Eğer auth/a/show'a gidiyorsa validation geçilmiş ve Shield aktivasyon sürecine girmiş demektir.
        $this->assertEquals(302, $statusCode, "Beklenen redirect (302) gerçekleşmedi. Mevcut: $statusCode");
        $this->assertStringContainsString('backend/users/create_user', $location, "Validation TAKILMALIYDI ama sistem kullanıcıyı aktivasyon/farklı bir sayfaya yönlendirdi: $location");

        // Form session içine validation error fırlattıysa XSS güvenliği aktif edilmiş demektir
        $errors = session('errors');
        $this->assertIsArray($errors, 'Validation errors dizisi dönmedi! XSS payloadı doğrulama kurallarını (regex) aşmış olabilir.');
        if (is_array($errors)) {
            $this->assertArrayHasKey('firstname', $errors, 'firstname alanı için XSS hatası dönmedi! Mevcut hatalar: ' . json_encode($errors));
        }

        // Kesin doğrulama: Veritabanında bu username ile bir kullanıcı OLUŞMAMIŞ olmalı.
        $db = \Config\Database::connect();
        $userInDb = $db->table('users')->where('username', 'xssuser')->get()->getRow();
        $this->assertNull($userInDb, 'XSS Koruması Başarısız: Kullanıcı veritabanına kaydedildi!');
    }

    /**
     * The identity created above is not guaranteed to be *this* class's own
     * 'seedadmin' user: `InstallService::createDefaultData()` is idempotent per
     * table now, so whichever test class's args first populated
     * `auth_groups_users` in this shared, `$refresh=false` `ci4ms_test` schema
     * keeps that identity for the rest of the process. Resolving by group
     * membership instead of by a hardcoded username works regardless of which
     * class won that race.
     */
    private function superadminUser(): ?\CodeIgniter\Shield\Entities\User
    {
        $link = (new \ci4commonmodel\CommonModel())->selectOne('auth_groups_users', ['group' => 'superadmin']);

        if ($link === null) {
            return null;
        }

        return (new UserModel())->find($link->user_id);
    }
}

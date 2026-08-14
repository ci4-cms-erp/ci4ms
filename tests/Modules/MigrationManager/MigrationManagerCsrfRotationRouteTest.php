<?php

declare(strict_types=1);

namespace Tests\Modules\MigrationManager;

use ci4commonmodel\CommonModel;
use CodeIgniter\Security\Exceptions\SecurityException;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Test\AuthenticationTesting;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Security as SecurityConfig;
use Modules\Methods\Libraries\ModuleScanner;

/**
 * BUG-1 regresyon testi: `backend/migration-manager/run-migration`'a GERÇEK
 * filtre zincirinden (CSRF dahil) geçen iki ARDIŞIK POST, ikisi de 200.
 *
 * `MigrationManagerControllerTest`'in `newController()`/`primeRequest()`
 * bypass deseni CSRF'i hiç devreye sokmadığı için BUG-1'i (her 2. isteğin
 * eski token yüzünden 403 alması) yakalayamaz — bu dosya `FeatureTestTrait`
 * ile gerçek HTTP isteği gönderip `backendGuard`'ın `after`'ına eklenen
 * `CsrfTokenRefreshFilter` (modules/Backend/Filters/CsrfTokenRefreshFilter.php)
 * ile dönen rotate edilmiş `X-CSRF-TOKEN` başlığını bir sonraki isteğe
 * taşıyarak client-side fix'in sunucu tarafındaki ön koşulunu pinler.
 *
 * CSRF senkronizasyon deseni `UpdateRollbackRouteTest::request()` ile
 * birebir aynıdır (session-based CSRF, hem `Config\Services::security()`
 * hem isteğin kendi rebuild ettiği tarafın görebileceği tek kanal).
 *
 * Fail-closed katman (`Ci4MsAuthFilter.php:48-57`) için ön koşul olarak
 * `ModuleScanner::runScan()` idempotent biçimde çağrılır (bkz.
 * `MigrationManagerViewRenderTest` docblock'u, aynı gerekçe).
 *
 * @internal
 */
final class MigrationManagerCsrfRotationRouteTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;
    use AuthenticationTesting;

    protected $refresh = false;

    protected $migrateOnce = true;

    protected $namespace = null;

    private const ROUTE = 'backend/migration-manager/run-migration';

    /**
     * Zaten migrate edilmiş, "up to date" bir namespace — 0 uygulanan
     * migration ile dahi 200 döner (`MigrationManagerControllerTest`'teki
     * aynı sabitle birebir aynı kanıtlanmış davranış).
     */
    private const REAL_MIGRATION_NAMESPACE = 'Modules\MigrationManager';

    private CommonModel $commonModel;

    private int $baselineMaxRunId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertStringContainsString(
            'ci4ms_test',
            (string) \Config\Database::connect()->database,
            'Refusing to run: the active connection is not pointed at the test database.',
        );

        // AuthGroups/Ci4MsAuthFilter permission cache'lerinin bayat olma
        // ihtimalini kapatır (bkz. ci4ms-protocol conventions.md).
        cache()->clean();

        // Fail-closed katman için ön koşul: auth_permissions_pages satırı
        // yoksa superadmin bile 403 alır (Ci4MsAuthFilter.php:48-51).
        (new ModuleScanner())->runScan();

        $this->commonModel = new CommonModel('tests');

        $baseline               = $this->commonModel->selectOne('migration_runs', [], '*', 'id DESC');
        $this->baselineMaxRunId = $baseline !== null ? (int) $baseline->id : 0;
    }

    protected function tearDown(): void
    {
        // Bu testin ürettiği migration_runs satırlarını (audit kaydı) temizle.
        $this->commonModel->remove('migration_runs', ['id >' => $this->baselineMaxRunId]);

        // actingAs() paylaşımlı Session authenticator singleton'ına yazar;
        // bu durum sonraki test dosyasına sızmasın diye resetlenir
        // (established fix, bkz. MigrationManagerControllerTest docblock'u).
        \Config\Services::resetSingle('auth');

        parent::tearDown();
    }

    /**
     * (1) `app/Config/Security.php`'de `$regenerate` gerçekten `true` —
     * testin varsayımı prod config'le eşleşiyor (dokunulmadı, yalnız okundu).
     *
     * (2) Geçerli bir CSRF token ile POST #1 → 200.
     * (3) Yanıt #1'in `X-CSRF-TOKEN` header'ı ORİJİNAL token'dan FARKLI
     *     (regenerate gerçekten aktif, test bir şey ölçüyor).
     * (4) Bu YENİ token ile POST #2 → 200 (client'ın rotate edilmiş token'ı
     *     doğru taşıdığı varsayımı sunucu tarafında doğrulanır — BUG-1'in
     *     tam olarak kırdığı senaryo).
     * (5) Negatif kontrol: artık bayat olan ORİJİNAL token'la POST #3 →
     *     `SecurityException` (mekanizmanın gerçekten "eski token artık
     *     geçersiz" olduğunun kanıtı; aksi halde regenerate=false bir
     *     ortamda da bu test yanlışlıkla yeşil kalırdı).
     */
    public function testTwoSequentialPostsWithRotatingCsrfTokenBothSucceed(): void
    {
        $this->assertTrue(
            config(SecurityConfig::class)->regenerate,
            'This test assumes Config\Security::$regenerate = true; the production config no longer matches.',
        );

        $admin = $this->createSuperadmin('mm_csrf_sa1');

        $security  = \Config\Services::security();
        $tokenName = $security->getTokenName();

        // .env overrides security.tokenRandomize to true in this project
        // (app/Config/Security.php's `false` default is NOT what runs —
        // verified via .env:113). generateHash() returns the raw internal
        // hash; the value that must actually travel in the POST body/session
        // is getHash()'s BREACH-randomized encoding of it. Posting the raw
        // hash directly (as CI4's own derandomize() cannot recover it) is
        // exactly what made this test throw SecurityException on the very
        // first POST before this fix.
        $rawHash = $security->generateHash();

        // Session based CSRF: session must hold the RAW hash for both the
        // already-built Security instance and the one the request rebuilds
        // (mirrors saveHashInSession()'s own storage format).
        $_SESSION[$tokenName] = $rawHash;

        // Captured once and reused verbatim for the negative control below;
        // every call to getHash() re-randomizes with a fresh BREACH key, but
        // any capture remains a valid encoding of $rawHash until the raw
        // hash itself rotates.
        $originalToken = $security->getHash();

        $result1 = $this->actingAs($admin)
            ->withSession([$tokenName => $rawHash])
            ->withHeaders($this->ajaxHeaders())
            ->post(self::ROUTE, [
                'namespace' => self::REAL_MIGRATION_NAMESPACE,
                $tokenName  => $originalToken,
            ]);

        $this->assertSame(
            200,
            $result1->response()->getStatusCode(),
            'First POST with a fresh CSRF token must succeed: ' . (string) $result1->response()->getBody(),
        );

        $rotatedToken = $result1->response()->getHeaderLine('X-CSRF-TOKEN');
        $this->assertNotSame('', $rotatedToken, 'CsrfTokenRefreshFilter must set a non-empty X-CSRF-TOKEN response header.');
        $this->assertNotSame(
            $originalToken,
            $rotatedToken,
            'security.regenerate=true must issue a new token after the first POST; got back the same token.',
        );

        $result2 = $this->withHeaders($this->ajaxHeaders())
            ->post(self::ROUTE, [
                'namespace' => self::REAL_MIGRATION_NAMESPACE,
                $tokenName  => $rotatedToken,
            ]);

        $this->assertSame(
            200,
            $result2->response()->getStatusCode(),
            'Second POST with the rotated CSRF token must succeed (BUG-1 regression guard): ' . (string) $result2->response()->getBody(),
        );

        try {
            $result3 = $this->withHeaders($this->ajaxHeaders())
                ->post(self::ROUTE, [
                    'namespace' => self::REAL_MIGRATION_NAMESPACE,
                    $tokenName  => $originalToken,
                ]);

            $this->fail('Expected a SecurityException for the now-stale original CSRF token, got status ' . $result3->response()->getStatusCode());
        } catch (SecurityException $e) {
            $this->assertStringContainsString('not allowed', strtolower($e->getMessage()));
        }
    }

    /**
     * @return array<string, string>
     */
    private function ajaxHeaders(): array
    {
        return ['X-Requested-With' => 'XMLHttpRequest'];
    }

    private function createSuperadmin(string $label): User
    {
        $suffix = bin2hex(random_bytes(4));

        $users = auth()->getProvider();
        $user  = new User([
            'firstname'    => 'Test',
            'surname'      => 'User',
            'username'     => $label . '_' . $suffix,
            'email'        => $label . '_' . $suffix . '@example.test',
            'password'     => 'SuperSecret123!',
            'active'       => 1,
            'own_language' => 'en',
        ]);
        $users->save($user);

        // Model::save() does not back-fill $user->id on the entity passed in;
        // a fresh, fully-hydrated fetch is required before actingAs()/auth()->id()
        // (same requirement as PermgroupPrivilegeEscalationTest::createUser()).
        $fresh = $users->findById($users->getInsertID());
        $this->assertNotNull($fresh, 'Newly created user could not be re-fetched.');
        $fresh->setGroupsCache(['superadmin']);

        return $fresh;
    }
}

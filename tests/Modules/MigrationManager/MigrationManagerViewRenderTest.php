<?php

declare(strict_types=1);

namespace Tests\Modules\MigrationManager;

use ci4commonmodel\CommonModel;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Test\AuthenticationTesting;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Modules\Methods\Libraries\ModuleScanner;

/**
 * `MigrationManager::index()`'in view render smoke testi.
 *
 * Görev 22'nin silinmiş geçici probe testinin kalıcı hali. Gerçek HTTP GET
 * (`FeatureTestTrait`) + superadmin `actingAs()` kullanılır, böylece Görev 20'nin
 * üç katmanlı guard'ının (backendGuard filtresi, `Ci4MsAuthFilter` fail-closed
 * permission kontrolü, controller-içi `inGroup()`) TAMAMINDAN geçilir — index()'i
 * doğrudan instantiate ederek bypass etmek bu testin amacına aykırı olurdu.
 *
 * Superadmin durumu DB'ye (`auth_groups_users`) hiç yazılmadan
 * `User::setGroupsCache()` ile bellekte kurulur (bkz.
 * `MigrationManagerControllerTest`'in aynı desen için docblock gerekçesi).
 *
 * setUp() KENDİ `ModuleScanner::runScan()` çağrısını yapar (idempotent,
 * zararsız) — `MigrationManagerPermissionScanTest` bu dosyadan önce
 * çalışmış olsun ya da olmasın, `auth_permissions_pages` satırlarının var
 * olduğu (ve dolayısıyla `Ci4MsAuthFilter`'ın fail-closed katmanının
 * superadmin'i bile 403'e düşürmediği) bu dosyanın kendi çalışma
 * sırasından bağımsız olarak garanti edilir.
 *
 * @internal
 */
final class MigrationManagerViewRenderTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;
    use AuthenticationTesting;

    protected $refresh = false;

    protected $migrateOnce = true;

    protected $namespace = null;

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

        // Fail-closed katman için ön koşul: bkz. sınıf docblock'u.
        (new ModuleScanner())->runScan();
    }

    protected function tearDown(): void
    {
        // actingAs() logs the user in on the *shared* Session authenticator
        // instance cached inside Shield's Auth facade; that state survives
        // into whichever test file runs next in the same PHPUnit process.
        // resetSingle('auth') discards the cached Auth instance so the next
        // service('auth') call builds everything fresh (established fix,
        // see tests/Feature/SecurityXSSCSRFTest.php's tearDown() for the
        // full empirical write-up of the same bug).
        \Config\Services::resetSingle('auth');

        // Ci4MsAuthFilter::before() (modules/Auth/Filters/Ci4MsAuthFilter.php:27-28)
        // writes the acting user's own_language onto the *shared* `language`
        // service singleton. Left unreset, that locale survives into
        // whichever test runs next in this process (same class of bug as
        // the `auth` singleton above and the shared `validation` service
        // documented in .ci4ms/knowledge/pitfalls.md) — a `tr` locale set by
        // testCi4msLocaleReflectsUsersOwnLanguage() would otherwise leak into
        // testIndexRendersSuccessfullyWithTodaysBaseline()'s lang() calls.
        \Config\Services::resetSingle('language');

        parent::tearDown();
    }

    /**
     * Bugünkü production baseline'ıyla (statusReport dolu, seeders=[
     * Ci4msReferenceDataSeeder -- bu seeder `WebRunnableSeeder` implement
     * ettiği için `SeederScanner::discover()`'ın artık BOŞ DÖNMEDİĞİ yeni
     * baseline, bkz. app/Database/Seeds/Ci4msReferenceDataSeeder.php],
     * recentRuns muhtemelen boş) index() patlamadan 200 render eder;
     * keşfedilen seeder'ın etiketi görünür; vendor namespace'ler
     * `vendorNamespaceReadOnly` metniyle işaretlenir.
     */
    public function testIndexRendersSuccessfullyWithTodaysBaseline(): void
    {
        $admin  = $this->createSuperadmin('mm_view_sa1');
        $result = $this->actingAs($admin)->get('backend/migration-manager');

        $result->assertStatus(200);

        $body = (string) $result->response()->getBody();

        $this->assertStringContainsString(esc(lang('MigrationManager.migrationManager')), $body);
        $this->assertStringContainsString(esc(lang('MigrationManager.seederReferenceData')), $body);
        $this->assertStringContainsString(esc(lang('MigrationManager.vendorNamespaceReadOnly')), $body);
        $this->assertStringContainsString('CodeIgniter\Settings', $body);
    }

    /**
     * `migration_runs`'daki serbest-metin `message` alanına konan bir XSS
     * payload'ı, yanıt gövdesinde HAM HALİYLE DEĞİL, escape edilmiş olarak
     * görünür ("dolu" recentRuns varyasyonu — aynı zamanda persisted-XSS
     * regresyon bekçisi).
     */
    public function testRunHistoryMessageXssPayloadIsEscapedInResponse(): void
    {
        $payload     = '<script>alert(1)</script>';
        $commonModel = new CommonModel('tests');
        $insertId    = $commonModel->create('migration_runs', [
            'kind'          => 'migration',
            'target'        => 'Modules\MigrationManager',
            'status'        => 'success',
            'applied_count' => 0,
            'batch'         => null,
            'message'       => $payload,
            'duration_ms'   => 1,
            'run_by'        => null,
            'ip'            => '127.0.0.1',
        ]);

        try {
            $admin  = $this->createSuperadmin('mm_view_sa2');
            $result = $this->actingAs($admin)->get('backend/migration-manager');

            $result->assertStatus(200);
            $body = (string) $result->response()->getBody();

            $this->assertStringNotContainsString($payload, $body, 'The raw XSS payload must never appear unescaped in the response body.');
            $this->assertStringContainsString(esc($payload), $body, 'The escaped form of the payload must be present (proves the value was rendered, just escaped).');
        } finally {
            $commonModel->remove('migration_runs', ['id' => $insertId]);
        }
    }

    /**
     * BUG-2: `run_by=NULL` (silinmiş kullanıcı veya sistem/otomasyon
     * kaynaklı çalıştırma) olan bir `migration_runs` satırı, index()'in
     * server-render ettiği tabloda ham `NULL`/boş hücre değil,
     * `lang('MigrationManager.deletedUser')` metniyle görünür
     * (`modules/MigrationManager/Views/list.php:225`).
     */
    public function testDeletedUserPlaceholderIsShownInIndexWhenRunByIsNull(): void
    {
        $commonModel = new CommonModel('tests');
        $insertId    = $commonModel->create('migration_runs', [
            'kind'          => 'seed',
            'target'        => 'App\Database\Seeds\SomeSeeder',
            'status'        => 'success',
            'applied_count' => 1,
            'batch'         => null,
            'message'       => null,
            'duration_ms'   => 1,
            'run_by'        => null,
            'ip'            => '127.0.0.1',
        ]);

        try {
            $admin  = $this->createSuperadmin('mm_view_sa3');
            $result = $this->actingAs($admin)->get('backend/migration-manager');

            $result->assertStatus(200);
            $body = (string) $result->response()->getBody();

            $this->assertStringContainsString(esc(lang('MigrationManager.deletedUser')), $body);
        } finally {
            $commonModel->remove('migration_runs', ['id' => $insertId]);
        }
    }

    /**
     * BUG-3 düzeltme turu 2: `Ci4MsAuthFilter::before()`
     * (`modules/Auth/Filters/Ci4MsAuthFilter.php:27-28`) çalıştıktan sonra
     * server-rendered `window.CI4MS_LOCALE` (`modules/Backend/Views/base.php:205`)
     * `service('request')->getLocale()` DEĞİL, `service('language')->getLocale()`
     * okumalı — ilki her zaman `app/Config/App.php`'nin `$defaultLocale`
     * fallback'ini (`.env:146` → `'en'`) döndürür, filtre onu hiç değiştirmez.
     * Bu test gerçek `Ci4MsAuthFilter`'ın koştuğu bir yoldan
     * (`FeatureTestTrait::get()`, bkz. sınıf docblock'u) `own_language='tr'`
     * kullanıcı için `'tr'` render edildiğini kanıtlar.
     */
    public function testCi4msLocaleReflectsTurkishOwnLanguage(): void
    {
        $admin  = $this->createSuperadmin('mm_view_locale_tr', 'tr');
        $result = $this->actingAs($admin)->get('backend/migration-manager');

        $result->assertStatus(200);
        $body = (string) $result->response()->getBody();

        $this->assertStringContainsString(
            "window.CI4MS_LOCALE = 'tr';",
            $body,
            'own_language=tr must render window.CI4MS_LOCALE = \'tr\'.',
        );
        $this->assertStringNotContainsString("window.CI4MS_LOCALE = 'en';", $body);
    }

    /**
     * Negatif taraf: `own_language='en'` kullanıcı için `'en'` render edilir
     * ve önceki testin `tr` locale'i (paylaşımlı `language` singleton'ı
     * üzerinden) bu teste SIZMAZ — bkz. tearDown()'daki
     * `Services::resetSingle('language')` gerekçesi. İki test art arda
     * (declaration sırasıyla) koşturularak izolasyon kanıtlanır.
     */
    public function testCi4msLocaleReflectsEnglishOwnLanguage(): void
    {
        $admin  = $this->createSuperadmin('mm_view_locale_en', 'en');
        $result = $this->actingAs($admin)->get('backend/migration-manager');

        $result->assertStatus(200);
        $body = (string) $result->response()->getBody();

        $this->assertStringContainsString(
            "window.CI4MS_LOCALE = 'en';",
            $body,
            'own_language=en must render window.CI4MS_LOCALE = \'en\'.',
        );
        $this->assertStringNotContainsString("window.CI4MS_LOCALE = 'tr';", $body);
    }

    /**
     * FAZ 5 I3: `run_source='cli'` (`run_by=null`) renders the CLI label,
     * and `run_source='web'` (also `run_by=null`) renders the deleted-user
     * placeholder in the same response — English locale.
     *
     * `run_by=null` alone cannot distinguish "CLI run" from "deleted user"
     * (BUG-2/BUG-3); the view disambiguates via `run_source`
     * (`modules/MigrationManager/Views/list.php:217-219`:
     * `$runSource = $run->run_source ?? 'web';` then, only when
     * `run_by_username` is null, `runSource === 'cli'` selects
     * `MigrationManager.runSourceCli`, anything else selects
     * `MigrationManager.deletedUser`). Both rows are inserted in the same
     * request so the test proves the two branches don't get crossed.
     * Assertions compare against the real `lang()` helper output, not a
     * hand-typed string — a raw array comparison would not catch this bug
     * class (`.ci4ms/knowledge/pitfalls.md` "Dil dosyalarinda %s hicbir
     * zaman degismez").
     */
    public function testRunSourceCliAndDeletedUserPlaceholdersAreShownCorrectlyInEnglish(): void
    {
        $admin       = $this->createSuperadmin('mm_view_runsource_en', 'en');
        $commonModel = new CommonModel('tests');

        $cliRowId = $commonModel->create('migration_runs', [
            'kind'          => 'migration',
            'target'        => '*',
            'status'        => 'success',
            'applied_count' => 0,
            'batch'         => null,
            'message'       => null,
            'duration_ms'   => 1,
            'run_by'        => null,
            'ip'            => null,
            'run_source'    => 'cli',
        ]);
        $webRowId = $commonModel->create('migration_runs', [
            'kind'          => 'migration',
            'target'        => 'Modules\MigrationManager',
            'status'        => 'success',
            'applied_count' => 1,
            'batch'         => null,
            'message'       => null,
            'duration_ms'   => 1,
            'run_by'        => null,
            'ip'            => '127.0.0.1',
            'run_source'    => 'web',
        ]);

        try {
            $result = $this->actingAs($admin)->get('backend/migration-manager');

            $result->assertStatus(200);
            $body = (string) $result->response()->getBody();

            $this->assertStringContainsString(
                '<td>' . esc(lang('MigrationManager.runSourceCli')) . '</td>',
                $body,
                'The run_source=cli row (run_by=null) must render the CLI label inside its <td>, not the deleted-user placeholder.',
            );
            $this->assertStringContainsString(
                '<td>' . esc(lang('MigrationManager.deletedUser')) . '</td>',
                $body,
                'The run_source=web row (run_by=null) must render the deleted-user placeholder inside its <td>, not the CLI label.',
            );
        } finally {
            $commonModel->remove('migration_runs', ['id' => $cliRowId]);
            $commonModel->remove('migration_runs', ['id' => $webRowId]);
        }
    }

    /**
     * FAZ 5 I3: same as
     * {@see self::testRunSourceCliAndDeletedUserPlaceholdersAreShownCorrectlyInEnglish()}
     * but for the Turkish locale. `Ci4MsAuthFilter::before()`
     * (`modules/Auth/Filters/Ci4MsAuthFilter.php:27-28`) sets the shared
     * `language` service's locale to the acting admin's `own_language`
     * during the `get()` call above; the `lang()` calls below run in the
     * same test method, after that request, so they resolve against the
     * same `tr` locale the response body was rendered with — not a
     * hand-typed Turkish string (same discipline as the English variant).
     * `tearDown()`'s `Services::resetSingle('language')` (see class
     * docblock) keeps this from leaking into the next test.
     */
    public function testRunSourceCliAndDeletedUserPlaceholdersAreShownCorrectlyInTurkish(): void
    {
        $admin       = $this->createSuperadmin('mm_view_runsource_tr', 'tr');
        $commonModel = new CommonModel('tests');

        $cliRowId = $commonModel->create('migration_runs', [
            'kind'          => 'migration',
            'target'        => '*',
            'status'        => 'success',
            'applied_count' => 0,
            'batch'         => null,
            'message'       => null,
            'duration_ms'   => 1,
            'run_by'        => null,
            'ip'            => null,
            'run_source'    => 'cli',
        ]);
        $webRowId = $commonModel->create('migration_runs', [
            'kind'          => 'migration',
            'target'        => 'Modules\MigrationManager',
            'status'        => 'success',
            'applied_count' => 1,
            'batch'         => null,
            'message'       => null,
            'duration_ms'   => 1,
            'run_by'        => null,
            'ip'            => '127.0.0.1',
            'run_source'    => 'web',
        ]);

        try {
            $result = $this->actingAs($admin)->get('backend/migration-manager');

            $result->assertStatus(200);
            $body = (string) $result->response()->getBody();

            $this->assertStringContainsString(
                '<td>' . esc(lang('MigrationManager.runSourceCli')) . '</td>',
                $body,
                'The run_source=cli row (run_by=null) must render the CLI label inside its <td>, not the deleted-user placeholder.',
            );
            $this->assertStringContainsString(
                '<td>' . esc(lang('MigrationManager.deletedUser')) . '</td>',
                $body,
                'The run_source=web row (run_by=null) must render the deleted-user placeholder inside its <td>, not the CLI label.',
            );
        } finally {
            $commonModel->remove('migration_runs', ['id' => $cliRowId]);
            $commonModel->remove('migration_runs', ['id' => $webRowId]);
        }
    }

    private function createSuperadmin(string $label, string $ownLanguage = 'en'): User
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
            'own_language' => $ownLanguage,
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

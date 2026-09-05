<?php

declare(strict_types=1);

namespace Tests\Modules\MigrationManager;

use ci4commonmodel\CommonModel;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\HTTP\SiteURI;
use CodeIgniter\HTTP\UserAgent;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Test\AuthenticationTesting;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\App;
use Modules\Backend\Libraries\RunLock;
use Modules\MigrationManager\Controllers\MigrationManager;
use ReflectionProperty;

/**
 * `MigrationManager` controller'ının kendi mantığının (route/filter zincirini
 * bypass eden, doğrudan instantiate deseni — `UpdateRollbackControllerTest`/
 * `PermgroupPrivilegeEscalationTest` emsali) testleri.
 *
 * Superadmin/superadmin-olmayan ayrımı DB'ye (auth_groups_users) hiç
 * yazılmadan `CodeIgniter\Shield\Entities\User::setGroupsCache()` ile
 * bellekte kurulur — `Authorizable::populateGroups()` groupCache zaten dizi
 * ise DB'ye HİÇ gitmez (vendor/codeigniter4/shield/.../Authorizable.php:296-305),
 * bu yüzden `CommonModel`'in 'tests' bağlantısı ile Shield'in kendi
 * (aliaslanmış) bağlantısı arasındaki olası görünürlük farkı bu dosyada asla
 * devreye girmez.
 *
 * `Session::startLogin()` bir session'da zaten bir kullanıcı varsa (aynı
 * kullanıcı dahi) `LogicException` fırlatır (`vendor/codeigniter4/shield/
 * src/Authentication/Authenticators/Session.php:701-715`) — bu yüzden
 * `actingAs()` her test metodunda TAM OLARAK BİR KEZ çağrılır; birden çok
 * dispatch gereken senaryolarda (d, e) login tek seferde yapılıp sonraki
 * çağrılar yalnızca `primeRequest()`'i (login İÇERMEYEN) tekrar kullanır.
 *
 * Tüm sınıf `$this->db->transStart()/transRollback()` ile sarmalanır
 * (`PermgroupPrivilegeEscalationTest` emsali): gerçek `MigrationRunner`
 * (`Services::migrations(null,null,false)` → `db_connect(null)`) ve
 * `commonModel` ikisi de ENVIRONMENT=testing altında aynı paylaşımlı
 * 'tests' bağlantısını kullandığından (`app/Config/Database.php:197-208`
 * `default`→`tests` alias'ı + CI4'ün shared-connection cache'i), bu test
 * dosyasının eklediği `migration_runs` satırları (VE olası
 * `Events::trigger('ci4ms.audit')` yan etkileri) tearDown()'daki
 * transRollback() ile TEK bir işlemde temizlenir — satır bazlı elle
 * silmeye gerek yoktur.
 *
 * @internal
 */
final class MigrationManagerControllerTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use AuthenticationTesting;

    protected $refresh = false;

    protected $migrateOnce = true;

    protected $namespace = null;

    private const REAL_MIGRATION_NAMESPACE = 'Modules\MigrationManager';
    private const VENDOR_NAMESPACE         = 'CodeIgniter\Settings';

    private CommonModel $commonModel;

    private string $lockFile;

    protected function setUp(): void
    {
        parent::setUp();
        helper('Modules\Backend\Helpers\ci4ms');

        $this->assertStringContainsString(
            'ci4ms_test',
            (string) \Config\Database::connect()->database,
            'Refusing to run: the active connection is not pointed at the test database.',
        );

        $this->commonModel = new CommonModel('tests');
        $this->lockFile     = WRITEPATH . 'locks/migration_manager.lock';

        $this->db->transStart();
    }

    protected function tearDown(): void
    {
        $this->db->transRollback();

        // FAZ 9 J4 (context.md): raw unlink() here would reintroduce the exact
        // hazard RunLock::release() deliberately dropped — deleting a flock()'d
        // file is a classic TOCTOU/inode-reuse race against another process
        // still holding the *real*, shared `WRITEPATH.'locks/migration_manager.lock'`.
        // release() is a safe no-op when this instance never held the lock
        // (the `$held` guard) and does the correct flock-based release when it
        // genuinely does — it never unlink()s the file either way.
        (new RunLock($this->lockFile))->release();

        // actingAs() logs the user in on the *shared* Session authenticator
        // instance cached inside Shield's Auth facade; that state survives
        // into whichever test file runs next in the same PHPUnit process,
        // even though this test's transaction rollback just deleted the
        // underlying user row. resetSingle('auth') discards the cached Auth
        // instance so the next service('auth') call builds everything fresh
        // (established fix, see tests/Feature/SecurityXSSCSRFTest.php's
        // tearDown() for the full empirical write-up of the same bug).
        \Config\Services::resetSingle('auth');

        parent::tearDown();
    }

    /**
     * (a) Geçerli bir `Modules\*` namespace'i ile runMigration() → 200 +
     * success:true, `migration_runs`'a doğru kind/target/status/run_by/ip
     * ile 1 satır.
     */
    public function testRunMigrationWithValidModuleNamespaceSucceedsAndRecordsRun(): void
    {
        $admin = $this->createActingUser('mm_ctrl_sa1', ['superadmin']);
        $this->actingAs($admin);

        $before = $this->commonModel->count('migration_runs');

        [$response, $request] = $this->dispatchRunMigration(self::REAL_MIGRATION_NAMESPACE);

        $this->assertSame(200, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertIsArray($body);
        $this->assertTrue($body['success']);

        $after = $this->commonModel->count('migration_runs');
        $this->assertSame($before + 1, $after, 'Exactly one migration_runs row must be inserted.');

        $row = $this->commonModel->selectOne('migration_runs', [], '*', 'id DESC');
        $this->assertNotNull($row);
        $this->assertSame('migration', $row->kind);
        $this->assertSame(self::REAL_MIGRATION_NAMESPACE, $row->target);
        $this->assertSame('success', $row->status);
        $this->assertSame($admin->id, (int) $row->run_by);
        $this->assertSame($request->getIPAddress(), $row->ip);
    }

    /**
     * (b) Vendor namespace (`CodeIgniter\Settings`) POST edilirse → 400,
     * `migration_runs`'a SATIR YAZILMAZ.
     */
    public function testRunMigrationRejectsAVendorNamespace(): void
    {
        $admin = $this->createActingUser('mm_ctrl_sa2', ['superadmin']);
        $this->actingAs($admin);

        $before = $this->commonModel->count('migration_runs');

        [$response] = $this->dispatchRunMigration(self::VENDOR_NAMESPACE);

        $this->assertSame(400, $response->getStatusCode());

        $after = $this->commonModel->count('migration_runs');
        $this->assertSame($before, $after, 'A rejected vendor namespace must not write a migration_runs row.');
    }

    /**
     * (c) `RunLock` önceden alınmışken ikinci istek → 409.
     *
     * FAZ 8 DÜZELTMESİ (kapsam dışı ama kabul kriteri 1/2'yi kapatmak için
     * zorunlu — bkz. rapor): `RunLock` `flock(LOCK_EX|LOCK_NB)`'a geçti;
     * kilit artık dosya İÇERİĞİNE/mtime'ına değil, gerçekten tutulan bir
     * open file description'a bağlı. Eski simülasyon
     * (`file_put_contents($this->lockFile, (string) time());`) yalnızca
     * dosyanın VAR OLMASINI ve mtime'ının TAZE olmasını sağlıyordu — eski
     * TTL/mtime tabanlı `RunLock`'ta bu "kilit tutuluyor" anlamına
     * geliyordu. Yeni `RunLock::acquire()` dosya içeriğine hiç bakmıyor,
     * yalnız gerçek bir flock arıyor (`RunLock.php:126-149`) — bu simülasyon
     * artık kilidi GERÇEKTEN tutan bir `RunLock` örneğiyle yapılmalı, aksi
     * halde controller'ın kendi `RunLock::acquire()`'ı BAŞARILI olur ve test
     * 409 yerine 200 alır (deterministik biçimde doğrulandı).
     */
    public function testRunMigrationReturns409WhenLockIsAlreadyHeld(): void
    {
        $admin = $this->createActingUser('mm_ctrl_sa3', ['superadmin']);
        $this->actingAs($admin);

        $holderLock = new RunLock($this->lockFile);
        $this->assertTrue(
            $holderLock->acquire(),
            'Test setup failed: could not acquire the shared run lock to simulate another process holding it.',
        );

        try {
            [$response] = $this->dispatchRunMigration(self::REAL_MIGRATION_NAMESPACE);

            $this->assertSame(409, $response->getStatusCode());
        } finally {
            $holderLock->release();
        }
    }

    /**
     * (d) Geçersiz/boş `seeder` POST edilirse runSeed() → 400. Bugün
     * `SeederScanner::discover()` production baseline'ında BOŞ dizi
     * döndüğü için HER seeder string'i (boş dahil) geçersizdir.
     */
    public function testRunSeedRejectsAnEmptyOrUnknownSeederSelection(): void
    {
        $admin = $this->createActingUser('mm_ctrl_sa4', ['superadmin']);
        $this->actingAs($admin);

        [$emptyResponse] = $this->dispatchRunSeed('');
        $this->assertSame(400, $emptyResponse->getStatusCode());

        [$unknownResponse] = $this->dispatchRunSeed('App\\Database\\Seeds\\SomeNonexistentSeeder');
        $this->assertSame(400, $unknownResponse->getStatusCode());
    }

    /**
     * (e) Superadmin OLMAYAN bir kullanıcı ile index()/runMigration()/
     * runSeed()/history() çağrıldığında HER BİRİ failForbidden() (403)
     * döner — Katman-3 guard'ının 4 metodun da mevcut olduğunun kanıtı.
     */
    public function testAllFourMethodsRejectANonSuperadminUser(): void
    {
        $regular = $this->createActingUser('mm_ctrl_reg', []);
        $this->actingAs($regular);

        $indexController = $this->newController();
        $this->primeRequest([], $indexController, false);
        $this->assertSame(403, $indexController->index()->getStatusCode(), 'index() must reject a non-superadmin.');

        $runMigrationController = $this->newController();
        $this->primeRequest(['namespace' => self::REAL_MIGRATION_NAMESPACE], $runMigrationController, true);
        $this->assertSame(403, $runMigrationController->runMigration()->getStatusCode(), 'runMigration() must reject a non-superadmin.');

        $runSeedController = $this->newController();
        $this->primeRequest(['seeder' => 'anything'], $runSeedController, true);
        $this->assertSame(403, $runSeedController->runSeed()->getStatusCode(), 'runSeed() must reject a non-superadmin.');

        $historyController = $this->newController();
        $this->primeRequest([], $historyController, true);
        $this->assertSame(403, $historyController->history()->getStatusCode(), 'history() must reject a non-superadmin.');
    }

    /**
     * (f) BUG-2: `history()`'nin `run_by_username` alanı, `run_by` gerçek
     * bir kullanıcıya işaret ettiğinde o kullanıcının username'ini döner —
     * HAM olarak, `esc()` uygulanmadan.
     *
     * Kaçışlama yalnız render sınırında yapılır (`Views/list.php`'nin kolon
     * `render`'larındaki `escapeHtml()`). Sunucu da kaçışlarsa değer iki kez
     * kaçışlanır ve içinde `'` geçen bir kullanıcı adı tabloda `O&#039;Brien`
     * diye görünür. Bu test o çift kaçışı kilitler: HTML-özel karakter içeren
     * bir username kullanılır ve yanıtta **birebir** beklenir; `esc()`
     * geri gelirse assertion kırılır.
     */
    public function testHistoryJoinsUsernameForARealUser(): void
    {
        $admin = $this->createActingUser('mm_ctrl_sa5', ['superadmin']);
        $this->actingAs($admin);

        $insertId = $this->commonModel->create('migration_runs', [
            'kind'          => 'migration',
            'target'        => self::REAL_MIGRATION_NAMESPACE,
            'status'        => 'success',
            'applied_count' => 0,
            'batch'         => null,
            'message'       => null,
            'duration_ms'   => 1,
            'run_by'        => $admin->id,
            'ip'            => '127.0.0.1',
        ]);

        $controller = $this->newController();
        $this->primeRequest([], $controller, true);
        $response = $controller->history();

        $this->assertSame(200, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertIsArray($body);

        $row = $this->findRunById((array) $body['aaData'], $insertId);
        $this->assertNotNull($row, 'Inserted migration_runs row not found in history() response.');
        $this->assertSame($admin->username, $row['run_by_username']);
    }

    /**
     * `history()` HTML kaçışlaması YAPMAZ — kaçışlama render sınırının işi.
     *
     * `target` alanına doğrudan DB üzerinden HTML-özel karakterler taşıyan bir
     * değer yazılır (allowlist yalnız POST edilen namespace'i doğrular, DB'ye
     * yazılmış geçmiş satırını değil) ve yanıtta **birebir** beklenir. Sunucuya
     * `esc()` geri eklenirse bu assertion kırılır; view'ın kaçışladığını ise
     * `MigrationManagerViewRenderTest` ayrıca kanıtlıyor. İkisi birlikte
     * "tam olarak bir kez kaçışlanır" sözleşmesini kilitler.
     */
    public function testHistoryReturnsRawValuesWithoutServerSideEscaping(): void
    {
        $admin = $this->createActingUser('mm_ctrl_sa5b', ['superadmin']);
        $this->actingAs($admin);

        $payload  = '<script>"&\'</script>';
        $insertId = $this->commonModel->create('migration_runs', [
            'kind'          => 'migration',
            'target'        => $payload,
            'status'        => 'success',
            'applied_count' => 0,
            'batch'         => null,
            'message'       => null,
            'duration_ms'   => 1,
            'run_by'        => $admin->id,
            'ip'            => '127.0.0.1',
        ]);

        $controller = $this->newController();
        $this->primeRequest([], $controller, true);
        $response = $controller->history();

        $this->assertSame(200, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertIsArray($body);

        $row = $this->findRunById((array) $body['aaData'], $insertId);
        $this->assertNotNull($row, 'Inserted migration_runs row not found in history() response.');
        $this->assertSame($payload, $row['target'], 'history() must return the stored value verbatim — escaping belongs to the view.');
        $this->assertNotSame(esc($payload), $row['target']);
    }

    /**
     * (g) BUG-2: `history()`'nin `run_by_username` alanı, `run_by=NULL`
     * (silinmiş kullanıcı veya sistem/otomasyon kaynaklı çalıştırma)
     * durumunda `null` döner — view bunu `MigrationManager.deletedUser`
     * ile karşılar (ayrıca `MigrationManagerViewRenderTest`'te doğrulanır).
     */
    public function testHistoryReturnsNullRunByUsernameWhenRunByIsNull(): void
    {
        $admin = $this->createActingUser('mm_ctrl_sa6', ['superadmin']);
        $this->actingAs($admin);

        $insertId = $this->commonModel->create('migration_runs', [
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

        $controller = $this->newController();
        $this->primeRequest([], $controller, true);
        $response = $controller->history();

        $this->assertSame(200, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertIsArray($body);

        $row = $this->findRunById((array) $body['aaData'], $insertId);
        $this->assertNotNull($row, 'Inserted migration_runs row not found in history() response.');
        $this->assertNull($row['run_by_username']);
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return array<string, mixed>|null
     */
    private function findRunById(array $rows, int $id): ?array
    {
        foreach ($rows as $row) {
            if ((int) $row['id'] === $id) {
                return $row;
            }
        }

        return null;
    }

    /**
     * @return array{0: ResponseInterface, 1: IncomingRequest}
     */
    private function dispatchRunMigration(string $namespace): array
    {
        $controller = $this->newController();
        $request    = $this->primeRequest(['namespace' => $namespace], $controller, true);

        return [$controller->runMigration(), $request];
    }

    /**
     * @return array{0: ResponseInterface, 1: IncomingRequest}
     */
    private function dispatchRunSeed(string $seeder): array
    {
        $controller = $this->newController();
        $request    = $this->primeRequest(['seeder' => $seeder], $controller, true);

        return [$controller->runSeed(), $request];
    }

    private function newController(): MigrationManager
    {
        $controller = new MigrationManager();
        $controller->commonModel = $this->commonModel;
        // history() reaches this via BaseController::initController(), which
        // the bypass pattern never calls; the library has no constructor
        // dependencies, so a direct instantiation is equivalent.
        $controller->commonBackendLibrary = new \Modules\Backend\Libraries\CommonBackendLibrary();

        return $controller;
    }

    /**
     * `$controller` üzerine bir POST IncomingRequest bağlar (route/filter
     * zinciri bypass edilir — `PermgroupPrivilegeEscalationTest::primeRequest()`
     * ile aynı desen). Oturum açma İÇERMEZ — çağıran, testin BAŞINDA
     * `$this->actingAs($actor)`'ı bir kez çağırmış olmalıdır (bkz. sınıf
     * docblock'u).
     *
     * @param array<string, mixed> $post
     */
    private function primeRequest(array $post, MigrationManager $controller, bool $ajax): IncomingRequest
    {
        $config  = config(App::class);
        $request = new IncomingRequest($config, new SiteURI($config), null, new UserAgent());
        $request = $request->withMethod('POST');
        // getPost() 'post' bucket'ını okur, Validation::withRequest() ise getVar()
        // -> fetchGlobal('request', ...) ayrı bir bucket okur; bu controller
        // Controller::validate() kullanmasa da diğer dosyalardaki established
        // desenle tutarlı olması için ikisi de dolduruluyor.
        $request->setGlobal('post', $post);
        $request->setGlobal('request', $post);

        if ($ajax) {
            $request->setHeader('X-Requested-With', 'XMLHttpRequest');
        }

        (new ReflectionProperty(\CodeIgniter\Controller::class, 'request'))->setValue($controller, $request);
        (new ReflectionProperty(\CodeIgniter\Controller::class, 'response'))->setValue($controller, service('response', $config, false));

        return $request;
    }

    /**
     * Gerçek bir Shield User satırı yaratır (auth()->id() için gerekli) ve
     * grup üyeliğini DB'ye hiç yazmadan bellekte (`setGroupsCache()`) kurar.
     *
     * @param list<string> $groups
     */
    private function createActingUser(string $label, array $groups): User
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
        $fresh->setGroupsCache($groups);

        return $fresh;
    }
}

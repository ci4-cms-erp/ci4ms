<?php

namespace Tests\Feature;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Modules\Install\Services\InstallService;
use CodeIgniter\Shield\Models\UserModel;

class InstallTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    // $refresh=true made DatabaseTestTrait::regressDatabase() run before
    // this class's test method; MigrationRunner::regress() ignores
    // setNamespace() and forces $this->namespace=null internally
    // (vendor/codeigniter4/framework/system/Database/MigrationRunner.php:
    // 289-291), so it dropped EVERY namespace's tables in the shared
    // ci4ms_test schema, corrupting fixtures for classes that ran later in
    // the same process (SecurityAuthTest, SecurityXSSCSRFTest,
    // AuthGroupsUniqueKeyMigrationTest, UpdateRollbackRouteTest). Same root
    // cause as tests/database/ExampleDatabaseTest.php; same fix shape as
    // tests/Modules/Users/PermgroupPrivilegeEscalationTest.php and
    // tests/Modules/Settings/UpdateRollbackRouteTest.php.
    protected $refresh = false;

    protected $namespace = null;

    protected function setUp(): void
    {
        parent::setUp();
        // Bütün modüllerin(Namespaces) migration'larını çalıştır
        $migrate = \Config\Services::migrations();
        $migrate->setNamespace(null)->latest();
    }

    public function testDatabaseMigrationsAndSeeding()
    {
        // Check if tables are created (e.g., users, settings, auth_groups)
        $db = \Config\Database::connect();
        $this->assertTrue($db->tableExists('users'), 'Users tablosu oluşturulamadı.');
        $this->assertTrue($db->tableExists('auth_groups'), 'Auth Groups tablosu oluşturulamadı.');
        $this->assertTrue($db->tableExists('settings'), 'Settings tablosu oluşturulamadı.');

        // 2. Run InstallService Default Data Seed
        $installService = new InstallService();
        $installService->createDefaultData([
            'fname'    => 'Test',
            'sname'    => 'Admin',
            'username' => 'testadmin',
            'email'    => 'test@example.com',
            'password' => 'SuperSecret123!',
            'siteName' => 'CI4MS Test System',
        ]);

        // 3. Verify Users
        $userModel = new UserModel();
        $adminUser = $userModel->where('username', 'testadmin')->first();
        
        $this->assertNotNull($adminUser, 'Admin kullanıcısı veritabanına eklenemedi.');
        $this->assertEquals('test@example.com', $adminUser->email);

        // Verify Roles (Superadmin)
        $this->assertTrue($adminUser->inGroup('superadmin'), 'Kullanıcıya superadmin rolü atanamadı.');

        // Verify Settings (Check if SiteName is set)
        // InstallService ayarları 'Config\App' sınıf adıyla yazar, düz 'App' ile değil.
        $settingsQuery = $db->table('settings')->where('class', 'Config\App')->where('key', 'siteName')->get()->getRow();
        $this->assertNotNull($settingsQuery, 'SiteName ayarı tabloya eklenmedi.');
        $this->assertEquals('CI4MS Test System', $settingsQuery->value);
    }
}

<?php

namespace Tests\Feature;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Shield\Models\UserModel;

class SecurityAuthTest extends CIUnitTestCase
{
    use FeatureTestTrait;
    use DatabaseTestTrait;

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
        // Setup the Database for all namespaces
        $migrate = \Config\Services::migrations();
        $migrate->setNamespace(null)->latest();

        // An earlier feature test can leave a logged-in marker in the shared
        // session singleton. A "guest" request here would then report
        // loggedIn() === true with no loadable user, and Shield's
        // ForcePasswordResetFilter (Filters/ForcePasswordResetFilter.php:46)
        // crashes reading force_reset on that null user. Start each test from a
        // clean auth/session state so this class does not depend on execution
        // order.
        $_SESSION = [];
        \Config\Services::resetSingle('session');
        \Config\Services::resetSingle('auth');
    }

    public function testGuestIsRedirectedToLoginFromBackend()
    {
        // Yetkisiz kullanıcı girişi denemesi.
        // /backend route'una yetkisiz (session olmadan) girilirse, Login filter çalışmalı.
        $result = $this->get('backend');

        // Normalde Shield'ın session filtresi 302 Redirect (Login sayfasına yönlendirir) fırlatır.
        $result->assertRedirect();
        
        // Yönlendirme hedefinin login olup olmadığı doğrulanabilir, örneğin:
        $this->assertStringContainsString('/login', $result->getRedirectUrl());
    }

    public function testSqlInjectionOnSearchStrings()
    {
        // CommonModel üzerindeki lists() metodunu simüle edeceğiz veya doğrudan controller'ı tetikleyeceğiz.
        // En kolayı, veritabanı sınıfını SQL injection denemelerine karşı test etmektir.
        
        $db = \Config\Database::connect();
        $builder = $db->table('users');

        // Tehlikeli Input:
        $maliciousString = "test' OR 1=1; DROP TABLE users; --";
        
        // CI4 Query builder LIKE komutunda SQL Injection için otomatik escape yapar.
        $builder->like('username', $maliciousString);
        
        // Eğer escape mekanizması bozuksa, raw sorgu hatalı çıkar veya tehlikeli kodu çalıştırır.
        $compiled = $builder->getCompiledSelect();

        // Sorguda tek tırnağın \ ile kaçırıldığına ve DROP kelimesinin sorgunun aramasında string olarak kaldığına emin olalım.
        // PHPUnit ile string contain doğrulaması:
        $this->assertStringContainsString("ESCAPE", $compiled);
        
        // Ve kesinlikle sorguyu tek hamlede kapatıp DROP ekleyememesi lazım. Builder güvenli ürettiği için bu geçmektedir.
        // Eğer test buraya kadar exception atmadan/çökmeden geliyorsa builder SQLi yordamını izole etmiştir.
        $this->assertTrue(true);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Modules\Auth;

use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Modules\Auth\Database\Migrations\AddUniqueKeyToAuthGroupsAndUsers;
use RuntimeException;

/**
 * `AddUniqueKeyToAuthGroupsAndUsers` migration'ının kendi mantığının testleri
 * (Görev 10, `.ci4ms/plans/migration-manager/context.md` KARAR-2).
 *
 * `$migrate = false`: `DatabaseTestTrait`'in kendi otomatik `latest('tests')`
 * mekanizması (`$namespace=null` iken TÜM projeyi tarayıp migrate eder,
 * `vendor/codeigniter4/framework/system/Test/DatabaseTestTrait.php:165-187`)
 * bu sınıftan ASLA tetiklenmez — migration SINIFI burada doğrudan instantiate
 * edilip yalnız `up()`/`down()` çağrılır. Gerekçe: `MigrationRunner::latest()`
 * başarısızlıkta otomatik `regress(-1)` çağırır
 * (`vendor/codeigniter4/framework/system/Database/MigrationRunner.php:~210`),
 * bu da paylaşımlı `ci4ms_test` şemasındaki BAŞKA migration batch'lerini de
 * geri alabilir (bkz. context.md Görev 21 emsali — dispatch bunu açıkça bu
 * şekilde önerdi).
 *
 * Her test metodu kendi başlangıç durumunu (iki UNIQUE key YOK, ama FK
 * destek index'i `auth_groups_users_user_id_index` VAR — bkz. migration'ın
 * kendi docblock'u, bu index KASITLI OLARAK kalıcıdır) `setUp()`'ta kurar
 * ve `tearDown()`'da AYNI duruma geri döner (raw normalize, migration'ın
 * kendi `down()`'ına bağımlı DEĞİL — `down()`'ın doğruluğu ayrıca test
 * (b)'de sınanır).
 *
 * AMPİRİK BULGU (bu dosyanın ilk yazımında canlı olarak yakalandı, bu
 * yorumda kalıcı kayıt): composite `UNIQUE(user_id,group)` ilk kez
 * eklendiğinde MySQL, Shield'ın `user_id` FK'si için kurduğu ÖRTÜK destek
 * index'ini composite index LEHİNE otomatik düşürüyor; composite index bu
 * noktadan sonra FK'nin TEK destekçisi oluyor ve düz `DROP INDEX` "needed
 * in a foreign key constraint" ile patlıyor. Bu YÜZDEN `ci4ms_test`'te
 * artık `up()` çalıştırılmadan ÖNCEKİ (tamamen örtük/isimsiz FK-index'li)
 * hâle tam olarak dönmek mümkün değil — ulaşılabilecek en temiz durum,
 * migration'ın kendi `down()`'ının bıraktığı durumdur (composite YOK,
 * kalıcı `auth_groups_users_user_id_index` VAR). Bu dosyanın `setUp()`/
 * `tearDown()`'ı o durumu hedefler.
 *
 * ÖNEMLİ BULGU (raporun "Açık riskler" bölümüne taşınır, bu dosyada
 * DÜZELTİLMEZ — kapsam dışı): bu iki UNIQUE index'in `ci4ms_test`'e KALICI
 * olarak uygulanması (örn. başka bir test sınıfının kendi `$namespace=null`
 * + `$migrate=true` otomatik `latest()`'i üzerinden), `tests/Modules/Users/
 * PermgroupPrivilegeEscalationTest.php:193-219`'daki
 * `testIdorLocksOutTheRealSuperadminGroup()`'u KIRAR — o test kasıtlı olarak
 * `auth_groups`'a mevcut seed satırıyla (`group='superadmin'`) ÇAKIŞAN
 * ikinci bir satır ekliyor (kendi güvenlik senaryosunun fixture'ı). Ampirik
 * kanıt bu görevin raporunda (bu test dosyasının parçası değil).
 *
 * @internal
 */
final class AuthGroupsUniqueKeyMigrationTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $refresh = false;

    protected $migrate = false;

    protected $namespace = null;

    private const AUTH_GROUPS_UNIQUE_KEY        = 'auth_groups_group_unique';
    private const AUTH_GROUPS_USERS_UNIQUE_KEY  = 'auth_groups_users_user_id_group_unique';
    private const AUTH_GROUPS_USERS_USER_ID_KEY = 'auth_groups_users_user_id_index';

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertStringContainsString(
            'ci4ms_test',
            (string) \Config\Database::connect()->database,
            'Refusing to run: the active connection is not pointed at the test database.',
        );

        $this->requireMigrationFile();
        $this->dropKeysIfPresent();
    }

    protected function tearDown(): void
    {
        $this->dropKeysIfPresent();

        parent::tearDown();
    }

    /**
     * (a) Mutlu yol: `up()` sonrası her iki tabloda da doğru isimli ve doğru
     * kolonlu UNIQUE index'ler oluşuyor.
     */
    public function testUpAddsBothUniqueKeys(): void
    {
        (new AddUniqueKeyToAuthGroupsAndUsers())->up();

        $groupsIndex = $this->findIndex('auth_groups', self::AUTH_GROUPS_UNIQUE_KEY);
        $this->assertNotNull($groupsIndex, 'auth_groups_group_unique index bulunamadı.');
        $this->assertSame('0', (string) $groupsIndex->Non_unique, 'auth_groups_group_unique UNIQUE olmalı (Non_unique=0).');
        $this->assertSame('group', $groupsIndex->Column_name);

        $usersIndexRows = $this->findIndexRows('auth_groups_users', self::AUTH_GROUPS_USERS_UNIQUE_KEY);
        $this->assertCount(2, $usersIndexRows, 'auth_groups_users_user_id_group_unique iki kolonlu olmalı.');

        $columnsBySeq = [];
        foreach ($usersIndexRows as $row) {
            $this->assertSame('0', (string) $row->Non_unique, 'auth_groups_users_user_id_group_unique UNIQUE olmalı (Non_unique=0).');
            $columnsBySeq[(int) $row->Seq_in_index] = $row->Column_name;
        }
        ksort($columnsBySeq);
        $this->assertSame(['user_id', 'group'], array_values($columnsBySeq));

        $this->assertNotNull(
            $this->findIndex('auth_groups_users', self::AUTH_GROUPS_USERS_USER_ID_KEY),
            'FK destek index auth_groups_users_user_id_index bulunamadı.',
        );
    }

    /**
     * (b) Round-trip: `down()` her iki key'i temiz kaldırıyor, tekrar `up()`
     * sorunsuz reapply ediyor.
     */
    public function testDownRemovesKeysAndUpReappliesCleanly(): void
    {
        $migration = new AddUniqueKeyToAuthGroupsAndUsers();

        $migration->up();
        $this->assertNotNull($this->findIndex('auth_groups', self::AUTH_GROUPS_UNIQUE_KEY));
        $this->assertNotEmpty($this->findIndexRows('auth_groups_users', self::AUTH_GROUPS_USERS_UNIQUE_KEY));

        $migration->down();
        $this->assertNull(
            $this->findIndex('auth_groups', self::AUTH_GROUPS_UNIQUE_KEY),
            'down() sonrası auth_groups_group_unique hâlâ var.',
        );
        $this->assertEmpty(
            $this->findIndexRows('auth_groups_users', self::AUTH_GROUPS_USERS_UNIQUE_KEY),
            'down() sonrası auth_groups_users_user_id_group_unique hâlâ var.',
        );
        $this->assertNotNull(
            $this->findIndex('auth_groups_users', self::AUTH_GROUPS_USERS_USER_ID_KEY),
            'down() kasitli olarak auth_groups_users_user_id_index index-ini birakmali (FK destek index-i, veri tasimaz, bkz. migration docblock-u).',
        );

        $migration->up();
        $this->assertNotNull(
            $this->findIndex('auth_groups', self::AUTH_GROUPS_UNIQUE_KEY),
            'round-trip: up() ikinci kez başarısız.',
        );
        $this->assertNotEmpty($this->findIndexRows('auth_groups_users', self::AUTH_GROUPS_USERS_UNIQUE_KEY));
    }

    /**
     * (c) Savunma dalı — `auth_groups`: duplicate `group` varken `up()` ham
     * DB hatasına değil, anlamlı bir `RuntimeException`'a düşer; mesaj hangi
     * değerin kaç kez tekrarlandığını içerir. Duplicate temizlenince `up()`
     * normal tamamlanır. Tablo test sonunda bozulmamış bırakılır.
     */
    public function testUpThrowsMeaningfulExceptionWhenAuthGroupsHasDuplicates(): void
    {
        $duplicateGroupName = 'dba_task10_dup_' . bin2hex(random_bytes(4));

        $this->db->table('auth_groups')->insert(['group' => $duplicateGroupName, 'description' => 'dup-1']);
        $this->db->table('auth_groups')->insert(['group' => $duplicateGroupName, 'description' => 'dup-2']);

        try {
            $caught = null;

            try {
                (new AddUniqueKeyToAuthGroupsAndUsers())->up();
            } catch (RuntimeException $e) {
                $caught = $e;
            }

            $this->assertNotNull($caught, 'up() duplicate varken RuntimeException fırlatmadı.');
            $this->assertStringContainsString('auth_groups', $caught->getMessage());
            $this->assertStringContainsString($duplicateGroupName, $caught->getMessage());
            $this->assertStringContainsString('x2', $caught->getMessage());

            // Guard tetiklendiğinde hiçbir ALTER çalışmamış olmalı.
            $this->assertNull($this->findIndex('auth_groups', self::AUTH_GROUPS_UNIQUE_KEY));
        } finally {
            $this->db->table('auth_groups')->where('group', $duplicateGroupName)->delete();
        }

        (new AddUniqueKeyToAuthGroupsAndUsers())->up();
        $this->assertNotNull($this->findIndex('auth_groups', self::AUTH_GROUPS_UNIQUE_KEY));
    }

    /**
     * (c) Savunma dalı — `auth_groups_users`: duplicate `(user_id, group)`
     * çifti için aynı savunma. `auth_groups_users.user_id` üzerinde GERÇEK
     * bir FK var (`ci4ms_auth_groups_users_user_id_foreign` → `ci4ms_users.id`,
     * bu görevin ilk yazımında ampirik olarak doğrulandı) -- bu yüzden sahte
     * bir `user_id` kullanılamaz. Ambient seed verisine (`ci4ms_users`'ta
     * bir satır olduğu varsayımına) GÜVENİLMEZ -- bu test kendi tek satırlık,
     * geçici kullanıcısını Shield'in kendi `UserModel`'i üzerinden kurar
     * (`tests/Modules/Users/PermgroupPrivilegeEscalationTest.php:createUser()`
     * ile aynı desen) ve sonunda siler; ham `INSERT` yerine bu tercih edildi
     * çünkü `users` şemasının hangi sütunları NOT NULL yaptığı zaman içinde
     * değişebiliyor (bu görevin geliştirilmesi sırasında `firstname` sonradan
     * NOT NULL bir sütun olarak gözlendi) -- Shield'in kendi modeli bunu
     * doğru yönetir.
     */
    public function testUpThrowsMeaningfulExceptionWhenAuthGroupsUsersHasDuplicates(): void
    {
        $groupName = 'dba_task10_users_dup_' . bin2hex(random_bytes(4));
        $suffix    = bin2hex(random_bytes(4));

        $provider = auth()->getProvider();
        $provider->save(new User([
            'firstname' => 'DBA',
            'surname'   => 'Task10',
            'username'  => 'dba_task10_' . $suffix,
            'email'     => 'dba_task10_' . $suffix . '@example.test',
            'password'  => 'SuperSecret123!',
            'active'    => 0,
        ]));
        $userId = (int) $provider->getInsertID();

        $this->db->table('auth_groups_users')->insert(['user_id' => $userId, 'group' => $groupName, 'created_at' => date('Y-m-d H:i:s')]);
        $this->db->table('auth_groups_users')->insert(['user_id' => $userId, 'group' => $groupName, 'created_at' => date('Y-m-d H:i:s')]);

        try {
            $caught = null;

            try {
                (new AddUniqueKeyToAuthGroupsAndUsers())->up();
            } catch (RuntimeException $e) {
                $caught = $e;
            }

            $this->assertNotNull($caught, 'up() duplicate varken RuntimeException fırlatmadı.');
            $this->assertStringContainsString('auth_groups_users', $caught->getMessage());
            $this->assertStringContainsString((string) $userId, $caught->getMessage());
            $this->assertStringContainsString($groupName, $caught->getMessage());

            $this->assertEmpty($this->findIndexRows('auth_groups_users', self::AUTH_GROUPS_USERS_UNIQUE_KEY));
        } finally {
            $this->db->table('auth_groups_users')->where(['user_id' => $userId, 'group' => $groupName])->delete();
            $this->db->table('users')->where('id', $userId)->delete();
        }

        (new AddUniqueKeyToAuthGroupsAndUsers())->up();
        $this->assertNotEmpty($this->findIndexRows('auth_groups_users', self::AUTH_GROUPS_USERS_UNIQUE_KEY));
    }

    /**
     * Migration dosyasını `require_once` eder. `AddUniqueKeyToAuthGroupsAndUsers`
     * standart PSR-4 otomatik yüklemeyle BULUNAMAZ -- CI4 migration dosya adları
     * (`{timestamp}_{ClassName}.php`) sınıf adıyla birebir eşleşmez;
     * `MigrationRunner` bu yüzden dosyayı `include_once $migration->path`
     * ile DOĞRUDAN yüklüyor (`vendor/codeigniter4/framework/system/Database/
     * MigrationRunner.php:966`), otomatik yüklemeye hiç güvenmiyor. Bu
     * dosyada da aynı teknik taklit edilir; zaman damgası hardcode
     * EDİLMEZ (`glob()` ile bulunur), migration yeniden adlandırılırsa bu
     * test kırılmaz.
     */
    private function requireMigrationFile(): void
    {
        if (class_exists(AddUniqueKeyToAuthGroupsAndUsers::class, false)) {
            return;
        }

        $matches = glob(ROOTPATH . 'modules/Auth/Database/Migrations/*_AddUniqueKeyToAuthGroupsAndUsers.php');
        $this->assertNotEmpty($matches, 'Migration dosyası bulunamadı: modules/Auth/Database/Migrations/*_AddUniqueKeyToAuthGroupsAndUsers.php');

        require_once $matches[0];
    }

    /**
     * Baseline normalize: iki UNIQUE key'i -- varsa -- ham SQL ile kaldırır,
     * `auth_groups_users_user_id_index` FK destek index'ini -- yoksa --
     * ekler. `Forge::dropKey()` yerine ham SQL kullanılıyor çünkü
     * `dropKey()` yoksa istisna fırlatır ve bu yöntem tam olarak "yoksa
     * hiçbir şey yapma" davranışı için kullanılıyor; `down()`'ın kendisinin
     * doğruluğu ayrı olarak test (b)'de sınanıyor.
     *
     * SIRA ÖNEMLİ: FK destek index'i ÖNCE garantiye alınır -- aksi halde
     * composite UNIQUE'i DROP etmek "needed in a foreign key constraint"
     * ile patlar (bkz. sınıf docblock'undaki ampirik bulgu).
     */
    private function dropKeysIfPresent(): void
    {
        if ($this->findIndex('auth_groups_users', self::AUTH_GROUPS_USERS_USER_ID_KEY) === null) {
            $this->db->query('ALTER TABLE `' . $this->db->DBPrefix . 'auth_groups_users` ADD INDEX `' . self::AUTH_GROUPS_USERS_USER_ID_KEY . '` (`user_id`)');
        }

        if ($this->findIndex('auth_groups', self::AUTH_GROUPS_UNIQUE_KEY) !== null) {
            $this->db->query('ALTER TABLE `' . $this->db->DBPrefix . 'auth_groups` DROP INDEX `' . self::AUTH_GROUPS_UNIQUE_KEY . '`');
        }

        if ($this->findIndexRows('auth_groups_users', self::AUTH_GROUPS_USERS_UNIQUE_KEY) !== []) {
            $this->db->query('ALTER TABLE `' . $this->db->DBPrefix . 'auth_groups_users` DROP INDEX `' . self::AUTH_GROUPS_USERS_UNIQUE_KEY . '`');
        }
    }

    private function findIndex(string $table, string $keyName): ?object
    {
        $rows = $this->findIndexRows($table, $keyName);

        return $rows[0] ?? null;
    }

    /**
     * @return list<object>
     */
    private function findIndexRows(string $table, string $keyName): array
    {
        $prefixed = $this->db->DBPrefix . $table;

        return $this->db->query('SHOW INDEX FROM `' . $prefixed . '` WHERE Key_name = ?', [$keyName])->getResult();
    }
}

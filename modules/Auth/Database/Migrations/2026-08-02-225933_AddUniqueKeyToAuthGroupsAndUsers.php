<?php

namespace Modules\Auth\Database\Migrations;

use CodeIgniter\Database\Migration;
use RuntimeException;

/**
 * `auth_groups.group` ve `auth_groups_users.(user_id,group)` çiftine UNIQUE
 * index ekler (bkz. KARAR-2, `.ci4ms/plans/migration-manager/context.md:614-625`).
 *
 * Kayıtlı isim ZORUNLU: `Forge::addUniqueKey()`'e verilen açık `$keyName`,
 * `Forge::createTable()`'ın aksine tabloyla BİRLEŞTİRİLMEZ (yalnız otomatik
 * üretilen isimde `$table . '_' . implode('_', $fields)` kalıbı kullanılır,
 * bkz. `vendor/codeigniter4/framework/system/Database/Forge.php:1179-1181`).
 * Bu yüzden `down()`'daki `dropKey()` çağrıları `$prefixKeyName=false` İLE
 * yapılır — aksi halde `dropKey()` `DBPrefix`'i (bu ortamda `ci4ms_`) isme
 * tekrar ekleyip var olmayan bir index adını aramaya çalışır
 * (`Forge.php:450-453`).
 *
 * `auth_groups_users.user_id`'de Shield'ın kurduğu bir FK var (`user_id` →
 * `users.id`, `vendor/codeigniter4/shield/src/Database/Migrations/
 * 2020-12-28-223112_create_auth_tables.php:150-152` — açık bir index
 * OLMADAN `addForeignKey()` çağrılıyor, MySQL/InnoDB FK için kendi örtük
 * destek index'ini otomatik kurar). AMPİRİK OLARAK DOĞRULANDI (bu görevin
 * raporunda): `(user_id, group)` composite UNIQUE eklendiğinde, MySQL
 * FK'nin örtük destek index'ini composite index LEHİNE otomatik DÜŞÜRÜYOR
 * (leftmost column eşleşiyor) — sonuçta composite UNIQUE, FK'nin TEK
 * destekleyici index'i hâline geliyor ve `down()`'da `DROP INDEX` "Cannot
 * drop index ...: needed in a foreign key constraint" ile PATLIYOR. Çözüm:
 * composite UNIQUE'den ÖNCE, ondan bağımsız, kalıcı ve açık isimli tekil bir
 * `user_id` index'i eklenir — bu index FK'nin daimi destekçisi olur,
 * composite UNIQUE serbestçe eklenip kaldırılabilir hâle gelir.
 */
class AddUniqueKeyToAuthGroupsAndUsers extends Migration
{
    private const AUTH_GROUPS_UNIQUE_KEY        = 'auth_groups_group_unique';
    private const AUTH_GROUPS_USERS_UNIQUE_KEY  = 'auth_groups_users_user_id_group_unique';
    private const AUTH_GROUPS_USERS_USER_ID_KEY = 'auth_groups_users_user_id_index';

    /**
     * `auth_groups.group` ve `auth_groups_users.(user_id,group)` için UNIQUE
     * index ekler. Duplicate satır varsa ham "Duplicate entry" DB hatasına
     * düşmeden önce anlamlı bir istisna fırlatır — bu koruma savunma
     * amaçlıdır: canlı `ci4ms`'te bu oturumda salt-okunur ölçülen durum
     * `auth_groups` 2 satır, `auth_groups_users` 3 satır, 0 duplicate ve
     * her iki tabloda da yalnızca `PRIMARY(id)` index'i (bu migration'la
     * eklenecek unique index'ler henüz yok) — yani KARAR-2'nin (unique
     * index kararı) kanıtı bu ölçüm değil, farklı/gelecekteki kurulumlarda
     * oluşabilecek duplicate'lere karşı önceden alınan bir tedbirdir.
     *
     * @throws RuntimeException Hedef tablolardan biri duplicate içeriyorsa.
     */
    public function up()
    {
        $this->guardNoDuplicateAuthGroups();
        $this->guardNoDuplicateAuthGroupsUsers();

        $this->forge->addUniqueKey('group', self::AUTH_GROUPS_UNIQUE_KEY);
        $this->forge->processIndexes('auth_groups');

        // FK'nin (user_id -> users.id) örtük destek index'ini composite
        // UNIQUE'in "yutmasını" önlemek için önce kalıcı, bağımsız bir tekil
        // index eklenir (bkz. sınıf docblock'u).
        if (! $this->indexExists('auth_groups_users', self::AUTH_GROUPS_USERS_USER_ID_KEY)) {
            $this->forge->addKey('user_id', false, false, self::AUTH_GROUPS_USERS_USER_ID_KEY);
            $this->forge->processIndexes('auth_groups_users');
        }

        $this->forge->addUniqueKey(['user_id', 'group'], self::AUTH_GROUPS_USERS_UNIQUE_KEY);
        $this->forge->processIndexes('auth_groups_users');
    }

    /**
     * `up()`'ta eklenen iki UNIQUE index'i kaldırır. Veri kaybı yoktur —
     * yalnızca constraint kalkar, satırlar dokunulmadan kalır.
     *
     * `auth_groups_users_user_id_index` KASITLI OLARAK burada drop
     * EDİLMEZ: `user_id` FK'sinin (bkz. sınıf docblock'u) kalıcı destek
     * index'idir — kaldırılırsa FK constraint hatası oluşur (composite
     * UNIQUE zaten kaldırıldığı için tek alternatif odur). Bu, `up()`'tan
     * önce de FK'nin (örtük biçimde) İHTİYAÇ DUYDUĞU bir yapıdır; bu
     * migration onu görünür/kalıcı hale getiriyor, veri taşımıyor.
     */
    public function down()
    {
        $this->forge->dropKey('auth_groups', self::AUTH_GROUPS_UNIQUE_KEY, false);
        $this->forge->dropKey('auth_groups_users', self::AUTH_GROUPS_USERS_UNIQUE_KEY, false);
    }

    /**
     * Verilen tabloda, verilen isimde bir index olup olmadığını salt-okunur
     * `SHOW INDEX` ile kontrol eder (idempotency guard — `up()` iki kez
     * çağrılırsa "Duplicate key name" hatasına düşmemek için).
     */
    private function indexExists(string $table, string $keyName): bool
    {
        $prefixed = $this->db->DBPrefix . $table;

        return $this->db->query('SHOW INDEX FROM `' . $prefixed . '` WHERE Key_name = ?', [$keyName])->getResultArray() !== [];
    }

    /**
     * `auth_groups.group` üzerinde duplicate değer var mı diye salt-okunur
     * kontrol yapar.
     *
     * @throws RuntimeException Duplicate `group` değeri bulunursa, hangi
     *                          değerlerin kaç kez tekrarlandığını mesaja yazar.
     */
    private function guardNoDuplicateAuthGroups(): void
    {
        $duplicates = $this->db->table('auth_groups')
            ->select('group, COUNT(*) AS duplicate_count')
            ->groupBy('group')
            ->having('COUNT(*) >', 1)
            ->get()
            ->getResultArray();

        if ($duplicates === []) {
            return;
        }

        $details = implode(', ', array_map(
            static fn (array $row): string => sprintf('"%s" (x%d)', $row['group'], (int) $row['duplicate_count']),
            $duplicates,
        ));

        throw new RuntimeException(
            "auth_groups: 'group' sütununda UNIQUE index eklenmeden önce temizlenmesi gereken duplicate değer(ler) var — {$details}. "
            . 'Bu migration çalıştırılmadan önce fazla satırlar manuel olarak silinmeli (bkz. KARAR-2).',
        );
    }

    /**
     * `auth_groups_users.(user_id,group)` çifti üzerinde duplicate var mı
     * diye salt-okunur kontrol yapar.
     *
     * @throws RuntimeException Duplicate (user_id, group) çifti bulunursa,
     *                          hangi çiftlerin kaç kez tekrarlandığını mesaja yazar.
     */
    private function guardNoDuplicateAuthGroupsUsers(): void
    {
        $duplicates = $this->db->table('auth_groups_users')
            ->select('user_id, group, COUNT(*) AS duplicate_count')
            ->groupBy(['user_id', 'group'])
            ->having('COUNT(*) >', 1)
            ->get()
            ->getResultArray();

        if ($duplicates === []) {
            return;
        }

        $details = implode(', ', array_map(
            static fn (array $row): string => sprintf('user_id=%s,group="%s" (x%d)', $row['user_id'], $row['group'], (int) $row['duplicate_count']),
            $duplicates,
        ));

        throw new RuntimeException(
            "auth_groups_users: (user_id, group) çiftinde UNIQUE index eklenmeden önce temizlenmesi gereken duplicate(lar) var — {$details}. "
            . 'Bu migration çalıştırılmadan önce fazla satırlar manuel olarak silinmeli (bkz. KARAR-2).',
        );
    }
}

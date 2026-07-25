<?php

namespace Modules\Notifications\Libraries;

use CodeIgniter\Database\BaseConnection;

/**
 * Additive şema yeteneklerinin (yeni kolon / yeni tablo) istek-ömürlü guard'ı.
 *
 * Modül klasörü bırakılıp migration'lar HENÜZ koşmamış olabilir; bu durumda ne
 * `notifications.exclude_users` / `notifications.created_by` kolonları ne de
 * `notification_preferences` tablosu vardır. Okuma ve yazma yolları bu guard'lara
 * bakarak ilgili SQL parçasını
 * tamamen atlar — modül migrate edilmeden fatal atmaz (Notifier::tablesReady()
 * ile aynı savunma refleksi, ama AYRI sözleşme: tablesReady() Model B'nin
 * ZORUNLU iki tablosu içindir, buradakiler opsiyonel yeteneklerdir).
 *
 * Sonuçlar istek başına (static) hafızalanır: şema tek bir istek içinde
 * değişmez, bu yüzden her sorguda `tableExists()`/`fieldExists()` çağırıp
 * DB'yi yormaya gerek yoktur. Hafıza anahtarı yeteneğin YANINDA bağlantı
 * kimliğini (veritabanı adı + tablo öneki) taşır: aynı istekte birden çok DB
 * grubuyla çalışan kurulumlarda (multi-tenant, ayrı rapor bağlantısı) bir
 * bağlantının cevabı diğerine sızmamalıdır — sızsaydı hata fail-open yönünde,
 * yani kolonu olmayan bir şemaya hariç tutma SQL'i üretmek yönünde olurdu.
 *
 * UZUN ÖMÜRLÜ SÜREÇ UYARISI: hafıza istek ömrü varsayımına dayanır. Bir queue
 * worker / daemon aynı PHP sürecinde migration sonrası çalışmaya devam ederse
 * eski cevabı taşımaya devam eder; böyle bir süreçte migration'dan sonra
 * {@see reset()} çağrılmalıdır.
 */
final class SchemaGuard
{
    /**
     * '{veritabanı}|{önek}|{yetenek}' => var mı (istek ömrü boyunca hafızalanır).
     *
     * @var array<string, bool>
     */
    private static array $memo = [];

    /**
     * `notifications.exclude_users` kolonu mevcut mu (hariç tutma yazılabilir/okunabilir mi).
     *
     * @param BaseConnection<object, object> $db Şemanın sorulacağı bağlantı.
     *
     * @return bool Kolon varsa true.
     */
    public static function hasExcludeUsers(BaseConnection $db): bool
    {
        return self::$memo[self::memoKey($db, 'exclude_users')] ??= $db->fieldExists('exclude_users', 'notifications');
    }

    /**
     * `notifications.created_by` kolonu mevcut mu (hesap verebilirlik izi yazılabilir mi).
     *
     * `hasExcludeUsers()` ile aynı kalıp, AMA farklı bir teslim sözleşmesi: bu yetenek
     * yokken satır YİNE yazılır (fail-open), yalnız iz kaybolur
     * ({@see \Modules\Notifications\Libraries\Channels\InAppChannel::buildRow()}).
     *
     * @param BaseConnection<object, object> $db Şemanın sorulacağı bağlantı.
     *
     * @return bool Kolon varsa true.
     */
    public static function hasCreatedBy(BaseConnection $db): bool
    {
        return self::$memo[self::memoKey($db, 'created_by')] ??= $db->fieldExists('created_by', 'notifications');
    }

    /**
     * `notification_preferences` tablosu mevcut mu (tercih JOIN'i eklenebilir mi).
     *
     * @param BaseConnection<object, object> $db Şemanın sorulacağı bağlantı.
     *
     * @return bool Tablo varsa true.
     */
    public static function hasPreferences(BaseConnection $db): bool
    {
        return self::$memo[self::memoKey($db, 'notification_preferences')] ??= $db->tableExists('notification_preferences');
    }

    /**
     * Bir yeteneğin, bağlantıya özgü hafıza anahtarı.
     *
     * @param BaseConnection<object, object> $db         Cevabın alındığı bağlantı.
     * @param string                         $capability Yetenek adı.
     *
     * @return string '{veritabanı}|{önek}|{yetenek}'.
     */
    private static function memoKey(BaseConnection $db, string $capability): string
    {
        return $db->getDatabase() . '|' . $db->getPrefix() . '|' . $capability;
    }

    /**
     * Hafızalanmış yetenekleri sıfırlar.
     *
     * @internal Yalnız test desteği: migration'ı aynı süreç içinde koşan ya da
     *           sahte bağlantı enjekte eden testler için.
     *
     * @return void
     */
    public static function reset(): void
    {
        self::$memo = [];
    }
}

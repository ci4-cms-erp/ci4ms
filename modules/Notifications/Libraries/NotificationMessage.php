<?php

namespace Modules\Notifications\Libraries;

use Modules\Notifications\Config\NotificationsConfig;

/**
 * Tek bir bildirim satır-tanımının değişmez taşıyıcısı (DTO).
 *
 * Yapıcıda tüm alanlar temizlenir (type/title/body/url için KARAKTER bazlı kırpma,
 * title/body strip_tags, url doğrulama, severity ve excludeUsers normalizasyonu). Böylece bir
 * NotificationMessage örneği daima iyi biçimlidir ve kanallar ham girdi temizliğiyle
 * uğraşmaz. `targetType`/`targetValue` Model B relevans sözleşmesini taşır
 * ('broadcast' | 'user' | 'group'); `excludeUsers` ise hedefin İÇİNDEN çıkarılacak
 * kullanıcıları taşır (sentinel-sarmalı CSV olarak saklanır, bkz. {@see encodeExcludeUsers()}).
 *
 * HARİÇ TUTMANIN KAYNAĞI (provenance) ayrı taşınır, çünkü teslim garantileri farklıdır:
 *   - `explicitExcludeUsers`: çağıranın {@see NotificationBuilder::exceptUser()} ile
 *     AÇIKÇA istediği dışlama. Bu bir GARANTİdir; uygulanamıyorsa kanal satırı hiç
 *     yazmamalıdır (FAIL-CLOSED) — aksi halde dışlanan kullanıcı bildirimi görür.
 *   - `derivedExcludeUsers`: aynı yayında hem doğrudan hem grup üzerinden hedeflenen
 *     kullanıcılar için {@see NotificationBuilder::dispatch()}'in TÜRETTİĞİ daraltma.
 *     Bu bir garanti değil, çift-teslim önleyen bir OPTİMİZASYONdur; uygulanamıyorsa
 *     satır yine de yazılır (FAIL-OPEN) ve kullanıcı bildirimi iki kez görür.
 * `excludeUsers` ikisinin BİRLEŞİMİdir ve depolanan tek değer odur; kaynak ayrımı
 * yalnız "uygulanamıyorsa ne olacak" kararında kullanılır.
 */
final class NotificationMessage
{
    /** Geçerli önem seviyeleri; bunun dışındaki her değer 'info'ya düşer. */
    public const SEVERITIES = ['info', 'warning', 'critical'];

    public string $type;
    public string $severity;
    public string $title;
    public ?string $body;
    public ?string $url;
    public string $targetType;
    public ?string $targetValue;

    /** @var string[] */
    public array $channels;

    /** @var list<int> Depolanan BİRLEŞİK liste: açık ∪ türetilmiş (tekil + artan sıralı). */
    public array $excludeUsers;

    /** @var list<int> exceptUser() ile AÇIKÇA istenen dışlamalar — teslim GARANTİSİ. */
    public array $explicitExcludeUsers;

    /** @var list<int> Örtüşme daraltmasından TÜREYEN dışlamalar — çift-teslim optimizasyonu. */
    public array $derivedExcludeUsers;

    /**
     * Yayını ÜRETEN kullanıcının kimliği (alıcısı değil) — hesap verebilirlik izi.
     *
     * Yalnız insan eliyle üretilen yayınlarda (FAZ 3 composer) dolar; olay/CLI
     * kaynaklı bildirimlerde null kalır ve bu ayrım kasıtlıdır: null = "sistem üretti".
     * Değerin kaynağı DAİMA sunucudur (`auth()->id()`); istemciden okunmuş bir kimlik
     * buraya giremez. Teslim kararlarını ETKİLEMEZ — relevans, hariç tutma ve tercih
     * filtrelerinin hiçbiri bu alana bakmaz.
     *
     * @var int|null
     */
    public ?int $createdBy;

    /**
     * @param string   $type        Makine-okunur olay tipi ('comment.new' ...).
     * @param string   $severity    info|warning|critical (geçersiz → info).
     * @param string   $title       Görünen başlık (strip_tags + TITLE_MAX karakter kırpma).
     * @param string|null $body      Görünen gövde ya da null (strip_tags + BODY_MAX karakter kırpma).
     * @param string|null $url       Tıklama hedefi; site-içi '/...' ya da http(s), aksi halde null.
     * @param string   $targetType  'broadcast' | 'user' | 'group'.
     * @param string|null $targetValue Hedef değeri (user id / grup adı), broadcast için null.
     * @param string[] $channels     Bu mesajın gönderileceği kanal slug'ları.
     * @param array<int, mixed> $explicitExcludeUsers exceptUser() ile açıkça istenen dışlamalar (garanti).
     * @param array<int, mixed> $derivedExcludeUsers  Örtüşme daraltmasından türeyen dışlamalar (optimizasyon).
     * @param int|null          $createdBy            Yayını üreten kullanıcının kimliği; 0/negatif → null (sistem).
     */
    public function __construct(
        string $type,
        string $severity,
        string $title,
        ?string $body,
        ?string $url,
        string $targetType,
        ?string $targetValue,
        array $channels,
        array $explicitExcludeUsers = [],
        array $derivedExcludeUsers = [],
        ?int $createdBy = null
    ) {
        // KARAKTER bazlı kırpma (mb_substr), BAYT bazlı değil: doğrulamanın
        // `max_length` kuralı da karakter sayar (mb_strlen). İkisi ayrışırsa 255
        // karakterlik Türkçe bir başlık doğrulamayı geçer, 255. BAYTTA kesilir ve son
        // çok-baytlı karakter ikiye bölünür; `strictOn = false` olduğu için MySQL bunu
        // sessizce kabul eder ve depoda bozuk bir dizi kalır.
        $this->type         = mb_substr(trim($type), 0, NotificationsConfig::TYPE_MAX, 'UTF-8');
        $this->severity     = self::normalizeSeverity($severity);
        $this->title        = mb_substr(strip_tags(trim($title)), 0, NotificationsConfig::TITLE_MAX, 'UTF-8');
        $this->body         = ($body !== null && trim($body) !== '')
            ? mb_substr(strip_tags(trim($body)), 0, NotificationsConfig::BODY_MAX, 'UTF-8')
            : null;
        $url                = self::sanitizeUrl($url);
        $this->url          = $url !== null ? mb_substr($url, 0, NotificationsConfig::URL_MAX, 'UTF-8') : null;
        $this->targetType   = $targetType;
        $this->targetValue  = $targetValue;
        $this->channels     = $channels;

        $this->explicitExcludeUsers = self::normalizeExcludeUsers($explicitExcludeUsers);
        $this->derivedExcludeUsers  = self::normalizeExcludeUsers($derivedExcludeUsers);
        $this->excludeUsers         = self::normalizeExcludeUsers(
            array_merge($this->explicitExcludeUsers, $this->derivedExcludeUsers)
        );

        // 0 ve negatif hiçbir kullanıcıya karşılık gelmez; "sistem üretti" (null) ile
        // "0 numaralı kullanıcı üretti" ayrımı depoda kalmasın diye burada indirgenir.
        $this->createdBy = ($createdBy !== null && $createdBy > 0) ? $createdBy : null;
    }

    /**
     * Hariç tutma listesini normalize eder: int'e cast, geçersizleri at, tekilleştir, sırala.
     *
     * 0 ve negatif kimlikler (ve sayıya çevrilemeyen girdiler) atılır — bunlar hiçbir
     * kullanıcıya karşılık gelmez ve saklanan CSV'yi gereksiz büyütür. Sıralama depolanan
     * değeri belirlenimci yapar (aynı hariç tutma kümesi daima aynı metni üretir).
     *
     * @param array<int, mixed> $userIds Ham kullanıcı kimlikleri.
     *
     * @return list<int> Tekil, artan sıralı, pozitif kimlikler.
     */
    public static function normalizeExcludeUsers(array $userIds): array
    {
        $ids = array_filter(array_map(static fn ($id): int => (int) $id, $userIds), static fn (int $id): bool => $id > 0);
        $ids = array_values(array_unique($ids));
        sort($ids);

        return $ids;
    }

    /**
     * Hariç tutma listesini SENTINEL-SARMALI CSV'ye çevirir (depolama biçimi).
     *
     * Değer daima baştan ve sondan virgüllüdür (`,5,12,`), böylece okuma yolundaki
     * `NOT LIKE '%,{userId},%'` kontrolü `,1,` ile `,12,` arasında karışmaz. Liste
     * boşsa NULL döner (kolon varsayılanı). JSON KULLANILMAZ: MySQL/MariaDB sürüm
     * farklarından bağımsız kalmak için düz metin + LIKE tercih edilmiştir.
     *
     * @param array<int, mixed> $userIds Ham ya da normalize edilmiş kimlikler.
     *
     * @return string|null `,5,12,` biçiminde metin ya da liste boşsa null.
     */
    public static function encodeExcludeUsers(array $userIds): ?string
    {
        $ids = self::normalizeExcludeUsers($userIds);

        return $ids === [] ? null : ',' . implode(',', $ids) . ',';
    }

    /**
     * Tek bir kullanıcı için okuma yolunda kullanılacak LIKE kalıbı (depolama biçiminin ikizi).
     *
     * {@see encodeExcludeUsers()} ile aynı sentinel sözleşmesini uygular; ikisi birlikte
     * değişmelidir. Kimlik int'e cast edildiği için kalıp joker karakter taşıyamaz.
     *
     * @param int $userId Okuyan kullanıcının kimliği.
     *
     * @return string `%,5,%` biçiminde LIKE kalıbı.
     */
    public static function excludeMatchPattern(int $userId): string
    {
        return '%,' . $userId . ',%';
    }

    /**
     * Geçersiz/boş önem seviyesini güvenli varsayılana ('info') indirger.
     *
     * @param string $severity Ham severity girdisi.
     *
     * @return string SEVERITIES içinden biri.
     */
    public static function normalizeSeverity(string $severity): string
    {
        $severity = strtolower(trim($severity));

        return in_array($severity, self::SEVERITIES, true) ? $severity : 'info';
    }

    /**
     * Yalnız site-içi ('/...') veya http(s) mutlak URL kabul eder; aksi halde null döner.
     *
     * 'javascript:' ve protokol-göreli '//host' / '/\host' (WHATWG parser '\'yi
     * '/'ye normalize eder) gibi open-redirect vektörlerini ve iç kontrol karakteri
     * (CR/LF/TAB/NUL vb.) barındıran URL'leri eler.
     * (Görüntüleme tarafında ayrıca esc() ile kaçılmalıdır — savunma iki katman.)
     *
     * @param string|null $url Ham URL girdisi.
     *
     * @return string|null Güvenli URL ya da null.
     */
    public static function sanitizeUrl(?string $url): ?string
    {
        if ($url === null) {
            return null;
        }

        $url = trim($url);
        if ($url === '') {
            return null;
        }

        // İç kontrol karakteri (CR/LF/TAB/NUL vb.) header/DOM enjeksiyon yüzeyidir;
        // trim yalnız uçları temizler, içerideki karakterler burada reddedilir.
        if (preg_match('/[\x00-\x1F\x7F]/', $url) === 1) {
            return null;
        }

        // '/backend/...' evet; ama '//host' ve '/\host' (protokol-göreli) hayır.
        if ($url[0] === '/' && (strlen($url) === 1 || ! in_array($url[1], ['/', '\\'], true))) {
            return $url;
        }

        if (preg_match('#^https?://#i', $url) === 1) {
            return $url;
        }

        return null;
    }
}

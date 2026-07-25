<?php namespace Modules\Notifications\Config;

use Modules\Notifications\Libraries\Channels\EmailChannel;
use Modules\Notifications\Libraries\Channels\InAppChannel;
use Modules\Notifications\Libraries\Channels\RealtimeChannel;
use Modules\Notifications\Libraries\Channels\WebhookChannel;

/**
 * Bildirim Merkezi modül yapılandırması (MenuConfig kalıbıyla birebir).
 * - filters: tüm backend/notifications uçlarını backendGuard arkasına alır.
 * - moduleInfo: modül listesindeki ikon.
 * - menus: sol menüde görünecek gezinme kaydı (etiket lang('Notifications.notifications')).
 * - channels: Model B yayın hattının taban kanal haritası (slug => sınıf). Başka
 *   modüller kendi Config'lerinde `public array $notificationChannels` tanımlayarak
 *   yeni kanal ekleyebilir; Notifier dispatch anında bunları tarayıp birleştirir.
 */
class NotificationsConfig extends \CodeIgniter\Config\BaseConfig
{
    /** Okunmamış-rozet cache'inin ömrü (sn) — polling DB'yi yormasın. */
    public const UNREAD_CACHE_TTL = 60;

    /** Üst çubuk çanı / feed ucunun döndürdüğü satır sayısı. */
    public const FEED_LIMIT = 10;

    /** Tam liste sayfasının döndürdüğü satır sayısı. */
    public const LIST_LIMIT = 50;

    /** Üst çubuk çanının istemci yoklama aralığı (ms). */
    public const POLL_MS = 60000;

    /** `type` alanının en fazla karakter uzunluğu (kolon VARCHAR(64)). */
    public const TYPE_MAX = 64;

    /** `title` alanının en fazla uzunluğu (kolon VARCHAR(255)). */
    public const TITLE_MAX = 255;

    /** `url` alanının en fazla uzunluğu (kolon VARCHAR(255)). */
    public const URL_MAX = 255;

    /**
     * `body` alanının en fazla KARAKTER uzunluğu.
     *
     * Kolon TEXT'tir (65.535 BAYT) ve proje `strictOn = false` ile çalışır: taşan değer
     * SESSİZCE kesilir. Tavan bu yüzden kolonun çok altında tutulur — 4.000 karakter,
     * en kötü durumda (4 baytlık UTF-8 karakterler) 16 KB eder, yani kolonun dörtte
     * biri; bir bildirim gövdesi için zaten cömert bir sınırdır. Aynı sabiti hem
     * doğrulama ({@see \Modules\Notifications\Controllers\ComposerController::validationRules()})
     * hem DTO clamp'i ({@see \Modules\Notifications\Libraries\NotificationMessage})
     * kullanır: ikisi ayrışırsa biri diğerinin geçirdiğini sessizce keserdi.
     */
    public const BODY_MAX = 4000;

    /**
     * Tek bir yayında verilebilecek en fazla HEDEF direktifi (kullanıcı + grup toplamı).
     *
     * Her direktif Model B'de BİR satır demektir: bir INSERT ve — küresel satır her
     * kullanıcının rozetini etkilediği için — satır başına bir
     * `cache()->deleteMatching('notif_unread_*')` süpürmesi
     * ({@see \Modules\Notifications\Libraries\Channels\InAppChannel::send()}). PHP'nin
     * varsayılan `max_input_vars` değeri 1000'dir, yani tavan olmadan TEK bir form
     * gönderimi ~1000 INSERT + ~1000 cache süpürmesi tetikleyebilir; 200 hem gerçek bir
     * duyuru için fazlasıyla yeterli hem de bu maliyetin beşte biri. Aşıldığında liste
     * KIRPILMAZ, gönderim reddedilir (fail-closed): kırpma, yöneticinin seçtiği bir
     * kısmın sessizce bildirim almaması demek olurdu.
     *
     * Grup adları ayrıca whitelist ile sınırlıdır ({@see
     * \Modules\Notifications\Controllers\ComposerController::allowedGroups()}), yani
     * sınırsız büyüyebilen eksen kullanıcı seçimidir; tavan yine de ikisinin TOPLAMINA
     * uygulanır çünkü maliyeti belirleyen şey satır sayısıdır, satırın türü değil.
     */
    public const TARGETS_MAX = 200;

    /**
     * Tek bir satırın `exclude_users` listesinde taşıyabileceği en fazla kullanıcı sayısı.
     *
     * `exclude_users` bir TEXT kolonudur (65.535 bayt) ve proje `strictOn = false` ile
     * çalışır: sınırı aşan bir CSV SESSİZCE KESİLİR. Kesilen değerde sentinel sarmalı
     * bozulur, okuma yolundaki `NOT LIKE '%,{id},%'` kalıbı eşleşmez ve hariç tutulan
     * kullanıcılar bildirimi GÖRÜR — yani taşma fail-OPEN yönünde bozulur. Bu yüzden
     * tavan, kesme sınırının çok altında tutulur (500 kimlik ≈ 4 KB, 64 KB'ın onda
     * biri bile değil) ve aşıldığında satır KIRPILMAZ, hiç yazılmaz
     * ({@see \Modules\Notifications\Libraries\Channels\InAppChannel::send()}).
     */
    public const EXCLUDE_USERS_MAX = 500;

    /**
     * `$realtimeConnCapDefault` GEÇERSİZ (0) hidre olduğunda düşülecek fail-closed değer.
     *
     * Sabittir, yani `.env` onu ezemez: kaynağı bozuk bir yapılandırmanın kendisi olamaz.
     * `$realtimeConnCapDefault`'un bildirim değeriyle aynı olmalıdır.
     */
    public const CONN_CAP_FALLBACK = 6;

    public $csrfExcept = [];

    public $filters = [
        'backendGuard' => ['before' => [
            'backend/notifications', 'backend/notifications/*'
        ]]
    ];

    public $moduleInfo = [
        'icon' => 'fas fa-bell',
    ];

    public $menus = [
        'Notifications.notifications' => [
            'icon'         => 'fas fa-bell',
            'inNavigation' => true,
            'hasChild'     => false,
            'pageSort'     => 9,
            'parent_pk'    => null,
        ],
    ];

    /**
     * `ci4ms.audit` olayından üretilen uyarıların gönderileceği Shield grubu.
     */
    public string $auditTargetGroup = 'superadmin';

    /**
     * Sayfa-içi anlık teslim (Redis destekli kendi PHP-SSE ucu) açık mı.
     *
     * false iken RealtimeChannel erken skipped döner, SSE stream() ucu 204 verir ve
     * çan yalnız 60 sn polling yapar (davranış birebir eski hali).
     * `.env`: notificationsconfig.realtimeEnabled.
     */
    public bool $realtimeEnabled = false;

    /**
     * Tek bir SSE bağlantısının saniye cinsinden ömrü (cap).
     *
     * Süre dolunca stream() temiz döner; tarayıcı EventSource otomatik reconnect eder.
     * `.env`: notificationsconfig.realtimeStreamTtl.
     */
    public int $realtimeStreamTtl = 30;

    /**
     * Redis `notif:sig:*` sinyal anahtarlarının saniye cinsinden TTL'i.
     *
     * bump() her INCR'de EXPIRE ile bu değere tazeler; ölü kanal sayaçları kendiliğinden
     * düşer. `.env`: notificationsconfig.realtimeSignalTtl.
     */
    public int $realtimeSignalTtl = 300;

    /**
     * Bir kullanıcının eşzamanlı açabileceği varsayılan SSE bağlantı sayısı.
     *
     * Her açık bağlantı TTL'i boyunca bir PHP-FPM worker'ını meşgul eder; cap olmadan
     * tek bir kimlik `pm.max_children` havuzunu tüketebilir. Kullanıcının gruplarından
     * hiçbiri `$realtimeConnCapByGroup`'ta eşleşmezse bu değer uygulanır.
     *
     * Varsayılan 6: çan her SEKMEDE kendi EventSource'unu açar (bkz. Views/Cells/bell.php),
     * bu yüzden cap bir KAYNAK eşiğidir, kimlik başına oturum sınırı değil — birkaç sekmeli
     * normal bir admin cap'e takılmamalı, 6 yine de tipik `pm.max_children` için güvenlidir.
     *
     * DEĞER SEMANTİĞİ:
     * - Pozitif  = uygulanacak cap.
     * - NEGATİF (ör. `-1`) = SINIRSIZ ve GLOBAL kaçış valfi: bağlantı defterine hiç
     *   dokunulmaz ve `$realtimeConnCapByGroup` tablosu da hiç değerlendirilmez.
     * - 0 = GEÇERSİZ, sınırsız DEĞİL: {@see CONN_CAP_FALLBACK} uygulanır (fail-closed) ve
     *   bir kez `warning` loglanır. Gerekçe: property `int` bildirimli olduğu için CI4
     *   (`BaseConfig::initEnvValue()`) `.env`'deki her sayısal olmayan değeri — yani her
     *   yazım hatasını — 0'a cast eder. Bu yüzden 0, "korumayı kapat" sentinel'i OLAMAZ;
     *   negatif bir değer bir typo'nun cast'inden asla çıkmaz, güvenli sentinel odur.
     * `.env`: notificationsconfig.realtimeConnCapDefault (sınırsız için `-1` yazın).
     */
    public int $realtimeConnCapDefault = self::CONN_CAP_FALLBACK;

    /**
     * Shield grup adına göre SSE bağlantı cap'i (grup => eşzamanlı bağlantı sayısı).
     *
     * Kullanıcı birden çok eşleşen gruptaysa EN YÜKSEK cap uygulanır; hiçbiri eşleşmezse
     * `$realtimeConnCapDefault`. Girdi semantiği `$realtimeConnCapDefault` ile aynıdır:
     * NEGATİF (ör. `-1`) = SINIRSIZ ve eşleşenler arasında kazanır; 0 = GEÇERSİZ, o satır
     * YOK SAYILIR (grup hiç eşleşmemiş sayılır), sessizce "sınırsız"a dönüşmez. Sayısal
     * olmayan bir değer de aynı şekilde yok sayılır — bu filtre, property'ye doğrudan
     * atama yapan çağrı yolları içindir; `.env` yolunda değer zaten 0'a cast edilmiş olur.
     * `$realtimeConnCapDefault` NEGATİF iken bu tablo hiç okunmaz.
     *
     * `.env`: notificationsconfig.realtimeConnCapByGroup.<grup>. DİKKAT — `.env` yalnız
     * BURADA VAR OLAN anahtarları ezebilir, yeni grup EKLEYEMEZ: `BaseConfig::initEnvValue()`
     * dizilerde `array_keys($property)` üzerinde döner, yani `superadmin` dışında bir grup
     * cap'lenecekse önce bu diziye satırı elle eklemek gerekir.
     *
     * @var array<string, int|string>
     */
    public array $realtimeConnCapByGroup = [
        'superadmin' => 10,
    ];

    /**
     * Tercih ekranında susturulabilen bildirim tipleri (whitelist): tip/tip-öneki => lang anahtarı.
     *
     * Anahtar bir TAM tip ('audit.login') ya da tip ÖNEKİ ('audit') olabilir; okuma
     * yolundaki `p.type = n.type OR n.type LIKE CONCAT(p.type, '.%')` koşulu ikisini de
     * karşılar. Bu dizi aynı zamanda PreferenceController::save()'in kabul ettiği tek
     * kaynaktır — dışındaki her `type` sessizce atılır.
     *
     * İçerik, repoda GERÇEKTEN yayınlanan tiplerden türetilmiştir: tek üretici
     * `ci4ms.audit` olayıdır (app/Config/Events.php → `notify('audit.' . $action)`).
     * `notifications:test` CLI'ının yazdığı 'test' tipi bilerek DIŞARIDA bırakılmıştır:
     * o bir tanılama aracıdır, susturulabilmesi amacını bozar. Yeni bir tip yayınlayan
     * modül, slug'ını buraya ve karşılık gelen anahtarı Language/{en,tr}'ye eklemelidir.
     *
     * @var array<string, string>
     */
    public array $preferenceTypes = [
        'audit' => 'Notifications.prefTypeAudit',
    ];

    /**
     * Tercih ekranında seçilebilen kanal anahtarları (whitelist).
     *
     * Şimdilik yalnız '*' (tüm kanallar) sunulur ve bu KASITLIDIR: kalıcı satırı
     * YALNIZ InAppChannel yazar (`notifications.channel` daima 'inapp'), realtime kanal
     * ise satır değil per-TOPIC bir Redis sinyali üretir. Sinyal kullanıcı/tip taşımaz,
     * dolayısıyla per-user 'realtime' susturması okuma yolunda uygulanamaz — arayüze
     * konsaydı hiçbir şey yapmayan ölü bir ayar olurdu. Satır yazan yeni bir kanal
     * eklendiğinde slug'ını buraya eklemek yeterlidir; applyRelevance()'taki
     * `(p.channel = '*' OR p.channel = n.channel)` koşulu granülerliği zaten destekler.
     *
     * @var list<string>
     */
    public array $preferenceChannels = ['*'];

    /**
     * Taban kanal haritası (slug => ChannelInterface uygulaması).
     *
     * @var array<string, class-string>
     */
    public array $channels = [
        'inapp'    => InAppChannel::class,
        'email'    => EmailChannel::class,
        'webhook'  => WebhookChannel::class,
        'realtime' => RealtimeChannel::class,
    ];
}

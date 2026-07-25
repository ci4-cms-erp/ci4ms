<?php

namespace Modules\Notifications\Controllers;

use Modules\Notifications\Config\NotificationsConfig;
use Modules\Notifications\Libraries\ConnectionRegistryInterface;
use Modules\Notifications\Libraries\Notifier;
use Modules\Notifications\Libraries\SignalStoreInterface;

/**
 * Bildirim Merkezi — Redis destekli kendi PHP SSE (Server-Sent Events) ucu.
 *
 * Uç (bkz. Config/Routes.php):
 *   GET backend/notifications/stream  stream()  — text/event-stream akışı (role: read)
 *
 * GÜVENLİK (IDOR gate): Dinlenecek kanallar KATI biçimde oturumdaki kullanıcıdan
 * `Notifier::topicsFor()` ile türetilir (applyRelevance'ın transport aynası). İstemci
 * hiçbir kanal/topic parametresi göndermez; kanallar yalnız sunucu tarafında session'dan
 * çözülür. Payload minimal bir "nudge"tır — istemci feed ucundan DB'yi yeniden okuyarak
 * reconcile eder. Kalıcı kaynak InAppChannel'ın DB yazımıdır; bu akış best-effort sinyaldir.
 *
 * KAYNAK KORUMASI (bağlantı cap'i): Her açık akış TTL'i boyunca bir PHP-FPM worker'ını
 * meşgul eder, bu yüzden kimlik başına eşzamanlı bağlantı sayısı rol bazlı cap'lenir
 * (NotificationsConfig::$realtimeConnCapDefault / $realtimeConnCapByGroup). Yuvalar
 * ConnectionRegistryInterface üzerinden ayrılır; kapasite dolu ise akış hiç açılmaz,
 * istemci 429 alıp polling'e düşer.
 */
class RealtimeController extends \Modules\Backend\Controllers\BaseController
{
    /** Redis sinyal sayaçlarını yoklama aralığı (mikrosaniye) — Redis GET sub-ms olduğundan ucuz. */
    private const STREAM_POLL_US = 1_000_000;

    /** İki heartbeat comment'i arası saniye (idle proxy/tarayıcı zaman aşımlarını canlı tutar). */
    private const HEARTBEAT_SECONDS = 15;

    /** Bir SSE bağlantısının izin verilen en uzun ömrü (sn) — .env TTL bunun üstüne çıkamaz. */
    private const STREAM_TTL_CAP_SECONDS = 120;

    /**
     * Bağlantı yuvası TTL'ine eklenen tampon (sn) — release() hiç çalışmasa da yuva kesin düşer.
     *
     * Kısa tutulur: yuva akış bittikten sonra ne kadar ayakta kalırsa, yetim yuva o kadar
     * uzun süre kullanıcının kendi cap'ini yer.
     */
    private const CONN_TTL_BUFFER_SECONDS = 5;

    /**
     * Paylaşımlı Notifier servis örneği.
     *
     * @return Notifier
     */
    private function notifier(): Notifier
    {
        /** @var Notifier $notifier */
        $notifier = service('notifier');

        return $notifier;
    }

    /**
     * Paylaşımlı eşzamanlı bağlantı defteri servis örneği.
     *
     * @return ConnectionRegistryInterface
     */
    private function registry(): ConnectionRegistryInterface
    {
        /** @var ConnectionRegistryInterface $registry */
        $registry = service('connectionRegistry');

        return $registry;
    }

    /** Geçersiz cap default'u için süreç başına tek uyarı — bkz. warnAboutInvalidCapDefault(). */
    private static bool $capFallbackWarned = false;

    /**
     * Kullanıcının gruplarından etkin SSE bağlantı cap'ini çözer.
     *
     * SENTİNEL SEMANTİĞİ — sınırsız için NEGATİF, 0 DEĞİL. Cap değerleri `int` bildirimli
     * olduğundan CI4 (`BaseConfig::initEnvValue()`) `.env`'deki sayısal olmayan her değeri
     * `resolveConnectionCap()`'e ulaşmadan ÖNCE 0'a cast eder; yani `... = ten` gibi tek
     * harflik bir yazım hatası 0 üretir. 0 bu yüzden "korumayı kapat" anlamına GELEMEZ:
     * geçersiz sayılır ve `NotificationsConfig::CONN_CAP_FALLBACK`'e fail-closed düşülür.
     * Negatif bir değer bir typo'nun cast'inden asla çıkmayacağı için kaçış valfi odur.
     *
     * `$realtimeConnCapDefault` NEGATİF ise özellik GLOBAL olarak sınırsızdır ve by-group
     * tablosuna hiç bakılmaz: kaçış valfini açan operatör, ürünle gelen `['superadmin' => 10]`
     * satırının hâlâ eşleşmesini beklemez. Aksi halde eşleşen grup yoksa default; birden çok
     * eşleşme varsa EN YÜKSEK cap uygulanır. Eşleşenlerden biri bile NEGATİF (SINIRSIZ)
     * tanımlıysa sınırsız kazanır — kaçış valfi daraltılmamalıdır.
     *
     * Grup tablosunda 0 ya da sayısal OLMAYAN değerler YOK SAYILIR, o grup hiç eşleşmemiş
     * gibi davranılır. `is_numeric()` filtresi `.env` yolunda ölüdür (değer oraya zaten int
     * olarak gelir) ama property'ye doğrudan string atayan çağrı yollarını korur.
     *
     * @param NotificationsConfig $config Cap tablosunu taşıyan modül yapılandırması.
     * @param string[]            $groups Kullanıcının üye olduğu Shield grupları.
     *
     * @return int Uygulanacak cap; yalnız 0 SINIRSIZ demektir (dönüş değeri asla negatif olmaz).
     */
    private function resolveConnectionCap(NotificationsConfig $config, array $groups): int
    {
        $default = $config->realtimeConnCapDefault;

        if ($default < 0) {
            return 0;
        }

        if ($default === 0) {
            $this->warnAboutInvalidCapDefault();
            $default = NotificationsConfig::CONN_CAP_FALLBACK;
        }

        $matched = array_filter(
            array_intersect_key($config->realtimeConnCapByGroup, array_flip($groups)),
            'is_numeric'
        );

        $caps = array_filter(
            array_map('intval', array_values($matched)),
            static fn (int $cap): bool => $cap !== 0
        );

        if ($caps === []) {
            return $default;
        }

        return min($caps) < 0 ? 0 : max($caps);
    }

    /**
     * Geçersiz (0) cap default'unun fail-closed karşılandığını loglar.
     *
     * Süreç başına yalnız BİR kez yazar: stream ucu her sekmeden TTL'de bir yeniden
     * bağlandığı için koşulsuz loglamak yanlış yapılandırma süresince log'u boğardı.
     * FPM'de her worker kendi uyarısını yazacağından kayıt yine de görünür kalır.
     */
    private function warnAboutInvalidCapDefault(): void
    {
        if (self::$capFallbackWarned) {
            return;
        }

        self::$capFallbackWarned = true;

        log_message(
            'warning',
            'NotificationsConfig::$realtimeConnCapDefault is 0, which is not a valid cap '
            . '(a non-numeric .env value is cast to 0 before it reaches the code). Falling back to '
            . NotificationsConfig::CONN_CAP_FALLBACK . ' concurrent SSE connections per identity. '
            . 'Set notificationsconfig.realtimeConnCapDefault to a positive number, or to -1 for unlimited.'
        );
    }

    /**
     * SSE akışını açar: kullanıcının yetkili kanallarının Redis sinyalini yoklar,
     * değişimde minimal bir "notification" nudge event'i gönderir.
     *
     * Akış ömrü `NotificationsConfig::$realtimeStreamTtl` (sn) ile CAP'lidir; süre
     * dolunca temiz `return` ile kapanır ve tarayıcı EventSource otomatik reconnect eder.
     *
     * KISIT (session kilidi): userId + kanallar çözüldükten HEMEN sonra `session()->close()`
     * ile oturum yazma kilidi serbest bırakılır. FileHandler driver'da kilit açık kalırsa
     * aynı kullanıcının TÜM backend istekleri bu uzun-ömürlü bağlantı boyunca bloke olur.
     *
     * KISIT (CI4 çıktı pipeline'ı): Header'lar `$this->response`'a set edilip döngüden
     * ÖNCE bir kez `sendHeaders()` ile gönderilir; framework'ün istek sonu `send()`'i
     * `headers_sent()` true olduğundan tekrar göndermez (test `pretend()` modunda no-op).
     * Gövde echo+flush ile stream edilir; metot `$this->response->setBody('')` döndürür.
     * Proje kuralı gereği exit;/die; KULLANILMAZ — döngü cap'li süre sonunda normal biter.
     *
     * KAYNAK KORUMASI: Akış açılmadan önce kimlik başına bir bağlantı yuvası ayrılır;
     * cap doluysa (ya da cap uygulanamıyorsa) 429 ile hiç açılmaz. Yuva iki yoldan geri
     * verilir: döngü normal bittiğinde hızlı yol, HER durumda ise `register_shutdown_function`.
     * İkincisi zorunludur — `ignore_user_abort(false)` altında PHP kopmayı bir yazma
     * sırasında fark edip script'i zend_bailout ile sonlandırır, yani döngüden SONRAKİ
     * kod hiç çalışmaz; shutdown fonksiyonu bailout/fatal ve `max_execution_time`
     * sonrasında da koşar (FPM `request_terminate_timeout`'unda KOŞMAZ, orada yuvayı
     * yalnız slot TTL'i düşürür).
     *
     * @return \CodeIgniter\HTTP\ResponseInterface
     */
    public function stream()
    {
        if ((int) auth()->id() <= 0) {
            return $this->failForbidden();
        }

        /** @var NotificationsConfig $config */
        $config = config(NotificationsConfig::class);

        if (! $config->realtimeEnabled) {
            // İstemci polling'e düşsün diye boş 204.
            return $this->response->setStatusCode(204);
        }

        $userId   = (int) auth()->id();
        $notifier = $this->notifier();

        // Cap politikası DB hâlâ açıkken çözülür.
        $groups = $notifier->groupsFor($userId);

        // Operatör .env'de aşırı büyük TTL verirse worker'ı uzun süre tutmasın: üst sınıra
        // clamp'la. Alt sınır 0 (0 = döngü hiç çalışmaz; test seam'i bunu kullanır).
        $ttl = max(0, min($config->realtimeStreamTtl, self::STREAM_TTL_CAP_SECONDS));

        // cap 0 = SINIRSIZ (yalnız NEGATİF yapılandırmanın açtığı kaçış valfi): defter hiç
        // devreye girmez, Redis'e yazılmaz.
        $cap      = $this->resolveConnectionCap($config, $groups);
        $registry = null;
        $connId   = null;
        $released = false;

        if ($cap > 0) {
            $slotTtl  = $ttl + self::CONN_TTL_BUFFER_SECONDS;
            $registry = $this->registry();
            $connId   = $registry->acquire($userId, $cap, $slotTtl);

            if ($connId === null) {
                // Kapasite dolu ya da defter erişilemez. Reddedilen istek mümkün olduğunca
                // ucuz olmalı: buraya kadar yalnız TEK grup sorgusu yapıldı, kanal listesi
                // (topicsFor) hiç türetilmedi. Kısa düz metin gövde: istemcinin tek ihtiyacı
                // 2xx-olmayan durumdur (EventSource kalıcı kapanır), cap/rol ayrıntısı
                // sızdırılmaz. Retry-After = yuva ömrü, yani en erken boşalma anı.
                return $this->response
                    ->setStatusCode(429)
                    ->setContentType('text/plain')
                    ->setHeader('Retry-After', (string) max(1, $slotTtl))
                    ->setHeader('X-Content-Type-Options', 'nosniff')
                    ->setBody(lang('Notifications.realtimeConnLimit'));
            }

            // Yuvayı bırakmanın TEK güvenilir yolu: ignore_user_abort(false) altında PHP,
            // istemci kopmasını bir yazma sırasında fark edip script'i zend_bailout ile
            // sonlandırır ve döngüden sonraki hızlı yol ÇALIŞMAZ. Shutdown fonksiyonları
            // istemci kopmasından ve max_execution_time fatal'ından sonra da koşar; $released
            // bayrağı hızlı yolla çift release'i engeller. KAPSAM DIŞI: FPM'in
            // request_terminate_timeout'u child sürecin kendisini öldürür, shutdown
            // fonksiyonları KOŞMAZ — o senaryoda yuvayı yalnız slot TTL'i düşürür.
            register_shutdown_function(static function () use ($registry, $userId, $connId, &$released): void {
                if (! $released) {
                    $released = true;
                    $registry->release($userId, $connId);
                }
            });
        }

        // Kanallar (grup üyeliği dahil) yalnız yuva ayrıldıktan sonra türetilir. topicsFor()
        // grupları kendi içinde yeniden sorgular; imzası IDOR kapısı olduğu için dışarıdan
        // hazır grup listesi ALMAZ — tek implementasyon, iki sorgu.
        $channels = $notifier->topicsFor($userId);

        // Kanallar çözüldü; oturum yazma kilidini hemen bırak.
        session()->close();

        // Poll döngüsü yalnız Redis'e dokunur; BaseController bootstrap'ının (CommonModel)
        // açtığı default DB bağlantısını burada kapat ki worker TTL boyunca idle bir bağlantı
        // tutmasın. Döngü sonrası DB'ye erişilmediğinden sonraki erişim yoktur.
        db_connect('default')->close();

        /** @var SignalStoreInterface $signal */
        $signal = service('signalStore');

        $this->response->setContentType('text/event-stream');
        $this->response->setHeader('Cache-Control', 'no-cache');
        $this->response->setHeader('X-Accel-Buffering', 'no');
        $this->response->setHeader('Connection', 'keep-alive');

        @set_time_limit($ttl + 5);
        ignore_user_abort(false);

        $this->response->sendHeaders();

        $baseline      = $signal->read($channels);
        $deadline      = time() + $ttl;
        $lastHeartbeat = time();

        while (time() < $deadline) {
            if (connection_aborted()) {
                break;
            }

            $current = $signal->read($channels);
            if ($current !== $baseline) {
                echo "event: notification\n";
                echo 'data: ' . json_encode(['t' => time()]) . "\n\n";
                $this->flushBuffers();
                $baseline = $current;
            }

            if (time() - $lastHeartbeat >= self::HEARTBEAT_SECONDS) {
                echo ": ping\n\n";
                $this->flushBuffers();
                $lastHeartbeat = time();
            }

            usleep(self::STREAM_POLL_US);
        }

        // Hızlı yol: döngü normal bittiyse yuvayı beklemeden geri ver. Buraya hiç
        // düşülmezse (kopma/fatal) aynı işi shutdown fonksiyonu yapar.
        if ($registry !== null && $connId !== null && ! $released) {
            $released = true;
            $registry->release($userId, $connId);
        }

        return $this->response->setBody('');
    }

    /**
     * Açık çıktı tamponlarını istemciye boşaltır (SSE her event sonrası).
     */
    private function flushBuffers(): void
    {
        if (ob_get_level() > 0) {
            @ob_flush();
        }

        flush();
    }
}

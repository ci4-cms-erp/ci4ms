<?php

declare(strict_types=1);

namespace Modules\Notifications\Libraries;

/**
 * Redis destekli eşzamanlı SSE bağlantı defteri (rol bazlı bağlantı cap'inin TEK erişim noktası).
 *
 * Kullanıcı başına açık bağlantılar `notif:conn:{userId}` ZSET'inde tutulur: üye =
 * bağlantı kimliği, skor = yuvanın son kullanma zaman damgası. INCR/DECR SAYACI
 * KULLANILMAZ; istemci koparsa DECR hiç çalışmaz ve sayaç kalıcı sızarak kullanıcıyı
 * kendi cap'ine kilitler. ZSET kendi kendini iyileştirir: her `acquire()` önce
 * ZREMRANGEBYSCORE ile süresi geçmiş yuvaları budar, ayrıca anahtarın kendisi de
 * EXPIRE ile ikinci bir güvenlik ağı taşır. Keyspace üzerinde O(N) olduğu için
 * SCAN MATCH kullanılmaz.
 *
 * ATOMİKLİK: buda → say → ekle → süre ver dizisi TEK bir `EVAL` betiğinde koşar.
 * PHP round-trip'leriyle bölünmüş bir ZCARD/ZADD ikilisinde eşzamanlı N istek cap'i
 * aşabilirdi (TOCTOU); ayrıca ZADD ile EXPIRE arasında süreç ölürse anahtar TTL'siz
 * kalıcı olarak sızardı. Tek betik hem bu iki yarışı kapatır hem de 4 round-trip'i 1'e
 * indirir.
 *
 * REDIS ERİŞİLEMEZSE DENY: `acquire()` null döner, yani stream 429 alır. Gerekçe —
 * Redis yokken sinyal deposu (RealtimeSignal) da hep 0 döndüğünden SSE zaten hiçbir
 * şey teslim edemez; bağlantıyı TTL boyunca açık tutmak saf worker israfıdır ve
 * korumak istediğimiz havuzu tüketir. İstemci bu durumda polling'e düşer.
 * Kardeş sınıf RealtimeSignal'ın best-effort (fail-open) davranışıyla bilinçli
 * olarak zıttır: orası sinyal, burası kaynak koruması.
 *
 * Bağlantı RedisConnectionTrait üzerinden lazy kurulur (kısa connect + read timeout);
 * bağlantı ya da komut hatasında ASLA istisna sızmaz.
 */
final class RedisConnectionRegistry implements ConnectionRegistryInterface
{
    use RedisConnectionTrait;

    /** Redis anahtar öneki; tam anahtar `notif:conn:{userId}` biçimindedir. */
    private const KEY_PREFIX = 'notif:conn:';

    /** Bağlantı kimliğinin bayt uzunluğu (hex'e çevrilince 16 karakter). */
    private const CONN_ID_BYTES = 8;

    /**
     * Yuva ayırmanın atomik betiği: budama, cap kontrolü, ekleme ve EXPIRE tek adımda.
     *
     * KEYS[1] = `notif:conn:{userId}`, ARGV = [cap, ttl, connId].
     * Alt sınır `'-inf'`tir, `'0'` DEĞİL: sistem saatinin geri alınması ya da elle
     * yazılmış bir üye yüzünden skoru <= 0 olan bir yuva aksi halde asla budanmaz ve
     * aktif kullanıcıda kalıcı olarak bir yuvayı işgal ederdi.
     *
     * SAAT KAYNAĞI REDIS'TİR (`TIME`), PHP'nin `time()`'ı DEĞİL: çok sunuculu kurulumda
     * saati ileri kaymış tek bir app sunucusu, ARGV ile gönderdiği `now` yüzünden her
     * `acquire()`'da DİĞER sunucuların hâlâ canlı yuvalarını budar ve cap'i fiilen kaldırırdı.
     * Skorlar tek bir saatle yazılıp tek bir saatle karşılaştırılmalıdır.
     *
     * TTL KISALTILMAZ: `EXPIRE` yalnız mevcut TTL daha küçükse yazılır. Koşulsuz `EXPIRE`,
     * kısa TTL'li bir acquire'ın uzun TTL'li bir yuvanın anahtarını erken silmesine yol
     * açıyordu (anahtar canlı yuvalarla birlikte gider, cap geçici olarak uygulanmaz).
     * `TTL` yokluğu -1 döndüğü ve -1 her ttl'den küçük olduğu için anahtarın ilk
     * yaratıldığı çağrıda süre normal biçimde verilir. Bu yüzden `EXPIRE ... GT`
     * KULLANILMAZ: GT, TTL'siz anahtarı sonsuz TTL sayıp hiçbir şey yazmaz (ölçüldü:
     * Redis 8.6.3'te ZADD sonrası `EXPIRE key 60 GT` → 0, TTL -1 kalır), yani ikinci
     * güvenlik ağı ilk acquire'da tamamen kaybolurdu.
     *
     * Dönüş: başarıda connId, cap doluysa nil (phpredis'te false).
     */
    private const ACQUIRE_SCRIPT = <<<'LUA'
        local key    = KEYS[1]
        local cap    = tonumber(ARGV[1])
        local ttl    = tonumber(ARGV[2])
        local connId = ARGV[3]

        local clock = redis.call('TIME')
        local now   = tonumber(clock[1])

        redis.call('ZREMRANGEBYSCORE', key, '-inf', now)

        if redis.call('ZCARD', key) >= cap then
            return false
        end

        redis.call('ZADD', key, now + ttl, connId)

        if redis.call('TTL', key) < ttl then
            redis.call('EXPIRE', key, ttl)
        end

        return connId
        LUA;

    /**
     * Kullanıcı için bir bağlantı yuvası ayırmaya çalışır (süresi geçmişleri budayarak).
     *
     * Tüm iş tek `EVAL` ile atomik koşar: TIME (saat Redis'ten) → ZREMRANGEBYSCORE (kendi
     * kendini iyileştirme) → ZCARD >= cap ise deny → ZADD (skor = now + ttl) → mevcut TTL
     * daha küçükse EXPIRE (ikinci güvenlik ağı, asla kısaltmaz).
     * `$ttl` en az 1'e yükseltilir; `EXPIRE key 0` anahtarı SİLER, yani yuva kaydedilir
     * ama cap sessizce uygulanmaz olurdu. Redis erişilemezse, betik hata verirse ya da
     * cap doluysa null (deny) döner; asla throw etmez.
     *
     * @param int $userId Oturumdaki kullanıcının kimliği.
     * @param int $cap    İzin verilen eşzamanlı bağlantı sayısı (çağıran daima >= 1 verir).
     * @param int $ttl    Yuvanın saniye cinsinden ömrü (bağlantı TTL'i + tampon); < 1 ise 1'e yükseltilir.
     *
     * @return string|null Bağlantı kimliği ya da null (kapasite dolu / cap uygulanamıyor).
     */
    public function acquire(int $userId, int $cap, int $ttl): ?string
    {
        $ttl = max(1, $ttl);

        $redis = $this->connection();
        if ($redis === null) {
            return null;
        }

        try {
            $connId = bin2hex(random_bytes(self::CONN_ID_BYTES));

            // phpredis imzası: eval(script, args, numKeys) — ilk numKeys argümanı KEYS'e gider.
            $granted = $redis->eval(
                self::ACQUIRE_SCRIPT,
                [self::KEY_PREFIX . $userId, (string) $cap, (string) $ttl, $connId],
                1
            );

            // Betik yalnız başarıda connId döner; false (cap dolu) ve her beklenmedik
            // yanıt deny'dir — fail-closed sözleşmesi burada da geçerlidir.
            return $granted === $connId ? $connId : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Ayrılmış bir bağlantı yuvasını ZREM ile serbest bırakır (hızlı yol).
     *
     * Çağrı hiç yapılmasa ya da hata verse bile yuva `acquire()`'daki skor/EXPIRE
     * ikilisiyle kendiliğinden düşer; bu yüzden hata sessizce yutulur.
     *
     * @param int    $userId Yuvanın sahibi kullanıcının kimliği.
     * @param string $connId `acquire()` tarafından döndürülen bağlantı kimliği.
     */
    public function release(int $userId, string $connId): void
    {
        $redis = $this->connection();
        if ($redis === null) {
            return;
        }

        try {
            $redis->zRem(self::KEY_PREFIX . $userId, $connId);
        } catch (\Throwable $e) {
            // TTL güvenlik ağı yuvayı zaten düşürecek; sessizce dön.
        }
    }
}

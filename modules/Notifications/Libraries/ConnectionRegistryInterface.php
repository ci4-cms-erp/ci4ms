<?php

declare(strict_types=1);

namespace Modules\Notifications\Libraries;

/**
 * Eşzamanlı SSE bağlantı defteri sözleşmesi (rol bazlı bağlantı cap'i için dar arayüz).
 *
 * Her açık SSE bağlantısı TTL'i boyunca bir PHP-FPM worker'ını meşgul eder; cap
 * olmadan tek bir kimlik `pm.max_children` havuzunu tüketebilir. RedisConnectionRegistry
 * (final) bunu uygular; RealtimeController::stream() yalnız bu tipe bağımlıdır, böylece
 * testler canlı Redis olmadan bir test double enjekte edebilir
 * (Services::connectionRegistry() seam'i).
 *
 * SignalStoreInterface'ten AYRILAN nokta: sinyal deposu best-effort'tur ve hata
 * durumunda sessizce "sinyal yok" der; bağlantı defteri ise bir GÜVENLİK sayacıdır,
 * uygulanamadığında `acquire()` null (deny) döner. Uygulamalar yine de ASLA istisna
 * sızdırmaz.
 */
interface ConnectionRegistryInterface
{
    /**
     * Kullanıcı için bir bağlantı yuvası ayırmaya çalışır.
     *
     * SÖZLEŞME: `$ttl` >= 1 olmalıdır. Sıfır ya da negatif bir ömür Redis'te `EXPIRE key 0`
     * anlamına gelir, yani anahtarı SİLER: yuva sayılmaz, geçerli bir kimlik döner ve cap
     * SESSİZCE uygulanmamış olur. Uygulamalar bu değeri en az 1'e yükseltmelidir.
     *
     * @param int $userId Oturumdaki kullanıcının kimliği.
     * @param int $cap    İzin verilen eşzamanlı bağlantı sayısı (çağıran daima >= 1 verir).
     * @param int $ttl    Yuvanın saniye cinsinden ömrü (bağlantı TTL'i + tampon), >= 1.
     *
     * @return string|null Yuva ayrıldıysa `release()`e geri verilecek bağlantı kimliği;
     *                     kapasite doluysa ya da cap uygulanamıyorsa null.
     */
    public function acquire(int $userId, int $cap, int $ttl): ?string;

    /**
     * Ayrılmış bir bağlantı yuvasını serbest bırakır (hızlı yol).
     *
     * Çağrılmasa bile yuva `acquire()` sırasında verilen TTL ile kendiliğinden düşer.
     *
     * @param int    $userId Yuvanın sahibi kullanıcının kimliği.
     * @param string $connId `acquire()` tarafından döndürülen bağlantı kimliği.
     */
    public function release(int $userId, string $connId): void;
}

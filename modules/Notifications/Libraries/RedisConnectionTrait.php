<?php

namespace Modules\Notifications\Libraries;

use Config\Cache;
use Redis;

/**
 * Modülün phpredis bağlantı kurulumunun TEK kaynağı (RealtimeSignal + RedisConnectionRegistry).
 *
 * İki depo sınıfı da aynı lazy bağlantıyı kurar; kopya kurulum kodu tutulursa biri
 * sertleştirilip diğeri unutulur (timeout ayarı tam olarak böyle kaçmıştı). Bağlantı
 * parametreleri `config('Cache')->redis` (host/port/password/database) dizisinden gelir —
 * yeni bir bağlantı anahtarı icat edilmez.
 *
 * ZAMAN AŞIMI SÖZLEŞMESİ: hem connect hem de READ timeout kısadır. Redis bağlantıyı
 * kabul edip yanıt vermezse (paket düşüren firewall, BGSAVE stall) read timeout olmadan
 * komutlar `default_socket_timeout` (tipik 60 sn) boyunca bloklar; bu, worker havuzunu
 * korumak için var olan bağlantı cap'ini havuzu kilitleyen bir amplifikasyona çevirir.
 * Ext yüklü değilse ya da connect başarısız/zaman aşımına uğrarsa bağlantı bir kez
 * `connectionFailed` işaretlenir ve aynı istek boyunca yeniden denenmez.
 */
trait RedisConnectionTrait
{
    /** Lazy bağlantı kurulum zaman aşımı (sn) — Redis down iken çağrıyı çabuk serbest bırakır. */
    private const CONNECT_TIMEOUT_SECONDS = 0.5;

    /** Komut yanıtı bekleme zaman aşımı (sn) — hung Redis worker'ı dakikalarca tutmasın. */
    private const READ_TIMEOUT_SECONDS = 0.5;

    private ?Redis $redis = null;

    /** Bir kez başarısız olan bağlantı aynı istek boyunca yeniden denenmez. */
    private bool $connectionFailed = false;

    /**
     * Lazy phpredis bağlantısı; Cache config'inden okur, hata → null.
     *
     * @return Redis|null Kullanıma hazır bağlantı ya da null.
     */
    private function connection(): ?Redis
    {
        if ($this->redis instanceof Redis) {
            return $this->redis;
        }

        if ($this->connectionFailed || ! extension_loaded('redis')) {
            return null;
        }

        /** @var Cache $cache */
        $cache = config('Cache');
        $conf  = $cache->redis;

        try {
            $redis     = new Redis();
            $connected = $redis->connect(
                $conf['host'] ?? '127.0.0.1',
                (int) ($conf['port'] ?? 6379),
                self::CONNECT_TIMEOUT_SECONDS,
                null,
                0,
                self::READ_TIMEOUT_SECONDS
            );

            if ($connected === false) {
                $this->connectionFailed = true;

                return null;
            }

            // connect()'in read_timeout parametresi yalnız kurulum anına uygulanır;
            // sonraki her komut için de aynı sınır geçerli olsun.
            $redis->setOption(Redis::OPT_READ_TIMEOUT, self::READ_TIMEOUT_SECONDS);

            if (! empty($conf['password'])) {
                $redis->auth($conf['password']);
            }

            if (! empty($conf['database'])) {
                $redis->select((int) $conf['database']);
            }

            $this->redis = $redis;

            return $this->redis;
        } catch (\Throwable $e) {
            // Bağlantı kurulamadı ya da zaman aşımına uğradı; aynı istekte tekrar denenmez.
            $this->connectionFailed = true;

            return null;
        }
    }
}

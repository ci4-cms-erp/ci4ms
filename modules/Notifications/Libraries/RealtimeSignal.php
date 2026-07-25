<?php

namespace Modules\Notifications\Libraries;

use Modules\Notifications\Config\NotificationsConfig;

/**
 * Redis destekli anlık-sinyal deposu (SSE nudge sayaçlarının TEK erişim noktası).
 *
 * KALICILIK BURADA DEĞİLDİR: bildirim satırını InAppChannel DB'ye yazar. Bu depo
 * yalnız "yeni bir şey var, reconcile et" sinyalini `notif:sig:{channel}` sayacında
 * tutar; `{channel}` DAİMA `Notifier::topicFor()` çıktısıdır (sunucu türetimli; asla
 * istemci girdisi). Yazan taraf RealtimeChannel (bump), okuyan taraf SSE stream()'dir
 * (read). Tek seam olması hem izolasyonu hem test edilebilirliği sağlar
 * (Services::signalStore() → injectMock).
 *
 * Bağlantı RedisConnectionTrait üzerinden lazy kurulur; kısa connect ve read timeout
 * kullanır: Redis down (ya da yanıt vermeyen) iken tetikleyici isteği veya SSE
 * açılışını bloklamamalı. Bağlantı/komut hatasında ASLA istisna sızmaz — bump false,
 * read baseline-0 döner.
 */
final class RealtimeSignal implements SignalStoreInterface
{
    use RedisConnectionTrait;

    /** Redis anahtar öneki; tam anahtar `notif:sig:{channel}` biçimindedir. */
    private const KEY_PREFIX = 'notif:sig:';

    private NotificationsConfig $config;

    /**
     * @param NotificationsConfig|null $config Enjekte edilmezse global config çözülür.
     */
    public function __construct(?NotificationsConfig $config = null)
    {
        $this->config = $config ?? config(NotificationsConfig::class);
    }

    /**
     * Bir kanalın sinyal sayacını artırır ve TTL'ini tazeler (best-effort).
     *
     * `INCR notif:sig:{channel}` + `EXPIRE realtimeSignalTtl`. Redis erişilemezse
     * ya da komut hata verirse sessizce false döner (asla throw etmez).
     *
     * @param string $channel Notifier::topicFor() çıktısı olan kanal adı.
     *
     * @return bool Sayaç artırılabildiyse true; aksi halde false.
     */
    public function bump(string $channel): bool
    {
        $redis = $this->connection();
        if ($redis === null) {
            return false;
        }

        try {
            $key = self::KEY_PREFIX . $channel;
            $redis->incr($key);
            $redis->expire($key, $this->config->realtimeSignalTtl);

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Verilen kanalların güncel sinyal sayaçlarını okur (yoksa/erişilemezse 0).
     *
     * Dönen dizi DAİMA istenen her kanal için bir anahtar taşır; böylece çağıran
     * baseline farkını güvenle karşılaştırabilir. Tüm kanallar tek `MGET` round-trip'i
     * ile okunur; SSE döngüsünde saniyede bir çağrılması ucuzdur ve DB'ye hiç dokunmaz.
     *
     * @param list<string> $channels Notifier::topicsFor() ile türetilen kanal adları.
     *
     * @return array<string, int> channel => güncel sayaç (yoksa 0).
     */
    public function read(array $channels): array
    {
        $result = [];
        foreach ($channels as $channel) {
            $result[$channel] = 0;
        }

        $redis = $this->connection();
        if ($redis === null || $channels === []) {
            return $result;
        }

        try {
            // Tek round-trip: kanal başına ayrı GET yerine MGET (SSE döngüsü saniyede bir okur).
            $values = $redis->mget(array_map(
                static fn (string $channel): string => self::KEY_PREFIX . $channel,
                $channels
            ));

            if (! is_array($values)) {
                return $result;
            }

            // MGET değerleri $channels ile aynı sırada döner; konumsal olarak eşle.
            $values = array_values($values);

            foreach ($channels as $index => $channel) {
                $value            = $values[$index] ?? false;
                $result[$channel] = is_numeric($value) ? (int) $value : 0;
            }
        } catch (\Throwable $e) {
            // Baseline zaten 0'larla dolu; sessizce dön.
        }

        return $result;
    }
}

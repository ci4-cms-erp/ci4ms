<?php

namespace Modules\Notifications\Libraries\Channels;

use Modules\Notifications\Config\NotificationsConfig;
use Modules\Notifications\Libraries\NotificationMessage;
use Modules\Notifications\Libraries\Notifier;
use Modules\Notifications\Libraries\SignalStoreInterface;

/**
 * Realtime kanal — mesaj için Redis'te bir SSE "nudge" sinyali bump'lar.
 *
 * KALICILIK BURADA DEĞİLDİR: notifications satırını InAppChannel yazar. Bu kanal
 * yalnızca "yeni bir şey var, reconcile et" sinyalini `notif:sig:{channel}` sayacında
 * artırır; istemci payload'a güvenmez, feed ucundan DB'yi source-of-truth olarak
 * yeniden okur. Bu yüzden Redis down / hata → daima ChannelResult::skipped, asla throw.
 * Kanal sırası dispatch() tarafından belirlenir; varsayılan ['inapp','realtime'] ile
 * inapp (DB commit) bu sinyalden ÖNCE çalışır.
 */
final class RealtimeChannel implements ChannelInterface
{
    private NotificationsConfig $config;
    private SignalStoreInterface $signalStore;

    /**
     * @param NotificationsConfig|null    $config      Enjekte edilmezse global config çözülür.
     * @param SignalStoreInterface|null   $signalStore Enjekte edilmezse service('signalStore') çözülür.
     */
    public function __construct(?NotificationsConfig $config = null, ?SignalStoreInterface $signalStore = null)
    {
        $this->config = $config ?? config(NotificationsConfig::class);

        if ($signalStore === null) {
            /** @var SignalStoreInterface $signalStore */
            $signalStore = service('signalStore');
        }

        $this->signalStore = $signalStore;
    }

    /**
     * Mesajın yetkili kanalında Redis sinyalini bump'lar; realtime kapalı ya da
     * sinyal başarısızsa atlar.
     *
     * @param NotificationMessage $message Temizlenmiş, tek-hedefli mesaj.
     *
     * @return ChannelResult Sinyal bump edilirse ok, aksi halde skipped (asla throw etmez).
     */
    public function send(NotificationMessage $message): ChannelResult
    {
        if (! $this->config->realtimeEnabled) {
            return ChannelResult::skipped('realtime-disabled');
        }

        $channel = Notifier::topicFor($message->targetType, $message->targetValue);

        return $this->signalStore->bump($channel)
            ? ChannelResult::ok(null, ['topic' => $channel])
            : ChannelResult::skipped('signal-failed', ['topic' => $channel]);
    }
}

<?php

namespace Modules\Notifications\Config;

use CodeIgniter\Config\BaseService;
use Modules\Notifications\Libraries\ConnectionRegistryInterface;
use Modules\Notifications\Libraries\Notifier;
use Modules\Notifications\Libraries\RealtimeSignal;
use Modules\Notifications\Libraries\RedisConnectionRegistry;
use Modules\Notifications\Libraries\SignalStoreInterface;

/**
 * Bildirim Merkezi servis fabrikası (CI4 tarafından otomatik keşfedilir).
 *
 * `service('notifier')` bu fabrikaya çözülür; böylece uygulamanın her yeri
 * (Events, controller'lar, cell'ler) paylaşımlı tek bir Notifier örneğine erişir.
 */
class Services extends BaseService
{
    /**
     * Bildirim servisini döndürür.
     *
     * @param bool $getShared Paylaşımlı örnek isteniyor mu.
     *
     * @return Notifier
     */
    public static function notifier(bool $getShared = true): Notifier
    {
        if ($getShared) {
            return static::getSharedInstance('notifier');
        }

        return new Notifier();
    }

    /**
     * Redis destekli anlık-sinyal deposunu döndürür.
     *
     * Hem RealtimeChannel (bump) hem SSE stream() (read) bu tek örneği kullanır;
     * testler `Services::injectMock('signalStore', ...)` ile sahte enjekte edebilir.
     *
     * @param bool $getShared Paylaşımlı örnek isteniyor mu.
     *
     * @return SignalStoreInterface
     */
    public static function signalStore(bool $getShared = true): SignalStoreInterface
    {
        if ($getShared) {
            return static::getSharedInstance('signalStore');
        }

        return new RealtimeSignal();
    }

    /**
     * Eşzamanlı SSE bağlantı defterini döndürür.
     *
     * SSE stream() rol bazlı bağlantı cap'ini yalnız bu tek örnek üzerinden uygular;
     * testler `Services::override('connectionRegistry', ...)` ile sahte enjekte edebilir.
     *
     * @param bool $getShared Paylaşımlı örnek isteniyor mu.
     *
     * @return ConnectionRegistryInterface
     */
    public static function connectionRegistry(bool $getShared = true): ConnectionRegistryInterface
    {
        if ($getShared) {
            return static::getSharedInstance('connectionRegistry');
        }

        return new RedisConnectionRegistry();
    }
}

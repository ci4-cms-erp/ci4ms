<?php

namespace Modules\Notifications\Config;

use CodeIgniter\Config\BaseService;
use Modules\Notifications\Libraries\ConnectionRegistryInterface;
use Modules\Notifications\Libraries\Notifier;
use Modules\Notifications\Libraries\RealtimeSignal;
use Modules\Notifications\Libraries\RedisConnectionRegistry;
use Modules\Notifications\Libraries\SignalStoreInterface;

/**
 * Notification Center service factory (auto-discovered by CI4).
 *
 * `service('notifier')` resolves to this factory; this way every part of the
 * application (Events, controllers, cells) accesses a single shared Notifier instance.
 */
class Services extends BaseService
{
    /**
     * Returns the notification service.
     *
     * @param bool $getShared Whether a shared instance is requested.
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
     * Returns the Redis-backed realtime-signal store.
     *
     * Both RealtimeChannel (bump) and SSE stream() (read) use this single instance;
     * tests can inject a mock via `Services::injectMock('signalStore', ...)`.
     *
     * @param bool $getShared Whether a shared instance is requested.
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
     * Returns the concurrent SSE connection registry.
     *
     * SSE stream() enforces its role-based connection cap only through this single
     * instance; tests can inject a mock via `Services::override('connectionRegistry', ...)`.
     *
     * @param bool $getShared Whether a shared instance is requested.
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

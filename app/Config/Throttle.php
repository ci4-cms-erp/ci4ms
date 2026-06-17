<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Rate-limit (throttle) profilleri.
 *
 * Her profil: [capacity, seconds]  => "seconds saniyede capacity istek".
 * Route/gruba `throttle:profil` filtresi ile uygulanır (bkz. App\Filters\ThrottleFilter).
 * Modül/route bazında farklı limitler buradan yönetilir; yeni profil eklemek yeterli.
 */
class Throttle extends BaseConfig
{
    /**
     * Profil verilmezse kullanılacak varsayılan.
     */
    public string $default = 'web';

    /**
     * profil => [capacity (istek sayısı), seconds (pencere/saniye)]
     *
     * @var array<string, array{0:int, 1:int}>
     */
    public array $profiles = [
        'web'     => [180, 60],  // genel web
        'backend' => [300, 60],  // yönetim paneli (AJAX yoğun)
        'api'     => [100, 60],  // ileride API
        'auth'    => [10, 60],   // login/register vb. (Shield ile aynı)
        'strict'  => [20, 60],   // hassas uçlar
    ];

    /**
     * Bu öneklerle başlayan path'ler için 429 yanıtı JSON döner (API istemcileri).
     *
     * @var list<string>
     */
    public array $apiPrefixes = ['api'];
}

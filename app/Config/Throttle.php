<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Rate-limit (throttle) profiles.
 *
 * Each profile: [capacity, seconds]  => "capacity requests per seconds".
 * Applied to a route/group via the `throttle:profile` filter (see App\Filters\ThrottleFilter).
 * Different limits per module/route are managed from here; adding a new profile is enough.
 */
class Throttle extends BaseConfig
{
    /**
     * Default used when no profile is given.
     */
    public string $default = 'web';

    /**
     * profile => [capacity (request count), seconds (window/seconds)]
     *
     * @var array<string, array{0:int, 1:int}>
     */
    public array $profiles = [
        'web'     => [180, 60],  // general web
        'backend' => [300, 60],  // admin panel (AJAX heavy)
        'api'     => [100, 60],  // for future API use
        'auth'    => [10, 60],   // login/register etc. (same as Shield)
        'strict'  => [20, 60],   // sensitive endpoints
    ];

    /**
     * Paths starting with these prefixes get a JSON 429 response (API clients).
     *
     * @var list<string>
     */
    public array $apiPrefixes = ['api'];
}

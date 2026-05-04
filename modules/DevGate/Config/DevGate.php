<?php

namespace Modules\DevGate\Config;

use CodeIgniter\Config\BaseConfig;

class DevGate extends BaseConfig
{
    /**
     * Users allowed to access the site in development environment.
     *
     * Plain text:
     *   'username' => 'password'
     *
     * Hashed (recommended):
     *   'username' => password_hash('password', PASSWORD_BCRYPT)
     *
     * Set $useHashedPasswords = true to enable hash comparison.
     */
    public array $users = [
        'admin' => 'admin', //'$2y$12$puK4KAwrt.6G.RywpVy0xO6LI7rKAR09L0iArxWzGocGCCLnEzxmy'
        'dev'   => 'devpass' //'$2y$12$NrHvzuu7zau7lvZskdGjweNO7.5sdTnx95gjLc6K8w5x935eVTlz6'
    ];

    /**
     * true  → values in $users are hashes created with password_hash()
     * false → plain text comparison
     */
    public bool $useHashedPasswords = false;

    /**
     * Realm label shown in the browser's Basic Auth dialog
     */
    public string $realm = 'Development — Authorized Access Only';

    /**
     * These paths are never checked (e.g. health checks, webhooks)
     * Regex is supported: '#^/health#'
     */
    public array $except = [
        // '#^/ping#',
    ];
}

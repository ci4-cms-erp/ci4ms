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
        'admin' => 'bertug',
        'dev'   => 'devpass',
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

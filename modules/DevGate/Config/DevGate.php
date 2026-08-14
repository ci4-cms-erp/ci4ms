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
     *
     * No credentials ship here by default — the installer (Install.php /
     * Ci4msSetup.php) generates a random password on setup and writes it
     * here, hashed. Until then this array is empty and DevGate rejects
     * every request (see DevGateFilter::isAuthenticated()).
     *
     * Example (illustrative only, not a real hash):
     *   'dev' => '$2y$12$abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ01'
     */
    public array $users = [];

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

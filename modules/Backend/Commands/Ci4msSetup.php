<?php

declare(strict_types=1);

namespace Modules\Backend\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Modules\Install\Services\InstallService;

class Ci4msSetup extends BaseCommand
{
    protected $group       = 'Ci4MS';
    protected $name        = 'ci4ms:setup';
    protected $description = 'Runs the full CI4MS installation process via CLI.';
    protected $usage       = 'php spark ci4ms:setup [options]';

    protected $options = [
        '--fname'    => 'Admin first name',
        '--sname'    => 'Admin last name',
        '--email'    => 'Admin email address',
        '--username' => 'Admin username',
        '--password' => 'Admin password',
        '--dbHost'   => 'Database hostname (default: localhost)',
        '--dbName'   => 'Database name',
        '--dbUser'   => 'Database username',
        '--dbPass'   => 'Database password',
        '--dbDriver' => 'Database driver (default: MySQLi)',
        '--dbPrefix' => 'Database table prefix (default: ci4ms_)',
        '--dbPort'   => 'Database port (default: 3306)',
        '--siteName' => 'Site name',
        '--baseUrl'  => 'Base URL (e.g. https://example.com)',
        '--slogan'   => 'Site slogan (optional)',
    ];

    /**
     * Are we running in non-interactive mode?
     * If all required arguments are supplied via CLI, the interactive prompt is skipped.
     */
    private bool $nonInteractive = false;

    /**
     * Runs the CI4MS setup wizard end to end (user information,
     * database connection, migration + seed, admin account, `.env`/routes
     * writing).
     *
     * @param array<int|string, string|null> $params
     *
     * @return int EXIT_SUCCESS (0) if the full setup succeeds; EXIT_ERROR (1)
     *              if any step fails.
     */
    public function run(array $params): int
    {
        CLI::write('');
        CLI::write('╔══════════════════════════════════════════╗', 'green');
        CLI::write('║         CI4MS Setup Wizard  v1.0         ║', 'green');
        CLI::write('╚══════════════════════════════════════════╝', 'green');
        CLI::write('');

        // ─────────────────────────────────────────────────────────────
        // GUARD: Do not continue if already installed
        // ─────────────────────────────────────────────────────────────
        if (file_exists(WRITEPATH . 'install.lock')) {
            CLI::error('CI4MS is already installed. Setup aborted.');
            return EXIT_ERROR;
        }

        // ─────────────────────────────────────────────────────────────
        // Read CLI arguments — check for non-interactive mode
        // ─────────────────────────────────────────────────────────────
        $cliArgs = $this->parseCliOptions();
        $this->nonInteractive = $this->hasAllRequired($cliArgs);

        if ($this->nonInteractive) {
            CLI::write('  Running in non-interactive mode...', 'light_gray');
            CLI::write('');
        }

        // ─────────────────────────────────────────────────────────────
        // 1. ADMIN USER INFORMATION
        // ─────────────────────────────────────────────────────────────
        CLI::write('[ Step 1/6 ] Admin User Information', 'yellow');
        CLI::write('─────────────────────────────────────', 'dark_gray');

        $name     = $cliArgs['fname']    ?? $this->promptRequired('First Name');
        $surname  = $cliArgs['sname']    ?? $this->promptRequired('Last Name');
        $email    = $cliArgs['email']    ?? $this->promptValidated('Email', function ($val) {
            return filter_var($val, FILTER_VALIDATE_EMAIL) ? null : 'Please enter a valid email address.';
        });
        $username = $cliArgs['username'] ?? $this->promptValidated('Username (alphanumeric, 3-50 chars)', function ($val) {
            if (!preg_match('/^[a-zA-Z0-9]{3,50}$/', $val)) return 'Username must be alphanumeric, 3-50 characters.';
            return null;
        });
        $password = $cliArgs['password'] ?? $this->promptSecret('Password (min 8 chars)', function ($val) {
            if (strlen($val) < 8) return 'Password must be at least 8 characters.';
            return null;
        });

        // ─────────────────────────────────────────────────────────────
        // 2. DATABASE INFORMATION
        // ─────────────────────────────────────────────────────────────
        CLI::write('');
        CLI::write('[ Step 2/6 ] Database Configuration', 'yellow');
        CLI::write('─────────────────────────────────────', 'dark_gray');

        if ($this->nonInteractive) {
            // Non-interactive: use existing DB settings from .env or take CLI arguments
            $dbHost     = $cliArgs['dbHost']   ?? $this->getEnvValue('database.default.hostname', 'localhost');
            $dbName     = $cliArgs['dbName']   ?? $this->getEnvValue('database.default.database', 'ci4ms');
            $dbUsername = $cliArgs['dbUser']    ?? $this->getEnvValue('database.default.username', 'root');
            $dbPassword = $cliArgs['dbPass']   ?? $this->getEnvValue('database.default.password', '');
            $dbDriver   = $cliArgs['dbDriver'] ?? $this->getEnvValue('database.default.DBDriver', 'MySQLi');
            $dbPrefix   = $cliArgs['dbPrefix'] ?? $this->getEnvValue('database.default.DBPrefix', 'ci4ms_');
            $dbPort     = $cliArgs['dbPort']   ?? $this->getEnvValue('database.default.port', '3306');
            CLI::write("  Using DB: {$dbHost}:{$dbPort} / {$dbName}", 'light_gray');
        } else {
            $dbHost     = CLI::prompt('DB Host', 'localhost');
            $dbName     = $this->promptValidated('DB Name (alphanumeric/dash)', function ($val) {
                if (!preg_match('/^[a-zA-Z0-9_-]{1,100}$/', $val)) return 'DB name must be alphanumeric (max 100 chars).';
                return null;
            });
            $dbUsername = $this->promptValidated('DB Username', function ($val) {
                if (!preg_match('/^[a-zA-Z0-9_-]{1,100}$/', $val)) return 'DB username must be alphanumeric (max 100 chars).';
                return null;
            });
            $dbPassword = CLI::prompt('DB Password (leave blank if none)', '');
            $dbDriver   = CLI::prompt('DB Driver', 'MySQLi');
            $dbPrefix   = CLI::prompt('DB Prefix', 'ci4ms_');
            $dbPort     = $this->promptValidated('DB Port', function ($val) {
                if (!ctype_digit($val) || (int)$val < 1 || (int)$val > 65535) return 'Port must be a number between 1-65535.';
                return null;
            }, '3306');
        }

        // ─────────────────────────────────────────────────────────────
        // 3. SITE INFORMATION
        // ─────────────────────────────────────────────────────────────
        CLI::write('');
        CLI::write('[ Step 3/6 ] Site Information', 'yellow');
        CLI::write('─────────────────────────────────────', 'dark_gray');

        $siteName = $cliArgs['siteName'] ?? $this->promptValidated('Site Name', function ($val) {
            if (empty(trim($val)) || strlen($val) > 255) return 'Site name is required (max 255 chars).';
            if (preg_match('/[<>{}=]/', $val)) return 'Site name contains invalid characters.';
            return null;
        });
        $baseUrl = $cliArgs['baseUrl'] ?? $this->promptValidated('Base URL (e.g. https://example.com)', function ($val) {
            if (!filter_var($val, FILTER_VALIDATE_URL)) return 'Please enter a valid URL.';
            return null;
        });
        $slogan = $cliArgs['slogan'] ?? '';
        if (!$this->nonInteractive && $slogan === '') {
            $slogan = CLI::prompt('Site Slogan (optional, leave blank to skip)', '');
            if ($slogan !== '') {
                while (strlen($slogan) > 255 || preg_match('/[<>{}=]/', $slogan)) {
                    CLI::error('Slogan must be max 255 chars and cannot contain < > { } = characters.');
                    $slogan = CLI::prompt('Site Slogan (optional)', '');
                }
            }
        }

        // ─────────────────────────────────────────────────────────────
        // SUMMARY — Proceed?
        // ─────────────────────────────────────────────────────────────
        CLI::write('');
        CLI::write('[ Summary ]', 'cyan');
        CLI::write('─────────────────────────────────────', 'dark_gray');
        CLI::write("  Site Name : {$siteName}");
        CLI::write("  Base URL  : {$baseUrl}");
        CLI::write("  DB Host   : {$dbHost}:{$dbPort}  /  DB: {$dbName}  /  Driver: {$dbDriver}");
        CLI::write("  Admin     : {$name} {$surname} <{$email}> @ {$username}");
        CLI::write("  Slogan    : " . ($slogan !== '' ? $slogan : '(not set)'));
        CLI::write('');

        if (!$this->nonInteractive) {
            $confirm = CLI::prompt('Everything looks correct? Proceed with installation?', ['y', 'n']);
            if (strtolower($confirm) !== 'y') {
                CLI::write('Setup cancelled by user.', 'red');
                return EXIT_ERROR;
            }
        }

        // ─────────────────────────────────────────────────────────────
        // 4. .ENV FILE
        // ─────────────────────────────────────────────────────────────
        CLI::write('');
        CLI::write('[ Step 4/6 ] Writing .env file...', 'yellow');

        // Skip copying in non-interactive mode if .env already exists
        if (!file_exists(ROOTPATH . '.env')) {
            if (!$this->copyEnvFile()) {
                CLI::error('Could not copy env → .env. Aborting.');
                return EXIT_ERROR;
            }
        }

        // Mirrors Install.php's web-installer logic: derive the HTTPS
        // posture from the operator-supplied baseUrl instead of hardcoding
        // insecure defaults, so a CLI install over https:// ends up as
        // secure-by-default as the web installer does.
        $isHttps            = stripos($baseUrl, 'https://') === 0;
        $cookieSecureValue  = $isHttps
            ? 'true'
            : 'false #Set this to true after enabling HTTPS in production.';
        $forceSecureValue   = $isHttps
            ? 'true'
            : 'false #Set to true after enabling HTTPS.';
        // CSP is not itself an HTTPS requirement, but tying it to the same
        // isHttps signal is a reasonable production-hardening default: an
        // operator installing over https:// is treating this as a real
        // deployment, not a local dev box.
        $cspEnabledValue = $isHttps ? 'true' : 'false #Content Security Policy';

        $updates = [
            'CI_ENVIRONMENT'                     => 'production',
            'app.forceGlobalSecureRequests'      => $forceSecureValue,
            'app.CSPEnabled'                     => $cspEnabledValue,
            'app.baseURL'                        => '\'' . $baseUrl . '\'',
            'database.default.hostname'          => $dbHost,
            'database.default.database'          => $dbName,
            'database.default.username'          => $dbUsername,
            'database.default.password'          => $dbPassword,
            'database.default.DBDriver'          => $dbDriver,
            'database.default.DBPrefix'          => $dbPrefix,
            'database.default.port'              => $dbPort,
            'cookie.prefix'                      => '\'ci4ms_\'',
            'cookie.expires'                     => 0,
            'cookie.path'                        => '\'/\'',
            'cookie.domain'                      => '\'\'',
            'cookie.secure'                      => $cookieSecureValue,
            'cookie.httponly'                     => 'true',
            'cookie.samesite'                    => '\'Lax\'',
            'cookie.raw'                         => 'false',
            'honeypot.hidden'                    => '\'true\'',
            'honeypot.label'                     => '\'Honey Pot CMS\'',
            'honeypot.name'                      => '\'honeypot_cms\'',
            'honeypot.template'                  => '\'<label>{label}</label><input type="text" name="{name}" value=""/>\'',
            'honeypot.container'                 => '\'<div style="display:none">{template}</div>\'',
            'security.csrfProtection'            => '\'session\'',
            'security.tokenRandomize'            => 'true',
            'security.tokenName'                 => '\'csrf_token_ci4ms\'',
            'security.headerName'                => '\'X-CSRF-TOKEN\'',
            'security.cookieName'                => '\'csrf_cookie_ci4ms\'',
            'security.expires'                   => 7200,
            'security.regenerate'                => 'true',
            'security.redirect'                  => 'true',
            'security.samesite'                  => '\'Lax\'',
            'app.defaultLocale'                  => '\'en\'',
            'app.supportedLocales'               => '["ar","de","en","es","fr","hi","ja","pt","ru","tr","zh"]',
            'app.negotiateLocale'                => 'true',
            'app.appTimezone'                    => '\'Europe/Istanbul\'',
            'app.version'                        => '0.33.2.0',
        ];

        if (!$this->updateEnvSettings($updates)) {
            CLI::error('Failed to update .env settings. Aborting.');
            return EXIT_ERROR;
        }

        $this->generateEncryptionKey();
        CLI::write('  ✓ .env file written and encryption key generated.', 'green');

        // ─────────────────────────────────────────────────────────────
        // 5. MIGRATION
        // ─────────────────────────────────────────────────────────────
        CLI::write('');
        CLI::write('[ Step 5/6 ] Running Migrations...', 'yellow');

        try {
            $migrate = \Config\Services::migrations();
            $result  = $migrate->setNamespace(null)->latest();

            // Defensive branch: MigrationRunner::latest() only returns
            // `false` when its internal `$silent` flag is `true`
            // (vendor/codeigniter4/framework/system/Database/MigrationRunner.php:215-218),
            // and nothing in this codebase calls `setSilent()`
            // (`grep -rn "setSilent" modules app tests` -> zero matches).
            // With the current default (`$silent = false`), `latest()`
            // instead throws on failure, which the `catch` below already
            // handles. This check is unreachable today; it stays as a
            // guard against a future call site enabling `$silent`.
            if ($result === false) {
                log_message('error', '[ci4ms:setup] Migration failed: latest() returned false (framework automatically regressed/rolled back the previous batch).');
                CLI::error('Migration failed: the framework automatically regressed (rolled back) the previous migration batch — schema may now be at an earlier state than before this run.');
                return EXIT_ERROR;
            }

            CLI::write('  ✓ Migrations completed successfully.', 'green');
        } catch (\Throwable $e) {
            log_message('error', '[ci4ms:setup] Migration failed: ' . $e->getMessage());
            CLI::error('Migration failed: ' . $e->getMessage());
            return EXIT_ERROR;
        }

        // ─────────────────────────────────────────────────────────────
        // 6. SEED DATA
        // ─────────────────────────────────────────────────────────────
        CLI::write('');
        CLI::write('[ Step 6/6 ] Creating Default Data...', 'yellow');

        try {
            $installService = new InstallService();
            $installService->createDefaultData([
                'fname'    => trim(strip_tags($name)),
                'sname'    => trim(strip_tags($surname)),
                'username' => trim(strip_tags($username)),
                'email'    => trim(strip_tags($email)),
                'password' => $password,
                'baseUrl'  => $baseUrl,
                'siteName' => trim(strip_tags($siteName)),
                'slogan'   => trim(strip_tags($slogan)) ?: null
            ]);
            CLI::write('  ✓ Default data created (user, pages, blog, menus, settings).', 'green');

            // Provision an independent DevGate credential (never the admin
            // password — see updateDevGateConfig() docblock). This is the
            // only chance to show the generated password: it is hashed
            // before being written to disk and cannot be recovered after.
            $devGatePassword = $this->updateDevGateConfig($username);
            if ($devGatePassword !== null) {
                CLI::write('  ✓ DevGate configuration updated with a freshly generated password.', 'green');
                CLI::write('');
                CLI::write('  ┌─────────────────────────────────────────────────────────┐', 'yellow');
                CLI::write('  │ DevGate credentials (development Basic-Auth gate)         │', 'yellow');
                CLI::write('  │ Shown ONCE — cannot be recovered after this. Save it now.  │', 'yellow');
                CLI::write('  └─────────────────────────────────────────────────────────┘', 'yellow');
                CLI::write("    Username: {$username}", 'white');
                CLI::write("    Password: {$devGatePassword}", 'white');
                CLI::write('');
            } else {
                CLI::write('  ! DevGate configuration was not updated (file missing or not writable).', 'yellow');
            }
        } catch (\Throwable $e) {
            log_message('error', '[ci4ms:setup] Seed failed: ' . $e->getMessage());
            CLI::error('Default data creation failed: ' . $e->getMessage());
            return EXIT_ERROR;
        }

        // ─────────────────────────────────────────────────────────────
        // DIRECTORIES
        // ─────────────────────────────────────────────────────────────
        $this->ensureDirectories();
        CLI::write('  ✓ Required directories verified.', 'green');

        // ─────────────────────────────────────────────────────────────
        // ROUTES FILE
        // ─────────────────────────────────────────────────────────────
        if (!$this->writeRoutesFile()) {
            CLI::error('Failed to write App/Config/Routes.php. Please check permissions.');
            return EXIT_ERROR;
        }
        CLI::write('  ✓ Routes.php updated.', 'green');

        file_put_contents(WRITEPATH . 'install.lock', 'Installed at: ' . date('Y-m-d H:i:s'));
        chmod(WRITEPATH . 'install.lock', 0444);

        // ─────────────────────────────────────────────────────────────
        // COMPLETED
        // ─────────────────────────────────────────────────────────────
        CLI::write('');
        CLI::write('╔══════════════════════════════════════════╗', 'green');
        CLI::write('║   CI4MS installed successfully!  🎉      ║', 'green');
        CLI::write('╚══════════════════════════════════════════╝', 'green');
        CLI::write('');
        CLI::write("  → Visit your site: {$baseUrl}", 'cyan');
        CLI::write("  → Admin panel  : {$baseUrl}/backend", 'cyan');
        CLI::write('');

        return EXIT_SUCCESS;
    }

    // ═════════════════════════════════════════════════════════════════
    // CLI OPTION PARSER
    // ═════════════════════════════════════════════════════════════════

    /**
     * Parses --key=value formatted arguments from $_SERVER['argv'].
     * CI4's BaseCommand::$params array doesn't work with this format,
     * so we read directly from argv.
     */
    private function parseCliOptions(): array
    {
        $options = [];
        $argv = $_SERVER['argv'] ?? [];

        foreach ($argv as $arg) {
            if (str_starts_with($arg, '--') && str_contains($arg, '=')) {
                [$key, $value] = explode('=', substr($arg, 2), 2);
                $options[$key] = $value;
            }
        }

        return $options;
    }

    /**
     * Are all required arguments present for non-interactive mode?
     */
    private function hasAllRequired(array $args): bool
    {
        $required = ['fname', 'sname', 'email', 'username', 'password', 'siteName', 'baseUrl'];

        foreach ($required as $key) {
            if (empty($args[$key] ?? '')) {
                return false;
            }
        }

        return true;
    }

    /**
     * Read a value from the existing .env file
     */
    private function getEnvValue(string $key, string $default = ''): string
    {
        // Try $_ENV / $_SERVER first (may have been loaded by CI4's .env loader)
        $envKey = str_replace('.', '_', $key);
        if (!empty($_ENV[$key])) return $_ENV[$key];
        if (!empty($_SERVER[$key])) return $_SERVER[$key];

        // Read directly from the .env file
        $envPath = ROOTPATH . '.env';
        if (!file_exists($envPath)) return $default;

        $contents = file_get_contents($envPath);
        $pattern = '/^' . preg_quote($key, '/') . '\s*=\s*(.+)$/m';

        if (preg_match($pattern, $contents, $matches)) {
            return trim($matches[1], " \t\n\r\0\x0B'\"");
        }

        return $default;
    }

    // ═════════════════════════════════════════════════════════════════
    // PRIVATE HELPERS
    // ═════════════════════════════════════════════════════════════════

    /**
     * Copy env → .env
     */
    private function copyEnvFile(): bool
    {
        $source      = ROOTPATH . 'env';
        $destination = ROOTPATH . '.env';

        if (!file_exists($source)) {
            CLI::error("'env' template file not found at: {$source}");
            return false;
        }

        if (!copy($source, $destination)) {
            CLI::error("Could not copy 'env' to '.env'. Check file permissions.");
            return false;
        }

        return true;
    }

    /**
     * Update / add key=value pairs in the .env file
     */
    private function updateEnvSettings(array $updates): bool
    {
        $envPath = ROOTPATH . '.env';

        if (!file_exists($envPath)) {
            CLI::error("'.env' file not found.");
            return false;
        }

        $contents = file_get_contents($envPath);

        foreach ($updates as $key => $value) {
            $pattern     = '/^' . preg_quote($key, '/') . '=.*/m';
            $replacement = "{$key}={$value}";

            if (preg_match($pattern, $contents)) {
                $contents = preg_replace($pattern, $replacement, $contents);
            } else {
                $contents .= PHP_EOL . $replacement;
            }
        }

        file_put_contents($envPath, $contents);
        return true;
    }

    /**
     * Generate an encryption key and write it to .env
     */
    private function generateEncryptionKey(): bool
    {
        $envPath = ROOTPATH . '.env';

        if (!file_exists($envPath)) {
            return false;
        }

        $contents    = file_get_contents($envPath);
        $key         = 'hex2bin:' . bin2hex(random_bytes(32));
        $pattern     = '/^encryption\.key=.*/m';
        $replacement = "encryption.key={$key}";

        if (preg_match($pattern, $contents)) {
            $contents = preg_replace($pattern, $replacement, $contents);
        } else {
            $contents .= PHP_EOL . $replacement;
        }

        file_put_contents($envPath, $contents);
        return true;
    }

    /**
     * Create required directories (if missing)
     */
    private function ensureDirectories(): void
    {
        $dirs = [
            WRITEPATH . 'backups/',
            FCPATH . 'media/.tmb',
            FCPATH . 'media/.trash',
        ];

        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }
    }

    /**
     * Write App/Config/Routes.php from the Backend tpl template
     */
    private function writeRoutesFile(): bool
    {
        helper('filesystem');
        $tplPath = ROOTPATH . 'modules/Backend/Commands/Views/routes.tpl.php';

        if (!file_exists($tplPath)) {
            CLI::error("Routes template not found: {$tplPath}");
            return false;
        }

        $content = file_get_contents($tplPath);
        $content = str_replace('<@', '<?', $content);

        @unlink(APPPATH . 'Config/Routes.php');

        return write_file(APPPATH . 'Config/Routes.php', $content);
    }

    // ═════════════════════════════════════════════════════════════════
    // CLI PROMPT HELPERS (used only in interactive mode)
    // ═════════════════════════════════════════════════════════════════

    /**
     * Simple prompt that cannot be left empty
     */
    private function promptRequired(string $label): string
    {
        while (true) {
            $value = CLI::prompt($label);
            if (trim($value) !== '') return $value;
            CLI::error("{$label} cannot be empty.");
        }
    }

    /**
     * Prompt validated via a callback.
     * $validator(string $val): ?string  →  null = valid, string = error message
     */
    private function promptValidated(string $label, callable $validator, string $default = ''): string
    {
        while (true) {
            $value = $default !== ''
                ? CLI::prompt($label, $default)
                : CLI::prompt($label);

            $error = $validator($value);
            if ($error === null) return $value;
            CLI::error($error);
        }
    }

    /**
     * Prompt for secret values like passwords (input is not shown on screen)
     * $validator(string $val): ?string
     */
    private function promptSecret(string $label, callable $validator): string
    {
        while (true) {
            // CodeIgniter CLI has no built-in hidden-input support;
            // on POSIX terminals, hiding is done via stty.
            if (function_exists('shell_exec') && stripos(PHP_OS, 'win') === false) {
                CLI::print("{$label}: ", 'white');
                system('stty -echo');
                $value = trim((string) fgets(STDIN));
                system('stty echo');
                CLI::write(''); // newline
            } else {
                // Normal prompt on Windows or when stty is unavailable
                $value = CLI::prompt($label);
            }

            $error = $validator($value);
            if ($error === null) return $value;
            CLI::error($error);
        }
    }

    /**
     * Generates a fresh, independent DevGate credential and persists it hashed.
     *
     * DevGate is a separate development-only Basic-Auth gate
     * (see modules/DevGate/Filters/DevGateFilter.php) unrelated to the admin
     * account created by this command. Reusing the admin password here would
     * mean a DevGate credential leak also compromises the admin account, so a
     * random password is generated instead. It is stored hashed
     * (PASSWORD_BCRYPT) and returned once in plaintext; it cannot be
     * recovered from the config file after this call.
     *
     * @param string $username DevGate username (reuses the installed admin
     *                         username for convenience only).
     *
     * @return string|null Generated plaintext password, or null if the
     *                      DevGate config file could not be updated.
     */
    private function updateDevGateConfig(string $username): ?string
    {
        $configPath = ROOTPATH . 'modules/DevGate/Config/DevGate.php';

        if (!file_exists($configPath) || !is_writable($configPath)) {
            return null;
        }

        try {
            $content = file_get_contents($configPath);

            $generatedPassword = bin2hex(random_bytes(16));
            $hashedPassword    = password_hash($generatedPassword, PASSWORD_BCRYPT);

            $userKey = var_export($username, true);
            $passVal = var_export($hashedPassword, true);

            // Prepare the new users array string
            $usersArray = "public array \$users = [" . PHP_EOL .
                "        {$userKey} => {$passVal}," . PHP_EOL .
                "    ];";

            // Update the users array in the file content.
            // preg_replace_callback (not preg_replace) is required here: a
            // bcrypt hash always starts with "$2y$12$..." and a plain
            // preg_replace() replacement string treats "$2"/"$12" as
            // backreferences (silently dropped, since this pattern has no
            // capture groups), corrupting every generated hash. The
            // callback's return value is inserted verbatim, with no
            // backreference parsing.
            $newContent = preg_replace_callback(
                '/public\s+array\s+\$users\s*=\s*\[.*?\];/s',
                static fn () => $usersArray,
                $content
            );

            if ($newContent === null || $newContent === $content) {
                return null;
            }

            // The value just written is a bcrypt hash, never the plaintext —
            // force hashed comparison in DevGateFilter. $matchCount confirms
            // the property was found and replaced exactly once; otherwise a
            // renamed/missing property would silently leave
            // $useHashedPasswords=false while $users holds a hash, locking
            // DevGate out entirely.
            $newContent = preg_replace(
                '/public\s+bool\s+\$useHashedPasswords\s*=\s*(?:true|false);/i',
                'public bool $useHashedPasswords = true;',
                $newContent,
                -1,
                $matchCount
            );

            if ($newContent === null || $matchCount !== 1) {
                return null;
            }

            return file_put_contents($configPath, $newContent) !== false ? $generatedPassword : null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}

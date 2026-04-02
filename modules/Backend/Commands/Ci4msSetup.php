<?php

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
     * Non-interactive modda mı çalışıyoruz?
     * Tüm zorunlu argümanlar CLI'dan verilmişse interaktif prompt atlanır.
     */
    private bool $nonInteractive = false;

    public function run(array $params)
    {
        CLI::write('');
        CLI::write('╔══════════════════════════════════════════╗', 'green');
        CLI::write('║         CI4MS Setup Wizard  v1.0         ║', 'green');
        CLI::write('╚══════════════════════════════════════════╝', 'green');
        CLI::write('');

        // ─────────────────────────────────────────────────────────────
        // GUARD: Zaten kurulmuşsa devam etme
        // ─────────────────────────────────────────────────────────────
        if (file_exists(ROOTPATH . '.env') && !empty(cache('settings'))) {
            CLI::error('CI4MS is already installed. Setup aborted.');
            return;
        }

        // ─────────────────────────────────────────────────────────────
        // CLI argümanlarını oku — non-interactive mod kontrolü
        // ─────────────────────────────────────────────────────────────
        $cliArgs = $this->parseCliOptions();
        $this->nonInteractive = $this->hasAllRequired($cliArgs);

        if ($this->nonInteractive) {
            CLI::write('  Running in non-interactive mode...', 'light_gray');
            CLI::write('');
        }

        // ─────────────────────────────────────────────────────────────
        // 1. KULLANICI BİLGİLERİ
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
        // 2. VERİTABANI BİLGİLERİ
        // ─────────────────────────────────────────────────────────────
        CLI::write('');
        CLI::write('[ Step 2/6 ] Database Configuration', 'yellow');
        CLI::write('─────────────────────────────────────', 'dark_gray');

        if ($this->nonInteractive) {
            // Non-interactive: .env'deki mevcut DB ayarlarını kullan veya CLI argümanlarını al
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
        // 3. SİTE BİLGİLERİ
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
        // ÖZET — Devam mı?
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
                return;
            }
        }

        // ─────────────────────────────────────────────────────────────
        // 4. .ENV DOSYASI
        // ─────────────────────────────────────────────────────────────
        CLI::write('');
        CLI::write('[ Step 4/6 ] Writing .env file...', 'yellow');

        // Non-interactive modda .env zaten mevcutsa kopyalama atla
        if (!file_exists(ROOTPATH . '.env')) {
            if (!$this->copyEnvFile()) {
                CLI::error('Could not copy env → .env. Aborting.');
                return;
            }
        }

        $updates = [
            'CI_ENVIRONMENT'                     => 'development',
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
            'cookie.secure'                      => 'false #Don\'t forget to set it to true when buying production mode.',
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
            'security.redirect'                  => 'false',
            'security.samesite'                  => '\'Lax\'',
            'app.defaultLocale'                  => '\'en\'',
            'app.supportedLocales'               => '["ar","de","en","es","fr","hi","ja","pt","ru","tr","zh"]',
            'app.negotiateLocale'                => 'true',
            'app.appTimezone'                    => '\'Europe/Istanbul\'',
            'app.version'                        => '0.31.3.0',
        ];

        if (!$this->updateEnvSettings($updates)) {
            CLI::error('Failed to update .env settings. Aborting.');
            return;
        }

        $this->generateEncryptionKey();
        CLI::write('  ✓ .env file written and encryption key generated.', 'green');

        // ─────────────────────────────────────────────────────────────
        // 5. MİGRATION
        // ─────────────────────────────────────────────────────────────
        CLI::write('');
        CLI::write('[ Step 5/6 ] Running Migrations...', 'yellow');

        try {
            $migrate = \Config\Services::migrations();
            $migrate->setNamespace(null)->latest();
            CLI::write('  ✓ Migrations completed successfully.', 'green');
        } catch (\Throwable $e) {
            log_message('error', '[ci4ms:setup] Migration failed: ' . $e->getMessage());
            CLI::error('Migration failed: ' . $e->getMessage());
            return;
        }

        // ─────────────────────────────────────────────────────────────
        // 6. SEED VERİLERİ
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
        } catch (\Throwable $e) {
            log_message('error', '[ci4ms:setup] Seed failed: ' . $e->getMessage());
            CLI::error('Default data creation failed: ' . $e->getMessage());
            return;
        }

        // ─────────────────────────────────────────────────────────────
        // KLASÖRLER
        // ─────────────────────────────────────────────────────────────
        $this->ensureDirectories();
        CLI::write('  ✓ Required directories verified.', 'green');

        // ─────────────────────────────────────────────────────────────
        // ROUTES DOSYASI
        // ─────────────────────────────────────────────────────────────
        if (!$this->writeRoutesFile()) {
            CLI::error('Failed to write App/Config/Routes.php. Please check permissions.');
            return;
        }
        CLI::write('  ✓ Routes.php updated.', 'green');

        // ─────────────────────────────────────────────────────────────
        // TAMAMLANDI
        // ─────────────────────────────────────────────────────────────
        CLI::write('');
        CLI::write('╔══════════════════════════════════════════╗', 'green');
        CLI::write('║   CI4MS installed successfully!  🎉      ║', 'green');
        CLI::write('╚══════════════════════════════════════════╝', 'green');
        CLI::write('');
        CLI::write("  → Visit your site: {$baseUrl}", 'cyan');
        CLI::write("  → Admin panel  : {$baseUrl}/backend", 'cyan');
        CLI::write('');
    }

    // ═════════════════════════════════════════════════════════════════
    // CLI OPTION PARSER
    // ═════════════════════════════════════════════════════════════════

    /**
     * $_SERVER['argv'] üzerinden --key=value formatındaki argümanları parse et.
     * CI4'ün BaseCommand::$params dizisi bu formatta çalışmadığı için
     * doğrudan argv'den okuyoruz.
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
     * Non-interactive mod için gerekli tüm zorunlu argümanlar var mı?
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
     * Mevcut .env dosyasından bir değer oku
     */
    private function getEnvValue(string $key, string $default = ''): string
    {
        // Önce $_ENV / $_SERVER dene (CI4 .env loader tarafından yüklenmiş olabilir)
        $envKey = str_replace('.', '_', $key);
        if (!empty($_ENV[$key])) return $_ENV[$key];
        if (!empty($_SERVER[$key])) return $_SERVER[$key];

        // .env dosyasından doğrudan oku
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
     * env → .env kopyala
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
     * .env dosyasındaki key=value çiftlerini güncelle / ekle
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
     * Encryption key üret ve .env'e yaz
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
     * Gerekli klasörleri oluştur (yoksa)
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
     * Backend tpl şablonundan App/Config/Routes.php yaz
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
    // CLI PROMPT HELPERS (sadece interaktif modda kullanılır)
    // ═════════════════════════════════════════════════════════════════

    /**
     * Boş geçilemeyen basit prompt
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
     * Callback ile validate edilen prompt.
     * $validator(string $val): ?string  →  null = geçerli, string = hata mesajı
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
     * Şifre gibi gizli değerler için prompt (girdi ekranda görünmez)
     * $validator(string $val): ?string
     */
    private function promptSecret(string $label, callable $validator): string
    {
        while (true) {
            // CodeIgniter CLI'da doğrudan gizli input desteği yok;
            // POSIX terminallerinde stty ile gizleme sağlanır.
            if (function_exists('shell_exec') && stripos(PHP_OS, 'win') === false) {
                CLI::write("{$label}: ", 'white', false);
                system('stty -echo');
                $value = trim(fgets(STDIN));
                system('stty echo');
                CLI::write(''); // newline
            } else {
                // Windows veya stty yoksa normal prompt
                $value = CLI::prompt($label);
            }

            $error = $validator($value);
            if ($error === null) return $value;
            CLI::error($error);
        }
    }
}

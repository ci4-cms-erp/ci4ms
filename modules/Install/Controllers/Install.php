<?php

namespace Modules\Install\Controllers;

use CodeIgniter\Controller;
use Modules\Install\Services\InstallService;

class Install extends Controller
{
    /** Cookie name carrying the per-installer-session nonce. */
    private const INSTALL_NONCE_COOKIE = 'install_nonce';

    public function index()
    {
        if ($this->request->is('post')) {
            // Pre-validate the install nonce. The shipped CSRF middleware is
            // disabled on install/* in InstallConfig (no session exists yet to
            // bind the standard token to), so we bind the form to a cookie
            // value the browser only sends back when the POST is same-site.
            // Combined with the SameSite=Lax default this neutralises the
            // pre-install CSRF window described in audit Finding 16.
            $cookieNonce = (string) ($this->request->getCookie(self::INSTALL_NONCE_COOKIE) ?? '');
            $postNonce   = (string) ($this->request->getPost(self::INSTALL_NONCE_COOKIE) ?? '');
            if ($cookieNonce === '' || $postNonce === '' || !hash_equals($cookieNonce, $postNonce)) {
                return redirect()->route('install')->withInput()->with('errors', ['install' => lang('Install.invalidNonce')]);
            }

            $valData = [
                'baseUrl' => ['label' => lang('Install.baseUrl'), 'rules' => 'required|valid_url'],
                'host' => ['label' => lang('Install.databaseHost'), 'rules' => 'required|max_length[255]|regex_match[/^[a-zA-Z0-9._-]+$/]'],
                'dbname' => ['label' => lang('Install.databaseName'), 'rules' => 'required|alpha_dash|max_length[100]'],
                'dbusername' => ['label' => lang('Install.databaseUsername'), 'rules' => 'required|alpha_dash|max_length[100]'],
                'dbpassword' => ['label' => lang('Install.databasePassword'), 'rules' => 'permit_empty|max_length[255]|regex_match[/^[^\r\n]*$/]'],
                'dbdriver' => ['label' => lang('Install.databaseDriver'), 'rules' => 'required|in_list[MySQLi]'],
                'dbpre' => ['label' => lang('Install.databasePrefix'), 'rules' => 'permit_empty|alpha_dash|max_length[20]'],
                'dbport' => ['label' => lang('Install.databasePort'), 'rules' => 'required|is_natural_no_zero|less_than[65536]'],
                'name' => ['label' => lang('Install.firstName'), 'rules' => 'required|max_length[100]|regex_match[/^[^<>{}=]+$/u]'],
                'surname' => ['label' => lang('Install.lastName'), 'rules' => 'required|max_length[100]|regex_match[/^[^<>{}=]+$/u]'],
                'username' => ['label' => lang('Backend.username'), 'rules' => 'required|alpha_numeric|min_length[3]|max_length[50]'],
                'password' => ['label' => lang('Install.password'), 'rules' => 'required|min_length[8]'],
                'email' => ['label' => lang('Install.email'), 'rules' => 'required|valid_email|max_length[255]'],
                'siteName' => ['label' => lang('Backend.siteName'), 'rules' => 'required|alpha_numeric_space|max_length[255]|regex_match[/^[^<>{}=]+$/u]']
            ];
            if ($this->request->getPost('slogan')) $valData['slogan'] = ['label' => lang('Install.slogan'), 'rules' => 'required|alpha_numeric_space|max_length[255]|regex_match[/^[^<>{}=]+$/u]'];

            if ($this->validate($valData) === false) return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());

            // Default cookie.secure to true whenever the operator-supplied
            // baseURL is https://. Local-dev installs over http:// fall back
            // to false. This matches the secure-by-default posture of the
            // committed Cookie.php config without breaking HTTP dev setups.
            $isHttps = stripos((string) $this->request->getPost('baseUrl'), 'https://') === 0;
            $cookieSecureValue = $isHttps
                ? 'true'
                : 'false #Set this to true after enabling HTTPS in production.';

            $updates = [
                'CI_ENVIRONMENT' => 'production',
                'app.baseURL' => '\'' . $this->request->getPost('baseUrl') . '\'',
                'app.forceGlobalSecureRequests' => $isHttps ? 'true' : 'false #Set to true after enabling HTTPS.',
                'database.default.hostname' => $this->request->getPost('host'),
                'database.default.database' => $this->request->getPost('dbname'),
                'database.default.username' => $this->request->getPost('dbusername'),
                'database.default.password' => $this->request->getPost('dbpassword'),
                'database.default.DBDriver' => $this->request->getPost('dbdriver'),
                'database.default.DBPrefix' => $this->request->getPost('dbpre'),
                'database.default.port' => $this->request->getPost('dbport'),
                'cookie.prefix' => '\'ci4ms_\'',
                'cookie.expires' => 0,
                'cookie.path' => '\'/\'',
                'cookie.domain' => '\'\'',
                'cookie.secure' => $cookieSecureValue,
                'cookie.httponly' => 'true',
                'cookie.samesite' => '\'Lax\'',
                'cookie.raw' => 'false',
                'honeypot.hidden' => '\'true\'',
                'honeypot.label' => '\'Honey Pot CMS\'',
                'honeypot.name' => '\'honeypot_cms\'',
                'honeypot.template' => '\'<label>{label}</label><input type="text" name="{name}" value=""/>\'',
                'honeypot.container' => '\'<div style="display:none">{template}</div>\'',
                'security.csrfProtection' => '\'session\'',
                'security.tokenRandomize' => 'true',
                'security.tokenName' => '\'csrf_token_ci4ms\'',
                'security.headerName' => '\'X-CSRF-TOKEN\'',
                'security.cookieName' => '\'csrf_cookie_ci4ms\'',
                'security.expires' => 7200,
                'security.regenerate' => 'true',
                'security.redirect' => 'true',
                'security.samesite' => '\'Lax\'',
                'app.defaultLocale' => '\'en\'',
                'app.supportedLocales' => '["ar","de","en","es","fr","hi","ja","pt","ru","tr","zh"]',
                'app.negotiateLocale' => 'true',
                'app.appTimezone' => '\'Europe/Istanbul\'',
                'app.version' => '0.33.2.0'
            ];
            if ($this->copyEnvFile() && $this->updateEnvSettings($updates)) $this->generateEncryptionKey();


            $installData = [
                'name' => $this->request->getPost('name'),
                'surname' => $this->request->getPost('surname'),
                'username' => $this->request->getPost('username'),
                'email' => $this->request->getPost('email'),
                'password' => $this->request->getPost('password'),
                'siteName' => $this->request->getPost('siteName'),
                'baseUrl' => $this->request->getPost('baseUrl'),
            ];
            if ($this->request->getPost('slogan')) $installData['slogan'] = $this->request->getPost('slogan') ?: null;
            $installData['geoLookup'] = $this->request->getPost('geoLookup') ? '1' : '0';

            return $this->dbsetup($installData);
        }

        // GET: ensure the browser holds an install_nonce cookie, generate one
        // if missing, and pass the value to the view so the form can echo it
        // back as a hidden field. Reusing an existing cookie avoids breaking
        // multi-tab / reload UX during the install flow.
        helper('cookie');
        $nonce = (string) ($this->request->getCookie(self::INSTALL_NONCE_COOKIE) ?? '');
        if ($nonce === '' || !preg_match('/^[a-f0-9]{32}$/', $nonce)) {
            $nonce = bin2hex(random_bytes(16));
            // Lax SameSite (CI4 default) and httpOnly: cross-origin POSTs
            // won't carry this cookie, so the hash_equals() above will fail
            // for any attacker-driven form submission.
            set_cookie([
                'name'     => self::INSTALL_NONCE_COOKIE,
                'value'    => $nonce,
                'expire'   => 3600,
                'path'     => '/',
                'secure'   => $this->request->isSecure(),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }

        return view('Modules\Install\Views\install', ['installNonce' => $nonce]);
    }

    private function updateEnvSettings(array $updates)
    {
        $envPath = ROOTPATH . '.env';
        if (!file_exists($envPath)) return ['error' => "'.env' file not found."];
        $contents = file_get_contents($envPath);
        foreach ($updates as $key => $value) {
            $value = str_replace(["\r", "\n"], '', (string) $value);
            $pattern = '/^' . preg_quote($key, '/') . '=.*/m';
            $replacement = "{$key}={$value}";
            if (preg_match($pattern, $contents)) $contents = preg_replace($pattern, $replacement, $contents);
            else $contents .= PHP_EOL . $replacement;
        }
        file_put_contents($envPath, $contents);
        return true;
    }

    private function copyEnvFile()
    {
        $source = ROOTPATH . 'env';
        $destination = ROOTPATH . '.env';
        if (!file_exists($source)) {
            return ['error' => "'env' file not found."];
        }
        if (!copy($source, $destination)) {
            return ['error' => "'env' file is not copy to .env file."];
        }
        return true;
    }

    private function generateEncryptionKey()
    {
        $envPath = ROOTPATH . '.env';
        if (!file_exists($envPath)) return false;
        $contents = file_get_contents($envPath);
        $key = 'hex2bin:' . bin2hex(random_bytes(32));
        $pattern = '/^encryption\.key=.*/m';
        $replacement = "encryption.key={$key}";
        if (preg_match($pattern, $contents)) {
            $contents = preg_replace($pattern, $replacement, $contents);
        } else {
            $contents .= PHP_EOL . $replacement;
        }
        file_put_contents($envPath, $contents);

        /* The Config\Encryption singleton was created at process boot, before
        .env existed, so its $key is empty. Rebind it to the decoded binary
        key here so Services::encrypter() works later in THIS same request
        (InstallService::createDefaultData). We store the decoded form
        because BaseConfig only parses the hex2bin: prefix at construction
        time, which has already passed for this request. */
        config('Encryption')->key = hex2bin(substr($key, 8));

        return true;
    }

    private function dbsetup(array $installData)
    {
        /* The .env file was written earlier in this same request, but the
        Config\Database singleton was instantiated at process boot — before
        .env existed — so its `default` group still carries an empty
        database name. Rebind the default group to the operator-supplied
        values here so both the migration runner below and InstallService
        (which opens the shared `default` connection) connect to the real
        database instead of issuing `SHOW TABLES FROM ` against an empty schema.*/
        $dbConfig = config('Database');
        $dbConfig->default = array_merge($dbConfig->default, [
            'hostname' => (string) $this->request->getPost('host'),
            'username' => (string) $this->request->getPost('dbusername'),
            'password' => (string) $this->request->getPost('dbpassword'),
            'database' => (string) $this->request->getPost('dbname'),
            'DBDriver' => (string) $this->request->getPost('dbdriver'),
            'DBPrefix' => (string) $this->request->getPost('dbpre'),
            'port'     => (int) $this->request->getPost('dbport'),
        ]);

        $migrate = \Config\Services::migrations();
        try {
            $migrate->setNamespace(null)->latest();
        } catch (\Throwable $e) {
            log_message('error', $e->getMessage());
            return redirect()->route('install')->withInput()->with('errors', ['migration' => $e->getMessage()]);
        }
        $createDBs = new InstallService();
        $createDBs->createDefaultData([
            'fname' => trim(strip_tags($installData['name'])),
            'sname' => trim(strip_tags($installData['surname'])),
            'username' => trim(strip_tags($installData['username'])),
            'email' => trim(strip_tags($installData['email'])),
            'password' => $installData['password'],
            'baseUrl' => $installData['baseUrl'],
            'siteName' => trim(strip_tags($installData['siteName'])),
        ]);

        // -----------------------------------------------------------------
        // Provision an independent DevGate credential (never the admin
        // account password — see updateDevGateConfig() docblock).
        // -----------------------------------------------------------------
        $devGateUsername = trim(strip_tags($installData['username']));
        $devGatePassword = $this->updateDevGateConfig($devGateUsername);

        @unlink(APPPATH . 'Config/Routes.php');
        $file = ROOTPATH . 'modules/Backend/Commands/Views/routes.tpl.php';
        $content = file_get_contents($file);
        $content = str_replace('<@', '<?', $content);
        if (! is_dir(WRITEPATH . 'backups/') && !is_dir(FCPATH . 'media/.tmb') && !is_dir(FCPATH . 'media/.trash')) {
            mkdir(WRITEPATH . 'backups/', 0755, true);
            mkdir(FCPATH . 'media/.tmb', 0755, true);
            mkdir(FCPATH . 'media/.trash', 0755, true);
        }
        if (!write_file(APPPATH . 'Config/Routes.php', $content)) {
            return redirect()->to($installData['baseUrl'])->withInput()->with('errors', ['route' => lang('Install.routeFileError')]);
        }

        file_put_contents(WRITEPATH . 'install.lock', 'Installed at: ' . date('Y-m-d H:i:s'));
        chmod(WRITEPATH . 'install.lock', 0444);

        // The generated DevGate password only exists in memory at this point
        // (it was hashed before being written to disk) — this is the one and
        // only chance to show it to the operator. Render it inline instead of
        // redirecting straight to baseUrl so it isn't lost in a page that was
        // never built to display it.
        if ($devGatePassword !== null) {
            return $this->response->setBody($this->devGateCredentialsBody(
                $devGateUsername,
                $devGatePassword,
                $installData['baseUrl']
            ));
        }

        return redirect()->to($installData['baseUrl'], 301);
    }

    /**
     * Generates a fresh, independent DevGate credential and persists it hashed.
     *
     * DevGate is a separate development-only Basic-Auth gate
     * (see modules/DevGate/Filters/DevGateFilter.php) unrelated to the admin
     * account created by this installer. Reusing the admin password here
     * would mean a DevGate credential leak (e.g. shoulder-surfing the
     * browser's Basic-Auth prompt) doubles as an admin account compromise —
     * so a random password is generated instead, independent of anything the
     * operator typed into this form. It is stored hashed
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
            log_message('info', "DevGate config file skipping: Not found or not writable.");
            return null;
        }

        try {
            $content = file_get_contents($configPath);

            $generatedPassword = bin2hex(random_bytes(16));
            $hashedPassword    = password_hash($generatedPassword, PASSWORD_BCRYPT);

            $userKey = var_export($username, true);
            $passVal = var_export($hashedPassword, true);

            // Maintain project's '[]' array style with proper indentation
            $usersArray = "public array \$users = [" . PHP_EOL .
                "        {$userKey} => {$passVal}," . PHP_EOL .
                "    ];";

            // Update the users array using a robust multiline regex.
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
            // the property was actually found and replaced exactly once;
            // without that check a renamed/missing property would silently
            // leave $useHashedPasswords=false while $users holds a hash,
            // which locks DevGate out entirely (hash never equals plaintext).
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

        } catch (\Exception $e) {
            log_message('error', "Failed to update DevGate config: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Renders the one-time DevGate credential disclosure page shown at the
     * end of a successful install. Kept as a self-contained inline response
     * (no view file) since it is shown exactly once, pre-session, and never
     * reachable again — see updateDevGateConfig() for why the password
     * cannot be re-displayed later.
     *
     * @param string $username DevGate username
     * @param string $password Generated plaintext DevGate password
     * @param string $continueUrl URL to the freshly installed site
     *
     * @return string Full HTML document
     */
    private function devGateCredentialsBody(string $username, string $password, string $continueUrl): string
    {
        $title    = esc(lang('Install.devGateCredentialsTitle'));
        $warning  = esc(lang('Install.devGateCredentialsWarning'));
        $note     = esc(lang('Install.devGateCredentialsNote'));
        $userLbl  = esc(lang('Install.devGateCredentialsUsername'));
        $passLbl  = esc(lang('Install.devGateCredentialsPassword'));
        $continue = esc(lang('Install.devGateCredentialsContinue'));
        $userVal  = esc($username);
        $passVal  = esc($password);
        $url      = esc($continueUrl, 'attr');

        return <<<HTML
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>{$title}</title>
            <style>
                *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
                body {
                    font-family: system-ui, -apple-system, sans-serif;
                    background: #0f172a;
                    color: #e2e8f0;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    min-height: 100vh;
                    padding: 2rem;
                }
                .card {
                    background: #1e293b;
                    border: 1px solid #334155;
                    border-radius: 12px;
                    padding: 2.5rem;
                    max-width: 480px;
                    width: 100%;
                }
                h1 { font-size: 1.35rem; font-weight: 700; margin-bottom: 1rem; }
                .warning {
                    background: #7c2d1222;
                    border: 1px solid #7c2d1255;
                    color: #fca5a5;
                    border-radius: 8px;
                    padding: 0.85rem 1rem;
                    font-size: 0.85rem;
                    margin-bottom: 1.25rem;
                }
                .note { color: #94a3b8; font-size: 0.85rem; margin-bottom: 1.5rem; line-height: 1.5; }
                .row { margin-bottom: 0.75rem; }
                .row span.label { display: block; font-size: 0.75rem; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; }
                .row code { display: block; background: #0f172a; border: 1px solid #334155; border-radius: 6px; padding: 0.6rem 0.8rem; font-size: 0.95rem; word-break: break-all; }
                a.continue { display: inline-block; margin-top: 1.5rem; color: #a78bfa; text-decoration: none; font-size: 0.9rem; }
            </style>
        </head>
        <body>
            <div class="card">
                <h1>{$title}</h1>
                <div class="warning">{$warning}</div>
                <p class="note">{$note}</p>
                <div class="row"><span class="label">{$userLbl}</span><code>{$userVal}</code></div>
                <div class="row"><span class="label">{$passLbl}</span><code>{$passVal}</code></div>
                <a class="continue" href="{$url}">{$continue} &rarr;</a>
            </div>
        </body>
        </html>
        HTML;
    }
}

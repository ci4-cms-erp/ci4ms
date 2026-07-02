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
                'username' => ['label' => lang('Install.username'), 'rules' => 'required|alpha_numeric|min_length[3]|max_length[50]'],
                'password' => ['label' => lang('Install.password'), 'rules' => 'required|min_length[8]'],
                'email' => ['label' => lang('Install.email'), 'rules' => 'required|valid_email|max_length[255]'],
                'siteName' => ['label' => lang('Install.siteName'), 'rules' => 'required|alpha_numeric_space|max_length[255]|regex_match[/^[^<>{}=]+$/u]']
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
                'CI_ENVIRONMENT' => 'development',
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
        // Update DevGate configuration with the installed user credentials
        // -----------------------------------------------------------------
        $this->updateDevGateConfig(
            trim(strip_tags($installData['username'])),
            $installData['password']
        );

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
        return redirect()->to($installData['baseUrl'], 301);
    }

    /**
     * Updates DevGate configuration with the initial admin credentials.
     *
     * @param string $username
     * @param string $password
     * @return bool
     */
    private function updateDevGateConfig(string $username, string $password): bool
    {
        $configPath = ROOTPATH . 'modules/DevGate/Config/DevGate.php';

        if (!file_exists($configPath) || !is_writable($configPath)) {
            log_message('info', "DevGate config file skipping: Not found or not writable.");
            return false;
        }

        try {
            $content = file_get_contents($configPath);

            // Robust detection of useHashedPasswords (handles spaces, newlines, and case-insensitivity)
            $useHashed = false;
            if (preg_match('/public\s+bool\s+\$useHashedPasswords\s*=\s*(true|1)/i', $content)) {
                $useHashed = true;
            }

            // Prepare credentials
            $finalPass = $useHashed ? password_hash($password, PASSWORD_BCRYPT) : $password;
            $userKey = var_export($username, true);
            $passVal = var_export($finalPass, true);

            // Maintain project's '[]' array style with proper indentation
            $usersArray = "public array \$users = [" . PHP_EOL .
                "        {$userKey} => {$passVal}," . PHP_EOL .
                "    ];";

            // Update the users array using a robust multiline regex
            $newContent = preg_replace(
                '/public\s+array\s+\$users\s*=\s*\[.*?\];/s',
                $usersArray,
                $content
            );

            if ($newContent === null || $newContent === $content) {
                return false;
            }

            return file_put_contents($configPath, $newContent) !== false;

        } catch (\Exception $e) {
            log_message('error', "Failed to update DevGate config: " . $e->getMessage());
            return false;
        }
    }
}

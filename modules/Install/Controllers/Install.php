<?php

namespace Modules\Install\Controllers;

use CodeIgniter\Controller;
use Modules\Install\Services\InstallService;

class Install extends Controller
{
    public function index()
    {
        if ($this->request->is('post')) {
            $valData = ([
                'baseUrl' => ['label' => '', 'rules' => 'required|valid_url'],
                'dbname' => ['label' => '', 'rules' => 'required|alpha_dash'],
                'dbusername' => ['label' => '', 'rules' => 'required|alpha_dash'],
                'dbpassword' => ['label' => '', 'rules' => 'required'],
                'dbdriver' => ['label' => '', 'rules' => 'required|in_list[MySQLi]'],
                'dbpre' => ['label' => '', 'rules' => 'required|permit_empty|alpha_dash'],
                'dbport' => ['label' => '', 'rules' => 'required|is_natural_no_zero|less_than[65536]'],
                'name' => ['label' => '', 'rules' => 'required|alpha_space'],
                'surname' => ['label' => '', 'rules' => 'required|alpha_space'],
                'username' => ['label' => '', 'rules' => 'required|alpha_numeric'],
                'email' => ['label' => '', 'rules' => 'required|valid_email'],
                'siteName' => ['label' => '', 'rules' => 'required|alpha_numeric_space']
            ]);
            if ($this->validate($valData) == false) return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());

            $this->copyEnvFile();
            $updates = [
                'CI_ENVIRONMENT' => 'development',
                'app.baseURL' => '\'' . $this->request->getPost('baseUrl') . '\'',
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
                'cookie.secure' => 'false #Don\'t forget to set it to true when buying production mode.',
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
                'security.redirect' => 'false',
                'security.samesite' => '\'Lax\'',
                'app.defaultLocale' => '\'en\'',
                'app.supportedLocales' => '[\'ar\',\'de\',\'en\',\'es\',\'fr\',\'hi\',\'ja\',\'pt\',\'ru\',\'tr\',\'zh\']',
                'app.negotiateLocale' => 'true',
                'app.appTimzezone' => '\'Europe/Istanbul\'',
                'app.version' => '0.29.0.0'
            ];
            if ($this->updateEnvSettings($updates)) $this->generateEncryptionKey();

            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
            session()->setFlashdata('install_data', [
                'name' => $this->request->getPost('name'),
                'surname' => $this->request->getPost('surname'),
                'username' => $this->request->getPost('username'),
                'email' => $this->request->getPost('email'),
                'password' => $this->request->getPost('password'),
                'siteName' => $this->request->getPost('siteName'),
            ]);
            return redirect()->to($protocol . $_SERVER['SERVER_NAME'] . '/install/dbsetup', 308);
        }
        return view('Modules\Install\Views\install');
    }

    private function updateEnvSettings(array $updates)
    {
        $envPath = ROOTPATH . '.env';
        if (!file_exists($envPath)) return ['error' => "'.env' file not found."];
        $contents = file_get_contents($envPath);
        foreach ($updates as $key => $value) {
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
        return true;
    }

    public function dbsetup()
    {
        $migrate = \Config\Services::migrations();
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
        $baseURL = $protocol . $_SERVER['SERVER_NAME'];
        try {
            $migrate->latest();
        } catch (\Throwable $e) {
            log_message('error', $e->getMessage());
            return redirect()->to($baseURL)->withInput()->with('errors', ['migration' => $e->getMessage()]);
        }
        $createDBs = new InstallService();
        $installData = session()->getFlashdata('install_data');
        if (empty($installData)) return redirect()->to($baseURL);
        $createDBs->createDefaultData([
            'fname' => trim(strip_tags($installData['name'])),
            'sname' => trim(strip_tags($installData['surname'])),
            'username' => trim(strip_tags($installData['username'])),
            'email' => trim(strip_tags($installData['email'])),
            'password' => $installData['password'],
            'baseUrl' => $installData['baseUrl'],
            'siteName' => trim(strip_tags($installData['siteName'])),
        ]);

        @unlink(APPPATH . 'Config/Routes.php');
        $file = APPPATH . 'Commands/Views/routes.tpl.php';
        $content = file_get_contents($file);
        $content = str_replace('<@', '<?', $content);
        if (! is_dir(WRITEPATH . 'backups/') && !is_dir(PUBLICPATH . 'media/.tmb') && !is_dir(PUBLICPATH . 'media/.trash')) {

            mkdir(WRITEPATH . 'backups/', 0755, true);
            mkdir(PUBLICPATH . 'uploads/.tmb', 0755, true);
            mkdir(PUBLICPATH . 'uploads/.trash', 0755, true);
        }
        if (!write_file(APPPATH . 'Config/Routes.php', $content)) {
            return redirect()->to($baseURL)->withInput()->with('errors', ['route' => 'Routes dosyası oluşturulamadı.']);
        }

        return redirect()->to($baseURL, 301);
    }
}

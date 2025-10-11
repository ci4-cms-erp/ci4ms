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
                'baseUrl' => ['label' => '', 'rules' => 'required'],
                'dbname' => ['label' => '', 'rules' => 'required'],
                'dbusername' => ['label' => '', 'rules' => 'required'],
                'dbpassword' => ['label' => '', 'rules' => 'required'],
                'dbdriver' => ['label' => '', 'rules' => 'required'],
                'dbpre' => ['label' => '', 'rules' => 'required'],
                'dbport' => ['label' => '', 'rules' => 'required'],
                'name' => ['label' => '', 'rules' => 'required'],
                'surname' => ['label' => '', 'rules' => 'required'],
                'username' => ['label' => '', 'rules' => 'required'],
                'email' => ['label' => '', 'rules' => 'required'],
                'siteName' => ['label' => '', 'rules' => 'required']
            ]);
            if ($this->validate($valData) == false) return redirect()->back()->withInput()->with('errors',$this->validator->getErrors());

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
                'cookie.secure' => 'false',
                'cookie.httponly' => 'false',
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
                'app.defaultLocale'=> '\'tr\'',
                'app.supportedLocales'=> '[\'tr\',\'en\']',
                'app.negotiateLocale'=> 'true',
                'app.appTimzezone'=> '\'Europe/Istanbul\'',
            ];
            if ($this->updateEnvSettings($updates)) $this->generateEncryptionKey();

            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
            return redirect()->to($protocol . $_SERVER['SERVER_NAME'] . '/install/dbsetup?fname=' . $this->request->getPost('name') . '&sname=' . $this->request->getPost('surname') . '&username=' . $this->request->getPost('username') . '&email=' . $this->request->getPost('email') . '&password=' . $this->request->getPost('password') . '&siteName=' . $this->request->getPost('baseUrl') . '&siteName=' . $this->request->getPost('siteName'), 308);
        }
        return view('Modules\Install\Views\install');
    }

    private function updateEnvSettings(array $updates)
    {
        $envPath = ROOTPATH . '.env';
        if (!file_exists($envPath)) return ['error' => "'.env' dosyası bulunamadı."];
        $contents = file_get_contents($envPath);
        foreach ($updates as $key => $value) {
            $pattern = '/^' . preg_quote($key, '/') . '=.*/m';
            $replacement = "{$key}={$value}";
            // Eğer satır varsa değiştir, yoksa en sona ekle
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
            return ['error' => "'env' dosyası bulunamadı."];
        }
        if (!copy($source, $destination)) {
            return ['error' => "'env' dosyası .env olarak kopyalanamadı."];
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
        // Create default database tables
        $migrate = \Config\Services::migrations();
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
        $baseURL = $protocol . $_SERVER['SERVER_NAME'];
        try {
            $migrate->latest();
        } catch (\Throwable $e) {
            // Hata mesajını görebilmek için logla veya ekrana yazdır
            log_message('error', $e->getMessage());
            return redirect()->to($baseURL)->withInput()->with('errors', ['migration' => $e->getMessage()]);
        }
        $createDBs = new InstallService();
        $createDBs->createDefaultData([
            'fname' => $this->request->getPost('name'),
            'sname' => $this->request->getPost('surname'),
            'username' => $this->request->getPost('username'),
            'email' => $this->request->getPost('email'),
            'password' => $this->request->getPost('password'),
            'baseUrl' => $this->request->getPost('baseUrl'),
            'siteName' => $this->request->getPost('siteName'),
        ]);

        // Create default routes file
        unlink(APPPATH . 'Config/Routes.php');
        $file = APPPATH . 'Commands/Views/routes.tpl.php';
        $content = file_get_contents($file);
        $content = str_replace('<@', '<?', $content);
        if (!write_file(APPPATH . 'Config/Routes.php', $content)) {
            return redirect()->to($baseURL)->withInput()->with('errors', ['route' => 'Routes dosyası oluşturulamadı.']);
        }

        return redirect()->to($baseURL, 301);
    }
}

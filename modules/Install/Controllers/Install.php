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
            if ($this->validate($valData) == false) return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
            shell_exec('php spark env development');
            shell_exec('php spark migrate');
            $updates = [
                'app.baseURL' => $this->request->getPost('baseUrl'),
                'database.default.hostname' => $this->request->getPost('host'),
                'database.default.database' => $this->request->getPost('dbname'),
                'database.default.username' => $this->request->getPost('dbusername'),
                'database.default.password' => $this->request->getPost('dbpassword'),
                'database.default.DBDriver' => $this->request->getPost('dbdriver'),
                'database.default.DBPrefix' => $this->request->getPost('dbpre'),
                'database.default.port' => $this->request->getPost('dbport'),
                'cookie.prefix' => 'ci4ms_',
                'security.tokenName' => 'csrf_token_ci4ms',
                'security.cookieName' => 'csrf_cookie_ci4ms',
            ];
            if ($this->updateEnvSettings($updates)) {
                shell_exec('php spark create:route');
                shell_exec('php spark key:generate');
            }
            $createDBs = new InstallService();
            $createDBs->createDefaultData([
                'fname' => $this->request->getPost('name'),
                'sname' => $this->request->getPost('surname'),
                'username' => $this->request->getPost('username'),
                'email' => $this->request->getPost('email'),
                'baseUrl' => $this->request->getPost('baseUrl'),
                'siteName' => $this->request->getPost('siteName'),
            ]);
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
}

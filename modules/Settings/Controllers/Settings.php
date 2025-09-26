<?php

namespace Modules\Settings\Controllers;

use Config\Mimes;

class Settings extends \Modules\Backend\Controllers\BaseController
{
    /**
     * @return string
     */
    public function index()
    {
        $this->defData['request'] = $this->request;
        $blacklists = $this->commonModel->selectOne('login_rules', ['type' => 'blacklist']);
        $whitelists = $this->commonModel->selectOne('login_rules', ['type' => 'whitelist']);

        if (!empty($blacklists)) {
            $blacklistRange = implode(', ', (array)$blacklists->range);
            $blacklistLine = implode(', ', (array)$blacklists->line);
            $blacklistUsername = implode(', ', (array)$blacklists->username);
        }

        if (!empty($whitelists)) {
            $whitelistRange = implode(', ', (array)$whitelists->range);
            $whitelistLine = implode(', ', (array)$whitelists->line);
            $whitelistUsername = implode(', ', (array)$whitelists->username);
        }

        $this->defData['blacklistRange'] = ($blacklistRange ?? '');
        $this->defData['blacklistLine'] = ($blacklistLine ?? '');
        $this->defData['blacklistUsername'] = ($blacklistUsername ?? '');
        $this->defData['whitelistRange'] = ($whitelistRange ?? '');
        $this->defData['whitelistLine'] = ($whitelistLine ?? '');
        $this->defData['whitelistUsername'] = ($whitelistUsername ?? '');
        $this->defData['mimes'] = Mimes::$mimes;
        return view('Modules\Settings\Views\settings', $this->defData);
    }

    /**
     * @return \CodeIgniter\HTTP\RedirectResponse
     */
    public function compInfosPost()
    {
        $valData = ([
            'cName' => ['label' => 'Şirket Adı', 'rules' => 'required'],
            'cUrl' => ['label' => 'Site Linki', 'rules' => 'required|valid_url'],
            'cAddress' => ['label' => 'Şirket Adresi', 'rules' => 'required'],
            'cPhone' => ['label' => 'Şirket Telefonu', 'rules' => 'required'],
            'cMail' => ['label' => 'Şirket Maili', 'rules' => 'required|valid_email'],
        ]);

        if (!empty($this->request->getPost('cSlogan'))) $valData['cSlogan'] = ['label' => 'Slogan', 'rules' => 'required'];
        if (!empty($this->request->getPost('cGSM'))) $valData['cGSM'] = ['label' => 'Şirket GSM', 'rules' => 'required'];
        if (!empty($this->request->getPost('cMap'))) $valData['cMap'] = ['label' => 'Google Map iframe linki', 'rules' => 'required'];
        if (!empty($this->request->getPost('cLogo'))) $valData['cLogo'] = ['label' => 'Şirket Logosu', 'rules' => 'required'];

        if ($this->validate($valData) == false) return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());

        $this->commonModel->edit('settings', ['content' => $this->request->getPost('cName')], ['option' => 'siteName']);
        $this->commonModel->edit('settings', ['content' => $this->request->getPost('cUrl')], ['option' => 'siteURL']);

        $data = [
            'address' => $this->request->getPost('cAddress'),
            'phone' => $this->request->getPost('cPhone'),
            'email' => $this->request->getPost('cMail')
        ];
        if (!empty($this->request->getPost('cSlogan'))) $this->commonModel->edit('settings', ['content' => $this->request->getPost('cSlogan')], ['option' => 'slogan']);
        if (!empty($this->request->getPost('cGSM'))) $data['gsm'] = $this->request->getPost('cGSM');
        if (!empty($this->request->getPost('cMap'))) $this->commonModel->edit('settings', ['content' => $this->request->getPost('cMap')], ['option' => 'map_iframe']);
        if (!empty($this->request->getPost('cLogo'))) $this->commonModel->edit('settings', ['content' => $this->request->getPost('cLogo')], ['option' => 'logo']);

        if ($this->commonModel->edit('settings', ['content' => json_encode($data)], ['option' => 'company'])) {
            cache()->delete('settings');
            return redirect()->back()->with('message', 'Şirket Bilgileri Güncellendi.');
        } else return redirect()->back()->withInput()->with('error', 'Şirket Bilgileri Güncellenemedi.');
    }

    /**
     * @return \CodeIgniter\HTTP\RedirectResponse
     */
    public function socialMediaPost()
    {
        $valData = (['socialNetwork' => ['label' => 'Sosyal medya adı veya linki boş bırakılamaz', 'rules' => 'required']]);
        $error = [];
        $socialNetwork = $this->request->getPost('socialNetwork');
        foreach ($socialNetwork as $key => $item) {
            $socialNetwork[$key]['link'] = trim($item['link']);
            $socialNetwork[$key]['smName'] = strtolower(trim($item['smName']));
            if (filter_var($item['link'], FILTER_VALIDATE_URL) === false) {
                $error['link'] = 'Sosyal Medya Linki URL olmalıdır !';
                unset($socialNetwork[$key]);
            }
            if (!empty($error)) return redirect()->back()->withInput()->with('errors', $error);
            if (!is_string($item['smName'])) {
                $error['snName'] = 'Sosyal Medya Adı yazı değeri olmalıdır !';
                unset($socialNetwork[$key]);
            }
            if (empty($item['link']) || empty($item['smName'])) {
                $error = ['Sosyal Medya Adı boş bırakılamaz !'];
                unset($socialNetwork[$key]);
            }
        }

        if (!empty($error)) return redirect()->back()->withInput()->with('errors', $error);
        if ($this->validate($valData) == false) return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        $result = $this->commonModel->edit('settings', ['content' => json_encode($socialNetwork, JSON_UNESCAPED_UNICODE)], ['option' => 'socialNetwork']);
        cache()->delete('settings');
        if ((bool)$result === false) return redirect()->back()->withInput()->with('error', 'Şirket Sosyal Medya Bilgileri Güncellenemedi.');
        else return redirect()->back()->with('message', 'Şirket Sosyal Medya Bilgileri Güncellendi.');
    }

    /**
     * @return \CodeIgniter\HTTP\RedirectResponse
     */
    public function mailSettingsPost()
    {
        $valData = [
            'mServer' => ['label' => 'Mail Server', 'rules' => 'required'],
            'mPort' => ['label' => 'Mail Port', 'rules' => 'required|is_natural_no_zero'],
            'mAddress' => ['label' => 'Mail Adresi', 'rules' => 'required|valid_email'],
            'mPwd' => ['label' => 'Mail Şifresi', 'rules' => 'required']
        ];

        if ($this->validate($valData) == false) return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());

        $data = [
            'server' => $this->request->getPost('mServer'),
            'port' => $this->request->getPost('mPort'),
            'address' => $this->request->getPost('mAddress'),
            'password' => base64_encode($this->encrypter->encrypt($this->request->getPost('mPwd'))),
            'protocol' => $this->request->getPost('mProtocol'),
            'tls' => false
        ];
        if ($this->request->getPost('mTls')) $data['tls'] = true;
        cache()->delete('settings');
        $result = $this->commonModel->edit('settings', ['content' => json_encode($data)], ['option' => 'mail']);
        if ((bool)$result === false) return redirect()->back()->withInput()->with('error', 'Mail Bilgileri Güncellenemedi.');
        else return redirect()->back()->with('message', 'Mail Bilgileri Güncellendi.');
    }

    public function testMail()
    {
        if ($this->request->isAJAX()) {
            $commonLibrary = new \App\Libraries\CommonLibrary();
            $mailResult = $commonLibrary->phpMailer(
                'noreply@' . $_SERVER['HTTP_HOST'],
                'noreply@' . $_SERVER['HTTP_HOST'],
                [['mail' => $this->request->getPost('testemail')]],
                'noreply@' . $_SERVER['HTTP_HOST'],
                'Information',
                'Test Mail',
                'Mail working correctly.',
            );
            if ($mailResult === true) return $this->response->setJSON(['result' => true, 'message' => 'Test e-mail başarıyla gönderildi.']);
            else return $this->response->setJSON(['result' => false, 'message' => $mailResult]);
        }
    }

    /**
     * @return \CodeIgniter\HTTP\RedirectResponse
     */
    public function loginSettingsPost()
    {
        $valData = [
            'lockedRecord' => ['label' => 'Kilitleme Sayısı', 'rules' => 'required|is_natural_no_zero|less_than[10]|greater_than[1]'],
            'lockedMin' => ['label' => 'Engellme Süresi', 'rules' => 'required|is_natural_no_zero|less_than[180]|greater_than[10]'],
            'lockedTry' => ['label' => 'Deneme Sayısı', 'rules' => 'required|is_natural_no_zero|less_than[20]|greater_than[2]'],
            'blackListRange' => ['label' => 'IP Aralığını Blokla', 'rules' => 'max_length[1000]|ipRangeControl'],
            'blacklistLine' => ['label' => 'Tekil Ip Bloklama', 'rules' => 'max_length[1000]'],
            'whitelistRange' => ['label' => 'Güvenilir IP Aralığını', 'rules' => 'max_length[1000]'],
            'whitelistLine' => ['label' => 'Güvenilir Tekil Ip', 'rules' => 'max_length[1000]'],
        ];

        if ($this->validate($valData) == false) return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        $data = [
            'record' => $this->request->getPost('lockedRecord'),
            'min' => $this->request->getPost('lockedMin'),
            'try' => $this->request->getPost('lockedTry'),
            'isActive' => ($this->request->getPost('lockedIsActive') == 'on') ? true : false,
            'userNotification' => ($this->request->getPost('lockedUserNotification') == 'on') ? true : false,
            'adminNotification' => ($this->request->getPost('lockedAdminNotification') == 'on') ? true : false,
        ];
        $result = $this->commonModel->edit('settings', ['content' => $data], ['option' => 'locked']);
        if ($this->request->getPost('blackListRange')) {
            $blackListRange = clearFilter(explode(',', preg_replace('/\s+/', '', $this->request->getPost('blackListRange'))));
            $blacklistLine = clearFilter(explode(',', preg_replace('/\s+/', '', $this->request->getPost('blacklistLine'))));
            $blacklistUsername = clearFilter(explode(',', preg_replace('/\s+/', '', $this->request->getPost('blacklistUsername'))));
            $blacklist_data = array(
                'username' => $blacklistUsername,
                'range' => $blackListRange,
                'line' => $blacklistLine,
            );
            $login_rules = $this->commonModel->selectOne('login_rules', ['type' => 'blacklist']);
            $result = $this->commonModel->edit('login_rules', $blacklist_data, ['id' => $login_rules->id]);
        }
        if ($this->request->getPost('whitelistRange')) {
            $whitelistRange = clearFilter(explode(',', preg_replace('/\s+/', '', $this->request->getPost('whitelistRange'))));
            $whitelistLine = clearFilter(explode(',', preg_replace('/\s+/', '', $this->request->getPost('whitelistLine'))));
            $whitelistUsername = clearFilter(explode(',', preg_replace('/\s+/', '', $this->request->getPost('whitelistUsername'))));
            $whitelist = array(
                'username' => $whitelistUsername,
                'range' => $whitelistRange,
                'line' => $whitelistLine,
            );
            $login_rules = $this->commonModel->selectOne('login_rules', ['type' => 'whitelist']);
            $result = $this->commonModel->edit('login_rules', $whitelist, ['id' => $login_rules->id]);
        }
        if ((bool)$result === false) return redirect()->back()->withInput()->with('error', 'Giriş Ayarları Bilgileri Güncellenemedi.');
        else {
            cache()->delete('settings');
            return redirect()->back()->with('message', 'Giriş Ayarları Bilgileri Güncellendi.');
        }
    }

    /**
     * @return \CodeIgniter\HTTP\RedirectResponse|\CodeIgniter\HTTP\ResponseInterface|void
     */
    public function templateSelectPost()
    {
        if ($this->request->isAJAX()) {
            $valData = ([
                'path' => ['label' => 'path', 'rules' => 'required'],
                'tName' => ['label' => 'tName', 'rules' => 'required']
            ]);
            if ($this->validate($valData) == false) return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
            if ($this->commonModel->edit(
                'settings',
                ['content' => json_encode([
                    'path' => $this->request->getPost('path'),
                    'name' => $this->request->getPost('name')
                ], JSON_UNESCAPED_UNICODE)],
                ['option' => 'templateInfos']
            )) {
                cache()->delete('settings');
                return $this->response->setJSON(['result' => true]);
            } else return $this->response->setJSON(['result' => false]);
        } else return $this->failForbidden();
    }

    /**
     * @return \CodeIgniter\HTTP\RedirectResponse
     */
    public function saveAllowedFiles()
    {
        $valData = ([
            'allowedFiles' => ['label' => 'Dosya Türleri', 'rules' => 'required'],
        ]);
        if ($this->validate($valData) == false) return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        $data = explode(',', $this->request->getPost('allowedFiles'));
        if ($this->commonModel->edit('settings', ['content' => json_encode($data, JSON_UNESCAPED_UNICODE)], ['option' => 'allowedFiles'])) {
            cache()->delete('settings');
            return redirect()->back()->with('message', 'Dosya Türleri Güncellendi.');
        } else return redirect()->back()->withInput()->with('error', 'Dosya Türleri Güncellenemedi.');
    }

    /**
     * @return string
     */
    public function templateSettings()
    {
        return view('templates/' . $this->defData['settings']->templateInfos->path . '/temp-settings', $this->defData);
    }

    /**
     * @return \CodeIgniter\HTTP\RedirectResponse
     */
    public function templateSettings_post()
    {
        $valData = (['settings' => ['label' => 'widgets', 'rules' => 'required']]);
        if ($this->validate($valData) == false) return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        $data = array_merge((array)$this->defData['settings']->templateInfos, $this->request->getPost('settings'));
        if ($this->commonModel->edit('settings', ['content' => json_encode($data, JSON_UNESCAPED_UNICODE)], ['option' => 'templateInfos'])) {
            cache()->delete('settings');
            return redirect()->back()->with('success', 'Tema Ayarları kayıt edildi.');
        } else return redirect()->back()->with('error', 'Tema Ayarları kayıt edilemedi');
    }

    public function elfinderConvertWebp()
    {
        if ($this->request->isAJAX()) {
            $valData = ([
                'isActive' => ['label' => 'isActive', 'rules' => 'required']
            ]);
            if ($this->validate($valData) == false) return redirect('403');
            if ($this->commonModel->edit('settings', ['content' => (int)$this->request->getPost('isActive')], ['option' => 'elfinderConvertWebp'])){
                cache()->delete('settings');
                return $this->respond(['result' => (bool)$this->request->getPost('isActive')], 200);
            } else
                return $this->fail(['pr' => false]);
        } else return $this->failForbidden();
    }
}

<?php namespace Modules\Backend\Controllers;

use Config\App;
use Config\Mimes;
use Config\Paths;
use MongoDB\BSON\ObjectId;

class Settings extends BaseController
{
    /**
     * @return string
     */
    public function index()
    {
        $this->defData['request'] = $this->request;
        $blacklists = $this->commonModel->getOne('login_rules', ['type' => 'blacklist']);
        $whitelists = $this->commonModel->getOne('login_rules', ['type' => 'whitelist']);

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
        return view('Modules\Backend\Views\settings', $this->defData);
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

        $data = ['siteName' => $this->request->getPost('cName'),
            'siteURL' => $this->request->getPost('cUrl'),
            'companyAddress' => $this->request->getPost('cAddress'),
            'companyPhone' => $this->request->getPost('cPhone'),
            'companyEMail' => $this->request->getPost('cMail')
        ];
        if (!empty($this->request->getPost('cSlogan'))) $data['slogan'] = $this->request->getPost('cSlogan');
        if (!empty($this->request->getPost('cGSM'))) $data['companyGSM'] = $this->request->getPost('cGSM');
        if (!empty($this->request->getPost('cMap'))) $data['map_iframe'] = $this->request->getPost('cMap');
        if (!empty($this->request->getPost('cLogo'))) $data['logo'] = $this->request->getPost('cLogo');

        $settings = $this->commonModel->getOne('settings');
        if ($this->commonModel->updateOne('settings', ['_id' => new ObjectId($settings->_id)], $data)) return redirect()->back()->with('message', 'Şirket Bilgileri Güncellendi.');
        else return redirect()->back()->withInput()->with('error', 'Şirket Bilgileri Güncellenemedi.');
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

        $settings = $this->commonModel->getOne('settings');
        $result = $this->commonModel->updateOne('settings', ['_id' => new ObjectId($settings->_id)], ['socialNetwork' => $socialNetwork]);

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

        if ($this->validate($valData) == false)
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());

        $data = ['mailServer' => $this->request->getPost('mServer'),
            'mailPort' => $this->request->getPost('mPort'),
            'mailAddress' => $this->request->getPost('mAddress'),
            'mailPassword' => $this->request->getPost('mPwd'),
            'mailProtocol' => $this->request->getPost('mProtocol'),
            'mailTLS' => false];
        if ($this->request->getPost('mTls'))
            $data['mailTLS'] = true;
        $settings = $this->commonModel->getOne('settings');
        $result = $this->commonModel->updateOne('settings', ['_id' => new ObjectId($settings->_id)], $data);
        if ((bool)$result === false)
            return redirect()->back()->withInput()->with('error', 'Şirket Sosyal Medya Bilgileri Güncellenemedi.');
        else
            return redirect()->back()->with('message', 'Şirket Sosyal Medya Bilgileri Güncellendi.');
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
        $blackListRange = clearFilter(explode(',', preg_replace('/\s+/', '', $this->request->getPost('blackListRange'))));
        $blacklistLine = clearFilter(explode(',', preg_replace('/\s+/', '', $this->request->getPost('blacklistLine'))));
        $blacklistUsername = clearFilter(explode(',', preg_replace('/\s+/', '', $this->request->getPost('blacklistUsername'))));
        $whitelistRange = clearFilter(explode(',', preg_replace('/\s+/', '', $this->request->getPost('whitelistRange'))));
        $whitelistLine = clearFilter(explode(',', preg_replace('/\s+/', '', $this->request->getPost('whitelistLine'))));
        $whitelistUsername = clearFilter(explode(',', preg_replace('/\s+/', '', $this->request->getPost('whitelistUsername'))));

        if ($this->validate($valData) == false)
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());

        $data = [
            'lockedRecord' => $this->request->getPost('lockedRecord'),
            'lockedMin' => $this->request->getPost('lockedMin'),
            'lockedTry' => $this->request->getPost('lockedTry'),
            'lockedIsActive' => ($this->request->getPost('lockedIsActive') == 'on') ? true : false,
            'lockedUserNotification' => ($this->request->getPost('lockedUserNotification') == 'on') ? true : false,
            'lockedAdminNotification' => ($this->request->getPost('lockedAdminNotification') == 'on') ? true : false,
        ];
        $settings = $this->commonModel->getOne('settings');
        $result = $this->commonModel->updateOne('settings', ['_id' => new ObjectId($settings->_id)], $data);
        $blacklist_data = array(
            'username' => $blacklistUsername,
            'range' => $blackListRange,
            'line' => $blacklistLine,
        );
        $login_rules = $this->commonModel->getOne('login_rules', ['type' => 'blacklist']);
        $result = $this->commonModel->updateOne('login_rules', ['_id' => new ObjectId($login_rules->_id)], $blacklist_data);
        $whitelist = array(
            'username' => $whitelistUsername,
            'range' => $whitelistRange,
            'line' => $whitelistLine,
        );
        $login_rules = $this->commonModel->getOne('login_rules', ['type' => 'whitelist']);
        $result = $this->commonModel->updateOne('login_rules', ['_id' => new ObjectId($login_rules->_id)], $whitelist);

        if ((bool)$result === false)
            return redirect()->back()->withInput()->with('error', 'Giriş Ayarları Bilgileri Güncellenemedi.');
        else
            return redirect()->back()->with('message', 'Giriş Ayarları Bilgileri Güncellendi.');
    }

    /**
     * @return \CodeIgniter\HTTP\RedirectResponse|\CodeIgniter\HTTP\ResponseInterface|void
     */
    public function templateSelectPost()
    {
        if ($this->request->isAJAX()) {
            $valData = ([
                'path' => ['label' => 'path', 'rules' => 'required'],
                'tName' => ['label' => 'tName', 'rules' => 'required'],
            ]);
            if ($this->validate($valData) == false) return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
            if ($this->commonModel->updateOne('settings', ['_id' => $this->defData['settings']->_id], ['templateInfos' => ['path' => $this->request->getPost('path'), 'name' => $this->request->getPost('name')]])) return $this->response->setJSON(['result' => true]);
            else return $this->response->setJSON(['result' => false]);
        } else redirect()->route('403');
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
        if ($this->commonModel->updateOne('settings', [], ['allowedFiles' => $data])) return redirect()->back()->with('message', 'Dosya Türleri Güncellendi.');
        else return redirect()->back()->withInput()->with('error', 'Dosya Türleri Güncellenemedi.');
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
        if ($this->commonModel->updateMany('settings', [], ['templateInfos' => $data])) return redirect()->back()->with('success', 'Tema Ayarları kayıt edildi.');
        else return redirect()->back()->with('error', 'Tema Ayarları kayıt edilemedi');
    }
}

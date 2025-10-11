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
            'cName' => ['label' => lang('Settings.companyName'), 'rules' => 'required'],
            'cUrl' => ['label' => lang('Settings.websiteUrl'), 'rules' => 'required|valid_url'],
            'cAddress' => ['label' => lang('Settings.companyAddress'), 'rules' => 'required'],
            'cPhone' => ['label' => lang('Settings.companyPhone'), 'rules' => 'required'],
            'cMail' => ['label' => lang('Settings.companyEmail'), 'rules' => 'required|valid_email'],
        ]);

        if (!empty($this->request->getPost('cSlogan'))) $valData['cSlogan'] = ['label' => lang('Settings.companySlogan'), 'rules' => 'required'];
        if (!empty($this->request->getPost('cGSM'))) $valData['cGSM'] = ['label' => lang('Settings.companyGsm'), 'rules' => 'required'];
        if (!empty($this->request->getPost('cMap'))) $valData['cMap'] = ['label' => lang('Settings.gmapIframe'), 'rules' => 'required'];
        if (!empty($this->request->getPost('cLogo'))) $valData['cLogo'] = ['label' => lang('Settings.companyLogo'), 'rules' => 'required'];

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
            return redirect()->back()->with('message', lang('Backend.updated',[lang('Settings.companyInfos')]));
        } else return redirect()->back()->withInput()->with('error', lang('Backend.notUpdated',[lang('Settings.companyInfos')]));
    }

    /**
     * @return \CodeIgniter\HTTP\RedirectResponse
     */
    public function socialMediaPost()
    {
        $valData = (['socialNetwork' => ['label' => lang('Settings.socialMediaNameOrLinkRequired'), 'rules' => 'required']]);
        $error = [];
        $socialNetwork = $this->request->getPost('socialNetwork');
        foreach ($socialNetwork as $key => $item) {
            $socialNetwork[$key]['link'] = trim($item['link']);
            $socialNetwork[$key]['smName'] = strtolower(trim($item['smName']));
            if (filter_var($item['link'], FILTER_VALIDATE_URL) === false) {
                $error['link'] = lang('Settings.socialMediaLinkMustBeUrl');
                unset($socialNetwork[$key]);
            }
            if (!empty($error)) return redirect()->back()->withInput()->with('errors', $error);
            if (!is_string($item['smName'])) {
                $error['snName'] = lang('Settings.socialMediaNameMustBeText');
                unset($socialNetwork[$key]);
            }
            if (empty($item['link']) || empty($item['smName'])) {
                $error = [lang('Settings.socialMediaNameRequired')];
                unset($socialNetwork[$key]);
            }
        }

        if (!empty($error)) return redirect()->back()->withInput()->with('errors', $error);
        if ($this->validate($valData) == false) return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        $result = $this->commonModel->edit('settings', ['content' => json_encode($socialNetwork, JSON_UNESCAPED_UNICODE)], ['option' => 'socialNetwork']);
        cache()->delete('settings');
        if ((bool)$result === false) return redirect()->back()->withInput()->with('error', lang('Backend.updated',[lang('Settings.socialMedia')]));
        else return redirect()->back()->with('message', lang('Backend.notUpdated',[lang('Settings.socialMedia')]));
    }

    /**
     * @return \CodeIgniter\HTTP\RedirectResponse
     */
    public function mailSettingsPost()
    {
        $valData = [
            'mServer' => ['label' => 'Mail Server', 'rules' => 'required'],
            'mPort' => ['label' => 'Mail Port', 'rules' => 'required|is_natural_no_zero'],
            'mAddress' => ['label' => lang('Settings.mailAddress'), 'rules' => 'required|valid_email'],
            'mPwd' => ['label' => lang('Settings.mailPassword'), 'rules' => 'required']
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
        if ((bool)$result === false) return redirect()->back()->withInput()->with('error', lang('Backend.updated',[lang('Settings.mailSettings')]));
        else return redirect()->back()->with('message', lang('Backend.notUpdated',[lang('Settings.mailSettings')]));
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
            if ($mailResult === true) return $this->response->setJSON(['result' => true, 'message' => lang('Settings.testEmailSent')]);
            else return $this->response->setJSON(['result' => false, 'message' => $mailResult]);
        }
    }

    /**
     * @return \CodeIgniter\HTTP\RedirectResponse
     */
    public function loginSettingsPost()
    {
        $valData = [
            'lockedRecord' => ['label' => lang('Settings.lockingCounter'), 'rules' => 'required|is_natural_no_zero|less_than[10]|greater_than[1]'],
            'lockedMin' => ['label' => lang('Settings.blockedTime'), 'rules' => 'required|is_natural_no_zero|less_than[180]|greater_than[10]'],
            'lockedTry' => ['label' => lang('Settings.tryCounter'), 'rules' => 'required|is_natural_no_zero|less_than[20]|greater_than[2]'],
            'blackListRange' => ['label' => lang('Settings.blockIps'), 'rules' => 'max_length[1000]|ipRangeControl'],
            'blacklistLine' => ['label' => lang('Settings.blockIp'), 'rules' => 'max_length[1000]'],
            'whitelistRange' => ['label' => lang('Settings.trustedIps'), 'rules' => 'max_length[1000]'],
            'whitelistLine' => ['label' => lang('Settings.trustedIp'), 'rules' => 'max_length[1000]'],
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
        if ((bool)$result === false) return redirect()->back()->withInput()->with('error', lang('Backend.updated',[lang('Settings.lockedSettings')]));
        else {
            cache()->delete('settings');
            return redirect()->back()->with('message', lang('Backend.notUpdated',[lang('Settings.lockedSettings')]));
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
            'allowedFiles' => ['label' => lang('Settings.fileTypes'), 'rules' => 'required'],
        ]);
        if ($this->validate($valData) == false) return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        $data = explode(',', $this->request->getPost('allowedFiles'));
        if ($this->commonModel->edit('settings', ['content' => json_encode($data, JSON_UNESCAPED_UNICODE)], ['option' => 'allowedFiles'])) {
            cache()->delete('settings');
            return redirect()->back()->with('message', lang('Backend.updated',[lang('Settings.fileTypes')]));
        } else return redirect()->back()->withInput()->with('error', lang('Backend.notUpdated',[lang('Settings.fileTypes')]));
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
            return redirect()->back()->with('success', lang('Backend.updated',[lang('Settings.templateSettings')]));
        } else return redirect()->back()->with('error', lang('Backend.notUpdated',[lang('Settings.templateSettings')]));
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

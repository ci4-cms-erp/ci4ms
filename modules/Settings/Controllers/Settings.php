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
        $this->defData['mimes'] = Mimes::$mimes;
        return view('Modules\Settings\Views\settings', $this->defData);
    }

    /**
     * @return \CodeIgniter\HTTP\RedirectResponse
     */
    public function compInfosPost()
    {
        $valData = ([
            'cName' => ['label' => lang('Settings.companyName'), 'rules' => 'required|regex_match[/^[^<>{}]*$/u]'],
            'cAddress' => ['label' => lang('Settings.companyAddress'), 'rules' => 'required|regex_match[/^[^<>{}]*$/u]'],
            'cPhone' => ['label' => lang('Settings.companyPhone'), 'rules' => 'required|regex_match[/^[\d\s\+\-\(\)]{7,20}$/]'],
            'cMail' => ['label' => lang('Settings.companyEmail'), 'rules' => 'required|valid_email'],
        ]);

        if (!empty($this->request->getPost('cSlogan'))) $valData['cSlogan'] = ['label' => lang('Settings.companySlogan'), 'rules' => 'required'];
        if (!empty($this->request->getPost('cGSM'))) $valData['cGSM'] = ['label' => lang('Settings.companyGsm'), 'rules' => 'required'];
        if (!empty($this->request->getPost('cMap'))) $valData['cMap'] = ['label' => lang('Settings.gmapIframe'), 'rules' => 'required'];
        if (!empty($this->request->getPost('cLogo'))) $valData['cLogo'] = ['label' => lang('Settings.companyLogo'), 'rules' => 'required'];

        if ($this->validate($valData) == false) return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());

        try {
            setting()->set('App.siteName', esc(trim(strip_tags($this->request->getPost('cName')))));

            $data = [
                'address' => esc(trim(strip_tags($this->request->getPost('cAddress')))),
                'phone' => esc(trim(strip_tags($this->request->getPost('cPhone')))),
                'email' => esc(trim(strip_tags($this->request->getPost('cMail'))))
            ];
            if (!empty($this->request->getPost('cSlogan'))) setting()->set('App.slogan', esc(trim(strip_tags($this->request->getPost('cSlogan')))));
            if (!empty($this->request->getPost('cGSM'))) $data['gsm'] = esc(trim(strip_tags($this->request->getPost('cGSM'))));
            if (!empty($this->request->getPost('cMap'))) setting()->set('Gmap.map_iframe', trim(strip_tags($this->request->getPost('cMap'), '<iframe>')));
            if (!empty($this->request->getPost('cLogo'))) setting()->set('App.logo', esc(trim(strip_tags($this->request->getPost('cLogo')))));

            setting()->set('App.contact', json_encode($data, JSON_UNESCAPED_UNICODE));
            cache()->delete('settings');
            return redirect()->back()->with('message', lang('Backend.updated', [lang('Settings.companyInfos')]));
        } catch (\Exception $e) {
            return redirect()->back()->withInput()->with('error', lang('Backend.notUpdated', [lang('Settings.companyInfos')]));
        }
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
            $socialNetwork[$key]['link'] = trim(strip_tags($item['link']));
            $socialNetwork[$key]['smName'] = strtolower(esc(trim(strip_tags($item['smName']))));
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
        try {
            setting()->set('App.socialNetwork', json_encode($socialNetwork, JSON_UNESCAPED_UNICODE));
            cache()->delete('settings');
            return redirect()->back()->withInput()->with('message', lang('Backend.updated', [lang('Settings.socialMedia')]));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', lang('Backend.notUpdated', [lang('Settings.socialMedia')]));
        }
    }

    /**
     * @return \CodeIgniter\HTTP\RedirectResponse
     */
    public function mailSettingsPost()
    {
        $valData = [
            'mServer' => ['label' => 'Mail Server', 'rules' => 'required|alpha_dash'],
            'mPort' => ['label' => 'Mail Port', 'rules' => 'required|is_natural_no_zero'],
            'mAddress' => ['label' => lang('Settings.mailAddress'), 'rules' => 'required|valid_email'],
            'mPwd' => ['label' => lang('Settings.mailPassword'), 'rules' => 'required']
        ];
        if (!empty($this->request->getPost('mPwd'))) $valData['mPwd'] = ['label' => lang('Settings.mailPassword'), 'rules' => 'required|min_length[8]'];
        if ($this->validate($valData) == false) return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        try {
            $data = [
                'server' => trim(strip_tags($this->request->getPost('mServer'))),
                'port' => trim(strip_tags($this->request->getPost('mPort'))),
                'address' => trim(strip_tags($this->request->getPost('mAddress'))),
                'password' => base64_encode($this->encrypter->encrypt(trim($this->request->getPost('mPwd')))),
                'protocol' => trim(strip_tags($this->request->getPost('mProtocol'))),
                'tls' => false
            ];
            if ($this->request->getPost('mTls')) $data['tls'] = true;
            setting()->set('App.mail', json_encode($data));
            cache()->delete('settings');
            return redirect()->back()->withInput()->with('message', lang('Backend.updated', [lang('Settings.mailSettings')]));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', lang('Backend.notUpdated', [lang('Settings.mailSettings')]));
        }
    }

    public function testMail()
    {
        if ($this->request->isAJAX()) {
            try {
                $email = service('email');

                $email->setFrom('noreply@' . $_SERVER['HTTP_HOST'], 'noreply@' . $_SERVER['HTTP_HOST']);
                $email->setTo($this->request->getPost('testemail'));

                $email->setSubject('Test Mail');
                $email->setMessage('Mail working correctly.');

                $email->send();
                return $this->response->setJSON(['result' => true, 'message' => lang('Settings.testEmailSent')]);
            } catch (\Exception $e) {
                return $this->response->setJSON(['result' => false, 'message' => $e->getMessage()]);
            }
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
            try {
                setting()->set('App.templateInfos', json_encode([
                    'path' => esc($this->request->getPost('path')),
                    'name' => esc($this->request->getPost('name'))
                ], JSON_UNESCAPED_UNICODE));
                cache()->delete('settings');
                return $this->response->setJSON(['result' => true]);
            } catch (\Exception $e) {
                return $this->response->setJSON(['result' => false]);
            }
        } else return $this->failForbidden();
    }

    /**
     * @return \CodeIgniter\HTTP\RedirectResponse
     */
    public function saveAllowedFiles()
    {
        $valData = ([
            'allowedFiles' => ['label' => lang('Settings.fileTypes'), 'rules' => 'required|alpha_numeric'],
        ]);
        if ($this->validate($valData) == false) return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        try {
            $data = explode(',', $this->request->getPost('allowedFiles'));
            setting()->set('Security.allowedFiles', json_encode($data, JSON_UNESCAPED_UNICODE));
            cache()->delete('settings');
            return redirect()->back()->with('message', lang('Backend.updated', [lang('Settings.fileTypes')]));
        } catch (\Exception $e) {
            return redirect()->back()->withInput()->with('error', lang('Backend.notUpdated', [lang('Settings.fileTypes')]));
        }
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
        try {

            $current = (array)$this->defData['settings']->templateInfos;
            $protectedKeys = ['path', 'name'];
            $postSettings = array_diff_key($this->request->getPost('settings'), array_flip($protectedKeys));
            $data = array_merge($current, $postSettings);
            setting()->set('App.templateInfos', json_encode($data, JSON_UNESCAPED_UNICODE));

            cache()->delete('settings');
            return redirect()->back()->with('success', lang('Backend.updated', [lang('Settings.templateSettings')]));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', lang('Backend.notUpdated', [lang('Settings.templateSettings')]));
        }
    }

    public function elfinderConvertWebp()
    {
        if ($this->request->isAJAX()) {
            $valData = ([
                'isActive' => ['label' => 'isActive', 'rules' => 'required|in_list[0,1]']
            ]);
            if ($this->validate($valData) == false) return redirect('403');
            try {
                setting()->set('Elfinder.convertWebp', (bool)$this->request->getPost('isActive'));
                cache()->delete('settings');
                return $this->respond(['result' => (bool)$this->request->getPost('isActive')], 200);
            } catch (\Exception $e) {
                return $this->fail(['pr' => false]);
            }
        } else return $this->failForbidden();
    }
}

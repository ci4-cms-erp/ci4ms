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

        if (!empty($this->request->getPost('cSlogan'))) $valData['cSlogan'] = ['label' => lang('Settings.companySlogan'), 'rules' => 'required|regex_match[/^[^<>{}]*$/u]'];
        if (!empty($this->request->getPost('cGSM'))) $valData['cGSM'] = ['label' => lang('Settings.companyGsm'), 'rules' => 'required|regex_match[/^[\d\s\+\-\(\)]{7,25}$/]'];
        if (!empty($this->request->getPost('cMap'))) $valData['cMap'] = ['label' => lang('Settings.gmapIframe'), 'rules' => 'required|max_length[2000]'];
        if (!empty($this->request->getPost('cLogo'))) $valData['cLogo'] = ['label' => lang('Settings.companyLogo'), 'rules' => 'required|regex_match[/^[^<>{}=]*$/u]'];

        if ($this->validate($valData) == false) return redirect()->route('settings')->withInput()->with('errors', $this->validator->getErrors());

        try {
            setting()->set('App.siteName', esc(trim(strip_tags($this->request->getPost('cName')))));

            $data = [
                'address' => esc(trim(strip_tags($this->request->getPost('cAddress')))),
                'phone' => esc(trim(strip_tags($this->request->getPost('cPhone')))),
                'email' => esc(trim(strip_tags($this->request->getPost('cMail'))))
            ];
            if (!empty($this->request->getPost('cSlogan'))) setting()->set('App.slogan', esc(trim(strip_tags($this->request->getPost('cSlogan')))));
            if (!empty($this->request->getPost('cGSM'))) $data['gsm'] = esc(trim(strip_tags($this->request->getPost('cGSM'))));
            if (!empty($this->request->getPost('cMap'))) {
                $mapValue = trim(strip_tags($this->request->getPost('cMap'), '<iframe>'));
                // Strip all attributes except safe ones for iframes
                $mapValue = preg_replace_callback(
                    '/<iframe\s+([^>]*)>/i',
                    function ($matches) {
                        $allowedAttrs = ['src', 'width', 'height', 'frameborder', 'style', 'allowfullscreen', 'loading', 'title'];
                        preg_match_all('/(\w+)\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|(\S+))/i', $matches[1], $attrs, PREG_SET_ORDER);
                        $safe = '';
                        foreach ($attrs as $attr) {
                            $name = strtolower($attr[1]);
                            $value = $attr[2] ?: $attr[3] ?: $attr[4];
                            if (in_array($name, $allowedAttrs, true)) {
                                // For src, only allow https URLs (block javascript: etc.)
                                if ($name === 'src' && !preg_match('#^https://#i', $value)) {
                                    continue;
                                }
                                $safe .= ' ' . $name . '="' . esc($value) . '"';
                            }
                        }
                        return '<iframe' . $safe . '>';
                    },
                    $mapValue
                );
                setting()->set('Gmap.map_iframe', $mapValue);
            }
            if (!empty($this->request->getPost('cLogo'))) setting()->set('App.logo', esc(trim(strip_tags($this->request->getPost('cLogo')))));

            setting()->set('App.contact', json_encode($data, JSON_UNESCAPED_UNICODE));
            cache()->delete('settings');
            return redirect()->route('settings')->with('message', lang('Backend.updated', [lang('Settings.companyInfos')]));
        } catch (\Exception $e) {
            return redirect()->route('settings')->withInput()->with('error', lang('Backend.notUpdated', [lang('Settings.companyInfos')]));
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
            if (!empty($error)) return redirect()->route('settings')->withInput()->with('errors', $error);
            if (!is_string($item['smName'])) {
                $error['snName'] = lang('Settings.socialMediaNameMustBeText');
                unset($socialNetwork[$key]);
            }
            $item['smName'] = strip_tags(trim($item['smName']));
            if (empty($item['link']) || empty($item['smName'])) {
                $error = [lang('Settings.socialMediaNameRequired')];
                unset($socialNetwork[$key]);
            }
        }

        if (!empty($error)) return redirect()->route('settings')->withInput()->with('errors', $error);
        if ($this->validate($valData) == false) return redirect()->route('settings')->withInput()->with('errors', $this->validator->getErrors());
        try {
            setting()->set('App.socialNetwork', json_encode($socialNetwork, JSON_UNESCAPED_UNICODE));
            cache()->delete('settings');
            return redirect()->route('settings')->withInput()->with('message', lang('Backend.updated', [lang('Settings.socialMedia')]));
        } catch (\Exception $e) {
            return redirect()->route('settings')->withInput()->with('error', lang('Backend.notUpdated', [lang('Settings.socialMedia')]));
        }
    }

    /**
     * @return \CodeIgniter\HTTP\RedirectResponse
     */
    public function mailSettingsPost()
    {
        $valData = [
            'mServer' => ['label' => lang('Settings.mailServer'), 'rules' => 'required|alpha_dash'],
            'mPort' => ['label' => lang('Settings.mailPort'), 'rules' => 'required|is_natural_no_zero'],
            'mAddress' => ['label' => lang('Settings.mailAddress'), 'rules' => 'required|valid_email'],
            'mPwd' => ['label' => lang('Settings.mailPassword'), 'rules' => 'required']
        ];
        if (!empty($this->request->getPost('mPwd'))) $valData['mPwd'] = ['label' => lang('Settings.mailPassword'), 'rules' => 'required|min_length[8]'];
        if ($this->validate($valData) == false) return redirect()->route('settings')->withInput()->with('errors', $this->validator->getErrors());
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
            return redirect()->route('settings')->withInput()->with('message', lang('Backend.updated', [lang('Settings.mailSettings')]));
        } catch (\Exception $e) {
            return redirect()->route('settings')->withInput()->with('error', lang('Backend.notUpdated', [lang('Settings.mailSettings')]));
        }
    }

    public function testMail()
    {
        if ($this->request->isAJAX()) {
            try {
                $email = service('email');

                $email->setFrom('noreply@' . $_SERVER['HTTP_HOST'], 'noreply@' . $_SERVER['HTTP_HOST']);
                $email->setTo($this->request->getPost('testemail'));

                $email->setSubject(lang('Settings.testMailSubject'));
                $email->setMessage(lang('Settings.testMailMessage'));

                $email->send();
                return $this->respond(['result' => true, 'message' => lang('Settings.testEmailSent')]);
            } catch (\Exception $e) {
                return $this->respond(['result' => false, 'message' => $e->getMessage()], 500);
            }
        }
    }


    /**
     * @return \CodeIgniter\HTTP\RedirectResponse|\CodeIgniter\HTTP\ResponseInterface|void
     */
    public function templateSelectPost()
    {
        if (!$this->request->isAJAX()) return $this->failForbidden();
        $valData = ([
            'path' => ['label' => lang('Backend.path'), 'rules' => 'required'],
            'tName' => ['label' => lang('Backend.name'), 'rules' => 'required']
        ]);
        if ($this->validate($valData) == false) return $this->respond(['status' => 'error', 'errors' => $this->validator->getErrors()], 422);
        try {
            $themeName = esc($this->request->getPost('path'));

            // RUN AUTO MIGRATION WHEN ACTIVATED
            $migrate = \Config\Services::migrations();
            $migrate->setNamespace('App');
            try {
                $migrate->latest('templates/' . $themeName);
            } catch (\Exception $e) {
                // If no migration exists, it's fine, skip
            }

            setting()->set('App.templateInfos', json_encode([
                'path' => $themeName,
                'name' => esc($this->request->getPost('name'))
            ], JSON_UNESCAPED_UNICODE));
            cache()->delete('settings');
            return $this->respond(['result' => true]);
        } catch (\Exception $e) {
            return $this->respond(['result' => false], 500);
        }
    }

    /**
     * @return \CodeIgniter\HTTP\RedirectResponse
     */
    public function saveAllowedFiles()
    {
        $valData = ([
            'allowedFiles' => ['label' => lang('Settings.fileTypes'), 'rules' => 'required|alpha_numeric'],
        ]);
        if ($this->validate($valData) == false) return redirect()->route('settings')->withInput()->with('errors', $this->validator->getErrors());
        try {
            $data = explode(',', $this->request->getPost('allowedFiles'));
            setting()->set('Security.allowedFiles', json_encode($data, JSON_UNESCAPED_UNICODE));
            cache()->delete('settings');
            return redirect()->route('settings')->with('message', lang('Backend.updated', [lang('Settings.fileTypes')]));
        } catch (\Exception $e) {
            return redirect()->route('settings')->withInput()->with('error', lang('Backend.notUpdated', [lang('Settings.fileTypes')]));
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
        try {
            $postSettings = $this->request->getPost('settings') ?? [];
            $protectedKeys = ['path', 'name'];
            $postSettings = array_diff_key($postSettings, array_flip($protectedKeys));

            // Merge with existing (preserve path, name and any other untouched keys)
            $current = (array)$this->defData['settings']->templateInfos;
            $data = array_merge($current, $postSettings);

            setting()->set('App.templateInfos', json_encode($data, JSON_UNESCAPED_UNICODE));
            cache()->delete('settings');

            return redirect()->route('settings')->with('success', lang('Backend.updated', [lang('Settings.templateSettings')]));
        } catch (\Exception $e) {
            return redirect()->route('settings')->with('error', lang('Backend.notUpdated', [lang('Settings.templateSettings')]));
        }
    }

    public function elfinderConvertWebp()
    {
        if ($this->request->isAJAX()) {
            $valData = ([
                'isActive' => ['label' => lang('Backend.status'), 'rules' => 'required|in_list[0,1]']
            ]);
            if ($this->validate($valData) == false) return $this->respond(['status' => 'error', 'errors' => $this->validator->getErrors()], 422);
            try {
                setting()->set('Elfinder.convertWebp', (bool)$this->request->getPost('isActive'));
                cache()->delete('settings');
                return $this->respond(['result' => (bool)$this->request->getPost('isActive')], 200);
            } catch (\Exception $e) {
                return $this->fail(['pr' => false]);
            }
        } else return $this->failForbidden();
    }

    public function checkVersion()
    {
        if (!$this->request->isAJAX()) return $this->failForbidden();
        $client = service('curlrequest');

        try {
            $response = $client->request('GET', 'https://api.github.com/repos/ci4-cms-erp/ci4ms/tags', [
                'headers' => [
                    'User-Agent' => 'CI4ms-Auto-Updater',
                    'Accept'     => 'application/vnd.github.v3+json',
                ],
                'http_errors' => false
            ]);

            $data = json_decode($response->getBody());

            if (!empty($data) && is_array($data) && isset($data[0]->name)) {
                $latestTag = $data[0];
                $latestVersion = ltrim($latestTag->name, 'v');
                $currentVersion = env('app.version');

                if (version_compare($latestVersion, $currentVersion, '>')) {
                    // Get changed files list using Compare API
                    $compareResponse = $client->request('GET', "https://api.github.com/repos/ci4-cms-erp/ci4ms/compare/v{$currentVersion}...v{$latestVersion}", [
                        'headers' => [
                            'User-Agent' => 'CI4ms-Auto-Updater',
                            'Accept'     => 'application/vnd.github.v3+json',
                        ],
                        'http_errors' => false
                    ]);

                    $compareData = json_decode($compareResponse->getBody());
                    $changedFiles = [];
                    if (isset($compareData->files)) {
                        foreach ($compareData->files as $file) {
                            $changedFiles[] = [
                                'filename' => $file->filename,
                                'status'   => $file->status,
                                'raw_url'  => $file->raw_url
                            ];
                        }
                    }

                    return $this->respond([
                        'result'            => true,
                        'update_available'  => true,
                        'message'           => lang('Backend.updateAvailable', [$latestVersion]),
                        'new_version'       => $latestVersion,
                        'current_version'   => $currentVersion,
                        'download_url'      => $latestTag->zipball_url ?? '',
                        'changed_count'     => count($changedFiles),
                        'changed_files'     => $changedFiles,
                        'compare_url'       => "https://github.com/ci4-cms-erp/ci4ms/compare/v{$currentVersion}...v{$latestVersion}"
                    ]);
                }

                return $this->respond(['result' => true, 'message' => lang('Settings.alreadyLastVersion')]);
            }

            return $this->respond(['result' => false, 'message' => lang('Settings.noTagsFound')], 404);
        } catch (\Exception $e) {
            return $this->respond(['result' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Save site language mode (single / multi).
     *
     * @return \CodeIgniter\HTTP\ResponseInterface
     */
    public function saveLanguageMode()
    {
        if (!$this->request->isAJAX()) {
            return $this->failForbidden();
        }

        $valRules = [
            'mode' => ['label' => lang('Settings.languageMode'), 'rules' => 'required|in_list[single,multi]'],
        ];
        if ($this->validate($valRules) === false) {
            return $this->respond(['status' => 'error', 'errors' => $this->validator->getErrors()], 422);
        }

        try {
            setting()->set('App.siteLanguageMode', $this->request->getPost('mode'));
            cache()->delete('settings');
            cache()->delete('frontend_languages');
            cache()->delete('default_frontend_language');
            $langs = $this->commonModel->lists('languages');
            foreach ($langs as $lang) {
                cache()->delete('menus_' . $lang->code);
            }

            return $this->respond([
                'status'  => 'success',
                'message' => lang('Backend.updated', [lang('Settings.languageMode')]),
            ]);
        } catch (\Exception $e) {
            return $this->respond([
                'status'  => 'error',
                'message' => lang('Backend.notUpdated', [lang('Settings.languageMode')]) . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Downloads only the changed files between current and latest version as a zip.
     */
    public function downloadPatch()
    {
        $currentVersion = env('app.version');
        $latestVersion = $this->request->getPost('latest');
        
        if (empty($latestVersion)) return $this->fail(lang('Settings.newVersionRequired'));

        $client = service('curlrequest');
        $zip = new \ZipArchive();
        $zipName = "patch-v{$currentVersion}-to-v{$latestVersion}.zip";
        $zipPath = WRITEPATH . 'uploads/' . $zipName;

        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== TRUE) {
            return $this->response->setStatusCode(500)->setBody(lang('Settings.errorCreatingZip'));
        }

        try {
            $response = $client->request('GET', "https://api.github.com/repos/ci4-cms-erp/ci4ms/compare/v{$currentVersion}...v{$latestVersion}", [
                'headers' => [
                    'User-Agent' => 'CI4ms-Auto-Updater',
                    'Accept'     => 'application/vnd.github.v3+json',
                ],
            ]);

            $data = json_decode($response->getBody());
            
            if (!isset($data->files) || empty($data->files)) {
                return $this->response->setStatusCode(404)->setBody(lang('Settings.noChangesFound'));
            }

            foreach ($data->files as $file) {
                if ($file->status === 'removed') continue;
                
                // Fetch the raw content of the file
                $fileResponse = $client->request('GET', $file->raw_url, [
                    'headers' => ['User-Agent' => 'CI4ms-Auto-Updater']
                ]);
                
                // Add to zip preserving directory structure
                $zip->addFromString($file->filename, $fileResponse->getBody());
            }

            $zip->close();

            if (file_exists($zipPath)) {
                return $this->response->download($zipPath, null)->setFileName($zipName);
            }

            return $this->response->setStatusCode(404)->setBody(lang('Settings.zipNotFound'));

        } catch (\Exception $e) {
            return $this->response->setStatusCode(500)->setBody($e->getMessage());
        }
    }

    /**
     * Automatic Patch Update (One-Click)
     */
    public function autoUpdate()
    {
        if (!$this->request->isAJAX()) return $this->failForbidden();

        $currentVersion = env('app.version');
        $latestVersion = $this->request->getPost('latest');
        
        if (empty($latestVersion)) return $this->fail(lang('Settings.newVersionRequired'));

        // Check if ROOTPATH is writable
        if (!is_writable(ROOTPATH)) {
            return $this->respond(['result' => false, 'message' => lang('Settings.permissionsError')], 500);
        }

        $client = service('curlrequest');
        $backupDir = WRITEPATH . 'backups/' . "v{$currentVersion}_" . date('Ymd_His') . '/';
        if (!is_dir($backupDir)) mkdir($backupDir, 0777, true);

        try {
            // Get files list
            $response = $client->request('GET', "https://api.github.com/repos/ci4-cms-erp/ci4ms/compare/v{$currentVersion}...v{$latestVersion}", [
                'headers' => [
                    'User-Agent' => 'CI4ms-Auto-Updater',
                    'Accept'     => 'application/vnd.github.v3+json',
                ],
            ]);

            $data = json_decode($response->getBody());
            if (!isset($data->files) || empty($data->files)) {
                return $this->respond(['result' => false, 'message' => lang('Settings.noChangesFound')], 404);
            }

            $updatedFiles = [];
            foreach ($data->files as $file) {
                $targetFile = ROOTPATH . $file->filename;
                $targetDir = dirname($targetFile);

                // Skip removals for safety (requires manual check usually)
                if ($file->status === 'removed') continue;

                // Backup existing file
                if (file_exists($targetFile)) {
                    $relDir = dirname($file->filename);
                    if ($relDir !== '.' && !is_dir($backupDir . $relDir)) mkdir($backupDir . $relDir, 0777, true);
                    copy($targetFile, $backupDir . $file->filename);
                }

                // Create target directory if not exists
                if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);

                // Fetch raw content
                $fileResponse = $client->request('GET', $file->raw_url, [
                    'headers' => ['User-Agent' => 'CI4ms-Auto-Updater']
                ]);

                // Write to target
                file_put_contents($targetFile, $fileResponse->getBody());
                $updatedFiles[] = $file->filename;
            }

            // Update .env version
            $envPath = ROOTPATH . '.env';
            if (is_writable($envPath)) {
                $envContent = file_get_contents($envPath);
                $envContent = preg_replace('/^app.version\s*=\s*.*/m', "app.version='{$latestVersion}'", $envContent);
                file_put_contents($envPath, $envContent);
            }

            // Run Migrations
            $migrate = \Config\Services::migrations();
            $migrate->setNamespace('App');
            $migrate->latest();

            // Clear Cache
            cache()->clean();

            return $this->respond([
                'result'  => true,
                'message' => lang('Settings.updateSuccess', [$latestVersion]),
                'files'   => $updatedFiles
            ]);

        } catch (\Exception $e) {
            return $this->respond(['result' => false, 'message' => lang('Settings.updateFail', [$e->getMessage()])], 500);
        }
    }
}

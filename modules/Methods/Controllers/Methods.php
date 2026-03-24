<?php

namespace Modules\Methods\Controllers;

use ZipArchive;
use Modules\Methods\Libraries\ModuleInstaller;

class Methods extends \Modules\Backend\Controllers\BaseController
{
    public function index()
    {
        if ($this->request->is('post') && $this->request->isAJAX()) {
            $valData = [
                'status' => ['label' => 'Status', 'rules' => 'required|in_list[active,inactive]'],
            ];
            if (!empty($this->request->getPost('module_id')))
                $valData['module_id'] = ['label' => 'Module ID', 'rules' => 'required|is_natural_no_zero'];
            if (!empty($this->request->getPost('page_id')))
                $valData['page_id'] = ['label' => 'Page ID', 'rules' => 'required|is_natural_no_zero'];
            if ($this->validate($valData) == false) return $this->fail($this->validator->getErrors());
            $flag = false;
            if (!empty($this->request->getPost('module_id')) && $this->commonModel->edit('modules', ['isActive' => $this->request->getPost('status') == 'inactive' ? false : true], ['id' => $this->request->getPost('module_id')]) && $this->commonModel->edit('auth_permissions_pages', ['isActive' => $this->request->getPost('status') == 'inactive' ? false : true], ['module_id' => $this->request->getPost('module_id')]))
                $flag = true;
            if (!empty($this->request->getPost('page_id')) && $this->commonModel->edit('auth_permissions_pages', ['isActive' => $this->request->getPost('status') == 'inactive' ? false : true], ['id' => $this->request->getPost('page_id')]))
                $flag = true;
            if ($flag == true) {
                cache()->delete("{$this->defData['logged_in_user']->id}_permissions");
                return $this->respond(['success' => 'success'], 200);
            }
        }
        $methodsModel = new \Modules\Methods\Models\MethodsModel();
        $this->defData['modules'] = $methodsModel->getModules();
        return view('Modules\Methods\Views\list', $this->defData);
    }

    public function create()
    {
        if ($this->request->is('post')) {
            $valData = ([
                'pagename' => ['label' => '', 'rules' => 'required|regex_match[/^[^<>{}]*$/u]'],
                'sefLink' => ['label' => '', 'rules' => 'required|regex_match[/^[^<>{}]*$/u]|is_unique[auth_permissions_pages.sefLink]'],
                'typeOfPermissions' => ['label' => '', 'rules' => 'required'],
            ]);
            if ($this->validate($valData) == false) return redirect()->route('methodCreate')->withInput()->with('errors', $this->validator->getErrors());
            $roles = $this->request->getPost('typeOfPermissions');
            $r = [
                'create_r' => in_array('create', $roles),
                'update_r' => in_array('update', $roles),
                'read_r' => in_array('read', $roles),
                'delete_r' => in_array('delete', $roles),
            ];
            $roles = json_encode($r, JSON_UNESCAPED_UNICODE);
            if ($this->commonModel->create('auth_permissions_pages', [
                'pagename' => esc(strip_tags(trim($this->request->getPost('pagename')))),
                'description' => esc(strip_tags(trim($this->request->getPost('description')))),
                'className' => esc(strip_tags(trim($this->request->getPost('className')))),
                'methodName' => esc(strip_tags(trim($this->request->getPost('methodName')))),
                'sefLink' => $this->request->getPost('sefLink'),
                'hasChild' => $this->request->getPost('hasChild') ?? 0,
                'pageSort' => !empty($this->request->getPost('pageSort')) ? $this->request->getPost('pageSort') : NULL,
                'parent_pk' => $this->request->getPost('parent_pk') ?? NULL,
                'symbol' => !empty($this->request->getPost('symbol')) ? $this->request->getPost('symbol') : NULL,
                'inNavigation' => $this->request->getPost('inNavigation') ?? 0,
                'isBackoffice' => $this->request->getPost('isBackoffice') ?? 0,
                'typeOfPermissions' => $this->request->getPost('typeOfPermissions')
            ])) {
                return redirect()->route('list')->with('success', lang('Backend.created', [$this->request->getPost('pagename')]));
            } else
                return redirect()->route('methodCreate')->withInput()->with('error', lang('Backend.notCreated', [$this->request->getPost('pagename')]));
        }
        $this->defData['modules'] = $this->commonModel->lists('modules');
        $this->defData['permPages'] = $this->commonModel->lists('auth_permissions_pages');
        return view('Modules\Methods\Views\create', $this->defData);
    }

    public function update(int $pk)
    {
        if ($this->request->is('post')) {
            $valData = ([
                'pagename' => ['label' => '', 'rules' => 'required|regex_match[/^[^<>{}]*$/u]'],
                'sefLink' => ['label' => '', 'rules' => 'required|regex_match[/^[^<>{}]*$/u]'],
                'typeOfPermissions' => ['label' => '', 'rules' => 'required']
            ]);
            if ($this->validate($valData) == false) return redirect()->route('methodUpdate', [$pk])->withInput()->with('errors', $this->validator->getErrors());
            $roles = $this->request->getPost('typeOfPermissions');
            $r = [
                'create_r' => in_array('create', $roles),
                'update_r' => in_array('update', $roles),
                'read_r' => in_array('read', $roles),
                'delete_r' => in_array('delete', $roles),
            ];
            $roles = json_encode($r, JSON_UNESCAPED_UNICODE);
            if ($this->commonModel->edit('auth_permissions_pages', [
                'pagename' => esc(strip_tags(trim($this->request->getPost('pagename')))),
                'description' => esc(strip_tags(trim($this->request->getPost('description')))),
                'className' => esc(strip_tags(trim($this->request->getPost('className')))),
                'methodName' => esc(strip_tags(trim($this->request->getPost('methodName')))),
                'sefLink' => $this->request->getPost('sefLink'),
                'hasChild' => (bool)$this->request->getPost('hasChild') == true ? 1 : 0,
                'pageSort' => $this->request->getPost('pageSort') ?? 0,
                'parent_pk' => $this->request->getPost('parent_pk') ?? NULL,
                'symbol' => $this->request->getPost('symbol') ?? NULL,
                'inNavigation' => (bool)$this->request->getPost('inNavigation') == true ? 1 : 0,
                'isBackoffice' => (bool)$this->request->getPost('isBackoffice') == true ? 1 : 0,
                'typeOfPermissions' => $roles
            ], ['id' => $pk])) {
                cache()->delete('sidebar_menu');
                return redirect()->route('list')->with('success', lang('Backend.updated', [$this->request->getPost('pagename')]));
            } else
                return redirect()->route('methodUpdate', [$pk])->withInput()->with('error', lang('Backend.notUpdated', [$this->request->getPost('pagename')]));
        }
        $this->defData['method'] = $this->commonModel->selectOne('auth_permissions_pages', ['id' => $pk]);
        $this->defData['methods'] = $this->commonModel->lists('auth_permissions_pages', '*', ['id!=' => $pk, 'inNavigation' => true], 'pagename ASC');
        $this->defData['modules'] = $this->commonModel->lists('modules');
        $this->defData['permPages'] = $this->commonModel->lists('auth_permissions_pages');
        return view('Modules\Methods\Views\update', $this->defData);
    }

    public function moduleScan()
    {
        if (!$this->request->isAJAX()) return $this->failForbidden();
        
        $scanner = new \Modules\Methods\Libraries\ModuleScanner();
        $isChanged = $scanner->runScan();

        if ($isChanged) {
            return $this->respondCreated(['result' => true]);
        } else {
            return $this->respond(['result' => false]);
        }
    }

    public function moduleUpload()
    {
        if (!$this->request->isAJAX()) return $this->failForbidden();
        $file = $this->request->getFile('modules');

        if (!$file->isValid() || $file->getClientExtension() !== 'zip') {
            return $this->response->setJSON(['status' => 'error', 'message' => lang('Methods.invalidZipFile')]);
        }

        $tempPath = WRITEPATH . 'tmp/';
        $zip = new ZipArchive();

        if ($zip->open($file->getTempName()) === true) {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entryName = $zip->getNameIndex($i);
                $realEntry = realpath($tempPath . $entryName);
                if ($realEntry !== false && strpos($realEntry, realpath($tempPath)) !== 0) {
                    $zip->close();
                    return $this->response->setJSON(['status' => 'error', 'message' => 'ZIP contains invalid paths']);
                }
                if (preg_match('/\.\./', $entryName)) {
                    $zip->close();
                    return $this->response->setJSON(['status' => 'error', 'message' => 'ZIP contains path traversal']);
                }
            }
            $zip->extractTo($tempPath);
            $zip->close();
        } else {
            return $this->response->setJSON(['status' => 'error', 'message' => lang('Methods.zipOpenFailed')]);
        }

        $folders = array_filter(glob($tempPath . '*'), 'is_dir');
        $moduleFolder = basename(reset($folders));
        $finalPath = ROOTPATH . "modules/" . $moduleFolder;

        if (is_dir($finalPath)) {
            helper('filesystem');
            delete_files($tempPath, true);
            return $this->response->setJSON(['status' => 'error', 'message' => lang('Methods.moduleAlreadyExists')]);
        }

        rename(reset($folders), $finalPath);

        helper('filesystem');
        delete_files($tempPath, true);

        // Run migrations for the newly installed module
        $installer = new ModuleInstaller();
        $migResult = $installer->runModuleMigrations($moduleFolder);

        $message = lang('Methods.moduleInstallSuccess', [$moduleFolder]);
        if ($migResult['migrated'] > 0) {
            $message .= ' ' . lang('Methods.migrationsRun', [$migResult['migrated']]);
        }
        if (!$migResult['success']) {
            $message .= ' ' . lang('Methods.migrationWarning', [$migResult['error']]);
        }

        return $this->response->setJSON(['status' => 'success', 'message' => $message]);
    }

    public function moduleCreate()
    {
        if (!$this->request->isAJAX()) return $this->failForbidden();
        $valData = ([
            'module_name' => ['label' => lang('Methods.moduleName'), 'rules' => 'required|alpha_dash'],
        ]);
        if ($this->validate($valData) == false) return $this->fail($this->validator->getErrors());
        $moduleName = $this->request->getPost('module_name');

        $moduleName = ucfirst((string)$moduleName);
        $modulePath = ROOTPATH . 'modules/' . $moduleName;

        if (is_dir($modulePath)) {
            return $this->response->setJSON(['status' => 'error', 'message' => lang('Methods.moduleAlreadyExists')]);
        }

        try {
            command('make:module ' . escapeshellarg($moduleName));

            // Run migrations for the newly created module
            $installer = new ModuleInstaller();
            $migResult = $installer->runModuleMigrations($moduleName);

            $message = "'{$moduleName}' " . lang('Methods.moduleCreatedSuccess');
            if ($migResult['migrated'] > 0) {
                $message .= ' ' . lang('Methods.migrationsRun', [$migResult['migrated']]);
            }

            return $this->response->setJSON(['status' => 'success', 'message' => $message]);
        } catch (\Exception $e) {
            return $this->response->setJSON(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }
}

<?php

namespace Modules\Methods\Controllers;

use ZipArchive;
use Modules\Methods\Libraries\ModuleInstaller;

class Methods extends \Modules\Backend\Controllers\BaseController
{
    /**
     * Core modules that are forbidden to be deleted
     */
    private const PROTECTED_MODULES = [
        'Auth',
        'Backend',
        'Install',
        'Methods',
        'Settings',
        'LanguageManager',
        'Pages',
        'Blog',
        'Theme',
        'Users',
        'DashboardWidgets',
        'Media',
        'Menu'
    ];
    public function index()
    {
        if ($this->request->is('post') && $this->request->isAJAX()) {
            $valData = [
                'status' => ['label' => lang('Backend.status'), 'rules' => 'required|in_list[active,inactive]'],
            ];
            if (!empty($this->request->getPost('module_id')))
                $valData['module_id'] = ['label' => lang('Backend.id'), 'rules' => 'required|is_natural_no_zero'];
            if (!empty($this->request->getPost('page_id')))
                $valData['page_id'] = ['label' => lang('Backend.id'), 'rules' => 'required|is_natural_no_zero'];
            if ($this->validate($valData) === false)
                return $this->fail($this->validator->getErrors());
            $flag = false;
            if (!empty($this->request->getPost('module_id')) && $this->commonModel->edit('modules', ['isActive' => $this->request->getPost('status') == 'inactive' ? false : true], ['id' => $this->request->getPost('module_id')]) && $this->commonModel->edit('auth_permissions_pages', ['isActive' => $this->request->getPost('status') == 'inactive' ? false : true], ['module_id' => $this->request->getPost('module_id')]))
                $flag = true;
            if (!empty($this->request->getPost('page_id')) && $this->commonModel->edit('auth_permissions_pages', ['isActive' => $this->request->getPost('status') == 'inactive' ? false : true], ['id' => $this->request->getPost('page_id')]))
                $flag = true;
            if ($flag === true) {
                cache()->delete('sidebar_menu');
                return $this->respond(['success' => 'success'], 200);
            }
        }
        $methodsModel = new \Modules\Methods\Models\MethodsModel();
        $this->defData['modules'] = $methodsModel->getModules();
        $this->defData['protectedModules'] = self::PROTECTED_MODULES;
        return view('Modules\Methods\Views\list', $this->defData);
    }

    /**
     * Creates a new auth_permissions_pages row from the method form.
     *
     * className/methodName are the exact keys Ci4MsAuthFilter uses to look up
     * the authorization row for a request, so a new row is refused if it would
     * collide with an already-registered controller/method pair (see
     * classNameMethodNameCollides()). module_id is resolved from the form's
     * `moduleName` field (which actually posts modules.id, see form.php) via
     * resolveModuleId() and refused if missing/invalid, because
     * auth_permissions_pages.module_id is NOT NULL with no default and carries
     * a foreign key to modules(id).
     *
     * @return \CodeIgniter\HTTP\RedirectResponse|string
     */
    public function create()
    {
        if ($this->request->is('post')) {
            if ($this->validate($this->validationRules()) === false)
                return redirect()->route('methodCreate')->withInput()->with('errors', $this->validator->getErrors());

            $className = esc(strip_tags(trim((string) $this->request->getPost('className'))));
            $methodName = esc(strip_tags(trim((string) $this->request->getPost('methodName'))));

            if ($this->classNameMethodNameCollides($className, $methodName)) {
                return redirect()->route('methodCreate')->withInput()->with('error', lang('Methods.classNameMethodNameDuplicate'));
            }

            $moduleId = $this->resolveModuleId($this->request->getPost('moduleName'));
            if ($moduleId === null) {
                return redirect()->route('methodCreate')->withInput()->with('error', lang('Methods.moduleRequired'));
            }

            $roles = $this->request->getPost('typeOfPermissions');
            $r = [
                'create_r' => in_array('create', $roles),
                'update_r' => in_array('update', $roles),
                'read_r' => in_array('read', $roles),
                'delete_r' => in_array('delete', $roles),
            ];
            $roles = json_encode($r, JSON_UNESCAPED_UNICODE);
            if (
                $this->commonModel->create('auth_permissions_pages', [
                    'pagename' => esc(strip_tags(trim($this->request->getPost('pagename')))),
                    'description' => esc(strip_tags(trim($this->request->getPost('description')))),
                    'className' => $className,
                    'methodName' => $methodName,
                    'sefLink' => $this->request->getPost('sefLink'),
                    'hasChild' => $this->request->getPost('hasChild') ?? 0,
                    'pageSort' => !empty($this->request->getPost('pageSort')) ? $this->request->getPost('pageSort') : NULL,
                    'parent_pk' => $this->request->getPost('parent_pk') ?? NULL,
                    'symbol' => !empty($this->request->getPost('symbol')) ? $this->request->getPost('symbol') : NULL,
                    'inNavigation' => $this->request->getPost('inNavigation') ?? 0,
                    'isBackoffice' => $this->request->getPost('isBackoffice') ?? 0,
                    'module_id' => $moduleId,
                    'typeOfPermissions' => $roles
                ])
            ) {
                return redirect()->route('methodList')->with('success', lang('Backend.created', [$this->request->getPost('pagename')]));
            } else
                return redirect()->route('methodCreate')->withInput()->with('error', lang('Backend.notCreated', [$this->request->getPost('pagename')]));
        }
        $this->defData['modules'] = $this->commonModel->lists('modules');
        $this->defData['permPages'] = $this->commonModel->lists('auth_permissions_pages');
        return view('Modules\Methods\Views\form', $this->defData);
    }

    /**
     * Updates an existing auth_permissions_pages row from the method form.
     *
     * Same className/methodName collision guard as create(); a match against
     * a DIFFERENT row is refused, a match against the row's own current pk is
     * allowed (submitting the form unchanged must not be blocked).
     *
     * @param int $pk auth_permissions_pages.id
     *
     * @return \CodeIgniter\HTTP\RedirectResponse|string
     */
    public function update(int $pk)
    {
        if ($this->request->is('post')) {
            if ($this->validate($this->validationRules()) === false)
                return redirect()->route('methodUpdate', [$pk])->withInput()->with('errors', $this->validator->getErrors());

            $className = esc(strip_tags(trim((string) $this->request->getPost('className'))));
            $methodName = esc(strip_tags(trim((string) $this->request->getPost('methodName'))));

            if ($this->classNameMethodNameCollides($className, $methodName, $pk)) {
                return redirect()->route('methodUpdate', [$pk])->withInput()->with('error', lang('Methods.classNameMethodNameDuplicate'));
            }

            $roles = $this->request->getPost('typeOfPermissions');
            $r = [
                'create_r' => in_array('create', $roles),
                'update_r' => in_array('update', $roles),
                'read_r' => in_array('read', $roles),
                'delete_r' => in_array('delete', $roles),
            ];
            $roles = json_encode($r, JSON_UNESCAPED_UNICODE);
            if (
                $this->commonModel->edit('auth_permissions_pages', [
                    'pagename' => esc(strip_tags(trim($this->request->getPost('pagename')))),
                    'description' => esc(strip_tags(trim($this->request->getPost('description')))),
                    'className' => $className,
                    'methodName' => $methodName,
                    'sefLink' => $this->request->getPost('sefLink'),
                    'hasChild' => (bool) $this->request->getPost('hasChild') === true ? 1 : 0,
                    'pageSort' => $this->request->getPost('pageSort') ?? 0,
                    'parent_pk' => $this->request->getPost('parent_pk') ?? NULL,
                    'symbol' => $this->request->getPost('symbol') ?? NULL,
                    'inNavigation' => (bool) $this->request->getPost('inNavigation') === true ? 1 : 0,
                    'isBackoffice' => (bool) $this->request->getPost('isBackoffice') === true ? 1 : 0,
                    'typeOfPermissions' => $roles
                ], ['id' => $pk])
            ) {
                cache()->delete('sidebar_menu');
                return redirect()->route('methodList')->with('success', lang('Backend.updated', [$this->request->getPost('pagename')]));
            } else
                return redirect()->route('methodUpdate', [$pk])->withInput()->with('error', lang('Backend.notUpdated', [$this->request->getPost('pagename')]));
        }
        $this->defData['method'] = $this->commonModel->selectOne('auth_permissions_pages', ['id' => $pk]);
        $this->defData['methods'] = $this->commonModel->lists('auth_permissions_pages', '*', ['id!=' => $pk, 'inNavigation' => true], 'pagename ASC');
        $this->defData['modules'] = $this->commonModel->lists('modules');
        return view('Modules\Methods\Views\form', $this->defData);
    }

    public function moduleScan()
    {
        if (!$this->request->isAJAX())
            return $this->failForbidden();

        $scanner = new \Modules\Methods\Libraries\ModuleScanner();
        $isChanged = $scanner->runScan();

        if ($isChanged) {
            return $this->respondCreated(['result' => true]);
        } else {
            return $this->respond(['result' => false]);
        }
    }

    /** Hard cap on uncompressed module zip contents, to prevent zip-bomb DoS. */
    private const MAX_MODULE_UNCOMPRESSED_BYTES = 50 * 1024 * 1024;

    /** Per-entry size cap. */
    private const MAX_MODULE_ENTRY_BYTES = 20 * 1024 * 1024;

    public function moduleUpload()
    {
        if (!$this->request->isAJAX())
            return $this->failForbidden();
        $file = $this->request->getFile('modules');

        if (!$file->isValid() || $file->getClientExtension() !== 'zip') {
            return $this->response->setJSON(['status' => 'error', 'message' => lang('Methods.invalidZipFile')]);
        }

        $zip = new ZipArchive();
        if ($zip->open($file->getTempName()) !== true) {
            return $this->response->setJSON(['status' => 'error', 'message' => lang('Methods.zipOpenFailed')]);
        }

        // ── Pre-extraction validation ──────────────────────────────
        // Validate every entry STRING-side before any file actually exists.
        // The previous realpath() containment check was dead code: realpath
        // returns false for paths that don't exist yet (i.e. pre-extraction),
        // so the short-circuit `$realEntry !== false && ...` never fired.
        $totalUncompressed = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entryName = $zip->getNameIndex($i);
            $stat = $zip->statIndex($i);

            if (
                $entryName === ''
                || preg_match('/^[\\/\\\\]/', $entryName)            // absolute path
                || preg_match('/^[A-Za-z]:[\\/\\\\]/', $entryName)   // Windows drive letter
                || preg_match('/(^|[\\/\\\\])\.\.([\\/\\\\]|$)/', $entryName) // .. segment anywhere
                || str_contains($entryName, "\0")                    // null byte
            ) {
                $zip->close();
                return $this->response->setJSON(['status' => 'error', 'message' => lang('Methods.zipPathTraversal')]);
            }

            if ($stat !== false) {
                $size = (int) ($stat['size'] ?? 0);
                if ($size > self::MAX_MODULE_ENTRY_BYTES) {
                    $zip->close();
                    return $this->response->setJSON(['status' => 'error', 'message' => lang('Methods.zipBombDetected')]);
                }
                $totalUncompressed += $size;
                if ($totalUncompressed > self::MAX_MODULE_UNCOMPRESSED_BYTES) {
                    $zip->close();
                    return $this->response->setJSON(['status' => 'error', 'message' => lang('Methods.zipBombDetected')]);
                }

                // Entry-level symlink rejection (S_IFLNK in upper 16 bits of external_attr).
                $extAttr = (int) ($stat['external_attr'] ?? 0);
                if (($extAttr >> 16) & 0xA000) {
                    $zip->close();
                    return $this->response->setJSON(['status' => 'error', 'message' => lang('Methods.symlinkRejected')]);
                }
            }
        }

        // ── Extract to a dedicated quarantine directory ───────────
        // Random suffix isolates concurrent uploads and stops one upload from
        // colliding with files another half-finished upload left behind.
        $quarantine = WRITEPATH . 'tmp/module_upload_' . bin2hex(random_bytes(8)) . '/';
        if (!@mkdir($quarantine, 0750, true) && !is_dir($quarantine)) {
            $zip->close();
            return $this->response->setJSON(['status' => 'error', 'message' => lang('Methods.zipOpenFailed')]);
        }

        $zip->extractTo($quarantine);
        $zip->close();

        helper('filesystem');

        // ── Post-extraction validation ────────────────────────────
        // Defense-in-depth symlink walk for ZIPs whose external_attr didn't
        // expose Unix mode bits.
        if ($this->extractedTreeContainsSymlink($quarantine)) {
            delete_files($quarantine, true);
            @rmdir($quarantine);
            return $this->response->setJSON(['status' => 'error', 'message' => lang('Methods.symlinkRejected')]);
        }

        // Exactly one top-level directory expected (the module folder itself).
        $folders = array_filter(glob($quarantine . '*'), 'is_dir');
        if (count($folders) !== 1) {
            delete_files($quarantine, true);
            @rmdir($quarantine);
            return $this->response->setJSON(['status' => 'error', 'message' => lang('Methods.invalidZipPath')]);
        }
        $sourceDir = reset($folders);
        $moduleFolder = basename($sourceDir);

        // Module folder name must be a safe PascalCase-ish identifier so it
        // is a valid PSR-4 namespace component AND can't escape modules/ via
        // concatenation when it later flows into Modules\{Folder}\… loads.
        if (!preg_match('/^[A-Za-z][A-Za-z0-9_]{0,49}$/', $moduleFolder)) {
            delete_files($quarantine, true);
            @rmdir($quarantine);
            return $this->response->setJSON(['status' => 'error', 'message' => lang('Methods.invalidZipPath')]);
        }

        $finalPath = ROOTPATH . 'modules/' . $moduleFolder;
        if (is_dir($finalPath)) {
            delete_files($quarantine, true);
            @rmdir($quarantine);
            return $this->response->setJSON(['status' => 'error', 'message' => lang('Methods.moduleAlreadyExists')]);
        }

        // Final containment check: $sourceDir must resolve INSIDE the quarantine.
        $realSource = realpath($sourceDir);
        $realQuarantine = realpath($quarantine);
        if (
            $realSource === false || $realQuarantine === false
            || strncmp($realSource, rtrim($realQuarantine, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR, strlen(rtrim($realQuarantine, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)) !== 0
        ) {
            delete_files($quarantine, true);
            @rmdir($quarantine);
            return $this->response->setJSON(['status' => 'error', 'message' => lang('Methods.invalidZipPath')]);
        }

        if (!rename($sourceDir, $finalPath)) {
            delete_files($quarantine, true);
            @rmdir($quarantine);
            return $this->response->setJSON(['status' => 'error', 'message' => lang('Methods.zipOpenFailed')]);
        }

        delete_files($quarantine, true);
        @rmdir($quarantine);

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

    /**
     * Walk a freshly-extracted directory tree and return true if any entry is
     * a symbolic link. Defense-in-depth on top of the pre-extraction
     * external_attr check.
     */
    private function extractedTreeContainsSymlink(string $root): bool
    {
        if (!is_dir($root)) {
            return false;
        }
        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST
            );
        } catch (\Throwable $e) {
            return true; // unreadable = fail closed
        }
        foreach ($iterator as $entry) {
            if (is_link($entry->getPathname())) {
                return true;
            }
        }
        return false;
    }

    public function moduleCreate()
    {
        if (!$this->request->isAJAX()) return $this->failForbidden();
        $valData = ([
            'module_name' => ['label' => lang('Methods.moduleName'), 'rules' => 'required|alpha_dash'],
        ]);
        if ($this->validate($valData) === false) return $this->fail($this->validator->getErrors());
        $moduleName = $this->request->getPost('module_name');

        $moduleName = ucfirst((string) $moduleName);
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

    /**
     * Returns module information (information before deletion)
     */
    public function moduleInfo(int $moduleId)
    {
        if (!$this->request->isAJAX())
            return $this->failForbidden();

        $module = $this->commonModel->selectOne('modules', ['id' => $moduleId]);
        if (empty($module))
            return $this->failNotFound(lang('Methods.deleteModuleFailed'));

        if (in_array($module->name, self::PROTECTED_MODULES))
            return $this->respond([
                'status' => 'protected',
                'message' => lang('Methods.deleteModuleProtected'),
            ]);

        $installer = new ModuleInstaller();
        $tables = $installer->getModuleTables($module->name);
        $stats = $installer->getModuleTableStats($module->name);

        $totalRecords = 0;
        $tableInfo = [];
        foreach ($stats as $tableName => $count) {
            $tableInfo[] = [
                'name' => $tableName,
                'count' => $count,
            ];
            if ($count > 0) {
                $totalRecords += $count;
            }
        }

        return $this->respond([
            'status' => 'ok',
            'module_id' => $module->id,
            'module_name' => $module->name,
            'tables' => $tableInfo,
            'totalRecords' => $totalRecords,
        ]);
    }

    /**
     * Deletes the module (tables + file system + records)
     */
    public function moduleDelete()
    {
        if (!$this->request->isAJAX() || !$this->request->is('post'))
            return $this->failForbidden();

        $valData = [
            'module_id' => ['label' => lang('Backend.id'), 'rules' => 'required|is_natural_no_zero'],
            'confirm_name' => ['label' => lang('Methods.moduleName'), 'rules' => 'required|regex_match[/^[^<>{}=]+$/u]'],
        ];
        if ($this->validate($valData) === false)
            return $this->fail($this->validator->getErrors());

        $moduleId = (int) $this->request->getPost('module_id');
        $confirmName = trim((string) $this->request->getPost('confirm_name'));

        $module = $this->commonModel->selectOne('modules', ['id' => $moduleId]);
        if (empty($module))
            return $this->failNotFound(lang('Methods.deleteModuleFailed'));

        // Protected module check
        if (in_array($module->name, self::PROTECTED_MODULES)) {
            return $this->respond([
                'status' => 'error',
                'message' => lang('Methods.deleteModuleProtected'),
            ]);
        }

        // Name match check
        if ($confirmName !== $module->name)
            return $this->respond([
                'status' => 'error',
                'message' => lang('Methods.deleteModuleNameMismatch'),
            ]);

        $installer = new ModuleInstaller();

        $migrationPath = ROOTPATH . 'modules/' . $module->name . '/Database/Migrations';
        if (is_dir($migrationPath)) {
            $files = glob($migrationPath . '/*.php');
            if (!empty($files)) {
                $rollbackResult = $installer->rollbackModuleMigrations($module->name);
                if (!$rollbackResult['success'])
                    return $this->respond([
                        'status' => 'error',
                        'message' => lang('Methods.rollbackFailed', [$rollbackResult['error']]),
                    ]);
            }
        }

        $this->commonModel->remove('auth_permissions_pages', ['module_id' => $moduleId]);
        $this->commonModel->remove('modules', ['id' => $moduleId]);
        $fileResult = $installer->removeModuleFiles($module->name);
        cache()->delete('sidebar_menu');

        $message = lang('Methods.deleteModuleSuccess', [$module->name]);
        if (!$fileResult['success'])
            $message .= ' ' . $fileResult['error'];

        return $this->respond([
            'status' => 'success',
            'message' => $message,
        ]);
    }

    /**
     * Validation rules for the create()/update() method form.
     *
     * className/methodName feed Ci4MsAuthFilter's authorization lookup
     * (auth_permissions_pages.className + .methodName), so they are
     * restricted to the charset ModuleScanner itself produces
     * (str_replace('\\', '-', ...) of a PSR-4 class name, plus a plain
     * method identifier) instead of the generic "no angle brackets" rule
     * used for free-text fields elsewhere in this controller.
     *
     * @return array<string, array{label: string, rules: string}>
     */
    private function validationRules(): array
    {
        return [
            'pagename' => ['label' => '', 'rules' => 'required|regex_match[/^[^<>{}]*$/u]'],
            'sefLink' => ['label' => '', 'rules' => 'required|regex_match[/^[^<>{}]*$/u]'],
            'className' => ['label' => '', 'rules' => 'permit_empty|regex_match[/^[A-Za-z0-9_\-]*$/]'],
            'methodName' => ['label' => '', 'rules' => 'permit_empty|regex_match[/^[A-Za-z0-9_\-]*$/]'],
            'typeOfPermissions' => ['label' => '', 'rules' => 'required']
        ];
    }

    /**
     * Whether the given className/methodName pair is already registered to a
     * DIFFERENT auth_permissions_pages row.
     *
     * auth_permissions_pages has no unique constraint on (className,
     * methodName) — a real DB-level fix is tracked separately (schema/index
     * decision) — so this is an application-level guard: it stops create()/
     * update() from writing a row that duplicates an existing controller's
     * authorization key, which is exactly the key Ci4MsAuthFilter looks up
     * to decide which typeOfPermissions applies to an incoming request.
     * The empty/empty pair is exempt: it is the legitimate "menu-only,
     * no route" parent page pattern also produced by
     * ModuleScanner::createVirtualParents().
     *
     * @param string   $className  Sanitized className value about to be written.
     * @param string   $methodName Sanitized methodName value about to be written.
     * @param int|null $excludePk  auth_permissions_pages.id to treat as "self" on update(); null on create().
     *
     * @return bool True if the pair collides with another row.
     */
    private function classNameMethodNameCollides(string $className, string $methodName, ?int $excludePk = null): bool
    {
        if ($className === '' && $methodName === '') {
            return false;
        }

        $existing = $this->commonModel->selectOne('auth_permissions_pages', ['className' => $className, 'methodName' => $methodName]);
        if (empty($existing)) {
            return false;
        }

        return $excludePk === null || (int) $existing->id !== $excludePk;
    }

    /**
     * Resolves the posted `moduleName` field to a real modules.id.
     *
     * The Methods form's module <select> is named `moduleName` but its
     * option values are actually modules.id (see
     * modules/Methods/Views/form.php:146-154), not a module name string.
     * This validates that the posted value is a positive integer AND that a
     * modules row with that id actually exists, instead of trusting an
     * attacker-controlled value that could otherwise violate the
     * module_id -> modules(id) foreign key
     * (modules/Backend/Database/Migrations/2026-02-25-062806_AddForeignKeys.php:15).
     *
     * @param mixed $moduleIdPost Raw POST value of the `moduleName` field.
     *
     * @return int|null Resolved modules.id, or null if missing, not a
     *                   positive integer, or not an existing module.
     */
    private function resolveModuleId(mixed $moduleIdPost): ?int
    {
        if (!is_scalar($moduleIdPost) || !ctype_digit((string) $moduleIdPost) || (int) $moduleIdPost < 1) {
            return null;
        }

        $module = $this->commonModel->selectOne('modules', ['id' => (int) $moduleIdPost]);

        return empty($module) ? null : (int) $module->id;
    }
}

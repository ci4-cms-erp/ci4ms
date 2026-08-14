<?php
declare(strict_types=1);

namespace Modules\Fileeditor\Controllers;

use DirectoryIterator;

class Fileeditor extends \Modules\Backend\Controllers\BaseController
{
    protected $allowedExtensions = ['css', 'js', 'html', 'txt', 'json', 'sql', 'md'];
    protected $dangerousExtensionsAllowed = false;
    protected $dangerousExtensions = ['php', 'phtml', 'phar', 'php3', 'php4', 'php5', 'php7', 'php8', 'phps', 'cgi', 'pl', 'asp', 'aspx', 'jsp', 'sh', 'bat', 'exe', 'htaccess'];
    protected $hiddenItems = ['.git', '.github', '.idea', '.vscode', 'node_modules', 'vendor', 'writable', '.env', 'env', 'composer.json', 'composer.lock', 'tests', 'spark', 'phpunit.xml.dist', 'preload.php'];

    /**
     * The only location content-writing/structural methods (saveFile,
     * createFile, renameFile, createFolder, deleteFileOrFolder) may target.
     * Scoped explicitly to public/templates/ — theme editing is Fileeditor's
     * one legitimate write use case; nothing else under public/ (index.php,
     * .htaccess, be-assets/js/ci4ms.js, etc.) may be touched.
     */
    private const WRITABLE_ROOT = 'public/templates/';

    public function index()
    {
        return view('Modules\Fileeditor\Views\fileEditor', $this->defData);
    }

    public function listFiles()
    {
        $vData = [
            '_' => ['label' => '', 'rules' => 'required|max_length[255]|regex_match[/^[a-zA-Z0-9_ \-\.]+$/]'],
        ];
        if ($this->request->getVar('path'))
            $vData['path'] = ['label' => '', 'rules' => 'required|max_length[255]|regex_match[/^[a-zA-Z0-9_ \-\.\/]+$/]'];
        $valData = ($vData);
        if ($this->validate($valData) === false)
            return $this->fail($this->validator->getErrors());
        $path = $this->request->getVar('path') ?? '/';

        $pathParts = explode('/', trim($path, '/'));
        foreach ($pathParts as $part) {
            if (in_array($part, $this->hiddenItems)) {
                return $this->failForbidden();
            }
        }
        $fullPath = realpath(ROOTPATH . $path);

        if (!$fullPath || !$this->isInsideProject($fullPath)) {
            return $this->response->setJSON(['error' => lang('Backend.invalid', [lang('Backend.path')])])->setStatusCode(400);
        }
        $iterator = new DirectoryIterator($fullPath);
        $result = [];

        foreach ($iterator as $file) {
            if ($file->isDot())
                continue;
            $name = $file->getFilename();
            $lowerName = strtolower($name);

            if (strpos($lowerName, '.') === 0)
                continue;
            if (in_array($name, $this->hiddenItems))
                continue;
            $result[] = [
                'title' => $name,
                'key' => $path . '/' . $name,
                'folder' => $file->isDir(),
                'lazy' => $file->isDir()
            ];
        }

        return $this->response->setJSON($result);
    }

    public function readFile()
    {
        $valData = (['path' => ['label' => '', 'rules' => 'required|max_length[255]|regex_match[/^[a-zA-Z0-9_ \-\.\/]+$/]']]);
        if ($this->validate($valData) === false)
            return $this->fail($this->validator->getErrors());
        $path = $this->request->getVar('path');
        if ($this->isHiddenPath($path))
            return $this->failForbidden();
        // An attacker-planted symlink under a non-hidden, non-core location
        // (e.g. docs/) would otherwise let readFile() disclose whatever the
        // link points to, regardless of the target's real location.
        if ($this->pathContainsSymlink($path))
            return $this->failForbidden();
        $fullPath = realpath(ROOTPATH . $path);
        if (!$this->allowedFileTypes($fullPath))
            return $this->failForbidden();
        if (!$fullPath || !is_file($fullPath) || !$this->isInsideProject($fullPath))
            return $this->response->setJSON(['error' => lang('Backend.invalid', [lang('Backend.path')])])->setStatusCode(400);
        return $this->response->setJSON(['content' => file_get_contents($fullPath)]);
    }

    private function triggerFileevent($path, $action)
    {
        \CodeIgniter\Events\Events::trigger('ci4ms.audit', [
            'severity' => 'warning',
            'action' => 'fileeditor.' . $action,
            'message' => sprintf('%s dosyası %s tarafından düzenlendi', $path, auth()->user()->username),
            'url' => base_url('backend/fileeditor'),
        ]);
    }

    public function saveFile()
    {
        $valData = ([
            'path' => ['label' => '', 'rules' => 'required|max_length[255]|regex_match[/^[a-zA-Z0-9_ \-\.\/]+$/]'],
            'content' => ['label' => '', 'rules' => 'required'],
        ]);
        if ($this->validate($valData) === false)
            return $this->fail($this->validator->getErrors());
        $path = $this->request->getVar('path');
        if ($this->isHiddenPath($path))
            return $this->failForbidden();
        if ($this->pathContainsSymlink($path))
            return $this->failForbidden();
        $content = $this->request->getVar('content');
        $fullPath = realpath(ROOTPATH . $path);
        if (!$this->allowedFileTypes($fullPath))
            return $this->failForbidden();
        if (!$fullPath || !is_file($fullPath) || !$this->isInsideProject($fullPath))
            return $this->response->setJSON(['error' => lang('Backend.invalid', [lang('Backend.path')])])->setStatusCode(400);
        // isWritableTarget() scopes writes to public/templates/ only —
        // theme editing is Fileeditor's one legitimate write use case.
        if (!$this->isWritableTarget($path))
            return $this->failForbidden(lang('Fileeditor.writeNotAllowed'));
        // Block writing to dangerous file types (defense-in-depth)
        $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
        if (!$this->dangerousExtensionsAllowed && in_array($ext, $this->dangerousExtensions, true))
            return $this->failForbidden(lang('Fileeditor.dangerousFileType'));
        if (file_put_contents($fullPath, $content) === false)
            return $this->response->setJSON(['error' => lang('Backend.notUpdated', [''])])->setStatusCode(500);
        $this->triggerFileevent($fullPath, 'write');
        return $this->response->setJSON(['success' => true]);
    }

    public function renameFile()
    {
        $valData = (['path' => ['label' => '', 'rules' => 'required|max_length[255]|regex_match[/^[a-zA-Z0-9_ \-\.\/]+$/]'], 'newName' => ['label' => '', 'rules' => 'required|max_length[255]|regex_match[/^[a-zA-Z0-9_ \-\.]+$/]']]);
        if ($this->validate($valData) === false)
            return $this->fail($this->validator->getErrors());
        $path = $this->request->getVar('path');
        // isWritableTarget() (below) scopes renames to public/templates/
        // only — theme renaming is one of Fileeditor's legitimate write use
        // cases.
        if ($this->isHiddenPath($path))
            return $this->failForbidden();
        if ($this->pathContainsSymlink($path))
            return $this->failForbidden();
        $newName = $this->request->getVar('newName');

        // Reject the rename target outright if the new name is not in the editor's
        // allowlist (css, js, html, txt, json, sql, md). Without this, a content
        // payload first written to a .html file could be promoted to .php and
        // executed by the web server — the exact regression patched in e1aad28.
        if (!$this->allowedFileTypes($newName))
            return $this->failForbidden(lang('Fileeditor.dangerousFileType'));

        $fullPath = realpath(ROOTPATH . $path);
        // Resolve and bound-check the source before deriving anything from
        // it: realpath() returns false for a nonexistent path, and
        // dirname(false) throws a TypeError under this file's
        // declare(strict_types=1) (:2) rather than letting the request fail
        // gracefully — this check must run before dirname($fullPath) is
        // ever called.
        if (!$fullPath || !file_exists($fullPath) || !$this->isInsideProject($fullPath))
            return $this->response->setJSON(['error' => lang('Backend.invalid', [lang('Backend.path')])])->setStatusCode(400);
        $newPath = dirname($fullPath) . DIRECTORY_SEPARATOR . $newName;

        // Verify the rename target's directory stays within ROOTPATH.
        // $newName cannot contain a path separator (enforced by the
        // 'newName' regex above), so checking $realNewDir alone is
        // sufficient — there is no traversal component left in $newName to
        // escape it with.
        $realNewDir = realpath(dirname($fullPath));
        if (!$realNewDir || !$this->isInsideProject($realNewDir))
            return $this->response->setJSON(['error' => lang('Backend.invalid', [lang('Backend.path')])])->setStatusCode(400);

        if (!$this->isWritableTarget($path))
            return $this->failForbidden(lang('Fileeditor.writeNotAllowed'));

        // Block renaming to dangerous extensions (defense-in-depth alongside the allowlist above)
        $ext = strtolower(pathinfo($newName, PATHINFO_EXTENSION));
        if (!$this->dangerousExtensionsAllowed && in_array($ext, $this->dangerousExtensions, true))
            return $this->failForbidden(lang('Fileeditor.dangerousFileType'));
        if (rename($fullPath, $newPath)) {
            $this->triggerFileevent($newPath, 'rename');
            return $this->response->setJSON(['success' => true]);
        }
        return $this->response->setJSON(['error' => lang('Fileeditor.renameFailed')])->setStatusCode(500);
    }

    public function createFile()
    {
        $valData = ([
            'path' => ['label' => '', 'rules' => 'required|max_length[255]|regex_match[/^[a-zA-Z0-9_ \-\.\/]+$/]'],
            'name' => ['label' => '', 'rules' => 'required|max_length[255]|regex_match[/^[a-zA-Z0-9_ \-\.]+$/]'],
        ]);
        if ($this->validate($valData) === false)
            return $this->fail($this->validator->getErrors());
        $path = $this->request->getVar('path');
        if ($this->isHiddenPath($path))
            return $this->failForbidden();
        // Same rationale as readFile()/saveFile(): a symlinked directory under
        // path could otherwise redirect the write outside the intended target.
        if ($this->pathContainsSymlink($path))
            return $this->failForbidden();
        $name = $this->request->getVar('name');
        $fullPath = realpath(ROOTPATH . $path);

        if (!$this->allowedFileTypes($name))
            return $this->failForbidden();
        if (!$fullPath || !is_dir($fullPath) || !$this->isInsideProject($fullPath))
            return $this->response->setJSON(['error' => lang('Backend.invalid', [lang('Backend.path')])])->setStatusCode(400);
        // isWritableTarget() scopes writes to public/templates/ only — same
        // reasoning as saveFile().
        if (!$this->isWritableTarget($path))
            return $this->failForbidden(lang('Fileeditor.writeNotAllowed'));

        // Block creating dangerous file types
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!$this->dangerousExtensionsAllowed && in_array($ext, $this->dangerousExtensions, true))
            return $this->failForbidden(lang('Fileeditor.dangerousFileType'));

        $newFilePath = $fullPath . DIRECTORY_SEPARATOR . $name;

        // Prevent overwriting existing files
        if (file_exists($newFilePath))
            return $this->response->setJSON(['error' => lang('Fileeditor.fileAlreadyExists')])->setStatusCode(409);

        if (file_put_contents($newFilePath, '') !== false) {
            $this->triggerFileevent($newFilePath, 'create');
            return $this->response->setJSON(['success' => true]);
        }
        return $this->response->setJSON(['error' => lang('Backend.notCreated', [''])])->setStatusCode(500);
    }

    public function createFolder()
    {
        $valData = ([
            'path' => ['label' => '', 'rules' => 'required|max_length[255]|regex_match[/^[a-zA-Z0-9_ \-\.\/]+$/]'],
            'name' => ['label' => '', 'rules' => 'required|max_length[255]|regex_match[/^[a-zA-Z0-9_ \-\.]+$/]'],
        ]);
        if ($this->validate($valData) === false)
            return $this->fail($this->validator->getErrors());
        $path = $this->request->getVar('path');
        // isWritableTarget() (below) scopes writes to public/templates/
        // only — theme folder creation is createFolder()'s one legitimate
        // use case.
        if ($this->isHiddenPath($path))
            return $this->failForbidden();
        if ($this->pathContainsSymlink($path))
            return $this->failForbidden();
        $name = $this->request->getVar('name');
        // The name regex above (allowed-character class) has no special
        // handling for '..' and accepts it; reject it explicitly instead of
        // widening the regex.
        if (str_contains($name, '..'))
            return $this->failForbidden();
        $fullPath = realpath(ROOTPATH . $path);
        if (!$fullPath || !is_dir($fullPath) || !$this->isInsideProject($fullPath))
            return $this->response->setJSON(['error' => lang('Backend.invalid', [lang('Backend.path')])])->setStatusCode(400);
        if (!$this->isWritableTarget($path))
            return $this->failForbidden(lang('Fileeditor.writeNotAllowed'));

        $newFolderPath = $fullPath . DIRECTORY_SEPARATOR . $name;

        if (mkdir($newFolderPath)) {
            $this->triggerFileevent($newFolderPath, 'create');
            return $this->response->setJSON(['success' => true]);
        }
        return $this->response->setJSON(['error' => lang('Backend.notCreated', [$newFolderPath])])->setStatusCode(500);
    }

    public function deleteFileOrFolder()
    {
        $valData = ([
            'path' => ['label' => '', 'rules' => 'required|max_length[255]|regex_match[/^[a-zA-Z0-9_ \-\.\/]+$/]'],
        ]);
        if ($this->validate($valData) === false)
            return $this->fail($this->validator->getErrors());
        $path = $this->request->getVar('path');
        // isWritableTarget() (below) scopes deletes to public/templates/
        // only — theme file/folder deletion is one of Fileeditor's
        // legitimate write use cases.
        if ($this->isHiddenPath($path))
            return $this->failForbidden();
        if ($this->pathContainsSymlink($path))
            return $this->failForbidden();
        $fullPath = realpath(ROOTPATH . $path);
        if (!$fullPath || !$this->isInsideProject($fullPath))
            return $this->response->setJSON(['error' => lang('Fileeditor.invalidFileOrFolder')])->setStatusCode(400);
        if (!$this->isWritableTarget($path))
            return $this->failForbidden(lang('Fileeditor.writeNotAllowed'));

        if (is_dir($fullPath)) {
            $result = rmdir($fullPath);
        } else {
            // Restrict deletion to the editor's allowlist so an admin can't be
            // tricked (or coerced via CSRF) into removing protective files such
            // as .htaccess that gate PHP execution in upload directories.
            if (!$this->allowedFileTypes($fullPath))
                return $this->failForbidden(lang('Fileeditor.dangerousFileType'));
            $result = unlink($fullPath);
        }

        if ($result) {
            $this->triggerFileevent($fullPath, 'delete');
            return $this->response->setJSON(['success' => true]);
        }
        return $this->response->setJSON(['error' => lang('Fileeditor.folderNotEmpty')])->setStatusCode(500);
    }

    private function allowedFileTypes(string $file): bool
    {
        $extension = pathinfo($file, PATHINFO_EXTENSION);
        if (!in_array(strtolower($extension), $this->allowedExtensions))
            return false;
        return true;
    }

    private function isHiddenPath(string $path): bool
    {
        $pathParts = explode('/', trim($path, '/'));
        foreach ($pathParts as $part) {
            if (in_array($part, $this->hiddenItems))
                return true;
        }
        return false;
    }

    /**
     * Returns true only when $fullPath is realpath(ROOTPATH) itself, or lies
     * strictly inside it. Replaces the repeated
     * `strpos($fullPath, realpath(ROOTPATH)) !== 0` pattern, which has no
     * notion of a path boundary: realpath(ROOTPATH) carries no trailing
     * separator, so a sibling of ROOTPATH whose name happens to start with
     * ROOTPATH's own basename (e.g. a real "ci4ms-evil" or "ci4ms_x.txt" next
     * to a project rooted at ".../ci4ms") is wrongly treated as inside. The
     * trailing DIRECTORY_SEPARATOR appended to $root below is what makes the
     * boundary check exact.
     *
     * @param string $fullPath Absolute, realpath()-resolved path to check
     *
     * @return bool
     */
    private function isInsideProject(string $fullPath): bool
    {
        $root = realpath(ROOTPATH);
        if ($root === false) {
            return false;
        }
        return $fullPath === $root || str_starts_with($fullPath, $root . DIRECTORY_SEPARATOR);
    }

    /**
     * Returns true only when $path resolves strictly inside self::WRITABLE_ROOT
     * (public/templates/), the sole location content-writing/structural
     * methods may target. This is a sequential prefix match: the path's
     * first two segments must be exactly ['public', 'templates'], in that
     * order — a "segment contains 'templates' somewhere" check would wrongly
     * allow app/templates/evil.php. Any '..' segment fails the check
     * outright, so a path like public/templates/../../etc/passwd cannot
     * satisfy the prefix test and then escape it via a later traversal
     * segment.
     *
     * @param string $path Raw, slash-normalized path as submitted by the client
     *
     * @return bool
     */
    private function isWritableTarget(string $path): bool
    {
        $pathParts = explode('/', trim($path, '/'));
        if (in_array('..', $pathParts, true)) {
            return false;
        }
        $writableParts = explode('/', trim(self::WRITABLE_ROOT, '/'));

        return array_slice($pathParts, 0, count($writableParts)) === $writableParts;
    }

    /**
     * Returns true if any segment of $path (under ROOTPATH) is a symlink.
     * Catches the case where an attacker plants a symlink and then targets
     * file operations at it to read/write/rename/delete the symlink's
     * target instead of the symlink itself.
     */
    private function pathContainsSymlink(string $path): bool
    {
        $check = rtrim(ROOTPATH, DIRECTORY_SEPARATOR);
        foreach (explode('/', trim($path, '/')) as $part) {
            if ($part === '' || $part === '.' || $part === '..') {
                continue;
            }
            $check .= DIRECTORY_SEPARATOR . $part;
            if (is_link($check)) {
                return true;
            }
        }
        return false;
    }
}

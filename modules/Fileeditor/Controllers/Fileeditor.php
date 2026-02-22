<?php

namespace Modules\Fileeditor\Controllers;

use DirectoryIterator;

class Fileeditor extends \Modules\Backend\Controllers\BaseController
{
    protected $allowedExtensions = ['css', 'js', 'html', 'txt', 'json', 'sql', 'md'];
    protected $hiddenItems = [
        '.git',
        '.github',
        '.idea',
        '.vscode',
        'node_modules',
        'vendor',
        'writable',
        '.env',
        'composer.lock',
        'tests',
        'spark',
        'phpunit.xml.dist',
        'preload.php'
    ];

    public function index()
    {
        return view('Modules\Fileeditor\Views\fileEditor', $this->defData);
    }

    public function listFiles()
    {
        $valData = ([
            'path' => ['label' => '', 'rules' => 'required|max_length[255]|regex_match[/^[a-zA-Z0-9_ \-\.]+$/]'],
        ]);
        if ($this->validate($valData) == false) return $this->fail($this->validator->getErrors());
        $path = $this->request->getVar('path') ?? '/';

        $pathParts = explode('/', trim($path, '/'));
        foreach ($pathParts as $part) {
            if (in_array($part, $this->hiddenItems)) {
                return $this->failForbidden();
            }
        }
        $fullPath = realpath(ROOTPATH . $path);

        if (!$fullPath || strpos($fullPath, realpath(ROOTPATH)) !== 0) {
            return $this->response->setJSON(['error' => lang('Backend.invalid', [lang('Fileeditor.path')])])->setStatusCode(400);
        }
        $iterator = new DirectoryIterator($fullPath);
        $result = [];

        foreach ($iterator as $file) {
            if ($file->isDot()) continue;
            $name = $file->getFilename();
            $lowerName = strtolower($name);

            if (strpos($lowerName, '.') === 0) continue;
            if (in_array($name, $this->hiddenItems)) continue;
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
        $valData = ([
            'path' => ['label' => '', 'rules' => 'required|max_length[255]|regex_match[/^[a-zA-Z0-9_ \-\.]+$/]'],
        ]);
        if ($this->validate($valData) == false) return $this->fail($this->validator->getErrors());
        $path = $this->request->getVar('path');
        $fullPath = realpath(ROOTPATH . $path);

        if (!$fullPath || !is_file($fullPath) || strpos($fullPath, realpath(ROOTPATH)) !== 0) {
            return $this->response->setJSON(['error' => lang('Backend.invalid', [lang('Fileeditor.path')])])->setStatusCode(400);
        }

        return $this->response->setJSON(['content' => file_get_contents($fullPath)]);
    }

    public function saveFile()
    {
        $valData = ([
            'path' => ['label' => '', 'rules' => 'required|max_length[255]|regex_match[/^[a-zA-Z0-9_ \-\.]+$/]'],
            'content' => ['label' => '', 'rules' => 'required'],
        ]);
        if ($this->validate($valData) == false) return $this->fail($this->validator->getErrors());
        $path = $this->request->getVar('path');
        $content = $this->request->getVar('content');
        $fullPath = realpath(ROOTPATH . $path);

        if (!$this->allowedFileTypes($fullPath)) {
            return $this->failForbidden();
        }

        if (!$fullPath || !is_file($fullPath) || strpos($fullPath, realpath(ROOTPATH)) !== 0) {
            return $this->response->setJSON(['error' => lang('Backend.invalid', [lang('Fileeditor.path')])])->setStatusCode(400);
        }

        file_put_contents($fullPath, $content);

        return $this->response->setJSON(['success' => true]);
    }

    public function renameFile()
    {
        $valData = ([
            'path' => ['label' => '', 'rules' => 'required|max_length[255]|regex_match[/^[a-zA-Z0-9_ \-\.]+$/]'],
            'newName' => ['label' => '', 'rules' => 'required|max_length[255]|regex_match[/^[a-zA-Z0-9_ \-\.]+$/]'],
        ]);
        if ($this->validate($valData) == false) return $this->fail($this->validator->getErrors());
        $path = $this->request->getVar('path');
        $newName = $this->request->getVar('newName');
        $fullPath = realpath(ROOTPATH . $path);

        $newPath = dirname($fullPath) . DIRECTORY_SEPARATOR . $newName;

        if (!$this->allowedFileTypes($newName))
            return $this->failForbidden();

        if (!$fullPath || !file_exists($fullPath) || strpos($fullPath, realpath(ROOTPATH)) !== 0) {
            return $this->response->setJSON(['error' => lang('Backend.invalid', [lang('Fileeditor.path')])])->setStatusCode(400);
        }

        if (rename($fullPath, $newPath)) {
            return $this->response->setJSON(['success' => true]);
        } else {
            return $this->response->setJSON(['error' => lang('Fileeditor.renameFailed')])->setStatusCode(500);
        }
    }

    public function createFile()
    {
        $valData = ([
            'path' => ['label' => '', 'rules' => 'required|max_length[255]|regex_match[/^[a-zA-Z0-9_ \-\.]+$/]'],
            'name' => ['label' => '', 'rules' => 'required|max_length[255]|regex_match[/^[a-zA-Z0-9_ \-\.]+$/]'],
        ]);
        if ($this->validate($valData) == false) return $this->fail($this->validator->getErrors());
        $path = $this->request->getVar('path');
        $name = $this->request->getVar('name');
        $fullPath = realpath(ROOTPATH . $path);

        if (!$this->allowedFileTypes($name))
            return $this->failForbidden();

        if (!$fullPath || !is_dir($fullPath) || strpos($fullPath, realpath(ROOTPATH)) !== 0) {
            return $this->response->setJSON(['error' => lang('Backend.invalid', [lang('Fileeditor.path')])])->setStatusCode(400);
        }

        $newFilePath = $fullPath . DIRECTORY_SEPARATOR . $name;

        if (file_put_contents($newFilePath, '') !== false) {
            return $this->response->setJSON(['success' => true]);
        } else {
            return $this->response->setJSON(['error' => lang('Backend.notCreated', [$newFilePath])])->setStatusCode(500);
        }
    }

    public function createFolder()
    {
        $valData = ([
            'path' => ['label' => '', 'rules' => 'required|max_length[255]|regex_match[/^[a-zA-Z0-9_ \-\.]+$/]'],
            'name' => ['label' => '', 'rules' => 'required|max_length[255]|regex_match[/^[a-zA-Z0-9_ \-\.]+$/]'],
        ]);
        if ($this->validate($valData) == false) return $this->fail($this->validator->getErrors());
        $path = $this->request->getVar('path');
        $name = $this->request->getVar('name');
        $fullPath = realpath(ROOTPATH . $path);

        if (!$fullPath || !is_dir($fullPath) || strpos($fullPath, realpath(ROOTPATH)) !== 0) {
            return $this->response->setJSON(['error' => lang('Backend.invalid', [lang('Fileeditor.path')])])->setStatusCode(400);
        }

        $newFolderPath = $fullPath . DIRECTORY_SEPARATOR . $name;

        if (mkdir($newFolderPath)) {
            return $this->response->setJSON(['success' => true]);
        } else {
            return $this->response->setJSON(['error' => lang('Backend.notCreated', [$newFolderPath])])->setStatusCode(500);
        }
    }

    public function deleteFileOrFolder()
    {
        $valData = ([
            'path' => ['label' => '', 'rules' => 'required|max_length[255]|regex_match[/^[a-zA-Z0-9_ \-\.]+$/]'],
        ]);
        if ($this->validate($valData) == false) return $this->fail($this->validator->getErrors());
        $path = $this->request->getVar('path');
        $fullPath = realpath(ROOTPATH . $path);

        if (!$fullPath || strpos($fullPath, realpath(ROOTPATH)) !== 0) {
            return $this->response->setJSON(['error' => lang('Fileeditor.invalidFileOrFolder')])->setStatusCode(400);
        }

        if (is_dir($fullPath)) {
            $result = rmdir($fullPath);
        } else {
            $result = unlink($fullPath);
        }

        if ($result) {
            return $this->response->setJSON(['success' => true]);
        } else {
            return $this->response->setJSON(['error' => lang('Fileeditor.folderNotEmpty')])->setStatusCode(500);
        }
    }

    private function allowedFileTypes(string $file): bool
    {
        $extension = pathinfo($file, PATHINFO_EXTENSION);
        if (!in_array(strtolower($extension), $this->allowedExtensions)) {
            return false;
        }
        return true;
    }
}

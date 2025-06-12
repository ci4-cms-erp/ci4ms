<?php

namespace Modules\Fileeditor\Controllers;

class Fileeditor extends \Modules\Backend\Controllers\BaseController
{
    public function index()
    {
        return view('Modules\Fileeditor\Views\fileEditor', $this->defData);
    }

    public function listFiles()
    {
        $path = $this->request->getVar('path') ?? '/';
        $fullPath = realpath(ROOTPATH . $path);

        if (!$fullPath || strpos($fullPath, realpath(ROOTPATH)) !== 0) {
            return $this->response->setJSON(['error' => 'Geçersiz yol'])->setStatusCode(400);
        }

        $files = array_diff(scandir($fullPath), ['.', '..']);
        $result = [];

        foreach ($files as $file) {
            $filePath = $fullPath . DIRECTORY_SEPARATOR . $file;
            $result[] = [
                'title' => $file,
                'key' => str_replace(realpath(ROOTPATH), '', $filePath),
                'folder' => is_dir($filePath),
                'lazy' => is_dir($filePath)
            ];
        }

        return $this->response->setJSON($result);
    }

    public function readFile()
    {
        $path = $this->request->getVar('path');
        $fullPath = realpath(ROOTPATH . $path);

        if (!$fullPath || !is_file($fullPath) || strpos($fullPath, realpath(ROOTPATH)) !== 0) {
            return $this->response->setJSON(['error' => 'Geçersiz dosya'])->setStatusCode(400);
        }

        return $this->response->setJSON(['content' => file_get_contents($fullPath)]);
    }

    public function saveFile()
    {
        $path = $this->request->getVar('path');
        $content = $this->request->getVar('content');
        $fullPath = realpath(ROOTPATH . $path);

        if (!$fullPath || !is_file($fullPath) || strpos($fullPath, realpath(ROOTPATH)) !== 0) {
            return $this->response->setJSON(['error' => 'Geçersiz dosya'])->setStatusCode(400);
        }

        file_put_contents($fullPath, $content);

        return $this->response->setJSON(['success' => true]);
    }

    public function renameFile()
    {
        $path = $this->request->getVar('path');
        $newName = $this->request->getVar('newName');
        $fullPath = realpath(ROOTPATH . $path);
        $newPath = dirname($fullPath) . DIRECTORY_SEPARATOR . $newName;

        if (!$fullPath || !file_exists($fullPath) || strpos($fullPath, realpath(ROOTPATH)) !== 0) {
            return $this->response->setJSON(['error' => 'Geçersiz dosya'])->setStatusCode(400);
        }

        if (rename($fullPath, $newPath)) {
            return $this->response->setJSON(['success' => true]);
        } else {
            return $this->response->setJSON(['error' => 'Dosya adı değiştirilemedi'])->setStatusCode(500);
        }
    }

    public function createFile()
    {
        $path = $this->request->getVar('path');
        $name = $this->request->getVar('name');
        $fullPath = realpath(ROOTPATH . $path);

        if (!$fullPath || !is_dir($fullPath) || strpos($fullPath, realpath(ROOTPATH)) !== 0) {
            return $this->response->setJSON(['error' => 'Geçersiz yol'])->setStatusCode(400);
        }

        $newFilePath = $fullPath . DIRECTORY_SEPARATOR . $name;

        if (file_put_contents($newFilePath, '') !== false) {
            return $this->response->setJSON(['success' => true]);
        } else {
            return $this->response->setJSON(['error' => 'Dosya oluşturulamadı'])->setStatusCode(500);
        }
    }

    public function createFolder()
    {
        $path = $this->request->getVar('path');
        $name = $this->request->getVar('name');
        $fullPath = realpath(ROOTPATH . $path);

        if (!$fullPath || !is_dir($fullPath) || strpos($fullPath, realpath(ROOTPATH)) !== 0) {
            return $this->response->setJSON(['error' => 'Geçersiz yol'])->setStatusCode(400);
        }

        $newFolderPath = $fullPath . DIRECTORY_SEPARATOR . $name;

        if (mkdir($newFolderPath)) {
            return $this->response->setJSON(['success' => true]);
        } else {
            return $this->response->setJSON(['error' => 'Klasör oluşturulamadı'])->setStatusCode(500);
        }
    }

    public function moveFileOrFolder()
    {
        $sourcePath = $this->request->getVar('sourcePath');
        $targetPath = $this->request->getVar('targetPath');
        $fullSourcePath = realpath(ROOTPATH . $sourcePath);
        $fullTargetPath = realpath(ROOTPATH . $targetPath) . DIRECTORY_SEPARATOR . basename($fullSourcePath);

        if (!$fullSourcePath || !file_exists($fullSourcePath) || strpos($fullSourcePath, realpath(ROOTPATH)) !== 0) {
            return $this->response->setJSON(['error' => 'Geçersiz kaynak dosya veya klasör'])->setStatusCode(400);
        }

        if (!$fullTargetPath || strpos($fullTargetPath, realpath(ROOTPATH)) !== 0) {
            return $this->response->setJSON(['error' => 'Geçersiz hedef yol'])->setStatusCode(400);
        }

        if (rename($fullSourcePath, $fullTargetPath)) {
            return $this->response->setJSON(['success' => true]);
        } else {
            return $this->response->setJSON(['error' => 'Dosya veya klasör taşınamadı'])->setStatusCode(500);
        }
    }

    public function deleteFileOrFolder()
    {
        $path = $this->request->getVar('path');
        $fullPath = realpath(ROOTPATH . $path);

        if (!$fullPath || strpos($fullPath, realpath(ROOTPATH)) !== 0) {
            return $this->response->setJSON(['error' => 'Geçersiz dosya veya klasör'])->setStatusCode(400);
        }

        if (is_dir($fullPath)) {
            $result = rmdir($fullPath);
        } else {
            $result = unlink($fullPath);
        }

        if ($result) {
            return $this->response->setJSON(['success' => true]);
        } else {
            return $this->response->setJSON(['error' => 'Klasörün içi boş değil veya silme işlemi başarısız !'])->setStatusCode(500);
        }
    }
}

<?php

namespace Modules\ModulesInstaller\Controllers;

use ZipArchive;

class ModulesInstaller extends \Modules\Backend\Controllers\BaseController
{
    public function index()
    {
        return view('Modules\ModulesInstaller\Views\list', $this->defData);
    }

    public function moduleUpload()
    {
        if (!$this->request->isAJAX()) return $this->failForbidden();
        $file = $this->request->getFile('modules');

        if (!$file->isValid() || $file->getClientExtension() !== 'zip') {
            return $this->response->setJSON(['status' => 'error', 'message' => 'Geçersiz ZIP dosyası']);
        }

        $tempPath = WRITEPATH . 'tmp/';
        $zip = new ZipArchive();

        if ($zip->open($file->getTempName()) === true) {
            $zip->extractTo($tempPath);
            $zip->close();
        } else {
            return $this->response->setJSON(['status' => 'error', 'message' => 'ZIP dosyası açılamadı']);
        }

        // Çıkarılan klasörün adını bul (örneğin "Blog")
        $folders = array_filter(glob($tempPath . '*'), 'is_dir');
        $moduleFolder = basename(reset($folders));
        $finalPath = ROOTPATH . "modules/" . $moduleFolder;

        if (is_dir($finalPath)) {
            helper('filesystem');
            delete_files($tempPath, true);
            return $this->response->setJSON(['status' => 'error', 'message' => "Zaten '$moduleFolder' adında bir modül var"]);
        }

        // Klasörü taşı
        rename(reset($folders), $finalPath);

        // Geçici dizini temizle
        helper('filesystem');
        delete_files($tempPath, true);
        cache()->delete("{$this->logged_in_user->id}_permissions");
        return $this->response->setJSON(['status' => 'success', 'message' => "'$moduleFolder' modülü başarıyla yüklendi"]);
    }
}

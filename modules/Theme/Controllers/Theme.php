<?php

namespace Modules\Theme\Controllers;

class Theme extends \Modules\Backend\Controllers\BaseController
{
    public function index()
    {
        helper('modules/Theme/Helpers/theme_helper');
        return view('Modules\Theme\Views\index', $this->defData);
    }

    public function upload()
    {
        $valData = ([
            'theme' => ['label' => lang('Theme.backendTheme'), 'rules' => 'uploaded[theme]|ext_in[theme,zip]|mime_in[theme,application/x-zip,application/zip,application/x-zip-compressed,application/s-compressed,multipart/x-zip]'],
        ]);
        if ($this->validate($valData) == false) return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        $file = $this->request->getFile('theme');
        $tempPath = WRITEPATH . 'tmp/';
        $zip = new \ZipArchive();
        if ($zip->open($file->getTempName()) === true) {
            $zip->extractTo($tempPath);
            $zip->close();
        } else {
            return redirect()->back()->withInput()->with('errors', [lang('Theme.zipOpenFailed')]);
        }

        $themeName = str_replace('.zip', '', $file->getName());
        $paths = [
            APPPATH . "Config/templates/" . $themeName,
            APPPATH . "Controllers/templates/" . $themeName,
            APPPATH . "Helpers/templates/" . $themeName,
            APPPATH . "Libraries/templates/" . $themeName,
            APPPATH . "Views/templates/" . $themeName,
            ROOTPATH . "public/templates/" . $themeName
        ];
        helper('Modules\Theme\Helpers\themes');
        $duplicates = findDuplicateSubfolders($paths);
        if (!empty($duplicates)) {
            echo lang('Theme.foldersWithSameNameHeader');
            foreach ($duplicates as $folder => $dirs) {
                echo lang('Theme.foldersWithSameNameMessage',[$folder]);
                foreach ($dirs as $dir) {
                    echo lang('Install.foldersWithSameNameListItem', [$dir]);
                }
                echo "</ul>";
            }
            deleteFldr(rtrim($tempPath, '/'), true);
        } else {
            $log = install_theme_from_tmp($themeName);
            deleteFldr(rtrim($tempPath, '/'), true);
            return redirect()->back()->with('log', $log);
        }
    }
}

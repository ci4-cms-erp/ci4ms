<?php

namespace Modules\Backend\Controllers;

class Media extends BaseController
{
    public function index()
    {
        return view('Modules\Backend\Views\media', $this->defData);
    }

    public function elfinderConnection()
    {
        // ELFinder ayarlarını yapın
        $webpElfinder=$this->commonModel->selectOne('settings', ['option'=>'elfinderConvertWebp'],'content');
        $allowedFiles = json_decode(array_reduce(cache('settings'), fn($carry, $item) => $carry ?? ('allowedFiles' == $item->option ? $item : null))->content);
        $opts = array(
            // 'debug' => true,
            'roots' => array(
                // Items volume
                array(
                    'driver' => 'LocalFileSystem',           // driver for accessing file system (REQUIRED)
                    'path' => ROOTPATH.'/public/uploads/media/',                 // path to files (REQUIRED)
                    'URL' => site_url('uploads/media/'), // URL to files (REQUIRED)
                    'trashHash' => 't1_Lw',                     // elFinder's hash of trash folder
                    'winHashFix' => DIRECTORY_SEPARATOR !== '/', // to make hash same to Linux one on windows too
                    'uploadDeny' => array('all'),                // All Mimetypes not allowed to upload
                    'uploadAllow' => (array)$allowedFiles, // Mimetype `image` and `text/plain` allowed to upload
                    'uploadOrder' => array('deny', 'allow'),      // allowed Mimetype `image` and `text/plain` only
                    'accessControl' => array($this,'elfinderAccess'), // disable and hide dot starting files (OPTIONAL)
                    'dirrm'=>true
                ),
                // Trash volume
                array(
                    'id' => '1',
                    'driver' => 'Trash',
                    'path' => ROOTPATH.'/public/uploads/media/.trash/',
                    'tmbURL' => site_url('uploads/media/.trash/.tmb/'),
                    'winHashFix' => DIRECTORY_SEPARATOR !== '/', // to make hash same to Linux one on windows too
                    'uploadDeny' => array('all'),                // Recomend the same settings as the original volume that uses the trash
                    'uploadAllow' => (array)$allowedFiles, // Same as above
                    'uploadOrder' => array('deny', 'allow'),      // Same as above
                    'accessControl' => array($this,'elfinderAccess')                   // Same as above
                ),
            )
        );

        $char_map = ['.jpg' => '.webp', '.png' => '.webp', '.jpeg' => '.webp'];
        if ((bool)$webpElfinder===true && $this->request->getFileMultiple('upload')) {
            $webp_converter = new \claviska\SimpleImage();
            foreach ($this->request->getFileMultiple('upload') as $file) {
                $file_type = $file->getClientMimeType();
                // Dosya uzantısı kontrolü ve dönüştürme
                if ($file_type == 'image/gif' || $file_type == 'image/jpg' || $file_type == 'image/jpeg'
                    || $file_type == 'image/png' || $file_type == 'image/bmp') {
                    $file_name = $file->getName();
                    $file_path = ROOTPATH.'public/uploads/media/' . $file_name;
                    if ($file->isValid() && !$file->hasmoved() && $file->move(ROOTPATH . '/public/uploads/media/', $file_name)) {
                        $nImg = ROOTPATH . 'public/uploads/media/' . str_replace(array_keys($char_map), $char_map, $file_name);
                        $webp_converter
                            ->fromFile($file_path)
                            ->toFile($nImg, 'image/webp', ['quality' => 80]);
                        @unlink($file_path);
                    }
                }
            }
        }

        // ELFinder'ı başlatın
        $elfinder = new \elFinderConnector(new \elFinder($opts));
        $elfinder->run();
    }

    public function elfinderAccess($attr, $path, $data, $volume, $isDir, $relpath)
    {
        $basename = basename($path);
        return $basename[0] === '.'                  // if file/folder begins with '.' (dot)
        && strlen($relpath) !== 1           // but with out volume root
            ? !($attr == 'read' || $attr == 'write') // set read+write to false, other (locked+hidden) set to true
            :  null;                                 // else elFinder decide it itself
    }
}

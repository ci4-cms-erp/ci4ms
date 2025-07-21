<?php

namespace Modules\Media\Controllers;

/**
 * Media Controller for handling media management and elFinder integration.
 *
 * This controller extends the BaseController from the Backend module and provides
 * functionalities for displaying the media view and managing file operations via elFinder.
 *
 * Methods:
 * - index(): Renders the media view page.
 * - elfinderConnection(): Configures and initializes elFinder file manager with custom settings,
 *   including allowed file types, trash management, and optional WebP conversion for image uploads.
 * - elfinderAccess($attr, $path, $data, $volume, $isDir, $relpath): Access control callback for elFinder,
 *   restricting access to files/folders starting with a dot ('.').
 *
 * @package Modules\Media\Controllers
 */
class Media extends \Modules\Backend\Controllers\BaseController
{
    public function index()
    {
        return view('Modules\Media\Views\media', $this->defData);
    }

    public function elfinderConnection()
    {
        // ELFinder ayarlarını yapın
        $allowedFiles = $this->defData['settings']->allowedFiles;
        $opts = array(
            // 'debug' => true,
            'roots' => array(
                // Items volume
                array(
                    'driver' => 'LocalFileSystem',           // driver for accessing file system (REQUIRED)
                    'path' => ROOTPATH . '/public/uploads/media/',                 // path to files (REQUIRED)
                    'URL' => site_url('uploads/media/'), // URL to files (REQUIRED)
                    'trashHash' => 't1_Lw',                     // elFinder's hash of trash folder
                    'winHashFix' => DIRECTORY_SEPARATOR !== '/', // to make hash same to Linux one on windows too
                    'uploadDeny' => array('all'),                // All Mimetypes not allowed to upload
                    'uploadAllow' => (array)$allowedFiles, // Mimetype `image` and `text/plain` allowed to upload
                    'uploadOrder' => array('deny', 'allow'),      // allowed Mimetype `image` and `text/plain` only
                    'accessControl' => array($this, 'elfinderAccess'), // disable and hide dot starting files (OPTIONAL)
                    'dirrm' => true
                ),
                // Trash volume
                array(
                    'id' => '1',
                    'driver' => 'Trash',
                    'path' => ROOTPATH . '/public/uploads/media/.trash/',
                    'tmbURL' => site_url('uploads/media/.trash/.tmb/'),
                    'winHashFix' => DIRECTORY_SEPARATOR !== '/', // to make hash same to Linux one on windows too
                    'uploadDeny' => array('all'),                // Recomend the same settings as the original volume that uses the trash
                    'uploadAllow' => (array)$allowedFiles, // Same as above
                    'uploadOrder' => array('deny', 'allow'),      // Same as above
                    'accessControl' => array($this, 'elfinderAccess')                   // Same as above
                )
            ),
            'bind' => array(
                'upload.presave' => array(function (&$thash, &$name, $tmpname, $elfinder, $volume) {
                    $convertWebp = (bool)$this->defData['settings']->elfinderConvertWebp->scalar;
                    if ($convertWebp) {
                        $char_map = ['.jpg' => '.webp', '.png' => '.webp', '.jpeg' => '.webp'];
                        $ext = strtolower(strrchr($name, '.'));
                        if (in_array($ext, array('.jpg', '.jpeg', '.png'))) {
                            $webpName = str_replace(array_keys($char_map), $char_map, $name);
                            $webpPath = dirname($tmpname) . DIRECTORY_SEPARATOR . $webpName;
                            $img = new \claviska\SimpleImage();
                            $img->fromFile($tmpname)->toFile($webpPath, 'image/webp', ['quality' => 80]);
                            $name = $webpName;
                            $tmpname = $webpPath;
                        }
                    }
                })
            )
        );

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

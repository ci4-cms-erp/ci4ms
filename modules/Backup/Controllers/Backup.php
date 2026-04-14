<?php

namespace Modules\Backup\Controllers;

class Backup extends \Modules\Backend\Controllers\BaseController
{
    public function index()
    {
        if ($this->request->is('post') && $this->request->isAJAX()) {
            $parsed = $this->commonBackendLibrary->getDatatablesPagination($this->request->getPost());
            $like = [];
            $postData = [];
            if (!empty($parsed['searchString'])) $like = ['filename' => $parsed['searchString']];
            $results = $this->commonModel->lists('db_backups', '*', $postData, 'id DESC', $parsed['length'], $parsed['start'], $like);
            $totalRecords = $this->commonModel->count('db_backups', $postData, $like);
            $totalDisplayRecords = $totalRecords;
            helper('number');
            foreach ($results as $result) {
                $result->file_size = number_to_size($result->file_size, 2);
                $result->created_at = date('Y-m-d H:i:s', strtotime($result->created_at));
                $result->actions = '<a class="btn btn-primary btn-sm" href="' . route_to('backupDownload', $result->filename) . '"><i class="fas fa-download"></i></a>
                <button type="button" class="btn btn-danger btn-sm" onclick="remove(' . $result->id . ')"><i class="fas fa-trash"></i></button>';
            }

            $data = [
                'draw' => $parsed['draw'],
                'iTotalRecords' => $totalRecords,
                'iTotalDisplayRecords' => $totalDisplayRecords,
                'aaData' => $results,
            ];
            return $this->respond($data, 200);
        }

        $this->defData['stats'] = [
            'totalBackups' => $this->commonModel->count('db_backups'),
            'lastBackup' => $this->commonModel->selectOne('db_backups', [], 'created_at', 'id DESC')->created_at ?? '-'
        ];
        return view('Modules\Backup\Views\list', $this->defData);
    }

    public function create()
    {
        if (!$this->request->isAJAX()) return $this->failForbidden();
        $dbBackup = new \Modules\Backup\Libraries\DbBackup();
        $fileName = 'backup_' . date('Y-m-d_H-i-s');
        $format   = 'zip';

        // Create backup content
        $content = $dbBackup->backup(['format' => $format, 'filename' => $fileName]);

        // Save the file to writable/backups folder
        $path = WRITEPATH . 'backups/';
        if (! is_dir($path)) {
            mkdir($path, 0755, true);
        }

        $fullPath = $path . $fileName . '.' . $format;
        file_put_contents($fullPath, $content);
        $file = new \CodeIgniter\Files\File($fullPath);

        if ($this->commonModel->create('db_backups', ['filename' => $fileName . '.zip', 'file_size' => $file->getSize(), 'created_by' => session('logged_in')]))
            return $this->respond(['success' => true, 'download_url' => route_to('backupDownload', $fileName . '.zip')]);
        else {
            @unlink($fullPath);
            return $this->respond(['success' => false, 'error' => lang('Backend.notCreated', [$fileName . '.zip'])], 400);
        }
    }


    public function delete(int $id)
    {
        $infos = $this->commonModel->selectOne('db_backups', ['id' => $id]);
        if ($this->commonModel->remove('db_backups', ['id' => $id])) {
            @unlink(WRITEPATH . 'backups/' . $infos->filename);
            return $this->respond(['success' => true, 'message' => lang('Backend.deleted', [$infos->filename])]);
        }
        $this->respond(['success' => false, 'error' => lang('Backend.notDeleted', [$infos->filename])], 400);
    }

    public function restore()
    {
        $valData = ([
            'backup_file' => ['label' => 'Backup File', 'rules' => 'uploaded[backup_file]|ext_in[backup_file,zip]'],
        ]);
        if ($this->validate($valData) == false) return redirect()->route('backup')->withInput()->with('errors', $this->validator->getErrors());
        $file = $this->request->getFile('backup_file');

        if ($file && $file->isValid() && ! $file->hasMoved()) {
            $newName    = $file->getRandomName();
            $uploadPath = WRITEPATH . 'uploads/';
            if (! is_dir($uploadPath)) {
                mkdir($uploadPath, 0755, true);
            }
            $ext = $file->getExtension();
            $file->move($uploadPath, $newName);
            $filePath = WRITEPATH . 'uploads/' . $newName;
            $sqlPath  = $filePath;
                        if ($ext === 'zip') {
                $zip = new \ZipArchive();
                if ($zip->open($filePath) === true) {
                    $extractedSql = '';
                    for ($i = 0; $i < $zip->numFiles; $i++) {
                        $entryName = $zip->getNameIndex($i);
                        $safeEntryName = basename($entryName);

                        if (preg_match('/\.sql$/i', $safeEntryName)) {
                            $fileContent = $zip->getFromIndex($i);
                            $extractedSql = $uploadPath . $safeEntryName;
                            file_put_contents($extractedSql, $fileContent);
                            break;
                        }
                    }
                    $zip->close();
                    @unlink($filePath);

                    if (empty($extractedSql)) {
                        return redirect()->route('backup')->with('error', lang('Backup.dbNotRestore'));
                    }
                    $sqlPath = $extractedSql;
                } else {
                    @unlink($filePath);
                    return redirect()->route('backup')->with('error', lang('Backup.dbNotRestore'));
                }
            }
  }
            }

            $dbBackup = new \Modules\Backup\Libraries\DbBackup();
            if ($dbBackup->restore($sqlPath)) {
                @unlink($sqlPath);
                cache()->delete('menus');
                cache()->delete('settings');
                cache()->delete('shield_auth_dynamic_config');
                cache()->delete('sidebar_menu');
                return redirect()->route('backup')->with('message', lang('Backup.dbRestore'));
            }
        }
        return redirect()->route('backup')->with('error', lang('Backup.dbNotRestore'));
    }

    public function download($fileName)
    {
        $fileName = basename($fileName);
        if (!preg_match('/^backup_[\d\-_]+\.zip$/', $fileName)) {
            return redirect()->route('backup')->with('error', 'Geçersiz dosya adı.');
        }
        $path = WRITEPATH . 'backups/' . $fileName;
        if (file_exists($path)) {
            return $this->response->download($path, null);
        }
        return redirect()->route('backup')->with('error', 'Dosya bulunamadı.');
    }
}

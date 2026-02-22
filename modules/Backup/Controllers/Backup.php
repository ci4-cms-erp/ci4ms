<?php

namespace Modules\Backup\Controllers;

class Backup extends \Modules\Backend\Controllers\BaseController
{
    public function index()
    {
        if ($this->request->is('post') && $this->request->isAJAX()) {
            $data = clearFilter($this->request->getPost());
            $like = $data['search']['value'];
            $l = [];
            $postData = [];

            if (!empty($like)) $l = ['filename' => $like];
            $results = $this->commonModel->lists('db_backups', '*', $postData, 'id DESC', ($data['length'] == '-1') ? 0 : (int)$data['length'], ($data['length'] == '-1') ? 0 : (int)$data['start'], $l);
            $totalRecords = $this->commonModel->count('db_backups', $postData, $l);
            $totalDisplayRecords = $totalRecords;
            helper('number');
            foreach ($results as $result) {
                $result->file_size = number_to_size($result->file_size, 2);
                $result->created_at = date('Y-m-d H:i:s', strtotime($result->created_at));
                $result->actions = '<a class="btn btn-primary btn-sm" href="' . route_to('backupDownload', $result->filename) . '"><i class="fas fa-download"></i></a>
                <button type="button" class="btn btn-danger btn-sm" onclick="remove(' . $result->id . ')"><i class="fas fa-trash"></i></button>';
            }

            $data = [
                'draw' => intval($data['draw']),
                'iTotalRecords' => $totalRecords,
                'iTotalDisplayRecords' => $totalDisplayRecords,
                'aaData' => $results,
            ];
            return $this->respond($data, 200);
        }

        return view('Modules\Backup\Views\list', $this->defData);
    }

    public function create()
    {
        if (!$this->request->isAJAX()) return $this->failForbidden();
        $dbBackup = new \Modules\Backup\Libraries\DbBackup();
        $fileName = 'backup_' . date('Y-m-d_H-i-s');
        $format   = 'zip';

        // Yedek içeriğini oluştur
        $content = $dbBackup->backup(['format' => $format, 'filename' => $fileName]);

        // Dosyayı writable/backups klasörüne kaydet
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
            'backup_file' => ['label' => 'Backup File', 'rules' => 'ext_in[zip]'],
        ]);
        if ($this->validate($valData) == false) return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
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
                    $zip->extractTo($uploadPath);
                    $sqlPath = $uploadPath . $zip->getNameIndex(0);
                    $zip->close();
                    @unlink($filePath);
                }
            }

            $dbBackup = new \Modules\Backup\Libraries\DbBackup();
            if ($dbBackup->restore($sqlPath)) {
                @unlink($sqlPath);
                return redirect()->back()->with('message', lang('Backup.dbRestore'));
            }
        }
        return redirect()->back()->with('error', lang('Backup.dbNotRestore'));
    }

    public function download($fileName)
    {
        $fileName = basename($fileName);
        if (!preg_match('/^backup_[\d\-_]+\.zip$/', $fileName)) {
            return redirect()->back()->with('error', 'Geçersiz dosya adı.');
        }
        $path = WRITEPATH . 'backups/' . $fileName;
        if (file_exists($path)) {
            return $this->response->download($path, null);
        }
        return redirect()->back()->with('error', 'Dosya bulunamadı.');
    }
}

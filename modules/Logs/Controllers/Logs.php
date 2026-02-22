<?php

namespace Modules\Logs\Controllers;

use Modules\Logs\Libraries\LogViewer;

class Logs extends \Modules\Backend\Controllers\BaseController
{
    public function index()
    {
        $logViewer = new LogViewer();

        // Handle File Download
        if ($dl = $this->request->getGet('dl')) {
            $fileName = basename(base64_decode($dl));
            $filePath = WRITEPATH . 'logs/' . $fileName;
            if (is_file($filePath)) {
                return $this->response->download($filePath, null);
            }
        }

        $files = $logViewer->getFiles();
        $fileName = $this->request->getGet('f') ? base64_decode($this->request->getGet('f')) : ($files[0] ?? null);

        $this->defData['files'] = $files;
        $this->defData['currentFile'] = $fileName;
        $this->defData['logs'] = $fileName ? $logViewer->getLogs($fileName) : [];

        return view('Modules\Logs\Views\list', $this->defData);
    }

    public function delete_post()
    {
        if (!$this->request->isAJAX()) return $this->failForbidden();

        $logViewer = new LogViewer();
        $file = base64_decode($this->request->getPost('id'));

        if ($logViewer->deleteFile($file)) {
            return $this->respond(['status' => 'success', 'message' => lang('Backend.deleted', [$file])]);
        }

        return $this->respond(['status' => 'error', 'message' => lang('Backend.notDeleted', [$file])]);
    }
}

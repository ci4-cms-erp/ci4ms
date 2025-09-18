<?php

namespace Modules\Logs\Controllers;

use CILogViewer\CILogViewer;

class Logs extends \Modules\Backend\Controllers\BaseController
{
    public function index()
    {
        $this->defData['logViewer'] = new CILogViewer();
        return view('Modules\Logs\Views\list', $this->defData);
    }
}

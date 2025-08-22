<?php

namespace Modules\Backend\Controllers;

class Errors extends BaseController
{
    public function error_403()
    {
        $this->defData['title'] = (object)['pagename' => '403 Forbidden'];
        return view('Modules\Backend\Views\errors\html\error_403', $this->defData);
    }

    public function error_404()
    {
        $this->defData['title'] = (object)['pagename' => '404  not found'];
        return view('Modules\Backend\Views\errors\html\error_404', $this->defData);
    }
}

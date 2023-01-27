<?php

namespace Modules\Backend\Controllers;

class Media extends BaseController
{
    public function index()
    {
        return view('Modules\Backend\Views\media',$this->defData);
    }
}

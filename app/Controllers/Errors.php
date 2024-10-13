<?php

namespace App\Controllers;

use App\Controllers\BaseController;

class Errors extends BaseController
{
    public function error404()
    {
        $this->defData['referer']=$this->request->getServer('HTTP_REFERER');
        echo view('templates/'.$this->defData['settings']->templateInfos->path.'/404',$this->defData);
    }
}

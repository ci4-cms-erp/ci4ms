<?php namespace Modules\Backend\Controllers;

class Backend extends BaseController
{
    public function index()
    {
        $this->defData['dashboard'] = (object)['pageCount'=>(object)['icon' => '<i class="far fa-copy"></i>', 'count' => $this->commonModel->count('pages',[]),'lang'=>'pages'],
            'blogCount'=>(object)['icon'=>'<i class="far fa-file-alt"></i>','count'=>$this->commonModel->count('blog',[]),'lang'=>'blogs']];
        return view('Modules\Backend\Views\welcome_message', $this->defData);
    }
}

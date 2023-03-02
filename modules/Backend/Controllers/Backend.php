<?php namespace Modules\Backend\Controllers;

class Backend extends BaseController
{
    public function index()
    {
        $db      = \Config\Database::connect();
        $counts=$db->table('pages')->select('count(pages.id) as pageCount, (select count(blog.id) from blog) as blogCount')->get()->getRow();
        $this->defData['dashboard'] = (object)['pageCount'=>(object)['icon' => '<i class="far fa-copy"></i>', 'count' => $counts->pageCount,'lang'=>'pages'],
            'blogCount'=>(object)['icon'=>'<i class="far fa-file-alt"></i>','count'=>$counts->blogCount,'lang'=>'blogs']];
        return view('Modules\Backend\Views\welcome_message', $this->defData);
    }
}

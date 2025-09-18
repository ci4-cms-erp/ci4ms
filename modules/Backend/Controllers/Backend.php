<?php

namespace Modules\Backend\Controllers;

class Backend extends BaseController
{
    public function index()
    {
        $counts = $this->commonModel->selectOne('pages', [], 'count(id) as pageCount, (select count(id) from ci4ms_blog) as blogCount');
        $this->defData['dashboard'] = (object)[
            'pageCount' => (object)['icon' => '<i class="far fa-copy"></i>', 'count' => $counts->pageCount, 'lang' => lang('Pages.pages')],
            'blogCount' => (object)['icon' => '<i class="far fa-file-alt"></i>', 'count' => $counts->blogCount, 'lang' => lang('Blog.blogs')]
        ];
        return view('Modules\Backend\Views\welcome_message', $this->defData);
    }

    public function test()
    {
        return 'çalışıyor';
    }
}

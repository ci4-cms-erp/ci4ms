<?php

namespace Modules\Install\Filters;

use ci4commonmodel\Models\CommonModel;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class InstallFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $commonModel = new CommonModel();
        if ($commonModel->isHave('users', [])) return show_404();
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        //
    }
}

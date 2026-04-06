<?php

namespace Modules\Install\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class InstallFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (file_exists(WRITEPATH . 'install.lock')) return show_404();
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        //
    }
}

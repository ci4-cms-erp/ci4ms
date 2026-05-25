<?php

namespace Modules\Install\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class InstallFilter implements FilterInterface
{
    /**
     * Block any /install request once the application is installed.
     *
     * Two independent signals are checked so a partial install (e.g. the
     * controller wrote .env but crashed before creating install.lock) still
     * keeps the installer locked. Either signal returning truthy is enough
     * to 404 and prevent re-installation that would let an attacker
     * overwrite credentials or reseed the admin account.
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        if (file_exists(WRITEPATH . 'install.lock')) {
            return show_404();
        }
        if (file_exists(ROOTPATH . '.env')) {
            return show_404();
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        //
    }
}

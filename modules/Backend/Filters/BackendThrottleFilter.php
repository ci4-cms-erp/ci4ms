<?php

namespace Modules\Backend\Filters;

use CodeIgniter\HTTP\RequestInterface;

class BackendThrottleFilter extends \App\Filters\ThrottleFilter
{
    public function before(RequestInterface $request, $arguments = null)
    {
        // Argüman ne olursa olsun 'backend' profilini zorla.
        return parent::before($request, ['backend']);
    }
}

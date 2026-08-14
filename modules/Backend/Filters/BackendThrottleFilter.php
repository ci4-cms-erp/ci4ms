<?php

declare(strict_types=1);

namespace Modules\Backend\Filters;

use CodeIgniter\HTTP\RequestInterface;

class BackendThrottleFilter extends \App\Filters\ThrottleFilter
{
    public function before(RequestInterface $request, $arguments = null)
    {
        // Force the 'backend' profile regardless of the argument.
        return parent::before($request, ['backend']);
    }
}

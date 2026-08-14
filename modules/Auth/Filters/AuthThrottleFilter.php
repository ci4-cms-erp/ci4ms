<?php

declare(strict_types=1);

namespace Modules\Auth\Filters;

use CodeIgniter\HTTP\RequestInterface;

/**
 * Rate-limit for Shield auth routes (login/register/forgot, etc.).
 *
 * The `auth-rates` alias is routed to this class (see Config\Filters).
 * Instead of Shield's bare 429, it always runs with the 'auth' profile and
 * shows the branded error_429 page with a REAL countdown.
 */
class AuthThrottleFilter extends \App\Filters\ThrottleFilter
{
    public function before(RequestInterface $request, $arguments = null)
    {
        // Force the 'auth' profile regardless of the argument passed in.
        return parent::before($request, ['auth']);
    }
}

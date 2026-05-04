<?php

declare(strict_types=1);

namespace Modules\Backend\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * CsrfTokenRefreshFilter
 *
 * After filter that injects the fresh CSRF token into every
 * AJAX response header so the client-side JS (ci4ms.js) can
 * update its stored token for the next request.
 *
 * This is required because security.regenerate = true causes
 * CI4 to issue a new token after every POST — without this
 * filter the client would be stuck with a stale token.
 */
class CsrfTokenRefreshFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        // No action needed before the request
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        /** @var \CodeIgniter\HTTP\IncomingRequest $request */
        if ($request->isAJAX()) {
            $security = \Config\Services::security();
            $response->setHeader('X-CSRF-TOKEN', $security->getHash());
        }

        return $response;
    }
}

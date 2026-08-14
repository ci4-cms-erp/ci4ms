<?php

declare(strict_types=1);

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * General-purpose, profile-based rate-limit filter.
 *
 * Usage (route / group):
 *   ['filter' => 'throttle:backend']   // Config\Throttle::$profiles['backend']
 *   ['filter' => 'throttle:api']
 *
 * When the limit is exceeded:
 *   - Web request  → branded error_429 page, counter with the ACTUAL remaining time (Retry-After)
 *   - API / AJAX   → JSON { status:429, retry_after:N }
 * In both cases HTTP 429 + a `Retry-After` header is set.
 */
class ThrottleFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        // Skip CLI / non-HTTP requests
        if (! $request instanceof IncomingRequest) {
            return;
        }

        $config  = config('Throttle');
        $profile = $arguments[0] ?? $config->default;
        [$capacity, $seconds] = $config->profiles[$profile] ?? $config->profiles[$config->default];

        $throttler = service('throttler');
        $key       = $this->buildKey($request, $profile);

        if ($throttler->check($key, (int) $capacity, (int) $seconds) === false) {
            return $this->reject($request, $throttler->getTokenTime());
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // no-op
    }

    /**
     * Bucket key: profile + IP (+ logged-in user).
     */
    protected function buildKey(IncomingRequest $request, string $profile): string
    {
        $id = (function_exists('auth') && auth()->loggedIn()) ? (string) auth()->id() : 'guest';

        return md5('throttle:' . $profile . ':' . $request->getIPAddress() . ':' . $id);
    }

    /**
     * Build the 429 response (HTML or JSON depending on content type).
     */
    protected function reject(IncomingRequest $request, int $retryAfter): ResponseInterface
    {
        $response = service('response')
            ->setStatusCode(429)
            ->setHeader('Retry-After', (string) $retryAfter);

        if ($this->wantsJson($request)) {
            return $response->setJSON([
                'status'      => 429,
                'error'       => 'Too Many Requests',
                'retry_after' => $retryAfter,
            ]);
        }

        return $response->setBody(
            view('Modules\Backend\Views\errors\html\error_429', ['retryAfter' => $retryAfter])
        );
    }

    /**
     * Does the request expect JSON? (AJAX, Accept: application/json, or an API path prefix)
     */
    protected function wantsJson(IncomingRequest $request): bool
    {
        if ($request->isAJAX()) {
            return true;
        }

        if (str_contains($request->getHeaderLine('Accept'), 'application/json')) {
            return true;
        }

        $path = ltrim($request->getUri()->getPath(), '/');
        foreach ((array) config('Throttle')->apiPrefixes as $prefix) {
            $prefix = trim((string) $prefix, '/');
            if ($prefix !== '' && ($path === $prefix || str_starts_with($path, $prefix . '/'))) {
                return true;
            }
        }

        return false;
    }
}

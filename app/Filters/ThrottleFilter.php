<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Genel amaçlı, profil tabanlı rate-limit filtresi.
 *
 * Kullanım (route / grup):
 *   ['filter' => 'throttle:backend']   // Config\Throttle::$profiles['backend']
 *   ['filter' => 'throttle:api']
 *
 * Limit aşılınca:
 *   - Web isteği   → markalı error_429 sayfası, sayaç GERÇEK kalan süreyle (Retry-After)
 *   - API / AJAX   → JSON { status:429, retry_after:N }
 * Her iki durumda da HTTP 429 + `Retry-After` header'ı set edilir.
 */
class ThrottleFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        // CLI / non-HTTP istekleri atla
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
     * Bucket anahtarı: profil + IP (+ giriş yapan kullanıcı).
     */
    protected function buildKey(IncomingRequest $request, string $profile): string
    {
        $id = (function_exists('auth') && auth()->loggedIn()) ? (string) auth()->id() : 'guest';

        return md5('throttle:' . $profile . ':' . $request->getIPAddress() . ':' . $id);
    }

    /**
     * 429 yanıtını üret (içerik tipine göre HTML veya JSON).
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
     * İstek JSON mı bekliyor? (AJAX, Accept: application/json veya API path öneki)
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

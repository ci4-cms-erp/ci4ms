<?php

namespace Modules\DevGate\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Modules\DevGate\Config\DevGate;

class DevGateFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        // Only activate in the 'development' environment
        if (ENVIRONMENT !== 'development') {
            return null;
        }

        /** @var DevGate $config */
        $config = config('DevGate');

        // Check if the current path is excluded
        $currentPath = '/' . ltrim($request->getPath(), '/');
        foreach ($config->except as $pattern) {
            if (@preg_match($pattern, $currentPath)) {
                return null;
            }
        }

        // Credentials sent by the browser
        $sentUser = $request->getServer('PHP_AUTH_USER') ?? '';
        $sentPass = $request->getServer('PHP_AUTH_PW')  ?? '';

        // Check if the user exists and the password matches
        if ($this->isAuthenticated($sentUser, $sentPass, $config)) {
            return null; // Access granted
        }

        // Unauthorized → send Basic Auth challenge and lock the page
        return service('response')
            ->setStatusCode(401)
            ->setHeader('WWW-Authenticate', 'Basic realm="' . $config->realm . '", charset="UTF-8"')
            ->setHeader('Content-Type', 'text/html; charset=utf-8')
            ->setBody($this->unauthorizedBody());
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Nothing to do in the after hook
    }

    // -------------------------------------------------------------------------

    private function isAuthenticated(string $user, string $pass, DevGate $config): bool
    {
        if ($user === '' || ! array_key_exists($user, $config->users)) {
            return false;
        }

        $stored = $config->users[$user];

        return $config->useHashedPasswords
            ? password_verify($pass, $stored)
            : hash_equals($stored, $pass); // timing-safe comparison
    }

    private function unauthorizedBody(): string
    {
        return <<<HTML
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>401 – Unauthorized</title>
            <style>
                *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
                body {
                    font-family: system-ui, -apple-system, sans-serif;
                    background: #0f172a;
                    color: #e2e8f0;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    min-height: 100vh;
                    padding: 2rem;
                }
                .card {
                    background: #1e293b;
                    border: 1px solid #334155;
                    border-radius: 12px;
                    padding: 3rem 2.5rem;
                    max-width: 440px;
                    width: 100%;
                    text-align: center;
                }
                .badge {
                    display: inline-block;
                    background: #7c3aed22;
                    color: #a78bfa;
                    border: 1px solid #7c3aed55;
                    font-size: 0.75rem;
                    font-weight: 600;
                    letter-spacing: 0.1em;
                    text-transform: uppercase;
                    padding: 0.3rem 0.8rem;
                    border-radius: 99px;
                    margin-bottom: 1.5rem;
                }
                h1 { font-size: 1.5rem; font-weight: 700; margin-bottom: 0.75rem; }
                p  { color: #94a3b8; font-size: 0.95rem; line-height: 1.6; }
                .hint {
                    margin-top: 2rem;
                    padding: 0.85rem 1rem;
                    background: #0f172a;
                    border: 1px solid #1e3a5f;
                    border-radius: 8px;
                    font-size: 0.8rem;
                    color: #64748b;
                }
            </style>
        </head>
        <body>
            <div class="card">
                <div class="badge">Development Mode</div>
                <h1>Unauthorized Access</h1>
                <p>This environment is restricted to authorized developers only. Please enter a valid username and password to continue.</p>
                <div class="hint">
                    Users are defined in <code>Modules/DevGate/Config/DevGate.php</code>
                </div>
            </div>
        </body>
        </html>
        HTML;
    }
}

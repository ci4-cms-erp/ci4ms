<?php

declare(strict_types=1);

namespace Tests\Support\Settings;

use CodeIgniter\HTTP\CURLRequest;
use CodeIgniter\HTTP\Response;
use CodeIgniter\HTTP\ResponseInterface;
use Config\App;
use RuntimeException;

/**
 * Offline CURLRequest double for the updater gate tests.
 *
 * Satisfies the `CURLRequest $client` type hint UpdateService accepts through its
 * constructor while never opening a socket. The parent constructor is deliberately
 * NOT called: it initialises curl handles and share options this stub has no use
 * for, and request() is overridden end to end so no parent state is ever read.
 *
 * Every call is recorded with its URL and its options array, which is what lets the
 * tests assert both "zero files were downloaded" and "allow_redirects was passed".
 */
final class StubCurlRequest extends CURLRequest
{
    /**
     * Every request the service made, in order.
     *
     * @var list<array{method: string, url: string, options: array<string, mixed>}>
     */
    public array $calls = [];

    /**
     * Registered responses: exact matches first, then substring matches.
     *
     * @var list<array{needle: string, exact: bool, status: int, body: string, throw: bool, headers: array<string, string>}>
     */
    private array $routes = [];

    /**
     * Builds the stub without touching the real CURLRequest constructor.
     *
     * @noinspection PhpMissingParentConstructorInspection
     */
    public function __construct()
    {
    }

    /**
     * Registers a response for an exact URL.
     *
     * @param string                $url     Exact request URL
     * @param int                   $status  HTTP status code to return
     * @param string                $body    Response body
     * @param array<string, string> $headers Response headers, e.g. a forged Content-Length
     *
     * @return self
     */
    public function on(string $url, int $status, string $body, array $headers = []): self
    {
        $this->routes[] = ['needle' => $url, 'exact' => true, 'status' => $status, 'body' => $body, 'throw' => false, 'headers' => $headers];

        return $this;
    }

    /**
     * Registers a response for any URL containing the needle.
     *
     * @param string                $needle  Substring to match
     * @param int                   $status  HTTP status code to return
     * @param string                $body    Response body
     * @param array<string, string> $headers Response headers, e.g. a forged Content-Length
     *
     * @return self
     */
    public function onContains(string $needle, int $status, string $body, array $headers = []): self
    {
        $this->routes[] = ['needle' => $needle, 'exact' => false, 'status' => $status, 'body' => $body, 'throw' => false, 'headers' => $headers];

        return $this;
    }

    /**
     * Registers a transport level failure for any URL containing the needle.
     *
     * @param string $needle  Substring to match
     * @param string $message Exception message
     *
     * @return self
     */
    public function throwOn(string $needle, string $message): self
    {
        $this->routes[] = ['needle' => $needle, 'exact' => false, 'status' => 0, 'body' => $message, 'throw' => true, 'headers' => []];

        return $this;
    }

    /**
     * Records the call and replays the registered response.
     *
     * @param string               $method  HTTP verb
     * @param string               $url     Absolute request URL
     * @param array<string, mixed> $options Per request options
     *
     * @return ResponseInterface
     *
     * @throws RuntimeException When a throwOn() route matches
     */
    public function request($method, string $url, array $options = []): ResponseInterface
    {
        $this->calls[] = ['method' => (string) $method, 'url' => $url, 'options' => $options];

        foreach ($this->routes as $route) {
            $matches = $route['exact'] ? $route['needle'] === $url : str_contains($url, $route['needle']);

            if (!$matches) {
                continue;
            }

            if ($route['throw']) {
                throw new RuntimeException($route['body']);
            }

            return $this->buildResponse($route['status'], $route['body'], $route['headers']);
        }

        return $this->buildResponse(404, '');
    }

    /**
     * Every recorded call whose URL contains the needle.
     *
     * @param string $needle Substring to match
     *
     * @return list<array{method: string, url: string, options: array<string, mixed>}>
     */
    public function callsMatching(string $needle): array
    {
        return array_values(array_filter(
            $this->calls,
            static fn (array $call): bool => str_contains($call['url'], $needle)
        ));
    }

    /**
     * Options array of the first recorded call whose URL contains the needle.
     *
     * @param string $needle Substring to match
     *
     * @return array<string, mixed> Empty array when no call matched
     */
    public function optionsFor(string $needle): array
    {
        $matched = $this->callsMatching($needle);

        return $matched === [] ? [] : $matched[0]['options'];
    }

    /**
     * Forgets every recorded call, keeping the registered routes.
     *
     * @return void
     */
    public function forgetCalls(): void
    {
        $this->calls = [];
    }

    /**
     * Wraps a status/body pair into a real CI4 Response object.
     *
     * @param int                   $status  HTTP status code
     * @param string                $body    Response body
     * @param array<string, string> $headers Response headers to set verbatim
     *
     * @return ResponseInterface
     */
    private function buildResponse(int $status, string $body, array $headers = []): ResponseInterface
    {
        $response = new Response(new App());
        $response->setStatusCode($status);
        $response->setBody($body);

        foreach ($headers as $name => $value) {
            $response->setHeader($name, $value);
        }

        return $response;
    }
}

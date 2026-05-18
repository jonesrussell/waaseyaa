<?php

declare(strict_types=1);

namespace Waaseyaa\AI\Agent\Tests\Unit\Mcp\Fixture;

use Waaseyaa\HttpClient\HttpClientInterface;
use Waaseyaa\HttpClient\HttpRequestException;
use Waaseyaa\HttpClient\HttpResponse;

/**
 * Deterministic HTTP client stub used by the MCP client unit + integration
 * tests. Responses are enqueued in FIFO order; an unexpected request will
 * fail loudly so missing fixtures surface immediately.
 */
final class StubHttpClient implements HttpClientInterface
{
    /** @var list<HttpResponse|HttpRequestException|\Closure> */
    private array $queue = [];

    /**
     * @var list<array{
     *     method: string,
     *     url: string,
     *     headers: array<string, string>,
     *     body: array<string, mixed>|string|null,
     * }>
     */
    public array $requests = [];

    public function enqueueResponse(HttpResponse $response): void
    {
        $this->queue[] = $response;
    }

    /**
     * Enqueue a JSON response. Wraps the payload in a 200 OK by default.
     *
     * @param array<string, mixed> $payload
     */
    public function enqueueJson(array $payload, int $statusCode = 200): void
    {
        $this->queue[] = new HttpResponse(
            $statusCode,
            json_encode($payload, JSON_THROW_ON_ERROR),
            ['Content-Type' => 'application/json'],
        );
    }

    public function enqueueException(HttpRequestException $exception): void
    {
        $this->queue[] = $exception;
    }

    /**
     * Enqueue a handler that inspects the request payload and returns a
     * response. Useful when the response depends on the call body.
     *
     * @param \Closure(array{
     *     method: string,
     *     url: string,
     *     headers: array<string, string>,
     *     body: array<string, mixed>|string|null,
     * }): HttpResponse $handler
     */
    public function enqueueHandler(\Closure $handler): void
    {
        $this->queue[] = $handler;
    }

    public function request(string $method, string $url, array $headers = [], array|string|null $body = null): HttpResponse
    {
        $this->requests[] = [
            'method' => $method,
            'url' => $url,
            'headers' => $headers,
            'body' => $body,
        ];

        if ($this->queue === []) {
            throw new \RuntimeException(sprintf('StubHttpClient: no enqueued response for %s %s', $method, $url));
        }

        $next = array_shift($this->queue);
        if ($next instanceof HttpRequestException) {
            throw $next;
        }
        if ($next instanceof \Closure) {
            return $next(end($this->requests) ?: ['method' => $method, 'url' => $url, 'headers' => $headers, 'body' => $body]);
        }

        return $next;
    }

    public function get(string $url, array $headers = []): HttpResponse
    {
        return $this->request('GET', $url, $headers, null);
    }

    public function post(string $url, array $headers = [], array|string|null $body = null): HttpResponse
    {
        return $this->request('POST', $url, $headers, $body);
    }
}

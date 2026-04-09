<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Http\Inbound;

use Symfony\Component\HttpFoundation\Request as HttpRequest;

/**
 * Immutable snapshot of inbound HTTP data. Does not retain a reference to Symfony's Request.
 *
 * Construct via {@see self::fromSymfonyRequest()} at the controller boundary; type-hint
 * {@see InboundHttpRequestInterface} for dependents.
 */
final readonly class InboundHttpRequest implements InboundHttpRequestInterface
{
    /**
     * @param array<string, mixed> $routeParams
     * @param array<string, mixed> $query
     * @param array<string, mixed> $body
     * @param array<string, string> $headersLowercaseFirstLine lower-cased header names → first header line
     * @param array<string, string> $cookies
     */
    private function __construct(
        private string $method,
        private array $routeParams,
        private array $query,
        private array $body,
        private array $headersLowercaseFirstLine,
        private array $cookies,
        private string $path,
        private string $rawContent,
    ) {}

    /**
     * @param array<string, mixed> $routeParams Route attributes from the dispatcher (non-`_` keys)
     * @param array<string, mixed> $query       Query string parameters (e.g. from WaaseyaaContext)
     */
    public static function fromSymfonyRequest(HttpRequest $request, array $routeParams, array $query): self
    {
        $form = $request->request->all();
        $parsed = $request->attributes->get('_parsed_body');
        $body = \is_array($parsed) ? array_merge($form, $parsed) : $form;

        $headersLowercase = [];
        foreach ($request->headers->all() as $name => $values) {
            $headersLowercase[strtolower($name)] = $values[0] ?? '';
        }

        $rawContent = $request->getContent();

        return new self(
            method: $request->getMethod(),
            routeParams: $routeParams,
            query: $query,
            body: $body,
            headersLowercaseFirstLine: $headersLowercase,
            cookies: $request->cookies->all(),
            path: $request->getPathInfo(),
            rawContent: $rawContent,
        );
    }

    public function method(): string
    {
        return $this->method;
    }

    public function routeParams(): array
    {
        return $this->routeParams;
    }

    public function routeParam(string $key, mixed $default = null): mixed
    {
        return $this->routeParams[$key] ?? $default;
    }

    public function query(): array
    {
        return $this->query;
    }

    public function queryParam(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    public function body(): array
    {
        return $this->body;
    }

    public function header(string $name): ?string
    {
        $key = strtolower($name);
        if (!\array_key_exists($key, $this->headersLowercaseFirstLine)) {
            return null;
        }

        return $this->headersLowercaseFirstLine[$key];
    }

    public function cookie(string $name): ?string
    {
        return $this->cookies[$name] ?? null;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function rawContent(): string
    {
        return $this->rawContent;
    }
}

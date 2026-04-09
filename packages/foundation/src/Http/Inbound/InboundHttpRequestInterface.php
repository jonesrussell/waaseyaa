<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Http\Inbound;

/**
 * Read-only HTTP view for application and domain layers.
 *
 * Built at the SSR/controller boundary from Symfony's Request plus the route and
 * query arrays Waaseyaa's dispatcher already extracts.
 */
interface InboundHttpRequestInterface
{
    public function method(): string;

    /**
     * @return array<string, mixed>
     */
    public function routeParams(): array;

    public function routeParam(string $key, mixed $default = null): mixed;

    /**
     * @return array<string, mixed>
     */
    public function query(): array;

    public function queryParam(string $key, mixed $default = null): mixed;

    /**
     * Parsed POST/form parameters merged with JSON `_parsed_body` when present
     * (JSON keys overlay form keys).
     *
     * @return array<string, mixed>
     */
    public function body(): array;

    public function header(string $name): ?string;

    public function cookie(string $name): ?string;

    public function path(): string;

    public function rawContent(): string;
}

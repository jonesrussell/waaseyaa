<?php

declare(strict_types=1);

namespace Aurora\SSR;

final class SsrResponse
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        public readonly string $content,
        public readonly int $statusCode = 200,
        public readonly array $headers = ['Content-Type' => 'text/html; charset=UTF-8'],
    ) {}
}

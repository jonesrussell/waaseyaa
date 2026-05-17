<?php

declare(strict_types=1);

namespace Waaseyaa\ErrorHandler;

/**
 * Chooses between developer HTML and structured production payloads.
 * @api
 */
final class ExceptionRenderer
{
    public function __construct(
        private readonly bool $debug,
        private readonly ?SolutionProviderRegistry $solutionRegistry = null,
    ) {}

    /**
     * @return array{body: string, statusCode: int, contentType: string}
     */
    public function render(\Throwable $e): array
    {
        if ($this->debug) {
            return [
                'body' => new DevExceptionRenderer($this->solutionRegistry)->render($e),
                'statusCode' => 500,
                'contentType' => 'text/html; charset=UTF-8',
            ];
        }

        $payload = json_encode([
            'jsonapi' => ['version' => '1.1'],
            'errors' => [[
                'status' => '500',
                'title' => 'Internal Server Error',
                'detail' => 'An unexpected error occurred.',
            ]],
        ], JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);

        return [
            'body' => $payload,
            'statusCode' => 500,
            'contentType' => 'application/vnd.api+json',
        ];
    }
}

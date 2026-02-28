<?php

declare(strict_types=1);

namespace Aurora\Api;

/**
 * Value object representing a JSON:API error object.
 *
 * @see https://jsonapi.org/format/#error-objects
 */
final readonly class JsonApiError
{
    /**
     * @param string      $status HTTP status code as a string.
     * @param string      $title  Short, human-readable summary of the problem.
     * @param string      $detail Detailed explanation specific to this occurrence.
     * @param array<string, string> $source An object containing references to the primary source of the error.
     */
    public function __construct(
        public string $status,
        public string $title,
        public string $detail = '',
        public array $source = [],
    ) {}

    /**
     * Serialize this error to a JSON:API-compliant array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $error = [
            'status' => $this->status,
            'title' => $this->title,
        ];

        if ($this->detail !== '') {
            $error['detail'] = $this->detail;
        }

        if ($this->source !== []) {
            $error['source'] = $this->source;
        }

        return $error;
    }

    /**
     * Create a 404 Not Found error.
     */
    public static function notFound(string $detail = ''): self
    {
        return new self(
            status: '404',
            title: 'Not Found',
            detail: $detail,
        );
    }

    /**
     * Create a 403 Forbidden error.
     */
    public static function forbidden(string $detail = ''): self
    {
        return new self(
            status: '403',
            title: 'Forbidden',
            detail: $detail,
        );
    }

    /**
     * Create a 422 Unprocessable Entity error.
     */
    public static function unprocessable(string $detail = '', array $source = []): self
    {
        return new self(
            status: '422',
            title: 'Unprocessable Entity',
            detail: $detail,
            source: $source,
        );
    }

    /**
     * Create a 400 Bad Request error.
     */
    public static function badRequest(string $detail = ''): self
    {
        return new self(
            status: '400',
            title: 'Bad Request',
            detail: $detail,
        );
    }

    /**
     * Create a 500 Internal Server Error.
     */
    public static function internalError(string $detail = ''): self
    {
        return new self(
            status: '500',
            title: 'Internal Server Error',
            detail: $detail,
        );
    }
}

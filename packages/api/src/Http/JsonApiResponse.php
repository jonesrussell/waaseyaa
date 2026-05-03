<?php

declare(strict_types=1);

namespace Waaseyaa\Api\Http;

use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * JSON:API-shaped HTTP response.
 *
 * Per ratified contract C-001 of mission 1107-api-symfony-decoupling, this
 * class subclasses Symfony's `JsonResponse` so `instanceof Response` checks in
 * `ControllerDispatcher` keep working without a translation layer. App code
 * type-hints `JsonApiResponse` instead of Symfony's `JsonResponse` to mark the
 * payload as JSON:API and to inherit the canonical content-type and encoding
 * defaults.
 *
 * Encoding mirrors `Waaseyaa\Foundation\Http\JsonApiResponseTrait` (which
 * stays canonical per amended C-004): `JSON_UNESCAPED_SLASHES`,
 * `JSON_PRETTY_PRINT`, and `JSON_THROW_ON_ERROR`. The Content-Type header is
 * set to `application/vnd.api+json` per RFC 7159 / JSON:API spec section 5.1.
 */
class JsonApiResponse extends JsonResponse
{
    private const ENCODING_OPTIONS = JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR;
    private const CONTENT_TYPE = 'application/vnd.api+json';

    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $headers
     */
    public function __construct(array $data = [], int $status = 200, array $headers = [])
    {
        parent::__construct($data, $status, $headers);
        $this->setEncodingOptions(self::ENCODING_OPTIONS);
        $this->headers->set('Content-Type', self::CONTENT_TYPE);
    }
}

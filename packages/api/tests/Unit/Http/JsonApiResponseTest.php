<?php

declare(strict_types=1);

namespace Waaseyaa\Api\Tests\Unit\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Waaseyaa\Api\Http\JsonApiResponse;

#[CoversClass(JsonApiResponse::class)]
final class JsonApiResponseTest extends TestCase
{
    #[Test]
    public function extends_symfony_json_response(): void
    {
        $response = new JsonApiResponse();

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertInstanceOf(Response::class, $response);
    }

    #[Test]
    public function sets_jsonapi_content_type_header(): void
    {
        $response = new JsonApiResponse(['data' => []]);

        $this->assertSame(
            'application/vnd.api+json',
            $response->headers->get('Content-Type'),
        );
    }

    #[Test]
    public function applies_jsonapi_encoding_options(): void
    {
        $expected = JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR;

        $response = new JsonApiResponse();

        $this->assertSame($expected, $response->getEncodingOptions());
    }

    #[Test]
    public function preserves_data_round_trip(): void
    {
        $payload = [
            'data' => [
                'id' => '42',
                'type' => 'node',
                'attributes' => ['title' => 'Hello, /world'],
            ],
        ];

        $response = new JsonApiResponse($payload, 201, ['X-Test' => 'yes']);

        $decoded = json_decode((string) $response->getContent(), true);
        $this->assertSame($payload, $decoded);
        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('yes', $response->headers->get('X-Test'));
    }

    #[Test]
    public function caller_supplied_content_type_does_not_override_jsonapi_default(): void
    {
        $response = new JsonApiResponse([], 200, ['Content-Type' => 'application/json']);

        $this->assertSame(
            'application/vnd.api+json',
            $response->headers->get('Content-Type'),
            'JsonApiResponse must enforce vnd.api+json regardless of caller-supplied headers',
        );
    }
}

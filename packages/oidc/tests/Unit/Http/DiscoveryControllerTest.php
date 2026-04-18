<?php

declare(strict_types=1);

namespace Waaseyaa\Oidc\Tests\Unit\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Waaseyaa\Oidc\Http\DiscoveryController;

#[CoversClass(DiscoveryController::class)]
final class DiscoveryControllerTest extends TestCase
{
    #[Test]
    public function serveReturnsJsonResponseWithIssuerFromConfig(): void
    {
        $controller = new DiscoveryController(issuer: 'https://id.example');

        $response = $controller->serve();

        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertSame(200, $response->getStatusCode());

        $body = json_decode((string) $response->getContent(), true);
        self::assertSame('https://id.example', $body['issuer'] ?? null);
    }

    #[Test]
    public function serveResponseContainsOAuthEndpointsDerivedFromIssuer(): void
    {
        $controller = new DiscoveryController(issuer: 'https://id.example');

        $body = json_decode((string) $controller->serve()->getContent(), true);

        self::assertSame('https://id.example/authorize', $body['authorization_endpoint'] ?? null);
        self::assertSame('https://id.example/token', $body['token_endpoint'] ?? null);
        self::assertSame('https://id.example/userinfo', $body['userinfo_endpoint'] ?? null);
        self::assertSame('https://id.example/.well-known/jwks.json', $body['jwks_uri'] ?? null);
    }

    #[Test]
    public function serveResponseDeclaresSupportedCapabilities(): void
    {
        $controller = new DiscoveryController(issuer: 'https://id.example');

        $body = json_decode((string) $controller->serve()->getContent(), true);

        self::assertContains('code', $body['response_types_supported'] ?? []);
        self::assertContains('public', $body['subject_types_supported'] ?? []);
        self::assertContains('RS256', $body['id_token_signing_alg_values_supported'] ?? []);
    }
}

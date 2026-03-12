<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Tests\Unit\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Foundation\Http\CorsHandler;

#[CoversClass(CorsHandler::class)]
final class CorsHandlerTest extends TestCase
{
    #[Test]
    public function resolves_cors_headers_for_allowed_origin(): void
    {
        $handler = new CorsHandler(allowedOrigins: ['http://localhost:3000']);

        $headers = $handler->resolveCorsHeaders('http://localhost:3000');

        $this->assertCount(5, $headers);
        $this->assertContains('Access-Control-Allow-Origin: http://localhost:3000', $headers);
        $this->assertContains('Vary: Origin', $headers);
    }

    #[Test]
    public function resolves_cors_headers_for_disallowed_origin_returns_empty_list(): void
    {
        $handler = new CorsHandler(allowedOrigins: ['http://localhost:3000']);

        $headers = $handler->resolveCorsHeaders('http://evil.test');

        $this->assertSame([], $headers);
    }

    #[Test]
    public function allows_localhost_any_port_in_development_mode(): void
    {
        $handler = new CorsHandler(
            allowedOrigins: ['http://localhost:3000'],
            allowDevLocalhostPorts: true,
        );

        $headers = $handler->resolveCorsHeaders('http://localhost:4321');
        $this->assertContains('Access-Control-Allow-Origin: http://localhost:4321', $headers);

        $headersLoopback = $handler->resolveCorsHeaders('http://127.0.0.1:5173');
        $this->assertContains('Access-Control-Allow-Origin: http://127.0.0.1:5173', $headersLoopback);
    }

    #[Test]
    public function does_not_allow_non_localhost_in_development_mode(): void
    {
        $handler = new CorsHandler(
            allowedOrigins: ['http://localhost:3000'],
            allowDevLocalhostPorts: true,
        );

        $headers = $handler->resolveCorsHeaders('http://example.com:3001');
        $this->assertSame([], $headers);
    }

    #[Test]
    public function detects_cors_preflight_request_method(): void
    {
        $handler = new CorsHandler();

        $this->assertTrue($handler->isCorsPreflightRequest('OPTIONS'));
        $this->assertTrue($handler->isCorsPreflightRequest('options'));
        $this->assertFalse($handler->isCorsPreflightRequest('GET'));
    }

    #[Test]
    public function is_origin_allowed_checks_exact_match(): void
    {
        $handler = new CorsHandler(
            allowedOrigins: ['http://localhost:3000', 'http://myapp.test'],
        );

        $this->assertTrue($handler->isOriginAllowed('http://localhost:3000'));
        $this->assertTrue($handler->isOriginAllowed('http://myapp.test'));
        $this->assertFalse($handler->isOriginAllowed('http://other.test'));
    }

    #[Test]
    public function default_allowed_origins_include_common_dev_ports(): void
    {
        $handler = new CorsHandler();

        $this->assertTrue($handler->isOriginAllowed('http://localhost:3000'));
        $this->assertTrue($handler->isOriginAllowed('http://127.0.0.1:3000'));
        $this->assertFalse($handler->isOriginAllowed('http://localhost:8080'));
    }
}

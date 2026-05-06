<?php

declare(strict_types=1);

namespace Waaseyaa\Tests\Integration\Phase13\Fixtures;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;
use Waaseyaa\Routing\RouteBuilder;
use Waaseyaa\Routing\WaaseyaaRouter;

/**
 * Minimal service provider for CSRF integration tests.
 *
 * Registers three routes:
 *
 *   GET  /test/protected     — HTML response (causes XSRF-TOKEN cookie to be set)
 *   POST /test/protected     — CSRF-protected multipart endpoint (returns 200 OK)
 *   POST /test/api/json-route — JSON-exempt endpoint (returns 200 always)
 */
final class CsrfTestServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // No bindings needed for CSRF integration test routes.
    }

    public function routes(WaaseyaaRouter $router, EntityTypeManager $entityTypeManager): void
    {
        // GET /test/protected — HTML response triggers cookie attachment.
        $router->addRoute(
            'test.csrf.get',
            RouteBuilder::create('/test/protected')
                ->controller(static fn(Request $r): Response => new Response(
                    '<!DOCTYPE html><html><body><p>CSRF test page</p></body></html>',
                    200,
                    ['Content-Type' => 'text/html; charset=UTF-8'],
                ))
                ->allowAll()
                ->methods('GET')
                ->build(),
        );

        // POST /test/protected — CSRF-protected render route; middleware validates
        // token before this runs. Marked as render so the 403 error uses the
        // HTML "Invalid Security Token" body (matching contract §3 / §5).
        $router->addRoute(
            'test.csrf.post',
            RouteBuilder::create('/test/protected')
                ->controller(static fn(Request $r): Response => new Response(
                    '<!DOCTYPE html><html><body><p>OK</p></body></html>',
                    200,
                    ['Content-Type' => 'text/html; charset=UTF-8'],
                ))
                ->allowAll()
                ->render()
                ->methods('POST')
                ->build(),
        );

        // POST /test/api/json-route — application/json is exempt from CSRF.
        $router->addRoute(
            'test.csrf.json',
            RouteBuilder::create('/test/api/json-route')
                ->controller(static fn(Request $r): Response => new Response(
                    '{"ok":true}',
                    200,
                    ['Content-Type' => 'application/json'],
                ))
                ->allowAll()
                ->methods('POST')
                ->build(),
        );
    }
}

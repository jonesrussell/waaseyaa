<?php

declare(strict_types=1);

namespace Waaseyaa\Debug\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Waaseyaa\Debug\DebugToolbarMiddleware;
use Waaseyaa\Foundation\Middleware\HttpHandlerInterface;

#[CoversClass(DebugToolbarMiddleware::class)]
final class DebugToolbarMiddlewareTest extends TestCase
{
    #[Test]
    public function injects_bar_into_html_body(): void
    {
        $mw = new DebugToolbarMiddleware(1_700_000_000.0);
        $request = Request::create('/');
        $inner = new class implements HttpHandlerInterface {
            public function handle(Request $request): Response
            {
                return new Response('<html><body></body></html>', 200, ['Content-Type' => 'text/html; charset=UTF-8']);
            }
        };

        $response = $mw->process($request, $inner);
        self::assertStringContainsString('waaseyaa-debug-bar', (string) $response->getContent());
    }

    #[Test]
    public function skips_non_html(): void
    {
        $mw = new DebugToolbarMiddleware(1_700_000_000.0);
        $request = Request::create('/');
        $inner = new class implements HttpHandlerInterface {
            public function handle(Request $request): Response
            {
                return new Response('{}', 200, ['Content-Type' => 'application/json']);
            }
        };

        $response = $mw->process($request, $inner);
        self::assertSame('{}', (string) $response->getContent());
    }
}

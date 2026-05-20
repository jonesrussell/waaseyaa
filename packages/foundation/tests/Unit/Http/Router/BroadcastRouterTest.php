<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Tests\Unit\Http\Router;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Waaseyaa\Foundation\Http\Router\BroadcastRouter;

#[CoversClass(BroadcastRouter::class)]
final class BroadcastRouterTest extends TestCase
{
    #[Test]
    public function supports_broadcast_controller(): void
    {
        $router = new BroadcastRouter();
        $request = Request::create('/api/broadcast');
        $request->attributes->set('_controller', 'broadcast');
        self::assertTrue($router->supports($request));
    }

    #[Test]
    public function does_not_support_unrelated(): void
    {
        $router = new BroadcastRouter();
        $request = Request::create('/api/openapi');
        $request->attributes->set('_controller', 'openapi');
        self::assertFalse($router->supports($request));
    }

    #[Test]
    public function resolve_initial_cursor_uses_high_water_mark_when_no_last_event_id(): void
    {
        $request = Request::create('/api/broadcast?channels=admin');

        self::assertSame(42, BroadcastRouter::resolveInitialCursor($request, 42));
        self::assertSame(0, BroadcastRouter::resolveInitialCursor($request, 0));
    }

    #[Test]
    public function resolve_initial_cursor_resumes_from_last_event_id_header(): void
    {
        $request = Request::create('/api/broadcast?channels=admin');
        $request->headers->set('Last-Event-ID', '17');

        // Even though storage's high-water mark is 99, we resume at 17.
        self::assertSame(17, BroadcastRouter::resolveInitialCursor($request, 99));
    }

    #[Test]
    public function resolve_initial_cursor_rejects_non_numeric_last_event_id(): void
    {
        $request = Request::create('/api/broadcast?channels=admin');
        $request->headers->set('Last-Event-ID', 'not-a-number');

        self::assertSame(42, BroadcastRouter::resolveInitialCursor($request, 42));
    }

    #[Test]
    public function handle_returns_streamed_response(): void
    {
        $router = new BroadcastRouter();

        $db = \Waaseyaa\Database\DBALDatabase::createSqlite();
        $broadcastStorage = new \Waaseyaa\Api\Controller\BroadcastStorage($db);

        $account = $this->createStub(\Waaseyaa\Access\AccountInterface::class);
        $request = Request::create('/api/broadcast?channels=admin');
        $request->attributes->set('_controller', 'broadcast');
        $request->attributes->set('_account', $account);
        $request->attributes->set('_broadcast_storage', $broadcastStorage);
        $request->attributes->set('_parsed_body', null);
        $request->attributes->set('_waaseyaa_context',
            \Waaseyaa\Foundation\Http\Router\WaaseyaaContext::fromRequest($request)
        );

        $response = $router->handle($request);

        self::assertInstanceOf(\Symfony\Component\HttpFoundation\StreamedResponse::class, $response);
        self::assertSame('text/event-stream', $response->headers->get('Content-Type'));
    }
}

<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Tests\Unit\Http\Router;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Waaseyaa\Api\Controller\BroadcastStorage;
use Waaseyaa\Database\DBALDatabase;
use Waaseyaa\Foundation\Http\Router\CodifiedContextApiRouter;

#[CoversClass(CodifiedContextApiRouter::class)]
final class CodifiedContextApiRouterTest extends TestCase
{
    private function requestWithContext(string $controller, array $routeDefaults = []): Request
    {
        $db = DBALDatabase::createSqlite();
        $request = Request::create('/api/telescope/agent-context/sessions');
        $request->attributes->set('_controller', $controller);
        $request->attributes->set('_account', $this->createStub(\Waaseyaa\Access\AccountInterface::class));
        $request->attributes->set('_broadcast_storage', new BroadcastStorage($db));
        $request->attributes->set('_parsed_body', null);
        foreach ($routeDefaults as $k => $v) {
            $request->attributes->set($k, $v);
        }

        return $request;
    }

    #[Test]
    public function supports_codified_context_controller_actions(): void
    {
        $router = new CodifiedContextApiRouter();
        $request = $this->requestWithContext('Waaseyaa\\Api\\Controller\\CodifiedContextController::listSessions');

        self::assertTrue($router->supports($request));
    }

    #[Test]
    public function does_not_support_unrelated_controller(): void
    {
        $router = new CodifiedContextApiRouter();
        $request = $this->requestWithContext('Waaseyaa\\Api\\JsonApiController::index');

        self::assertFalse($router->supports($request));
    }

    #[Test]
    public function list_sessions_returns_empty_data_without_store(): void
    {
        $router = new CodifiedContextApiRouter();
        $request = $this->requestWithContext('Waaseyaa\\Api\\Controller\\CodifiedContextController::listSessions');

        $response = $router->handle($request);

        self::assertSame(200, $response->getStatusCode());
        $decoded = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(['data' => []], $decoded);
    }

    #[Test]
    public function unknown_action_returns_404(): void
    {
        $router = new CodifiedContextApiRouter();
        $request = $this->requestWithContext('Waaseyaa\\Api\\Controller\\CodifiedContextController::missing');

        $response = $router->handle($request);

        self::assertSame(404, $response->getStatusCode());
    }
}

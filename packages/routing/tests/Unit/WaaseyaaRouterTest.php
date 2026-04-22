<?php

declare(strict_types=1);

namespace Waaseyaa\Routing\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\RequestContext;
use Waaseyaa\Routing\RouteBuilder;
use Waaseyaa\Routing\WaaseyaaRouter;

final class WaaseyaaRouterTest extends TestCase
{
    #[Test]
    public function duplicate_route_name_throws(): void
    {
        $router = new WaaseyaaRouter(new RequestContext('', 'GET'));
        $route = RouteBuilder::create('/a')->controller('x')->methods('GET')->build();
        $router->addRoute('dup', $route);
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Duplicate route name registered: dup');
        $router->addRoute('dup', $route);
    }

    #[Test]
    public function sortRoutesByPriority_orders_higher_first(): void
    {
        $router = new WaaseyaaRouter(new RequestContext('', 'GET'));
        $router->addRoute('low', RouteBuilder::create('/conflict')->priority(0)->controller('low')->methods('GET')->build());
        $router->addRoute('high', RouteBuilder::create('/conflict')->priority(10)->controller('high')->methods('GET')->build());
        $router->sortRoutesByPriority();
        $params = $router->match('/conflict');
        $this->assertSame('high', $params['_route']);
    }
}

<?php

declare(strict_types=1);

namespace Aurora\Mcp\Tests\Unit;

use Aurora\Mcp\McpRouteProvider;
use Aurora\Routing\AuroraRouter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(McpRouteProvider::class)]
final class McpRouteProviderTest extends TestCase
{
    #[Test]
    public function registerRoutesAddsMcpEndpointRoute(): void
    {
        $router = new AuroraRouter();
        $provider = new McpRouteProvider();

        $provider->registerRoutes($router);

        $routes = $router->getRouteCollection();
        $endpointRoute = $routes->get('mcp.endpoint');

        $this->assertNotNull($endpointRoute);
        $this->assertSame('/mcp', $endpointRoute->getPath());
        $this->assertContains('POST', $endpointRoute->getMethods());
        $this->assertContains('GET', $endpointRoute->getMethods());
    }

    #[Test]
    public function registerRoutesAddsServerCardRoute(): void
    {
        $router = new AuroraRouter();
        $provider = new McpRouteProvider();

        $provider->registerRoutes($router);

        $routes = $router->getRouteCollection();
        $cardRoute = $routes->get('mcp.server_card');

        $this->assertNotNull($cardRoute);
        $this->assertSame('/.well-known/mcp.json', $cardRoute->getPath());
        $this->assertContains('GET', $cardRoute->getMethods());
    }

    #[Test]
    public function serverCardRouteIsPublic(): void
    {
        $router = new AuroraRouter();
        $provider = new McpRouteProvider();

        $provider->registerRoutes($router);

        $routes = $router->getRouteCollection();
        $cardRoute = $routes->get('mcp.server_card');

        $this->assertTrue($cardRoute->getOption('_public'));
    }
}

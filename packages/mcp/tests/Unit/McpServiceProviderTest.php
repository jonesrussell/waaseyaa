<?php

declare(strict_types=1);

namespace Waaseyaa\Mcp\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Mcp\McpServiceProvider;
use Waaseyaa\Routing\WaaseyaaRouter;

#[CoversClass(McpServiceProvider::class)]
final class McpServiceProviderTest extends TestCase
{
    #[Test]
    public function registers_mcp_routes_through_the_package_service_provider(): void
    {
        $router = new WaaseyaaRouter();
        $entityTypeManager = new EntityTypeManager(new EventDispatcher());

        (new McpServiceProvider())->routes($router, $entityTypeManager);

        $routes = $router->getRouteCollection();
        $this->assertNotNull($routes->get('mcp.endpoint'));
        $this->assertNotNull($routes->get('mcp.server_card'));
    }
}

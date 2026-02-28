<?php

declare(strict_types=1);

namespace Aurora\Mcp;

use Aurora\Routing\AuroraRouter;
use Aurora\Routing\RouteBuilder;

final readonly class McpRouteProvider
{
    public function registerRoutes(AuroraRouter $router): void
    {
        $router->addRoute(
            'mcp.endpoint',
            RouteBuilder::create('/mcp')
                ->controller('Aurora\\Mcp\\McpEndpoint::handle')
                ->methods('POST', 'GET')
                ->build(),
        );

        $router->addRoute(
            'mcp.server_card',
            RouteBuilder::create('/.well-known/mcp.json')
                ->controller('Aurora\\Mcp\\McpServerCard::toJson')
                ->methods('GET')
                ->allowAll()
                ->build(),
        );
    }
}

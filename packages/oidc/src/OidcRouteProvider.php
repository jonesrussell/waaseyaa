<?php

declare(strict_types=1);

namespace Waaseyaa\Oidc;

use Waaseyaa\Routing\RouteBuilder;
use Waaseyaa\Routing\WaaseyaaRouter;

final readonly class OidcRouteProvider
{
    public function registerRoutes(WaaseyaaRouter $router): void
    {
        $router->addRoute(
            'oidc.discovery',
            RouteBuilder::create('/.well-known/openid-configuration')
                ->controller('Waaseyaa\\Oidc\\Http\\DiscoveryController::serve')
                ->methods('GET')
                ->allowAll()
                ->build(),
        );

        $router->addRoute(
            'oidc.jwks',
            RouteBuilder::create('/.well-known/jwks.json')
                ->controller('Waaseyaa\\Oidc\\Http\\JwksController::serve')
                ->methods('GET')
                ->allowAll()
                ->build(),
        );
    }
}

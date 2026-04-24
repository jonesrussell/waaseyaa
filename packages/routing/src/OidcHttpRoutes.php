<?php

declare(strict_types=1);

namespace Waaseyaa\Routing;

use Waaseyaa\Oidc\Authorize\AuthorizeController;
use Waaseyaa\Oidc\Token\TokenController;

/**
 * OIDC HTTP route table. Lives in the routing package (L4) so L1 oidc is free of
 * RouteBuilder / WaaseyaaRouter type references.
 */
final readonly class OidcHttpRoutes
{
    public function __construct(
        private ?AuthorizeController $authorizeController = null,
        private ?TokenController $tokenController = null,
    ) {}

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

        if ($this->authorizeController !== null) {
            $router->addRoute(
                'oidc.authorize',
                RouteBuilder::create('/authorize')
                    ->controller($this->authorizeController)
                    ->methods('GET')
                    ->allowAll()
                    ->build(),
            );
        }

        if ($this->tokenController !== null) {
            $router->addRoute(
                'oidc.token',
                RouteBuilder::create('/token')
                    ->controller($this->tokenController)
                    ->methods('POST')
                    ->allowAll()
                    ->csrfExempt()
                    ->build(),
            );
        }
    }
}

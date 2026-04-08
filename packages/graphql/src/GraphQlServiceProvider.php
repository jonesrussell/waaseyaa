<?php

declare(strict_types=1);

namespace Waaseyaa\GraphQL;

use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Foundation\Kernel\HttpKernel;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;
use Waaseyaa\GraphQL\Http\Router\GraphQlRouter;
use Waaseyaa\Routing\WaaseyaaRouter;

final class GraphQlServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function httpDomainRouters(?HttpKernel $httpKernel = null): iterable
    {
        if ($httpKernel === null) {
            return [];
        }

        $gqlOverrides = [];
        foreach ($httpKernel->getProviders() as $provider) {
            foreach ($provider->graphqlMutationOverrides($httpKernel->getEntityTypeManager()) as $name => $override) {
                $gqlOverrides[$name] = $override;
            }
        }

        return [
            new GraphQlRouter(
                $httpKernel->getEntityTypeManager(),
                $httpKernel->getAccessHandler(),
                $gqlOverrides,
            ),
        ];
    }

    public function routes(WaaseyaaRouter $router, ?EntityTypeManager $entityTypeManager = null): void
    {
        (new GraphQlRouteProvider())->registerRoutes($router);
    }
}

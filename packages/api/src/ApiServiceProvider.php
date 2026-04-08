<?php

declare(strict_types=1);

namespace Waaseyaa\Api;

use Waaseyaa\Api\Http\Router\DiscoveryRouter;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Foundation\Kernel\HttpKernel;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;
use Waaseyaa\Routing\WaaseyaaRouter;

final class ApiServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function httpDomainRouters(?HttpKernel $httpKernel = null): iterable
    {
        if ($httpKernel === null) {
            return [];
        }

        return [
            new DiscoveryRouter(
                $httpKernel->getDiscoveryApiHandler(),
                $httpKernel->getEntityTypeManager(),
            ),
        ];
    }

    public function routes(WaaseyaaRouter $router, ?EntityTypeManager $entityTypeManager = null): void
    {
        if ($entityTypeManager === null) {
            return;
        }

        (new JsonApiRouteProvider($entityTypeManager))->registerRoutes($router);
    }
}

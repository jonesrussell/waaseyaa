<?php

declare(strict_types=1);

namespace Waaseyaa\Api;

use Waaseyaa\Api\Http\Router\DiscoveryRouter;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Foundation\Kernel\HttpKernel;
use Waaseyaa\Foundation\ServiceProvider\Capability\HasHttpDomainRoutersInterface;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;
use Waaseyaa\Routing\WaaseyaaRouter;

final class ApiServiceProvider extends ServiceProvider implements HasHttpDomainRoutersInterface
{
    public function register(): void {}

    public function httpDomainRouters(HttpKernel $httpKernel): iterable
    {
        return [
            new DiscoveryRouter(
                $httpKernel->getDiscoveryApiHandler(),
                $httpKernel->getEntityTypeManager(),
            ),
        ];
    }

    public function routes(WaaseyaaRouter $router, EntityTypeManager $entityTypeManager): void
    {
        (new JsonApiRouteProvider($entityTypeManager))->registerRoutes($router);
    }
}

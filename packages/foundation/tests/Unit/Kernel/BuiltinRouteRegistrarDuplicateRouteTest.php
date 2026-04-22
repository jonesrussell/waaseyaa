<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Tests\Unit\Kernel;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Routing\RequestContext;
use Waaseyaa\Entity\EntityType;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Foundation\Kernel\BuiltinRouteRegistrar;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;
use Waaseyaa\Routing\RouteBuilder;
use Waaseyaa\Routing\WaaseyaaRouter;

final class BuiltinRouteRegistrarDuplicateRouteTest extends TestCase
{
    #[Test]
    public function duplicate_route_name_across_providers_throws(): void
    {
        $dispatcher = new EventDispatcher();
        $entityTypeManager = new EntityTypeManager($dispatcher);
        $entityTypeManager->registerEntityType(new EntityType(
            id: 'article',
            label: 'Article',
            class: \stdClass::class,
            keys: ['id' => 'id'],
        ));

        $dupRoute = RouteBuilder::create('/dup-a')
            ->controller('test.a')
            ->allowAll()
            ->methods('GET')
            ->build();

        $providerA = new class($dupRoute) extends ServiceProvider {
            public function __construct(private readonly \Symfony\Component\Routing\Route $route) {}

            public function register(): void {}

            public function routes(WaaseyaaRouter $router, ?\Waaseyaa\Entity\EntityTypeManager $entityTypeManager = null): void
            {
                $router->addRoute('shared_name', $this->route);
            }
        };

        $providerB = new class extends ServiceProvider {
            public function register(): void {}

            public function routes(WaaseyaaRouter $router, ?\Waaseyaa\Entity\EntityTypeManager $entityTypeManager = null): void
            {
                $router->addRoute(
                    'shared_name',
                    RouteBuilder::create('/dup-b')
                        ->controller('test.b')
                        ->allowAll()
                        ->methods('GET')
                        ->build(),
                );
            }
        };

        $router = new WaaseyaaRouter(new RequestContext('', 'GET'));
        $registrar = new BuiltinRouteRegistrar($entityTypeManager, [$providerA, $providerB]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Duplicate route name registered: shared_name');

        $registrar->register($router);
    }
}

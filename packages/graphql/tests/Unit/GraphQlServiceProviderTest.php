<?php

declare(strict_types=1);

namespace Waaseyaa\GraphQL\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\GraphQL\GraphQlServiceProvider;
use Waaseyaa\Routing\WaaseyaaRouter;

#[CoversClass(GraphQlServiceProvider::class)]
final class GraphQlServiceProviderTest extends TestCase
{
    #[Test]
    public function registers_the_graphql_endpoint_through_the_package_service_provider(): void
    {
        $router = new WaaseyaaRouter();
        $entityTypeManager = new EntityTypeManager(new EventDispatcher());

        (new GraphQlServiceProvider())->routes($router, $entityTypeManager);

        $route = $router->getRouteCollection()->get('graphql.endpoint');
        $this->assertNotNull($route);
        $this->assertSame('/graphql', $route->getPath());
    }
}

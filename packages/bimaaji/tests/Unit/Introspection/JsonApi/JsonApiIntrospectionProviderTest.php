<?php

declare(strict_types=1);

namespace Waaseyaa\Bimaaji\Tests\Unit\Introspection\JsonApi;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Waaseyaa\Bimaaji\Graph\GraphSection;
use Waaseyaa\Bimaaji\Introspection\JsonApi\JsonApiIntrospectionProvider;
use Waaseyaa\Entity\EntityTypeManagerInterface;

#[CoversClass(JsonApiIntrospectionProvider::class)]
final class JsonApiIntrospectionProviderTest extends TestCase
{
    #[Test]
    public function getKeyReturnsJsonapi(): void
    {
        $routes = new RouteCollection();
        $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);

        $provider = new JsonApiIntrospectionProvider($routes, $entityTypeManager);

        self::assertSame('jsonapi', $provider->getKey());
    }

    #[Test]
    public function provideReturnsGraphSectionWithJsonApiRoutes(): void
    {
        $routes = new RouteCollection();

        // JSON:API route with entity parameter.
        $jsonApiRoute = new Route(
            '/api/node/{node}',
            ['_controller' => 'Waaseyaa\\Api\\Controller\\JsonApiController::show'],
            [],
            [
                '_json_api' => true,
                'parameters' => ['node' => ['type' => 'entity:node']],
            ],
        );
        $jsonApiRoute->setMethods(['GET']);
        $routes->add('api.node.show', $jsonApiRoute);

        // Non-JSON:API route — should be excluded.
        $otherRoute = new Route(
            '/admin/dashboard',
            ['_controller' => 'Waaseyaa\\Admin\\Controller\\DashboardController::index'],
        );
        $otherRoute->setMethods(['GET']);
        $routes->add('admin.dashboard', $otherRoute);

        $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);

        $provider = new JsonApiIntrospectionProvider($routes, $entityTypeManager);
        $section = $provider->provide();

        self::assertInstanceOf(GraphSection::class, $section);
        self::assertSame('jsonapi', $section->key);
        self::assertArrayHasKey('api.node.show', $section->data);
        self::assertArrayNotHasKey('admin.dashboard', $section->data);

        $resource = $section->data['api.node.show'];
        self::assertSame('node', $resource['entity_type']);
        self::assertSame('/api/node/{node}', $resource['path']);
        self::assertSame(['GET'], $resource['methods']);
        self::assertSame('Waaseyaa\\Api\\Controller\\JsonApiController::show', $resource['controller']);
    }

    #[Test]
    public function provideHandlesJsonApiRouteWithoutEntityParameter(): void
    {
        $routes = new RouteCollection();

        // JSON:API route without entity parameter.
        $jsonApiRoute = new Route(
            '/api/search',
            ['_controller' => 'Waaseyaa\\Api\\Controller\\SearchController::index'],
            [],
            ['_json_api' => true],
        );
        $jsonApiRoute->setMethods(['GET', 'POST']);
        $routes->add('api.search', $jsonApiRoute);

        $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);

        $provider = new JsonApiIntrospectionProvider($routes, $entityTypeManager);
        $section = $provider->provide();

        self::assertArrayHasKey('api.search', $section->data);

        $resource = $section->data['api.search'];
        self::assertNull($resource['entity_type']);
        self::assertSame('/api/search', $resource['path']);
        self::assertSame(['GET', 'POST'], $resource['methods']);
    }

    #[Test]
    public function provideReturnsEmptyDataWhenNoJsonApiRoutes(): void
    {
        $routes = new RouteCollection();

        $otherRoute = new Route('/admin', ['_controller' => 'SomeController::index']);
        $routes->add('admin.index', $otherRoute);

        $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);

        $provider = new JsonApiIntrospectionProvider($routes, $entityTypeManager);
        $section = $provider->provide();

        self::assertSame([], $section->data);
    }
}

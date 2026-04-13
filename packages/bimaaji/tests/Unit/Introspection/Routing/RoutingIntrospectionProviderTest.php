<?php

declare(strict_types=1);

namespace Waaseyaa\Bimaaji\Tests\Unit\Introspection\Routing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Waaseyaa\Bimaaji\Graph\GraphSection;
use Waaseyaa\Bimaaji\Introspection\Routing\RoutingIntrospectionProvider;

#[CoversClass(RoutingIntrospectionProvider::class)]
final class RoutingIntrospectionProviderTest extends TestCase
{
    #[Test]
    public function get_key_returns_routing(): void
    {
        $provider = new RoutingIntrospectionProvider(new RouteCollection());

        $this->assertSame('routing', $provider->getKey());
    }

    #[Test]
    public function provide_returns_graph_section_with_route_data(): void
    {
        $routes = new RouteCollection();

        $route = new Route('/api/nodes', ['_controller' => 'NodeController::index']);
        $route->setMethods(['GET']);
        $route->setOption('_public', true);
        $routes->add('api.nodes.index', $route);

        $provider = new RoutingIntrospectionProvider($routes);
        $section = $provider->provide();

        $this->assertInstanceOf(GraphSection::class, $section);
        $this->assertSame('routing', $section->key);
        $this->assertSame('1.0', $section->version);

        $data = $section->data;
        $this->assertArrayHasKey('api.nodes.index', $data);

        $entry = $data['api.nodes.index'];
        $this->assertSame('/api/nodes', $entry['path']);
        $this->assertSame(['GET'], $entry['methods']);
        $this->assertSame('NodeController::index', $entry['controller']);
        $this->assertTrue($entry['access']['public']);
    }

    #[Test]
    public function provide_includes_all_access_options_when_set(): void
    {
        $routes = new RouteCollection();

        $route = new Route('/admin/users', ['_controller' => 'UserController::index']);
        $route->setMethods(['GET', 'POST']);
        $route->setOption('_authenticated', true);
        $route->setOption('_permission', 'administer users');
        $route->setOption('_role', 'admin');
        $route->setOption('_gate', 'user');
        $route->setOption('_session', true);
        $routes->add('admin.users', $route);

        $provider = new RoutingIntrospectionProvider($routes);
        $section = $provider->provide();

        $entry = $section->data['admin.users'];
        $this->assertTrue($entry['access']['authenticated']);
        $this->assertSame('administer users', $entry['access']['permission']);
        $this->assertSame('admin', $entry['access']['role']);
        $this->assertSame('user', $entry['access']['gate']);
        $this->assertTrue($entry['access']['session']);
    }

    #[Test]
    public function provide_omits_unset_access_options(): void
    {
        $routes = new RouteCollection();

        $route = new Route('/health', ['_controller' => 'HealthController::check']);
        $route->setMethods(['GET']);
        $route->setOption('_public', true);
        $routes->add('health.check', $route);

        $provider = new RoutingIntrospectionProvider($routes);
        $section = $provider->provide();

        $access = $section->data['health.check']['access'];
        $this->assertArrayHasKey('public', $access);
        $this->assertArrayNotHasKey('authenticated', $access);
        $this->assertArrayNotHasKey('permission', $access);
        $this->assertArrayNotHasKey('role', $access);
        $this->assertArrayNotHasKey('gate', $access);
        $this->assertArrayNotHasKey('session', $access);
    }

    #[Test]
    public function provide_handles_empty_route_collection(): void
    {
        $provider = new RoutingIntrospectionProvider(new RouteCollection());
        $section = $provider->provide();

        $this->assertSame('routing', $section->key);
        $this->assertSame([], $section->data);
    }

    #[Test]
    public function provide_handles_route_without_controller(): void
    {
        $routes = new RouteCollection();

        $route = new Route('/redirect');
        $route->setMethods(['GET']);
        $routes->add('redirect', $route);

        $provider = new RoutingIntrospectionProvider($routes);
        $section = $provider->provide();

        $entry = $section->data['redirect'];
        $this->assertNull($entry['controller']);
    }
}

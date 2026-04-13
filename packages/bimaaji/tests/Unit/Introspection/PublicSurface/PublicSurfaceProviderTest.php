<?php

declare(strict_types=1);

namespace Waaseyaa\Bimaaji\Tests\Unit\Introspection\PublicSurface;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Waaseyaa\Bimaaji\Graph\GraphSection;
use Waaseyaa\Bimaaji\Introspection\PublicSurface\PublicSurfaceProvider;

#[CoversClass(PublicSurfaceProvider::class)]
final class PublicSurfaceProviderTest extends TestCase
{
    #[Test]
    public function get_key_returns_public_surface(): void
    {
        $provider = new PublicSurfaceProvider(new RouteCollection());

        $this->assertSame('public_surface', $provider->getKey());
    }

    #[Test]
    public function provide_includes_route_with_render_option(): void
    {
        $routes = new RouteCollection();

        $route = new Route('/about', ['_controller' => 'PageController::about']);
        $route->setMethods(['GET']);
        $route->setOption('_render', true);
        $route->setOption('_public', true);
        $routes->add('page.about', $route);

        $provider = new PublicSurfaceProvider($routes);
        $section = $provider->provide();

        $this->assertInstanceOf(GraphSection::class, $section);
        $this->assertSame('public_surface', $section->key);
        $this->assertSame('1.0', $section->version);

        $this->assertArrayHasKey('page.about', $section->data);

        $entry = $section->data['page.about'];
        $this->assertSame('/about', $entry['path']);
        $this->assertSame(['GET'], $entry['methods']);
        $this->assertSame('public', $entry['auth']);
    }

    #[Test]
    public function provide_includes_route_with_public_option_only(): void
    {
        $routes = new RouteCollection();

        $route = new Route('/health', ['_controller' => 'HealthController::check']);
        $route->setMethods(['GET']);
        $route->setOption('_public', true);
        $routes->add('health.check', $route);

        $provider = new PublicSurfaceProvider($routes);
        $section = $provider->provide();

        $this->assertArrayHasKey('health.check', $section->data);
        $this->assertSame('public', $section->data['health.check']['auth']);
    }

    #[Test]
    public function provide_excludes_route_without_render_or_public(): void
    {
        $routes = new RouteCollection();

        $route = new Route('/api/internal', ['_controller' => 'InternalController::index']);
        $route->setMethods(['GET']);
        $route->setOption('_authenticated', true);
        $routes->add('api.internal', $route);

        $provider = new PublicSurfaceProvider($routes);
        $section = $provider->provide();

        $this->assertArrayNotHasKey('api.internal', $section->data);
    }

    #[Test]
    public function provide_classifies_authenticated_routes(): void
    {
        $routes = new RouteCollection();

        $route = new Route('/dashboard', ['_controller' => 'DashboardController::index']);
        $route->setMethods(['GET']);
        $route->setOption('_render', true);
        $route->setOption('_authenticated', true);
        $routes->add('dashboard', $route);

        $provider = new PublicSurfaceProvider($routes);
        $section = $provider->provide();

        $this->assertSame('authenticated', $section->data['dashboard']['auth']);
    }

    #[Test]
    public function provide_classifies_session_routes(): void
    {
        $routes = new RouteCollection();

        $route = new Route('/preferences', ['_controller' => 'PreferencesController::index']);
        $route->setMethods(['GET']);
        $route->setOption('_render', true);
        $route->setOption('_session', 'user_preferences');
        $routes->add('preferences', $route);

        $provider = new PublicSurfaceProvider($routes);
        $section = $provider->provide();

        $this->assertSame('session', $section->data['preferences']['auth']);
    }

    #[Test]
    public function provide_classifies_restricted_routes_with_permission(): void
    {
        $routes = new RouteCollection();

        $route = new Route('/admin/settings', ['_controller' => 'SettingsController::index']);
        $route->setMethods(['GET']);
        $route->setOption('_render', true);
        $route->setOption('_permission', 'administer site');
        $routes->add('admin.settings', $route);

        $provider = new PublicSurfaceProvider($routes);
        $section = $provider->provide();

        $this->assertSame('restricted', $section->data['admin.settings']['auth']);
    }

    #[Test]
    public function provide_classifies_restricted_routes_with_role(): void
    {
        $routes = new RouteCollection();

        $route = new Route('/admin/users', ['_controller' => 'UserController::index']);
        $route->setMethods(['GET']);
        $route->setOption('_public', true);
        $route->setOption('_role', 'admin');
        $routes->add('admin.users', $route);

        $provider = new PublicSurfaceProvider($routes);
        $section = $provider->provide();

        $this->assertSame('restricted', $section->data['admin.users']['auth']);
    }

    #[Test]
    public function provide_classifies_restricted_routes_with_gate(): void
    {
        $routes = new RouteCollection();

        $route = new Route('/admin/content', ['_controller' => 'ContentController::index']);
        $route->setMethods(['GET']);
        $route->setOption('_render', true);
        $route->setOption('_gate', 'node');
        $routes->add('admin.content', $route);

        $provider = new PublicSurfaceProvider($routes);
        $section = $provider->provide();

        $this->assertSame('restricted', $section->data['admin.content']['auth']);
    }

    #[Test]
    public function provide_classifies_unknown_when_no_auth_options(): void
    {
        $routes = new RouteCollection();

        $route = new Route('/mystery', ['_controller' => 'MysteryController::index']);
        $route->setMethods(['GET']);
        $route->setOption('_render', true);
        $routes->add('mystery', $route);

        $provider = new PublicSurfaceProvider($routes);
        $section = $provider->provide();

        $this->assertSame('unknown', $section->data['mystery']['auth']);
    }

    #[Test]
    public function provide_handles_empty_route_collection(): void
    {
        $provider = new PublicSurfaceProvider(new RouteCollection());
        $section = $provider->provide();

        $this->assertSame('public_surface', $section->key);
        $this->assertSame([], $section->data);
    }

    #[Test]
    public function provide_mixed_routes_filters_correctly(): void
    {
        $routes = new RouteCollection();

        // Included: _render = true
        $rendered = new Route('/page', ['_controller' => 'PageController::show']);
        $rendered->setMethods(['GET']);
        $rendered->setOption('_render', true);
        $rendered->setOption('_public', true);
        $routes->add('page.show', $rendered);

        // Included: _public = true (no _render)
        $public = new Route('/feed', ['_controller' => 'FeedController::index']);
        $public->setMethods(['GET']);
        $public->setOption('_public', true);
        $routes->add('feed.index', $public);

        // Excluded: neither _render nor _public
        $internal = new Route('/api/nodes', ['_controller' => 'NodeController::index']);
        $internal->setMethods(['GET']);
        $internal->setOption('_authenticated', true);
        $routes->add('api.nodes.index', $internal);

        $provider = new PublicSurfaceProvider($routes);
        $section = $provider->provide();

        $this->assertCount(2, $section->data);
        $this->assertArrayHasKey('page.show', $section->data);
        $this->assertArrayHasKey('feed.index', $section->data);
        $this->assertArrayNotHasKey('api.nodes.index', $section->data);
    }

    #[Test]
    public function provide_restricted_takes_precedence_over_public(): void
    {
        $routes = new RouteCollection();

        // Has _public but also _permission — should classify as restricted
        $route = new Route('/special', ['_controller' => 'SpecialController::index']);
        $route->setMethods(['GET']);
        $route->setOption('_public', true);
        $route->setOption('_permission', 'access special');
        $routes->add('special', $route);

        $provider = new PublicSurfaceProvider($routes);
        $section = $provider->provide();

        $this->assertSame('restricted', $section->data['special']['auth']);
    }
}

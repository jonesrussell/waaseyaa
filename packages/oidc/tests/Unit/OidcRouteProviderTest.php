<?php

declare(strict_types=1);

namespace Waaseyaa\Oidc\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Oidc\OidcRouteProvider;
use Waaseyaa\Routing\WaaseyaaRouter;

#[CoversClass(OidcRouteProvider::class)]
final class OidcRouteProviderTest extends TestCase
{
    #[Test]
    public function registerRoutesAddsDiscoveryRoute(): void
    {
        $router = new WaaseyaaRouter();
        $provider = new OidcRouteProvider();

        $provider->registerRoutes($router);

        $route = $router->getRouteCollection()->get('oidc.discovery');

        self::assertNotNull($route);
        self::assertSame('/.well-known/openid-configuration', $route->getPath());
        self::assertContains('GET', $route->getMethods());
        self::assertTrue($route->getOption('_public'));
    }

    #[Test]
    public function registerRoutesAddsJwksRoute(): void
    {
        $router = new WaaseyaaRouter();
        $provider = new OidcRouteProvider();

        $provider->registerRoutes($router);

        $route = $router->getRouteCollection()->get('oidc.jwks');

        self::assertNotNull($route);
        self::assertSame('/.well-known/jwks.json', $route->getPath());
        self::assertContains('GET', $route->getMethods());
        self::assertTrue($route->getOption('_public'));
        self::assertSame(
            'Waaseyaa\\Oidc\\Http\\JwksController::serve',
            $route->getDefault('_controller'),
        );
    }

    #[Test]
    public function registerRoutesSkipsTokenRouteWhenControllerNotProvided(): void
    {
        $router = new WaaseyaaRouter();
        (new OidcRouteProvider())->registerRoutes($router);

        self::assertNull($router->getRouteCollection()->get('oidc.token'));
    }
}

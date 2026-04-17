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
}

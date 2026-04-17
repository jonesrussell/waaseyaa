<?php

declare(strict_types=1);

namespace Waaseyaa\Oidc\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Oidc\OidcServiceProvider;
use Waaseyaa\Routing\WaaseyaaRouter;

#[CoversClass(OidcServiceProvider::class)]
final class OidcServiceProviderTest extends TestCase
{
    #[Test]
    public function routesRegistersDiscoveryEndpoint(): void
    {
        $provider = new OidcServiceProvider();
        $router = new WaaseyaaRouter();

        $provider->routes($router);

        $route = $router->getRouteCollection()->get('oidc.discovery');
        self::assertNotNull($route);
        self::assertSame('/.well-known/openid-configuration', $route->getPath());
    }
}

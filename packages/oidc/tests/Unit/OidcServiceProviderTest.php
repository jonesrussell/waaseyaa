<?php

declare(strict_types=1);

namespace Waaseyaa\Oidc\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Oidc\Http\DiscoveryController;
use Waaseyaa\Oidc\OidcServiceProvider;
use Waaseyaa\Routing\WaaseyaaRouter;

#[CoversClass(OidcServiceProvider::class)]
final class OidcServiceProviderTest extends TestCase
{
    protected function tearDown(): void
    {
        putenv('OIDC_ISSUER');
    }

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

    #[Test]
    public function registerBindsDiscoveryControllerUsingConfigIssuer(): void
    {
        $provider = new OidcServiceProvider();
        $provider->setKernelContext('/tmp/oidc-test', [
            'oidc' => ['issuer' => 'https://id.example'],
        ]);

        $provider->register();

        self::assertArrayHasKey(DiscoveryController::class, $provider->getBindings());

        $controller = $provider->resolve(DiscoveryController::class);
        self::assertInstanceOf(DiscoveryController::class, $controller);

        $body = json_decode((string) $controller->serve()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('https://id.example', $body['issuer']);
    }

    #[Test]
    public function registerFallsBackToOidcIssuerEnvVarWhenConfigMissing(): void
    {
        putenv('OIDC_ISSUER=https://env.example');

        $provider = new OidcServiceProvider();
        $provider->setKernelContext('/tmp/oidc-test', []);

        $provider->register();

        $controller = $provider->resolve(DiscoveryController::class);
        $body = json_decode((string) $controller->serve()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('https://env.example', $body['issuer']);
    }

    #[Test]
    public function resolveReturnsSameDiscoveryControllerInstanceOnRepeatedCalls(): void
    {
        $provider = new OidcServiceProvider();
        $provider->setKernelContext('/tmp/oidc-test', [
            'oidc' => ['issuer' => 'https://id.example'],
        ]);

        $provider->register();

        self::assertSame(
            $provider->resolve(DiscoveryController::class),
            $provider->resolve(DiscoveryController::class),
        );
    }
}

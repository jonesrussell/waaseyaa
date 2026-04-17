<?php

declare(strict_types=1);

namespace Waaseyaa\Oidc;

use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;
use Waaseyaa\Oidc\Http\DiscoveryController;
use Waaseyaa\Routing\WaaseyaaRouter;

final class OidcServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->singleton(
            DiscoveryController::class,
            fn(): DiscoveryController => new DiscoveryController(issuer: $this->resolveIssuer()),
        );
    }

    public function routes(WaaseyaaRouter $router, ?EntityTypeManager $entityTypeManager = null): void
    {
        (new OidcRouteProvider())->registerRoutes($router);
    }

    /**
     * Resolve the OIDC issuer URL: `config['oidc']['issuer']`, then `$OIDC_ISSUER`,
     * then a localhost dev default so route wiring boots even in skeleton installs.
     */
    private function resolveIssuer(): string
    {
        $configIssuer = $this->config['oidc']['issuer'] ?? null;
        if (is_string($configIssuer) && $configIssuer !== '') {
            return $configIssuer;
        }

        $envIssuer = getenv('OIDC_ISSUER');
        if (is_string($envIssuer) && $envIssuer !== '') {
            return $envIssuer;
        }

        return 'http://localhost:8000';
    }
}

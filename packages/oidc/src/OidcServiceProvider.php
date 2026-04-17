<?php

declare(strict_types=1);

namespace Waaseyaa\Oidc;

use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;
use Waaseyaa\Oidc\Http\DiscoveryController;
use Waaseyaa\Oidc\Http\JwksController;
use Waaseyaa\Oidc\Keys\OidcKeyLoaderInterface;
use Waaseyaa\Oidc\Keys\PemFileKeyLoader;
use Waaseyaa\Routing\WaaseyaaRouter;

final class OidcServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->singleton(
            DiscoveryController::class,
            fn(): DiscoveryController => new DiscoveryController(issuer: $this->resolveIssuer()),
        );

        $this->singleton(
            OidcKeyLoaderInterface::class,
            fn(): OidcKeyLoaderInterface => $this->resolveKeyLoader(),
        );

        $this->singleton(
            JwksController::class,
            fn(): JwksController => new JwksController(
                keyLoader: $this->resolve(OidcKeyLoaderInterface::class),
            ),
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

    /**
     * Resolve the OIDC key loader: `config['oidc']['signing_keys']`, then `$OIDC_SIGNING_KEY_DIR`.
     * Throws when neither is set — OIDC signing must be explicit, no silent fallback.
     */
    private function resolveKeyLoader(): OidcKeyLoaderInterface
    {
        /** @var array<string, array{algorithm?: string, public_key_path: string, private_key_path?: string}>|null $configKeys */
        $configKeys = $this->config['oidc']['signing_keys'] ?? null;
        if (is_array($configKeys) && $configKeys !== []) {
            return PemFileKeyLoader::fromConfig($configKeys);
        }

        $envDir = getenv('OIDC_SIGNING_KEY_DIR');
        if (is_string($envDir) && $envDir !== '') {
            return PemFileKeyLoader::fromDirectory($envDir);
        }

        return PemFileKeyLoader::fromConfig([]);
    }
}

<?php

declare(strict_types=1);

namespace Waaseyaa\Oidc;

use Waaseyaa\Database\DatabaseInterface;
use Waaseyaa\Database\DBALDatabase;
use Waaseyaa\Entity\EntityType;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\EntityStorage\SqlEntityStorage;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;
use Waaseyaa\Oidc\Authorize\AuthorizationRequestValidator;
use Waaseyaa\Oidc\Authorize\AuthorizeController;
use Waaseyaa\Oidc\ClientRegistry\OidcClientLookup;
use Waaseyaa\Oidc\ClientRegistry\OidcClientSeeder;
use Waaseyaa\Oidc\Entity\OidcClient;
use Waaseyaa\Oidc\Http\DiscoveryController;
use Waaseyaa\Oidc\Http\JwksController;
use Waaseyaa\Oidc\Keys\OidcKeyLoaderInterface;
use Waaseyaa\Oidc\Keys\PemFileKeyLoader;
use Waaseyaa\Oidc\Repository\AuthorizationCodeRepositoryInterface;
use Waaseyaa\Oidc\Repository\DatabaseAuthorizationCodeRepository;
use Waaseyaa\Oidc\Token\IdTokenMinter;
use Waaseyaa\Oidc\Token\PkceVerifier;
use Waaseyaa\Oidc\Token\TokenController;
use Waaseyaa\Oidc\Token\TokenRequestValidator;

final class OidcServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // OidcClient's type metadata (id, label, keys, fields) lives on the
        // OidcClient class via #[ContentEntityType], #[ContentEntityKeys],
        // and #[Field] attributes.
        $this->entityType(EntityType::fromClass(
            OidcClient::class,
            group: 'oidc',
        ));

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

        $this->singleton(
            AuthorizationCodeRepositoryInterface::class,
            function (): AuthorizationCodeRepositoryInterface {
                $database = $this->resolve(DatabaseInterface::class);
                if (!$database instanceof DBALDatabase) {
                    throw new \RuntimeException(
                        'OIDC authorization code repository requires a DBALDatabase instance; '
                        . 'got ' . $database::class . '.',
                    );
                }

                return new DatabaseAuthorizationCodeRepository(database: $database);
            },
        );

        $this->singleton(
            OidcClientLookup::class,
            function (): OidcClientLookup {
                $entityTypeManager = $this->resolve(EntityTypeManager::class);
                $storage = $entityTypeManager->getStorage('oidc_client');
                if (!$storage instanceof SqlEntityStorage) {
                    throw new \RuntimeException(
                        'OIDC client lookup requires SqlEntityStorage; got ' . $storage::class . '.',
                    );
                }

                return new OidcClientLookup($storage);
            },
        );

        $this->singleton(
            AuthorizationRequestValidator::class,
            static fn(): AuthorizationRequestValidator => new AuthorizationRequestValidator(),
        );

        $this->singleton(
            AuthorizeController::class,
            fn(): AuthorizeController => new AuthorizeController(
                clientLookup: $this->resolve(OidcClientLookup::class),
                validator: $this->resolve(AuthorizationRequestValidator::class),
                codeRepository: $this->resolve(AuthorizationCodeRepositoryInterface::class),
                loginPath: $this->resolveLoginPath(),
            ),
        );

        $this->singleton(
            PkceVerifier::class,
            static fn(): PkceVerifier => new PkceVerifier(),
        );

        $this->singleton(
            TokenRequestValidator::class,
            static fn(): TokenRequestValidator => new TokenRequestValidator(),
        );

        $this->singleton(
            IdTokenMinter::class,
            fn(): IdTokenMinter => new IdTokenMinter(
                keyLoader: $this->resolve(OidcKeyLoaderInterface::class),
            ),
        );

        $this->singleton(
            TokenController::class,
            fn(): TokenController => new TokenController(
                clientLookup: $this->resolve(OidcClientLookup::class),
                validator: $this->resolve(TokenRequestValidator::class),
                pkceVerifier: $this->resolve(PkceVerifier::class),
                codeRepository: $this->resolve(AuthorizationCodeRepositoryInterface::class),
                idTokenMinter: $this->resolve(IdTokenMinter::class),
                issuer: $this->resolveIssuer(),
                clock: static fn(): \DateTimeImmutable => new \DateTimeImmutable(),
            ),
        );
    }

    public function boot(): void
    {
        $this->seedOidcClientsFromConfig();
    }

    private function seedOidcClientsFromConfig(): void
    {
        $clients = $this->config['oidc']['clients'] ?? null;
        if (!is_array($clients) || $clients === []) {
            return;
        }

        try {
            $entityTypeManager = $this->resolve(EntityTypeManager::class);
            $storage = $entityTypeManager->getStorage('oidc_client');
        } catch (\Throwable) {
            return;
        }

        if (!$storage instanceof SqlEntityStorage) {
            return;
        }

        (new OidcClientSeeder($storage))->seed($clients);
    }

    /**
     * Resolve the path to the login page for anonymous authorize redirects.
     * Defaults to `/login` (the admin SPA login route). Override via
     * `config['oidc']['login_path']` when the login UI lives elsewhere.
     */
    private function resolveLoginPath(): string
    {
        $configured = $this->config['oidc']['login_path'] ?? null;
        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        return '/login';
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

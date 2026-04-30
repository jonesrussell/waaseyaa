<?php

declare(strict_types=1);

namespace Waaseyaa\Auth;

use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Foundation\Middleware\HttpMiddlewareInterface;
use Waaseyaa\Foundation\ServiceProvider\Capability\HasMiddlewareInterface;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;

final class AuthServiceProvider extends ServiceProvider implements HasMiddlewareInterface
{
    public function register(): void
    {
        $this->singleton(AuthManager::class, fn() => new AuthManager());

        $this->singleton(RateLimiterInterface::class, function () {
            $db = $this->resolve(\Waaseyaa\Database\DatabaseInterface::class);
            return new DatabaseRateLimiter($db);
        });

        $authConfig = $this->config['auth'] ?? [];
        $appEnv = $this->config['app_env'] ?? ($_ENV['APP_ENV'] ?? 'production');

        $this->singleton(Config\AuthConfig::class, fn() => Config\AuthConfig::fromArray($authConfig, $appEnv));

        $this->singleton(Token\AuthTokenRepositoryInterface::class, function () use ($authConfig) {
            $secret = $authConfig['token_secret'] ?? ($this->config['app_secret'] ?? 'change-me');
            $db = $this->resolve(\Waaseyaa\Database\DatabaseInterface::class);
            $repo = new Token\AuthTokenRepository($db, $secret);
            $repo->ensureSchema();
            return $repo;
        });

        $this->singleton(TwoFactorManager::class, fn() => new TwoFactorManager());
    }

    /**
     * @return list<HttpMiddlewareInterface>
     */
    public function middleware(EntityTypeManager $entityTypeManager): array
    {
        return [];
    }
}

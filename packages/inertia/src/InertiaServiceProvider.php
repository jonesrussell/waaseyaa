<?php

declare(strict_types=1);

namespace Waaseyaa\Inertia;

use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Foundation\Asset\ViteAssetManager;
use Waaseyaa\Foundation\Middleware\HttpMiddlewareInterface;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;

final class InertiaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->registerWithRoot(null);
    }

    /**
     * Configure the Inertia renderer with Vite asset injection.
     *
     * @param string|null $root Project root directory. Defaults to getcwd().
     */
    public function registerWithRoot(?string $root): void
    {
        $cwd = getcwd();
        if ($root === null && $cwd === false) {
            return;
        }
        $root = $root ?? $cwd;
        $devServerUrl = $_ENV['VITE_DEV_SERVER'] ?? getenv('VITE_DEV_SERVER') ?: null;

        $assetManager = new ViteAssetManager(
            basePath: $root . '/public',
            baseUrl: '',
            devServerUrl: $devServerUrl ?: null,
        );

        $renderer = new RootTemplateRenderer(assetManager: $assetManager);
        Inertia::setRenderer($renderer);
    }

    /** @return list<HttpMiddlewareInterface> */
    public function middleware(EntityTypeManager $entityTypeManager): array
    {
        return [new InertiaMiddleware(Inertia::getVersion())];
    }
}

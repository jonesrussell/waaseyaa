<?php

declare(strict_types=1);

namespace Waaseyaa\AI\Tools;

use Psr\Container\ContainerInterface;
use Waaseyaa\AI\Tools\Catalogue\AttributeToolRegistry;
use Waaseyaa\Foundation\Discovery\PackageManifest;
use Waaseyaa\Foundation\Log\LoggerInterface;
use Waaseyaa\Foundation\Log\NullLogger;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;

/**
 * Registers the {@see AttributeToolRegistry} singleton implementing
 * {@see ToolRegistryInterface} alongside the eight stock tool
 * implementations shipped by this package.
 *
 * The registry is constructed lazily; concrete tool classes are
 * instantiated by the kernel container on first call to
 * {@see AttributeToolRegistry::all()} or {@see AttributeToolRegistry::get()}.
 *
 * @api
 */
final class AiToolsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->singleton(ToolRegistryInterface::class, function (): ToolRegistryInterface {
            $manifest = $this->resolveManifest();
            $container = $this->resolveContainer();
            $logger = $this->resolveLogger();

            return new AttributeToolRegistry(
                manifest: $manifest,
                container: $container,
                logger: $logger,
            );
        });

        $this->singleton(AttributeToolRegistry::class, function (): AttributeToolRegistry {
            $registry = $this->resolve(ToolRegistryInterface::class);
            \assert($registry instanceof AttributeToolRegistry);

            return $registry;
        });
    }

    private function resolveManifest(): PackageManifest
    {
        $manifest = $this->kernelServices?->get(PackageManifest::class);
        if ($manifest instanceof PackageManifest) {
            return $manifest;
        }
        // Empty fallback for early-boot / test paths without a kernel.
        return new PackageManifest();
    }

    private function resolveContainer(): ContainerInterface
    {
        $container = $this->kernelServices?->get(ContainerInterface::class);
        if ($container instanceof ContainerInterface) {
            return $container;
        }

        // Self-resolving fallback container: defers to $this->resolve() for
        // bindings registered on this provider. Used during unit tests that
        // do not boot the full kernel.
        $provider = $this;

        return new class ($provider) implements ContainerInterface {
            public function __construct(private readonly ServiceProvider $provider) {}

            public function get(string $id): object
            {
                return $this->provider->resolve($id);
            }

            public function has(string $id): bool
            {
                try {
                    $this->provider->resolve($id);
                    return true;
                } catch (\Throwable) {
                    return false;
                }
            }
        };
    }

    private function resolveLogger(): LoggerInterface
    {
        $logger = $this->kernelServices?->get(LoggerInterface::class);

        return $logger instanceof LoggerInterface ? $logger : new NullLogger();
    }
}

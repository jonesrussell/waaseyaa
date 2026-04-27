<?php

declare(strict_types=1);

namespace Waaseyaa\Field;

use Waaseyaa\Entity\Field\FieldDefinitionRegistryInterface;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;

/**
 * Wires the field package services and boots the BundleTemplateCompiler.
 *
 * Discovery: compile() is called with an empty list by default, making it
 * a no-op until callers explicitly supply template classes. Host applications
 * may resolve BundleTemplateCompiler and call compile(['My\Template', ...])
 * in their own boot step, or WP10 may wire automatic discovery from
 * PackageManifest.
 */
final class FieldServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->singleton(
            FieldDefinitionRegistryInterface::class,
            fn() => new FieldDefinitionRegistry(),
        );

        $this->singleton(
            FieldDefinitionRegistry::class,
            fn() => $this->resolve(FieldDefinitionRegistryInterface::class),
        );

        $this->singleton(
            FieldTypeManager::class,
            fn() => new FieldTypeManager(),
        );

        $this->singleton(
            BundleTemplateCompiler::class,
            fn() => new BundleTemplateCompiler(
                $this->resolve(FieldDefinitionRegistryInterface::class),
            ),
        );
    }

    public function boot(): void
    {
        /** @var BundleTemplateCompiler $compiler */
        $compiler = $this->resolve(BundleTemplateCompiler::class);

        // Pass an empty list: the compiler is a no-op until template classes
        // are supplied. Host applications may call compile([...]) explicitly,
        // or WP10 will wire PackageManifest-based discovery.
        $compiler->compile([]);
    }
}

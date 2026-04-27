<?php

declare(strict_types=1);

namespace Waaseyaa\StructuredImport;

use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;
use Waaseyaa\StructuredImport\Gfm\GfmTableParser;

/**
 * Service provider for the StructuredImport package.
 *
 * Registers the GfmTableParser as a singleton. The StructuredImporterInterface
 * is forward-bound to GfmTableImporter (a string class name) — that class ships
 * in WP09. PHP containers resolve the binding lazily, so no boot-time failure
 * occurs while GfmTableImporter does not yet exist on disk.
 */
final class StructuredImportServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->singleton(GfmTableParser::class, fn() => new GfmTableParser());

        // Forward-bind interface → implementation. Class name is a string so
        // the autoloader is not invoked until the binding is first resolved
        // (which will not occur until WP09 lands and callers request the interface).
        $this->bind(
            StructuredImporterInterface::class,
            'Waaseyaa\\StructuredImport\\Gfm\\GfmTableImporter',
        );
    }
}

<?php

declare(strict_types=1);

namespace Waaseyaa\Migration;

use Waaseyaa\Foundation\ServiceProvider\ServiceProvider as BaseServiceProvider;

/**
 * Service provider for the migration platform package.
 *
 * Discovered via `extra.waaseyaa.providers` in `packages/migration/composer.json`.
 * WP01 lands the package scaffold and stable plugin surface only — concrete
 * service bindings (`PluginRegistry` instance, `MigrationDefinition` loader,
 * `EntityDestination`, CLI commands) land in later work packages of the
 * mission.
 *
 * @api
 */
final class ServiceProvider extends BaseServiceProvider
{
    public function register(): void
    {
        // WP01: scaffold-only. Concrete bindings land in WP02 (definition
        // loader), WP05 (entity destination), WP07 (run service), WP09 (CLI).
    }
}

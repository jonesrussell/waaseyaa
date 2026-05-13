<?php

declare(strict_types=1);

namespace Waaseyaa\Migration\Log;

/**
 * Logger channel names emitted by the migration platform.
 *
 * Centralising these constants makes channel-based log filtering (e.g. for
 * deprecation dashboards) safe to refactor.
 *
 * @api
 */
final class Channels
{
    /**
     * Channel for "experimental plugin used" deprecation notices.
     *
     * Emitted at most once per plugin id per process by
     * {@see \Waaseyaa\Migration\Discovery\PluginRegistry::recordUse()} when an
     * experimental plugin is first accessed.
     */
    public const string MIGRATION_DEPRECATION = 'migration.deprecation';

    /**
     * Private constructor — constants-only holder.
     */
    private function __construct() {}
}

<?php

declare(strict_types=1);

namespace Waaseyaa\Scheduler;

/**
 * Contract for discoverable schedule entries.
 *
 * Implementations are auto-discovered by PackageManifestCompiler and
 * registered at kernel boot via ScheduleEntryRegistry. No ServiceProvider
 * wiring is required — implement this interface and ensure constructor
 * dependencies are container-resolvable.
 *
 * @api
 */
interface ScheduleEntriesInterface
{
    /**
     * Register recurring tasks on the supplied schedule.
     *
     * @return array<string, ScheduledTask> Keyed by task identity string.
     *                                      The key is used for introspection (e.g. schedule:list).
     */
    public function register(ScheduleInterface $schedule): array;
}

<?php

declare(strict_types=1);

namespace Waaseyaa\Migration\Plugin;

use Waaseyaa\Migration\SourceId;

/**
 * Per-call context passed to every {@see ProcessPluginInterface::transform()}.
 *
 * Carries the surrounding state a process plugin needs to compute one
 * destination field value without re-fetching anything: the originating
 * {@see SourceRecord}, the running migration id, the destination field name
 * currently being computed, and a `$lookup` closure that resolves cross-
 * migration references through the id-map.
 *
 * Chain semantics: when a destination field declares a chain of process
 * plugins, the runner threads the output of plugin N into the `$value` argument
 * of plugin N+1's `transform()` call while reusing the same `ProcessContext`
 * (only `$value` changes between steps).
 *
 * @api
 */
final readonly class ProcessContext
{
    /**
     * @param SourceRecord $sourceRecord The source row currently being processed.
     * @param string $migrationId Id of the running migration. Non-empty.
     * @param string $destinationField The destination field name being computed. Non-empty.
     * @param \Closure(string, SourceId): ?WriteResult $lookup Resolves a `SourceId` against a sibling migration's id-map. The runner injects the real implementation; tests pass a stub closure. WP01 ships the contract; WP04/WP06 wire the full implementation.
     *
     * @throws \InvalidArgumentException If $migrationId or $destinationField is empty.
     */
    public function __construct(
        public SourceRecord $sourceRecord,
        public string $migrationId,
        public string $destinationField,
        public \Closure $lookup,
    ) {
        if ($migrationId === '') {
            throw new \InvalidArgumentException('ProcessContext::$migrationId must be a non-empty string.');
        }
        if ($destinationField === '') {
            throw new \InvalidArgumentException('ProcessContext::$destinationField must be a non-empty string.');
        }
    }
}

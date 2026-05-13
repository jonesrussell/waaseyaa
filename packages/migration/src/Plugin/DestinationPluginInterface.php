<?php

declare(strict_types=1);

namespace Waaseyaa\Migration\Plugin;

use Waaseyaa\Migration\SourceId;

/**
 * Contract every destination plugin implements.
 *
 * Destination plugins are the consumer end of the pipeline. They accept a fully
 * transformed {@see DestinationRecord} and persist it to the target system,
 * returning a {@see WriteResult} that the migration runner records in the
 * id-map.
 *
 * Implementations MUST be idempotent: writing the same record twice MUST NOT
 * produce duplicate entities (the runner relies on the id-map + this guarantee
 * for safe re-runs).
 *
 * @api
 */
interface DestinationPluginInterface
{
    /**
     * Globally unique plugin identifier (snake_case, e.g. `entity_destination`).
     */
    public function id(): string;

    /**
     * Plugin stability marker — either `'stable'` or `'experimental'`.
     *
     * Experimental plugins emit a deprecation notice on first use per process.
     */
    public function stability(): string;

    /**
     * Persist the record and return a {@see WriteResult} for the id-map.
     *
     * Implementations must throw a typed exception on failure; the runner
     * records the failure and decides whether to halt or continue based on
     * migration policy.
     */
    public function write(DestinationRecord $record): WriteResult;

    /**
     * Reverse a previous {@see write()}.
     *
     * Called when a migration is rolled back. Implementations MUST treat a
     * missing target (entity already deleted by other means) as a no-op + warn
     * — the migration runner cannot guarantee the destination is untouched
     * between the original write and the rollback.
     */
    public function rollback(WriteResult $result): void;

    /**
     * Resolve a {@see SourceId} to its persisted {@see WriteResult}, or null
     * if this destination has not yet written that source row.
     *
     * Used by cross-migration lookup plugins (e.g. the `lookup` process plugin)
     * so process chains can reference entities written by sibling migrations.
     */
    public function lookup(SourceId $sourceId): ?WriteResult;
}

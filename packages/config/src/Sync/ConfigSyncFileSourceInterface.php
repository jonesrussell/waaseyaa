<?php

declare(strict_types=1);

namespace Waaseyaa\Config\Sync;

/**
 * Pluggable iteration surface over the set of {@see ConfigSyncFile} values
 * the active store would emit to the sync store.
 *
 * `ConfigExporter` depends only on this interface — not on a concrete
 * "config-entity registry" type — so the iteration source can be:
 *
 *  - the production active-store walker (delivered by a later WP that adds
 *    `listAllEntityTypes()` / `listEntitiesOfType()` to `ConfigManagerInterface`),
 *  - an in-memory fixture for tests,
 *  - or a synthetic stream for golden-file snapshot generation.
 *
 * Implementations MUST yield each {@see ConfigSyncFile} exactly once per
 * `iterate()` call. Ordering is implementation-defined but should be
 * deterministic so `config:export` output is stable across runs.
 *
 * @api
 */
interface ConfigSyncFileSourceInterface
{
    /**
     * @return iterable<ConfigSyncFile>
     */
    public function iterate(): iterable;
}

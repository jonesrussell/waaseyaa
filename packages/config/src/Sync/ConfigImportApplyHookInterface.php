<?php

declare(strict_types=1);

namespace Waaseyaa\Config\Sync;

use Waaseyaa\Config\Exception\ConfigImportFailedException;

/**
 * Callback contract — apply a single sync-store entry to the active store.
 *
 * The importer owns the orchestration (validation, DAG ordering, orphan
 * handling, error accumulation, summary emission). The actual mutation of
 * the active store is delegated to an implementation of this interface so
 * that later WPs (and app wiring) can provide the concrete `ConfigManager`
 * bridge without forcing the importer to depend on Layer 1 entity APIs that
 * are not yet on the stable surface.
 *
 * Lifecycle expectations:
 *  - Implementations open a transaction, write the entity, commit, and
 *    return one of {@see ConfigImportEntryResult::STATUS_CREATED},
 *    {@see ConfigImportEntryResult::STATUS_UPDATED}, or
 *    {@see ConfigImportEntryResult::STATUS_UNCHANGED}.
 *  - Failures MUST raise {@see ConfigImportFailedException} (or any
 *    `\Throwable` — the importer wraps non-typed throwables).
 *  - The importer never calls `apply()` in dry-run mode.
 *
 * Stability scope (charter §5.5): the interface FQCN, the method name, the
 * parameter shape, and the return-status set are on stable surface for
 * `waaseyaa/config` v1.x. Adding methods is a breaking change.
 *
 * Symmetrically applies to deletes when orphan deletion is enabled
 * ({@see self::delete()}). A null implementation can simply return
 * {@see ConfigImportEntryResult::STATUS_UNCHANGED} from `apply()` and
 * throw `\LogicException` from `delete()` — see {@see NullConfigImportApplyHook}.
 *
 * @api
 */
interface ConfigImportApplyHookInterface
{
    /**
     * Apply one sync-store entry to the active store.
     *
     * @return string One of the `ConfigImportEntryResult::STATUS_*` constants:
     *                `STATUS_CREATED`, `STATUS_UPDATED`, or `STATUS_UNCHANGED`.
     *
     * @throws ConfigImportFailedException on apply failure
     */
    public function apply(ConfigSyncFile $file): string;

    /**
     * Delete an orphaned active-store entity (no matching sync file).
     *
     * Only invoked when `--delete-orphans` is set on the import run.
     *
     * @param string $ref `<entity_type>.<entity_id>` of the orphan to delete.
     *
     * @throws ConfigImportFailedException on delete failure
     */
    public function delete(string $ref): void;
}

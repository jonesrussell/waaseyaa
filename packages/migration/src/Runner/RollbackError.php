<?php

declare(strict_types=1);

namespace Waaseyaa\Migration\Runner;

/**
 * Per-record rollback failure captured by {@see RollbackWalker} and
 * surfaced via {@see RollbackReport::$errors}.
 *
 * Best-effort semantics (FR-044): a {@see RollbackError} entry means the
 * walker continued past this row; the id-map row is preserved so an
 * operator can retry. The full audit trail (including the original
 * exception trace) is on the `entity.lifecycle` log channel — this value
 * object keeps the in-memory report bounded.
 *
 * @api
 *
 * @spec FR-044 — per-record rollback errors surface in the report
 */
final readonly class RollbackError
{
    /**
     * @param string $sourceIdHash          Id-map primary key fragment — `migration_run_state` lookups can join on this.
     * @param string $destinationEntityType Destination entity type id the row pointed at.
     * @param string $destinationUuid       UUIDv7 of the destination entity the walker tried to delete.
     * @param string $code                  Stable string code suitable for log parsing (e.g. `entity_delete_denied`, `entity_delete_failed`, `id_map_delete_failed`).
     * @param string $message               Human-readable summary; do not parse.
     */
    public function __construct(
        public string $sourceIdHash,
        public string $destinationEntityType,
        public string $destinationUuid,
        public string $code,
        public string $message,
    ) {
        if ($sourceIdHash === '') {
            throw new \InvalidArgumentException(
                'RollbackError::$sourceIdHash must be a non-empty string.',
            );
        }
        if ($destinationEntityType === '') {
            throw new \InvalidArgumentException(
                'RollbackError::$destinationEntityType must be a non-empty string.',
            );
        }
        if ($destinationUuid === '') {
            throw new \InvalidArgumentException(
                'RollbackError::$destinationUuid must be a non-empty string.',
            );
        }
        if ($code === '') {
            throw new \InvalidArgumentException(
                'RollbackError::$code must be a non-empty string.',
            );
        }
        if ($message === '') {
            throw new \InvalidArgumentException(
                'RollbackError::$message must be a non-empty string.',
            );
        }
    }
}

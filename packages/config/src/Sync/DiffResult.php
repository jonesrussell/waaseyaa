<?php

declare(strict_types=1);

namespace Waaseyaa\Config\Sync;

/**
 * Per-ref unified-diff result emitted by {@see ConfigDiffer::diff()}.
 *
 * Each result classifies the relationship between the sync-store YAML and the
 * active-store YAML for one config ref:
 *
 *  - {@see self::STATUS_IN_SYNC}: identical canonical YAML on both sides.
 *  - {@see self::STATUS_DRIFT}: same ref, differing canonical YAML.
 *  - {@see self::STATUS_SYNC_ONLY}: ref exists in sync store, not in active.
 *  - {@see self::STATUS_ACTIVE_ONLY}: ref exists in active store, not in sync.
 *  - {@see self::STATUS_RENAMED}: same `_meta.uuid` on both sides but
 *    different refs — sync wants to rename the active entity (FR-033).
 *
 * The `diff` property is a unified diff (RFC-style `---`/`+++` headers) of
 * the canonical YAML on both sides. It is empty when status is
 * `STATUS_IN_SYNC`.
 *
 * Stability scope: INTERNAL value-object surface. The CLI commands map these
 * statuses + diff text onto stable exit codes and human-readable output;
 * downstream code should consume {@see ConfigDiffer} not this class.
 */
final readonly class DiffResult
{
    public const STATUS_IN_SYNC = 'in_sync';
    public const STATUS_DRIFT = 'drift';
    public const STATUS_SYNC_ONLY = 'sync_only';
    public const STATUS_ACTIVE_ONLY = 'active_only';
    public const STATUS_RENAMED = 'renamed';

    /**
     * @param string      $ref       canonical `<entity_type>.<entity_id>` ref
     * @param string      $status    one of the STATUS_* constants
     * @param string      $diff      unified-diff text (empty when in-sync)
     * @param string|null $renamedFrom previous ref when $status is STATUS_RENAMED
     * @param string|null $uuid      `_meta.uuid` shared across both sides (when known)
     */
    public function __construct(
        public string $ref,
        public string $status,
        public string $diff = '',
        public ?string $renamedFrom = null,
        public ?string $uuid = null,
    ) {
        if (!\in_array($status, [
            self::STATUS_IN_SYNC,
            self::STATUS_DRIFT,
            self::STATUS_SYNC_ONLY,
            self::STATUS_ACTIVE_ONLY,
            self::STATUS_RENAMED,
        ], true)) {
            throw new \InvalidArgumentException(sprintf(
                'DiffResult status "%s" must be one of in_sync, drift, sync_only, active_only, renamed.',
                $status,
            ));
        }
        if ($status === self::STATUS_RENAMED && ($renamedFrom === null || $renamedFrom === '')) {
            throw new \InvalidArgumentException('DiffResult with status=renamed requires a non-empty renamedFrom.');
        }
    }

    public function hasDifferences(): bool
    {
        return $this->status !== self::STATUS_IN_SYNC;
    }
}

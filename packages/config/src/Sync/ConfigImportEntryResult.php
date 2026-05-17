<?php

declare(strict_types=1);

namespace Waaseyaa\Config\Sync;

/**
 * Immutable per-entity outcome produced by {@see ConfigImporter::import()}.
 *
 * One result per entry processed during the run — successful applies,
 * unchanged passes, orphan handling outcomes, and failures all surface as
 * `ConfigImportEntryResult` values. The CLI renders each as a single
 * operator-facing line via {@see ConfigImportCommand}.
 *
 * Stability scope (charter §5.5): the class FQCN, the public properties,
 * the `STATUS_*` constant values, and the constructor signature are on
 * stable surface for `waaseyaa/config` v1.x.
 *
 * @api
 */
final readonly class ConfigImportEntryResult
{
    public const STATUS_CREATED = 'created';
    public const STATUS_UPDATED = 'updated';
    public const STATUS_UNCHANGED = 'unchanged';
    public const STATUS_DELETED = 'deleted';
    public const STATUS_FAILED = 'failed';

    public function __construct(
        public string $ref,
        public string $status,
        public ?string $reason = null,
    ) {
        if (!in_array($status, [
            self::STATUS_CREATED,
            self::STATUS_UPDATED,
            self::STATUS_UNCHANGED,
            self::STATUS_DELETED,
            self::STATUS_FAILED,
        ], true)) {
            throw new \InvalidArgumentException(sprintf(
                'ConfigImportEntryResult status "%s" is not one of the documented STATUS_* constants.',
                $status,
            ));
        }
    }

    public function isFailure(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }
}

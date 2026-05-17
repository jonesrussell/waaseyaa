<?php

declare(strict_types=1);

namespace Waaseyaa\Config\Sync;

/**
 * Per-file write outcome produced by {@see ConfigExporter::export()}.
 *
 * - `STATUS_CREATED`  — sync file did not exist; was written (or would be in dry-run).
 * - `STATUS_UPDATED`  — sync file existed with different YAML; was overwritten.
 * - `STATUS_UNCHANGED` — sync file existed with byte-identical YAML; no write performed.
 *
 * @api
 */
final readonly class ConfigExportFileResult
{
    public const STATUS_CREATED = 'created';
    public const STATUS_UPDATED = 'updated';
    public const STATUS_UNCHANGED = 'unchanged';

    /**
     * @param self::STATUS_* $status
     */
    public function __construct(
        public string $ref,
        public string $filename,
        public string $status,
    ) {}
}

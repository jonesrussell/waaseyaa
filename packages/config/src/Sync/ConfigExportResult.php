<?php

declare(strict_types=1);

namespace Waaseyaa\Config\Sync;

/**
 * Aggregate outcome of a single `config:export` run.
 *
 * Carries per-file outcomes (in source-iteration order) plus the
 * `X created, Y updated, Z unchanged.` counts (FR-020). The CLI command
 * uses both: per-file lines render from `$files`, the trailing summary
 * line renders via {@see self::summary()}.
 *
 * @api
 */
final readonly class ConfigExportResult
{
    /**
     * @param list<ConfigExportFileResult> $files in source-iteration order
     */
    public function __construct(
        public array $files,
        public bool $dryRun,
    ) {}

    public function created(): int
    {
        return $this->countByStatus(ConfigExportFileResult::STATUS_CREATED);
    }

    public function updated(): int
    {
        return $this->countByStatus(ConfigExportFileResult::STATUS_UPDATED);
    }

    public function unchanged(): int
    {
        return $this->countByStatus(ConfigExportFileResult::STATUS_UNCHANGED);
    }

    /**
     * Canonical summary string (FR-020).
     */
    public function summary(): string
    {
        return sprintf(
            '%d created, %d updated, %d unchanged.',
            $this->created(),
            $this->updated(),
            $this->unchanged(),
        );
    }

    private function countByStatus(string $status): int
    {
        $count = 0;
        foreach ($this->files as $file) {
            if ($file->status === $status) {
                ++$count;
            }
        }

        return $count;
    }
}

<?php

declare(strict_types=1);

namespace Waaseyaa\Config\Sync;

/**
 * Aggregate result of a {@see ConfigSyncValidator::validate()} run.
 *
 * `isValid()` is the CI-gate signal (FR-040): true iff every entry is
 * valid. Entries are emitted in alphabetical ref order so CI diff
 * output stays stable across runs.
 *
 * @api
 */
final readonly class ConfigValidateResult
{
    /**
     * @param list<ConfigValidateEntry> $entries
     */
    public function __construct(
        public array $entries,
    ) {}

    public function isValid(): bool
    {
        foreach ($this->entries as $entry) {
            if (!$entry->isValid()) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<ConfigValidateEntry>
     */
    public function failingEntries(): array
    {
        $failing = [];
        foreach ($this->entries as $entry) {
            if (!$entry->isValid()) {
                $failing[] = $entry;
            }
        }

        return $failing;
    }

    public function totalViolations(): int
    {
        $total = 0;
        foreach ($this->entries as $entry) {
            $total += \count($entry->violations);
        }

        return $total;
    }
}

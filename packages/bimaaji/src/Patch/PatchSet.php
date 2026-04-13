<?php

declare(strict_types=1);

namespace Waaseyaa\Bimaaji\Patch;

final readonly class PatchSet
{
    /** @param list<PatchEntry> $patches */
    public function __construct(
        public array $patches,
    ) {}

    public function hasUnsafePatches(): bool
    {
        foreach ($this->patches as $patch) {
            if ($patch->unsafe) {
                return true;
            }
        }

        return false;
    }

    /** @return array{patches: list<array<string, mixed>>} */
    public function toArray(): array
    {
        return [
            'patches' => array_map(
                static fn(PatchEntry $entry) => $entry->toArray(),
                $this->patches,
            ),
        ];
    }
}

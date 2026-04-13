<?php

declare(strict_types=1);

namespace Waaseyaa\Bimaaji\Dsl;

use Waaseyaa\Bimaaji\Mutation\MutationResult;
use Waaseyaa\Bimaaji\Patch\PatchSet;

final readonly class TaskPipelineResult
{
    public function __construct(
        public MutationResult $mutationResult,
        public ?PatchSet $patchSet,
    ) {}

    /** @return array{mutation_result: array<string, mixed>, patch_set: ?array<string, mixed>} */
    public function toArray(): array
    {
        return [
            'mutation_result' => $this->mutationResult->toArray(),
            'patch_set' => $this->patchSet?->toArray(),
        ];
    }
}

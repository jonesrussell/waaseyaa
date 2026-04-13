<?php

declare(strict_types=1);

namespace Waaseyaa\Bimaaji\Mutation;

use Waaseyaa\Bimaaji\Graph\ApplicationGraph;

final class MutationValidator
{
    private const array CREATION_OPERATIONS = ['add_entity_type'];

    public function __construct(
        private readonly ApplicationGraph $graph,
    ) {}

    public function validate(MutationRequest $request): MutationResult
    {
        $errors = [];

        if (!in_array($request->operation, self::CREATION_OPERATIONS, true)) {
            $entitiesSection = $this->graph->getSection('entities');
            $entities = $entitiesSection?->data ?? [];

            if (!isset($entities[$request->entityType])) {
                $errors[] = 'UNKNOWN_ENTITY_TYPE';
            }
        }

        if ($errors !== []) {
            return MutationResult::failure($request, $errors);
        }

        return MutationResult::success($request);
    }
}

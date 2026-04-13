<?php

declare(strict_types=1);

namespace Waaseyaa\Bimaaji\Dsl;

use Waaseyaa\Bimaaji\Mutation\MutationRequest;
use Waaseyaa\Bimaaji\Mutation\MutationValidator;
use Waaseyaa\Bimaaji\Patch\PatchGenerator;

final class TaskPipeline
{
    public function __construct(
        private readonly MutationValidator $validator,
        private readonly PatchGenerator $patchGenerator,
    ) {}

    public function execute(TaskDefinition $task): TaskPipelineResult
    {
        $request = new MutationRequest(
            operation: $task->operation,
            entityType: $task->entityType,
            field: $task->field,
            parameters: $task->parameters,
        );

        $result = $this->validator->validate($request);

        if (!$result->isSuccess()) {
            return new TaskPipelineResult($result, null);
        }

        $patchSet = $this->patchGenerator->generate($result);

        return new TaskPipelineResult($result, $patchSet);
    }
}

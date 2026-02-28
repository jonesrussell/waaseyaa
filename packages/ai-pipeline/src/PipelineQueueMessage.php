<?php

declare(strict_types=1);

namespace Aurora\AI\Pipeline;

/**
 * Message for async pipeline execution via a queue.
 *
 * Contains all the information needed to execute a pipeline asynchronously:
 * the pipeline ID and the initial input data.
 */
final readonly class PipelineQueueMessage
{
    /**
     * @param string $pipelineId The ID of the pipeline to execute.
     * @param array<string, mixed> $input Initial input data for the pipeline.
     * @param int $createdAt Unix timestamp when the message was created.
     */
    public function __construct(
        public string $pipelineId,
        public array $input = [],
        public int $createdAt = 0,
    ) {}
}

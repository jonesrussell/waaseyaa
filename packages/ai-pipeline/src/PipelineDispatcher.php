<?php

declare(strict_types=1);

namespace Aurora\AI\Pipeline;

use Aurora\Queue\QueueInterface;

/**
 * Dispatches pipelines to a queue for async execution.
 *
 * This is a fire-and-forget dispatcher: it creates a queue message
 * and dispatches it, but does not execute the pipeline itself.
 */
final class PipelineDispatcher
{
    public function __construct(
        private readonly QueueInterface $queue,
    ) {}

    /**
     * Dispatch a pipeline for async execution.
     *
     * @param Pipeline $pipeline The pipeline to dispatch.
     * @param array<string, mixed> $input Initial input data for the pipeline.
     *
     * @return PipelineQueueMessage The dispatched message.
     */
    public function dispatch(Pipeline $pipeline, array $input = []): PipelineQueueMessage
    {
        $message = new PipelineQueueMessage(
            pipelineId: (string) ($pipeline->id() ?? ''),
            input: $input,
            createdAt: time(),
        );

        $this->queue->dispatch($message);

        return $message;
    }
}

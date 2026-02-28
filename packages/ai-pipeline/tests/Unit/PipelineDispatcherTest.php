<?php

declare(strict_types=1);

namespace Aurora\AI\Pipeline\Tests\Unit;

use Aurora\AI\Pipeline\Pipeline;
use Aurora\AI\Pipeline\PipelineDispatcher;
use Aurora\AI\Pipeline\PipelineQueueMessage;
use Aurora\Queue\InMemoryQueue;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Aurora\AI\Pipeline\PipelineDispatcher
 */
final class PipelineDispatcherTest extends TestCase
{
    public function testDispatchSendsMessageToQueue(): void
    {
        $queue = new InMemoryQueue();
        $dispatcher = new PipelineDispatcher($queue);

        $pipeline = new Pipeline(['id' => 'my_pipeline', 'label' => 'My Pipeline']);

        $message = $dispatcher->dispatch($pipeline, ['text' => 'hello']);

        $this->assertInstanceOf(PipelineQueueMessage::class, $message);
        $this->assertSame('my_pipeline', $message->pipelineId);
        $this->assertSame(['text' => 'hello'], $message->input);
        $this->assertGreaterThan(0, $message->createdAt);

        $messages = $queue->getMessages();
        $this->assertCount(1, $messages);
        $this->assertSame($message, $messages[0]);
    }

    public function testDispatchWithNoInput(): void
    {
        $queue = new InMemoryQueue();
        $dispatcher = new PipelineDispatcher($queue);

        $pipeline = new Pipeline(['id' => 'empty_input', 'label' => 'No Input']);

        $message = $dispatcher->dispatch($pipeline);

        $this->assertSame('empty_input', $message->pipelineId);
        $this->assertSame([], $message->input);

        $messages = $queue->getMessages();
        $this->assertCount(1, $messages);
    }

    public function testDispatchMultiplePipelines(): void
    {
        $queue = new InMemoryQueue();
        $dispatcher = new PipelineDispatcher($queue);

        $pipeline1 = new Pipeline(['id' => 'pipeline_1']);
        $pipeline2 = new Pipeline(['id' => 'pipeline_2']);

        $dispatcher->dispatch($pipeline1, ['a' => 1]);
        $dispatcher->dispatch($pipeline2, ['b' => 2]);

        $messages = $queue->getMessages();
        $this->assertCount(2, $messages);

        $this->assertSame('pipeline_1', $messages[0]->pipelineId);
        $this->assertSame('pipeline_2', $messages[1]->pipelineId);
    }

    public function testDispatchIsFireAndForget(): void
    {
        $queue = new InMemoryQueue();
        $dispatcher = new PipelineDispatcher($queue);

        $pipeline = new Pipeline([
            'id' => 'test',
            'steps' => [
                ['id' => 'step_1', 'plugin_id' => 'some_plugin', 'weight' => 0],
            ],
        ]);

        // Dispatch does not execute the pipeline, just queues it.
        $message = $dispatcher->dispatch($pipeline, ['data' => 'value']);

        // The message is queued but the pipeline has not been executed.
        $this->assertInstanceOf(PipelineQueueMessage::class, $message);
        $this->assertSame('test', $message->pipelineId);
    }
}

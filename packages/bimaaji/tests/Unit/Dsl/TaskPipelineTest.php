<?php

declare(strict_types=1);

namespace Waaseyaa\Bimaaji\Tests\Unit\Dsl;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Bimaaji\Dsl\TaskDefinition;
use Waaseyaa\Bimaaji\Dsl\TaskPipeline;
use Waaseyaa\Bimaaji\Graph\ApplicationGraph;
use Waaseyaa\Bimaaji\Graph\GraphSection;
use Waaseyaa\Bimaaji\Mutation\MutationValidator;
use Waaseyaa\Bimaaji\Patch\PatchGenerator;
use Waaseyaa\Bimaaji\Patch\PatchSet;

#[CoversClass(TaskPipeline::class)]
final class TaskPipelineTest extends TestCase
{
    #[Test]
    public function it_executes_full_pipeline_for_add_field(): void
    {
        $graph = new ApplicationGraph('1.0', [
            new GraphSection('entities', '1.0', [
                'article' => ['fields' => ['title' => []]],
            ]),
        ]);

        $pipeline = new TaskPipeline(
            new MutationValidator($graph),
            new PatchGenerator(),
        );

        $task = new TaskDefinition('add_field', 'article', 'subtitle', ['type' => 'string']);
        $result = $pipeline->execute($task);

        $this->assertInstanceOf(PatchSet::class, $result->patchSet);
        $this->assertTrue($result->mutationResult->isSuccess());
        $this->assertCount(1, $result->patchSet->patches);
    }

    #[Test]
    public function it_returns_failure_for_invalid_mutation(): void
    {
        $graph = new ApplicationGraph('1.0', [
            new GraphSection('entities', '1.0', []),
        ]);

        $pipeline = new TaskPipeline(
            new MutationValidator($graph),
            new PatchGenerator(),
        );

        $task = new TaskDefinition('add_field', 'nonexistent', 'title');
        $result = $pipeline->execute($task);

        $this->assertFalse($result->mutationResult->isSuccess());
        $this->assertNull($result->patchSet);
    }

    #[Test]
    public function pipeline_result_is_serializable(): void
    {
        $graph = new ApplicationGraph('1.0', [
            new GraphSection('entities', '1.0', [
                'node' => ['fields' => ['title' => []]],
            ]),
        ]);

        $pipeline = new TaskPipeline(
            new MutationValidator($graph),
            new PatchGenerator(),
        );

        $task = new TaskDefinition('add_field', 'node', 'body', ['type' => 'text']);
        $result = $pipeline->execute($task);
        $array = $result->toArray();

        $this->assertArrayHasKey('mutation_result', $array);
        $this->assertArrayHasKey('patch_set', $array);
        $this->assertSame('success', $array['mutation_result']['status']);
    }
}

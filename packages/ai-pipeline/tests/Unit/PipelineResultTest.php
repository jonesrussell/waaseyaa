<?php

declare(strict_types=1);

namespace Aurora\AI\Pipeline\Tests\Unit;

use Aurora\AI\Pipeline\PipelineResult;
use Aurora\AI\Pipeline\StepResult;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Aurora\AI\Pipeline\PipelineResult
 */
final class PipelineResultTest extends TestCase
{
    public function testConstruction(): void
    {
        $stepResult1 = StepResult::success(['text' => 'HELLO'], 'Uppercased');
        $stepResult2 = StepResult::success(['text' => 'HELLO!'], 'Appended');

        $result = new PipelineResult(
            success: true,
            stepResults: [$stepResult1, $stepResult2],
            finalOutput: ['text' => 'HELLO!'],
            message: 'Pipeline completed successfully.',
            durationMs: 12.5,
        );

        $this->assertTrue($result->success);
        $this->assertCount(2, $result->stepResults);
        $this->assertSame(['text' => 'HELLO!'], $result->finalOutput);
        $this->assertSame('Pipeline completed successfully.', $result->message);
        $this->assertSame(12.5, $result->durationMs);
    }

    public function testFailedResult(): void
    {
        $stepResult = StepResult::failure('Error occurred');

        $result = new PipelineResult(
            success: false,
            stepResults: [$stepResult],
            finalOutput: [],
            message: 'Pipeline failed at step "step_1": Error occurred',
            durationMs: 1.0,
        );

        $this->assertFalse($result->success);
        $this->assertCount(1, $result->stepResults);
        $this->assertFalse($result->stepResults[0]->success);
    }

    public function testEmptyStepResults(): void
    {
        $result = new PipelineResult(
            success: true,
            stepResults: [],
            finalOutput: ['initial' => 'data'],
            message: 'Pipeline has no steps.',
            durationMs: 0.01,
        );

        $this->assertTrue($result->success);
        $this->assertSame([], $result->stepResults);
        $this->assertSame(['initial' => 'data'], $result->finalOutput);
    }

    public function testIsReadonly(): void
    {
        $result = new PipelineResult(
            success: true,
            stepResults: [],
            finalOutput: [],
            message: '',
            durationMs: 0,
        );

        $reflection = new \ReflectionClass($result);
        $this->assertTrue($reflection->isReadOnly());
    }
}

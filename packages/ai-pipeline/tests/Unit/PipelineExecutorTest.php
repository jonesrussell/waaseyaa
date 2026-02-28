<?php

declare(strict_types=1);

namespace Aurora\AI\Pipeline\Tests\Unit;

use Aurora\AI\Pipeline\Pipeline;
use Aurora\AI\Pipeline\PipelineContext;
use Aurora\AI\Pipeline\PipelineExecutor;
use Aurora\AI\Pipeline\PipelineStepInterface;
use Aurora\AI\Pipeline\StepResult;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Aurora\AI\Pipeline\PipelineExecutor
 */
final class PipelineExecutorTest extends TestCase
{
    public function testExecuteEmptyPipeline(): void
    {
        $executor = new PipelineExecutor();
        $pipeline = new Pipeline(['id' => 'empty', 'label' => 'Empty']);

        $result = $executor->execute($pipeline, ['text' => 'hello']);

        $this->assertTrue($result->success);
        $this->assertSame([], $result->stepResults);
        $this->assertSame(['text' => 'hello'], $result->finalOutput);
        $this->assertSame('Pipeline has no steps.', $result->message);
        $this->assertGreaterThanOrEqual(0, $result->durationMs);
    }

    public function testExecuteWithOneStep(): void
    {
        $uppercaseStep = $this->createUppercaseStep();

        $executor = new PipelineExecutor(['uppercase' => $uppercaseStep]);
        $pipeline = new Pipeline([
            'id' => 'test',
            'steps' => [
                ['id' => 'step_1', 'plugin_id' => 'uppercase', 'weight' => 0],
            ],
        ]);

        $result = $executor->execute($pipeline, ['text' => 'hello world']);

        $this->assertTrue($result->success);
        $this->assertCount(1, $result->stepResults);
        $this->assertSame(['text' => 'HELLO WORLD'], $result->finalOutput);
        $this->assertSame('Pipeline completed successfully.', $result->message);
    }

    public function testExecuteWithMultipleStepsOutputChains(): void
    {
        $uppercaseStep = $this->createUppercaseStep();
        $appendStep = $this->createAppendStep('!!!');

        $executor = new PipelineExecutor([
            'uppercase' => $uppercaseStep,
            'append' => $appendStep,
        ]);
        $pipeline = new Pipeline([
            'id' => 'test',
            'steps' => [
                ['id' => 'step_1', 'plugin_id' => 'uppercase', 'weight' => 0],
                ['id' => 'step_2', 'plugin_id' => 'append', 'weight' => 10],
            ],
        ]);

        $result = $executor->execute($pipeline, ['text' => 'hello']);

        $this->assertTrue($result->success);
        $this->assertCount(2, $result->stepResults);
        $this->assertSame(['text' => 'HELLO!!!'], $result->finalOutput);
    }

    public function testStepFailureStopsPipeline(): void
    {
        $failStep = $this->createFailStep('Something broke');
        $uppercaseStep = $this->createUppercaseStep();

        $executor = new PipelineExecutor([
            'fail' => $failStep,
            'uppercase' => $uppercaseStep,
        ]);
        $pipeline = new Pipeline([
            'id' => 'test',
            'steps' => [
                ['id' => 'step_1', 'plugin_id' => 'fail', 'weight' => 0],
                ['id' => 'step_2', 'plugin_id' => 'uppercase', 'weight' => 10],
            ],
        ]);

        $result = $executor->execute($pipeline, ['text' => 'hello']);

        $this->assertFalse($result->success);
        $this->assertCount(1, $result->stepResults);
        $this->assertStringContainsString('Something broke', $result->message);
        $this->assertStringContainsString('step_1', $result->message);
    }

    public function testStepHaltStopsPipelineButSucceeds(): void
    {
        $haltStep = $this->createHaltStep('Threshold reached', ['score' => 0.95]);
        $uppercaseStep = $this->createUppercaseStep();

        $executor = new PipelineExecutor([
            'halt' => $haltStep,
            'uppercase' => $uppercaseStep,
        ]);
        $pipeline = new Pipeline([
            'id' => 'test',
            'steps' => [
                ['id' => 'step_1', 'plugin_id' => 'halt', 'weight' => 0],
                ['id' => 'step_2', 'plugin_id' => 'uppercase', 'weight' => 10],
            ],
        ]);

        $result = $executor->execute($pipeline, ['text' => 'hello']);

        $this->assertTrue($result->success);
        $this->assertCount(1, $result->stepResults);
        $this->assertSame(['score' => 0.95], $result->finalOutput);
        $this->assertStringContainsString('halted', $result->message);
        $this->assertStringContainsString('Threshold reached', $result->message);
    }

    public function testMissingPluginIdHandled(): void
    {
        $executor = new PipelineExecutor([]);
        $pipeline = new Pipeline([
            'id' => 'test',
            'steps' => [
                ['id' => 'step_1', 'plugin_id' => 'nonexistent_plugin', 'weight' => 0],
            ],
        ]);

        $result = $executor->execute($pipeline, []);

        $this->assertFalse($result->success);
        $this->assertCount(1, $result->stepResults);
        $this->assertFalse($result->stepResults[0]->success);
        $this->assertStringContainsString('nonexistent_plugin', $result->message);
        $this->assertStringContainsString('not found', $result->message);
    }

    public function testStepsExecutedInWeightOrder(): void
    {
        $executionOrder = [];

        $stepA = $this->createTrackingStep('A', $executionOrder);
        $stepB = $this->createTrackingStep('B', $executionOrder);
        $stepC = $this->createTrackingStep('C', $executionOrder);

        $executor = new PipelineExecutor([
            'step_a' => $stepA,
            'step_b' => $stepB,
            'step_c' => $stepC,
        ]);
        $pipeline = new Pipeline([
            'id' => 'test',
            'steps' => [
                ['id' => 'heavy', 'plugin_id' => 'step_c', 'weight' => 100],
                ['id' => 'light', 'plugin_id' => 'step_a', 'weight' => -10],
                ['id' => 'medium', 'plugin_id' => 'step_b', 'weight' => 50],
            ],
        ]);

        $result = $executor->execute($pipeline);

        $this->assertTrue($result->success);
        $this->assertSame(['A', 'B', 'C'], $executionOrder);
    }

    public function testDurationMsIsPositive(): void
    {
        $executor = new PipelineExecutor(['pass' => $this->createUppercaseStep()]);
        $pipeline = new Pipeline([
            'id' => 'test',
            'steps' => [
                ['id' => 'step_1', 'plugin_id' => 'pass', 'weight' => 0],
            ],
        ]);

        $result = $executor->execute($pipeline, ['text' => 'test']);

        $this->assertGreaterThanOrEqual(0, $result->durationMs);
    }

    public function testFailureInMiddleReturnsPartialResults(): void
    {
        $uppercaseStep = $this->createUppercaseStep();
        $failStep = $this->createFailStep('Broke in middle');
        $appendStep = $this->createAppendStep('!');

        $executor = new PipelineExecutor([
            'uppercase' => $uppercaseStep,
            'fail' => $failStep,
            'append' => $appendStep,
        ]);
        $pipeline = new Pipeline([
            'id' => 'test',
            'steps' => [
                ['id' => 'step_1', 'plugin_id' => 'uppercase', 'weight' => 0],
                ['id' => 'step_2', 'plugin_id' => 'fail', 'weight' => 10],
                ['id' => 'step_3', 'plugin_id' => 'append', 'weight' => 20],
            ],
        ]);

        $result = $executor->execute($pipeline, ['text' => 'hello']);

        $this->assertFalse($result->success);
        // Two step results: uppercase succeeded, fail failed
        $this->assertCount(2, $result->stepResults);
        $this->assertTrue($result->stepResults[0]->success);
        $this->assertFalse($result->stepResults[1]->success);
    }

    public function testContextPassedToSteps(): void
    {
        $contextCapture = null;

        $step = new class ($contextCapture) implements PipelineStepInterface {
            /** @var PipelineContext|null */
            private mixed $capture;

            public function __construct(private mixed &$captureRef)
            {
                $this->capture = &$captureRef;
            }

            public function process(array $input, PipelineContext $context): StepResult
            {
                $this->captureRef = $context;

                return StepResult::success($input);
            }

            public function describe(): string
            {
                return 'Captures context';
            }
        };

        $executor = new PipelineExecutor(['capture' => $step]);
        $pipeline = new Pipeline([
            'id' => 'my_pipeline',
            'steps' => [
                ['id' => 'step_1', 'plugin_id' => 'capture', 'weight' => 0],
            ],
        ]);

        $executor->execute($pipeline);

        $this->assertInstanceOf(PipelineContext::class, $contextCapture);
        $this->assertSame('my_pipeline', $contextCapture->pipelineId);
    }

    // --- Helper methods to create test step implementations ---

    private function createUppercaseStep(): PipelineStepInterface
    {
        return new class implements PipelineStepInterface {
            public function process(array $input, PipelineContext $context): StepResult
            {
                $output = $input;
                if (isset($output['text'])) {
                    $output['text'] = strtoupper((string) $output['text']);
                }

                return StepResult::success($output, 'Uppercased text');
            }

            public function describe(): string
            {
                return 'Converts text to uppercase';
            }
        };
    }

    private function createAppendStep(string $suffix): PipelineStepInterface
    {
        return new class ($suffix) implements PipelineStepInterface {
            public function __construct(private readonly string $suffix) {}

            public function process(array $input, PipelineContext $context): StepResult
            {
                $output = $input;
                if (isset($output['text'])) {
                    $output['text'] = $output['text'] . $this->suffix;
                }

                return StepResult::success($output, 'Appended suffix');
            }

            public function describe(): string
            {
                return 'Appends suffix to text';
            }
        };
    }

    private function createFailStep(string $message): PipelineStepInterface
    {
        return new class ($message) implements PipelineStepInterface {
            public function __construct(private readonly string $failMessage) {}

            public function process(array $input, PipelineContext $context): StepResult
            {
                return StepResult::failure($this->failMessage);
            }

            public function describe(): string
            {
                return 'Always fails';
            }
        };
    }

    private function createHaltStep(string $message, array $output = []): PipelineStepInterface
    {
        return new class ($message, $output) implements PipelineStepInterface {
            public function __construct(
                private readonly string $haltMessage,
                private readonly array $haltOutput,
            ) {}

            public function process(array $input, PipelineContext $context): StepResult
            {
                return StepResult::halt($this->haltMessage, $this->haltOutput);
            }

            public function describe(): string
            {
                return 'Halts the pipeline';
            }
        };
    }

    /**
     * Creates a step that tracks execution order.
     *
     * @param string $name Name to record when executed.
     * @param array<int, string> &$executionOrder Array to append name to.
     */
    private function createTrackingStep(string $name, array &$executionOrder): PipelineStepInterface
    {
        return new class ($name, $executionOrder) implements PipelineStepInterface {
            public function __construct(
                private readonly string $name,
                private array &$order,
            ) {}

            public function process(array $input, PipelineContext $context): StepResult
            {
                $this->order[] = $this->name;

                return StepResult::success($input);
            }

            public function describe(): string
            {
                return 'Tracking step: ' . $this->name;
            }
        };
    }
}

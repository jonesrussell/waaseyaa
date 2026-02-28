<?php

declare(strict_types=1);

namespace Aurora\AI\Pipeline\Tests\Unit;

use Aurora\AI\Pipeline\StepResult;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Aurora\AI\Pipeline\StepResult
 */
final class StepResultTest extends TestCase
{
    public function testConstructor(): void
    {
        $result = new StepResult(
            success: true,
            output: ['key' => 'value'],
            message: 'Done',
            stopPipeline: false,
        );

        $this->assertTrue($result->success);
        $this->assertSame(['key' => 'value'], $result->output);
        $this->assertSame('Done', $result->message);
        $this->assertFalse($result->stopPipeline);
    }

    public function testConstructorDefaults(): void
    {
        $result = new StepResult(success: true);

        $this->assertTrue($result->success);
        $this->assertSame([], $result->output);
        $this->assertSame('', $result->message);
        $this->assertFalse($result->stopPipeline);
    }

    public function testSuccessFactory(): void
    {
        $result = StepResult::success(['text' => 'hello'], 'Step succeeded');

        $this->assertTrue($result->success);
        $this->assertSame(['text' => 'hello'], $result->output);
        $this->assertSame('Step succeeded', $result->message);
        $this->assertFalse($result->stopPipeline);
    }

    public function testSuccessFactoryDefaults(): void
    {
        $result = StepResult::success();

        $this->assertTrue($result->success);
        $this->assertSame([], $result->output);
        $this->assertSame('', $result->message);
        $this->assertFalse($result->stopPipeline);
    }

    public function testFailureFactory(): void
    {
        $result = StepResult::failure('Something went wrong', ['error_code' => 42]);

        $this->assertFalse($result->success);
        $this->assertSame('Something went wrong', $result->message);
        $this->assertSame(['error_code' => 42], $result->output);
        $this->assertFalse($result->stopPipeline);
    }

    public function testFailureFactoryDefaults(): void
    {
        $result = StepResult::failure('Error');

        $this->assertFalse($result->success);
        $this->assertSame('Error', $result->message);
        $this->assertSame([], $result->output);
        $this->assertFalse($result->stopPipeline);
    }

    public function testHaltFactory(): void
    {
        $result = StepResult::halt('Threshold reached', ['score' => 0.95]);

        $this->assertTrue($result->success);
        $this->assertSame('Threshold reached', $result->message);
        $this->assertSame(['score' => 0.95], $result->output);
        $this->assertTrue($result->stopPipeline);
    }

    public function testHaltFactoryDefaults(): void
    {
        $result = StepResult::halt('Done early');

        $this->assertTrue($result->success);
        $this->assertSame('Done early', $result->message);
        $this->assertSame([], $result->output);
        $this->assertTrue($result->stopPipeline);
    }
}

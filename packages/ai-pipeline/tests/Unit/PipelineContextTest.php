<?php

declare(strict_types=1);

namespace Aurora\AI\Pipeline\Tests\Unit;

use Aurora\AI\Pipeline\PipelineContext;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Aurora\AI\Pipeline\PipelineContext
 */
final class PipelineContextTest extends TestCase
{
    public function testConstructor(): void
    {
        $context = new PipelineContext(pipelineId: 'test-pipeline', startedAt: 1700000000);

        $this->assertSame('test-pipeline', $context->pipelineId);
        $this->assertSame(1700000000, $context->startedAt);
    }

    public function testGetReturnsDefaultWhenKeyNotSet(): void
    {
        $context = new PipelineContext(pipelineId: 'p1', startedAt: 0);

        $this->assertNull($context->get('nonexistent'));
        $this->assertSame('fallback', $context->get('missing', 'fallback'));
    }

    public function testSetAndGet(): void
    {
        $context = new PipelineContext(pipelineId: 'p1', startedAt: 0);

        $context->set('counter', 42);
        $this->assertSame(42, $context->get('counter'));

        $context->set('name', 'test');
        $this->assertSame('test', $context->get('name'));
    }

    public function testSetOverwritesPreviousValue(): void
    {
        $context = new PipelineContext(pipelineId: 'p1', startedAt: 0);

        $context->set('key', 'first');
        $context->set('key', 'second');

        $this->assertSame('second', $context->get('key'));
    }

    public function testAllReturnsEmptyArrayInitially(): void
    {
        $context = new PipelineContext(pipelineId: 'p1', startedAt: 0);

        $this->assertSame([], $context->all());
    }

    public function testAllReturnsAllState(): void
    {
        $context = new PipelineContext(pipelineId: 'p1', startedAt: 0);

        $context->set('a', 1);
        $context->set('b', 'two');
        $context->set('c', [3, 4]);

        $this->assertSame([
            'a' => 1,
            'b' => 'two',
            'c' => [3, 4],
        ], $context->all());
    }

    public function testGetWithMixedValueTypes(): void
    {
        $context = new PipelineContext(pipelineId: 'p1', startedAt: 0);

        $context->set('null_val', null);
        $context->set('bool_val', false);
        $context->set('array_val', ['nested' => true]);

        $this->assertNull($context->get('null_val'));
        $this->assertFalse($context->get('bool_val'));
        $this->assertSame(['nested' => true], $context->get('array_val'));
    }
}

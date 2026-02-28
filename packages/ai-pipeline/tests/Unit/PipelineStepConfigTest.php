<?php

declare(strict_types=1);

namespace Aurora\AI\Pipeline\Tests\Unit;

use Aurora\AI\Pipeline\PipelineStepConfig;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Aurora\AI\Pipeline\PipelineStepConfig
 */
final class PipelineStepConfigTest extends TestCase
{
    public function testConstruction(): void
    {
        $config = new PipelineStepConfig(
            id: 'step_1',
            pluginId: 'uppercase',
            label: 'Uppercase Text',
            weight: 10,
            configuration: ['field' => 'title'],
        );

        $this->assertSame('step_1', $config->id);
        $this->assertSame('uppercase', $config->pluginId);
        $this->assertSame('Uppercase Text', $config->label);
        $this->assertSame(10, $config->weight);
        $this->assertSame(['field' => 'title'], $config->configuration);
    }

    public function testConstructionDefaults(): void
    {
        $config = new PipelineStepConfig(
            id: 'step_1',
            pluginId: 'my_plugin',
        );

        $this->assertSame('step_1', $config->id);
        $this->assertSame('my_plugin', $config->pluginId);
        $this->assertSame('', $config->label);
        $this->assertSame(0, $config->weight);
        $this->assertSame([], $config->configuration);
    }

    public function testIsReadonly(): void
    {
        $config = new PipelineStepConfig(id: 'step_1', pluginId: 'test');

        // Verify it's a readonly object by checking the reflection.
        $reflection = new \ReflectionClass($config);
        $this->assertTrue($reflection->isReadOnly());
    }
}

<?php

declare(strict_types=1);

namespace Aurora\Plugin\Tests\Unit\Factory;

use Aurora\Plugin\Definition\PluginDefinition;
use Aurora\Plugin\Factory\ContainerFactory;
use Aurora\Plugin\Tests\Fixtures\TestPlugin;
use PHPUnit\Framework\TestCase;

final class ContainerFactoryTest extends TestCase
{
    public function testCreateInstance(): void
    {
        $definition = new PluginDefinition(
            id: 'test_plugin',
            label: 'Test Plugin',
            class: TestPlugin::class,
            description: 'A test plugin',
        );

        $factory = new ContainerFactory();
        $factory->setDefinitions(['test_plugin' => $definition]);

        $instance = $factory->createInstance('test_plugin');

        $this->assertInstanceOf(TestPlugin::class, $instance);
        $this->assertSame('test_plugin', $instance->getPluginId());
        $this->assertSame($definition, $instance->getPluginDefinition());
    }

    public function testCreateInstanceWithConfiguration(): void
    {
        $definition = new PluginDefinition(
            id: 'test_plugin',
            label: 'Test Plugin',
            class: TestPlugin::class,
        );

        $factory = new ContainerFactory();
        $factory->setDefinitions(['test_plugin' => $definition]);

        $config = ['key' => 'value'];
        $instance = $factory->createInstance('test_plugin', $config);

        $this->assertInstanceOf(TestPlugin::class, $instance);
        $this->assertSame('test_plugin', $instance->getPluginId());
    }

    public function testCreateInstanceThrowsForMissing(): void
    {
        $factory = new ContainerFactory();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Plugin 'nonexistent' not found.");

        $factory->createInstance('nonexistent');
    }
}

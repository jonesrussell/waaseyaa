<?php

declare(strict_types=1);

namespace Aurora\Plugin\Tests\Unit;

use Aurora\Plugin\Definition\PluginDefinition;
use Aurora\Plugin\Tests\Fixtures\TestPlugin;
use PHPUnit\Framework\TestCase;

final class PluginBaseTest extends TestCase
{
    public function testGetPluginId(): void
    {
        $definition = new PluginDefinition(
            id: 'test_plugin',
            label: 'Test Plugin',
            class: TestPlugin::class,
        );

        $plugin = new TestPlugin('test_plugin', $definition);

        $this->assertSame('test_plugin', $plugin->getPluginId());
    }

    public function testGetPluginDefinition(): void
    {
        $definition = new PluginDefinition(
            id: 'test_plugin',
            label: 'Test Plugin',
            class: TestPlugin::class,
            description: 'A test plugin',
        );

        $plugin = new TestPlugin('test_plugin', $definition);

        $this->assertSame($definition, $plugin->getPluginDefinition());
        $this->assertSame('test_plugin', $plugin->getPluginDefinition()->id);
        $this->assertSame('Test Plugin', $plugin->getPluginDefinition()->label);
        $this->assertSame('A test plugin', $plugin->getPluginDefinition()->description);
    }

    public function testAcceptsConfiguration(): void
    {
        $definition = new PluginDefinition(
            id: 'test_plugin',
            label: 'Test Plugin',
            class: TestPlugin::class,
        );

        $config = ['key' => 'value', 'nested' => ['a' => 1]];
        $plugin = new TestPlugin('test_plugin', $definition, $config);

        $this->assertSame('test_plugin', $plugin->getPluginId());
        $this->assertSame($definition, $plugin->getPluginDefinition());
    }

    public function testDefaultConfigurationIsEmpty(): void
    {
        $definition = new PluginDefinition(
            id: 'test_plugin',
            label: 'Test Plugin',
            class: TestPlugin::class,
        );

        $plugin = new TestPlugin('test_plugin', $definition);

        $this->assertSame('test_plugin', $plugin->getPluginId());
    }
}

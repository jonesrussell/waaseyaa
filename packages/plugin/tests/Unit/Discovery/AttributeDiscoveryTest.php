<?php

declare(strict_types=1);

namespace Waaseyaa\Plugin\Tests\Unit\Discovery;

use Waaseyaa\Plugin\Attribute\WaaseyaaPlugin;
use Waaseyaa\Plugin\Definition\PluginDefinition;
use Waaseyaa\Plugin\Discovery\AttributeDiscovery;
use Waaseyaa\Plugin\Tests\Fixtures\AnotherPlugin;
use Waaseyaa\Plugin\Tests\Fixtures\TestPlugin;
use PHPUnit\Framework\TestCase;

final class AttributeDiscoveryTest extends TestCase
{
    private string $fixturesDir;

    protected function setUp(): void
    {
        $this->fixturesDir = dirname(__DIR__, 2) . '/Fixtures';
    }

    public function testDiscoverPlugins(): void
    {
        $discovery = new AttributeDiscovery(
            directories: [$this->fixturesDir],
            attributeClass: WaaseyaaPlugin::class,
        );

        $definitions = $discovery->getDefinitions();

        $this->assertCount(2, $definitions);
        $this->assertArrayHasKey('test_plugin', $definitions);
        $this->assertArrayHasKey('another_plugin', $definitions);
    }

    public function testDefinitionsHaveCorrectData(): void
    {
        $discovery = new AttributeDiscovery(
            directories: [$this->fixturesDir],
            attributeClass: WaaseyaaPlugin::class,
        );

        $definitions = $discovery->getDefinitions();

        // Test the TestPlugin definition.
        $testDef = $definitions['test_plugin'];
        $this->assertInstanceOf(PluginDefinition::class, $testDef);
        $this->assertSame('test_plugin', $testDef->id);
        $this->assertSame('Test Plugin', $testDef->label);
        $this->assertSame(TestPlugin::class, $testDef->class);
        $this->assertSame('A test plugin', $testDef->description);

        // Test the AnotherPlugin definition.
        $anotherDef = $definitions['another_plugin'];
        $this->assertInstanceOf(PluginDefinition::class, $anotherDef);
        $this->assertSame('another_plugin', $anotherDef->id);
        $this->assertSame('Another Plugin', $anotherDef->label);
        $this->assertSame(AnotherPlugin::class, $anotherDef->class);
        $this->assertSame('', $anotherDef->description);
    }

    public function testEmptyDirectory(): void
    {
        $emptyDir = sys_get_temp_dir() . '/aurora_plugin_test_empty_' . uniqid();
        mkdir($emptyDir);

        try {
            $discovery = new AttributeDiscovery(
                directories: [$emptyDir],
                attributeClass: WaaseyaaPlugin::class,
            );

            $definitions = $discovery->getDefinitions();

            $this->assertSame([], $definitions);
        } finally {
            rmdir($emptyDir);
        }
    }

    public function testNonExistentDirectory(): void
    {
        $discovery = new AttributeDiscovery(
            directories: ['/tmp/nonexistent_aurora_dir_' . uniqid()],
            attributeClass: WaaseyaaPlugin::class,
        );

        $definitions = $discovery->getDefinitions();

        $this->assertSame([], $definitions);
    }

    public function testDoesNotDiscoverClassesWithoutAttribute(): void
    {
        $discovery = new AttributeDiscovery(
            directories: [$this->fixturesDir],
            attributeClass: WaaseyaaPlugin::class,
        );

        $definitions = $discovery->getDefinitions();

        // NotAPlugin should not be discovered.
        foreach ($definitions as $definition) {
            $this->assertStringNotContainsString('NotAPlugin', $definition->class);
        }
    }

    public function testMultipleDirectories(): void
    {
        $emptyDir = sys_get_temp_dir() . '/aurora_plugin_test_empty_' . uniqid();
        mkdir($emptyDir);

        try {
            $discovery = new AttributeDiscovery(
                directories: [$this->fixturesDir, $emptyDir],
                attributeClass: WaaseyaaPlugin::class,
            );

            $definitions = $discovery->getDefinitions();

            $this->assertCount(2, $definitions);
            $this->assertArrayHasKey('test_plugin', $definitions);
            $this->assertArrayHasKey('another_plugin', $definitions);
        } finally {
            rmdir($emptyDir);
        }
    }
}

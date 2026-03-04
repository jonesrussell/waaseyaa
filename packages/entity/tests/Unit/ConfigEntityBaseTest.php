<?php

declare(strict_types=1);

namespace Waaseyaa\Entity\Tests\Unit;

use Waaseyaa\Entity\ConfigEntityBase;
use Waaseyaa\Entity\ConfigEntityInterface;
use Waaseyaa\Entity\EntityInterface;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Waaseyaa\Entity\ConfigEntityBase
 */
class ConfigEntityBaseTest extends TestCase
{
    public function testImplementsInterfaces(): void
    {
        $entity = new TestConfigEntity();

        $this->assertInstanceOf(EntityInterface::class, $entity);
        $this->assertInstanceOf(ConfigEntityInterface::class, $entity);
        $this->assertInstanceOf(ConfigEntityBase::class, $entity);
    }

    public function testStatusDefaultsToTrue(): void
    {
        $entity = new TestConfigEntity();

        $this->assertTrue($entity->status());
    }

    public function testStatusFromConstructorValues(): void
    {
        $entity = new TestConfigEntity(['status' => false]);

        $this->assertFalse($entity->status());
    }

    public function testEnable(): void
    {
        $entity = new TestConfigEntity(['status' => false]);
        $this->assertFalse($entity->status());

        $result = $entity->enable();

        $this->assertTrue($entity->status());
        $this->assertSame($entity, $result, 'enable() should return $this for fluent API');
    }

    public function testDisable(): void
    {
        $entity = new TestConfigEntity();
        $this->assertTrue($entity->status());

        $result = $entity->disable();

        $this->assertFalse($entity->status());
        $this->assertSame($entity, $result, 'disable() should return $this for fluent API');
    }

    public function testGetDependenciesDefaultsToEmpty(): void
    {
        $entity = new TestConfigEntity();

        $this->assertSame([], $entity->getDependencies());
    }

    public function testGetDependenciesFromConstructorValues(): void
    {
        $deps = [
            'config' => ['system.site'],
            'package' => ['waaseyaa/node'],
        ];
        $entity = new TestConfigEntity(['dependencies' => $deps]);

        $this->assertSame($deps, $entity->getDependencies());
    }

    public function testSetDependencies(): void
    {
        $entity = new TestConfigEntity();
        $deps = ['config' => ['core.entity_type.node']];

        $result = $entity->setDependencies($deps);

        $this->assertSame($deps, $entity->getDependencies());
        $this->assertSame($entity, $result, 'setDependencies() should return $this');
    }

    public function testToConfigIncludesAllValues(): void
    {
        $entity = new TestConfigEntity([
            'id' => 'article',
            'label' => 'Article',
        ]);

        $config = $entity->toConfig();

        $this->assertSame('article', $config['id']);
        $this->assertSame('Article', $config['label']);
        $this->assertTrue($config['status']);
        $this->assertArrayNotHasKey('uuid', $config);
    }

    public function testToConfigIncludesDependencies(): void
    {
        $deps = ['config' => ['system.site']];
        $entity = new TestConfigEntity([
            'id' => 'test',
            'dependencies' => $deps,
        ]);

        $config = $entity->toConfig();

        $this->assertSame($deps, $config['dependencies']);
    }

    public function testToConfigOmitsEmptyDependencies(): void
    {
        $entity = new TestConfigEntity(['id' => 'test']);

        $config = $entity->toConfig();

        // When dependencies are empty, they should not appear in config output.
        $this->assertArrayNotHasKey('dependencies', $config);
    }

    public function testToConfigReflectsStatusChanges(): void
    {
        $entity = new TestConfigEntity(['id' => 'test']);
        $entity->disable();

        $config = $entity->toConfig();

        $this->assertFalse($config['status']);
    }

    public function testEntityTypeId(): void
    {
        $entity = new TestConfigEntity();

        $this->assertSame('test_config', $entity->getEntityTypeId());
    }

    public function testToConfigReflectsSetDependencies(): void
    {
        $entity = new TestConfigEntity(['id' => 'test']);
        $entity->setDependencies(['package' => ['waaseyaa/field']]);

        $config = $entity->toConfig();

        $this->assertSame(['package' => ['waaseyaa/field']], $config['dependencies']);
    }
}

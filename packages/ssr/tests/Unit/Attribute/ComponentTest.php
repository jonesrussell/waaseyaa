<?php

declare(strict_types=1);

namespace Aurora\SSR\Tests\Unit\Attribute;

use Aurora\SSR\Attribute\Component;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Component::class)]
final class ComponentTest extends TestCase
{
    #[Test]
    public function constructorSetsProperties(): void
    {
        $component = new Component(name: 'article', template: 'components/article.html.twig');

        $this->assertSame('article', $component->name);
        $this->assertSame('components/article.html.twig', $component->template);
    }

    #[Test]
    public function propertiesAreReadonly(): void
    {
        $reflection = new \ReflectionClass(Component::class);

        $this->assertTrue($reflection->getProperty('name')->isReadOnly());
        $this->assertTrue($reflection->getProperty('template')->isReadOnly());
    }

    #[Test]
    public function attributeTargetsClasses(): void
    {
        $reflection = new \ReflectionClass(Component::class);
        $attributes = $reflection->getAttributes(\Attribute::class);

        $this->assertCount(1, $attributes);

        $attr = $attributes[0]->newInstance();
        $this->assertSame(\Attribute::TARGET_CLASS, $attr->flags);
    }

    #[Test]
    public function canBeReadViaReflection(): void
    {
        $reflection = new \ReflectionClass(ComponentTestFixture::class);
        $attributes = $reflection->getAttributes(Component::class);

        $this->assertCount(1, $attributes);

        $instance = $attributes[0]->newInstance();
        $this->assertSame('test-fixture', $instance->name);
        $this->assertSame('fixtures/test.html.twig', $instance->template);
    }
}

#[Component(name: 'test-fixture', template: 'fixtures/test.html.twig')]
final class ComponentTestFixture {}

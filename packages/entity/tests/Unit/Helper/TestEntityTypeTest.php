<?php

declare(strict_types=1);

namespace Waaseyaa\Entity\Tests\Unit\Helper;

use PHPUnit\Framework\TestCase;
use Waaseyaa\Entity\EntityType;
use Waaseyaa\Entity\Tests\Fixtures\AttributeFirstEntities\SimpleFixture;
use Waaseyaa\Entity\Tests\Helper\TestEntityType;
use Waaseyaa\Field\FieldDefinition;

require_once __DIR__ . '/../../Fixtures/AttributeFirstEntities/FactoryTestFixtures.php';

/**
 * @covers \Waaseyaa\Entity\Tests\Helper\TestEntityType
 */
final class TestEntityTypeTest extends TestCase
{
    protected function setUp(): void
    {
        EntityType::clearFromClassCache();
    }

    public function testStubReturnsEntityTypeWithDefaults(): void
    {
        $type = TestEntityType::stub('foo_bar');

        self::assertInstanceOf(EntityType::class, $type);
        self::assertSame('foo_bar', $type->id());
        self::assertSame('Foo bar', $type->getLabel());
        self::assertStringContainsString('FooBar', $type->getClass());
        self::assertSame(['id' => 'id', 'uuid' => 'uuid', 'label' => 'label'], $type->getKeys());
        self::assertSame([], $type->getFieldDefinitions());
    }

    public function testStubAcceptsFieldDefinitions(): void
    {
        $title = new FieldDefinition(name: 'title', type: 'string');
        $type = TestEntityType::stub('foo', ['title' => $title]);

        $fields = $type->getFieldDefinitions();
        self::assertArrayHasKey('title', $fields);
        self::assertSame($title, $fields['title']);
    }

    public function testStubAcceptsCustomKeys(): void
    {
        $keys = ['id' => 'nid', 'uuid' => 'uuid', 'label' => 'title', 'bundle' => 'type'];
        $type = TestEntityType::stub('node', keys: $keys);

        self::assertSame($keys, $type->getKeys());
    }

    public function testStubAcceptsCustomClassAndLabel(): void
    {
        $type = TestEntityType::stub(
            'foo',
            class: \stdClass::class,
            label: 'Custom Label',
        );

        self::assertSame(\stdClass::class, $type->getClass());
        self::assertSame('Custom Label', $type->getLabel());
    }

    public function testStubDoesNotPolluteFromClassCache(): void
    {
        // Build via stub then via fromClass for an unrelated class — they
        // must be different instances and stubbing must not have polluted
        // the fromClass cache.
        TestEntityType::stub('simple');
        $real = EntityType::fromClass(SimpleFixture::class);

        self::assertSame('simple', $real->id());
        self::assertSame(SimpleFixture::class, $real->getClass());
    }
}

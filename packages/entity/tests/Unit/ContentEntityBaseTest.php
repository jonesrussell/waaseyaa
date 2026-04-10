<?php

declare(strict_types=1);

namespace Waaseyaa\Entity\Tests\Unit;

use Waaseyaa\Entity\ContentEntityBase;
use Waaseyaa\Entity\ContentEntityInterface;
use Waaseyaa\Entity\EntityInterface;
use Waaseyaa\Entity\FieldableInterface;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Waaseyaa\Entity\ContentEntityBase
 */
class ContentEntityBaseTest extends TestCase
{
    public function testImplementsInterfaces(): void
    {
        $entity = new TestContentEntity();

        $this->assertInstanceOf(EntityInterface::class, $entity);
        $this->assertInstanceOf(ContentEntityInterface::class, $entity);
        $this->assertInstanceOf(FieldableInterface::class, $entity);
        $this->assertInstanceOf(ContentEntityBase::class, $entity);
    }

    public function testHasFieldReturnsTrueForExistingValue(): void
    {
        $entity = new TestContentEntity(['title' => 'Hello']);

        $this->assertTrue($entity->hasField('title'));
    }

    public function testHasFieldReturnsTrueForDefinedField(): void
    {
        $entity = new TestContentEntity(
            values: [],
            fieldDefinitions: ['body' => ['type' => 'text']],
        );

        $this->assertTrue($entity->hasField('body'));
    }

    public function testHasFieldReturnsFalseForUnknownField(): void
    {
        $entity = new TestContentEntity();

        $this->assertFalse($entity->hasField('nonexistent'));
    }

    public function testGetReturnsValue(): void
    {
        $entity = new TestContentEntity(['title' => 'Hello World']);

        $this->assertSame('Hello World', $entity->get('title'));
    }

    public function testGetReturnsNullForMissingField(): void
    {
        $entity = new TestContentEntity();

        $this->assertNull($entity->get('nonexistent'));
    }

    public function testSetStoresValue(): void
    {
        $entity = new TestContentEntity();

        $result = $entity->set('title', 'New Title');

        $this->assertSame('New Title', $entity->get('title'));
        $this->assertSame($entity, $result, 'set() should return $this for fluent API');
    }

    public function testSetOverwritesExistingValue(): void
    {
        $entity = new TestContentEntity(['title' => 'Old']);

        $entity->set('title', 'New');

        $this->assertSame('New', $entity->get('title'));
    }

    public function testGetFieldDefinitions(): void
    {
        $definitions = [
            'title' => ['type' => 'string', 'label' => 'Title'],
            'body' => ['type' => 'text', 'label' => 'Body'],
        ];
        $entity = new TestContentEntity(
            values: [],
            fieldDefinitions: $definitions,
        );

        $this->assertSame($definitions, $entity->getFieldDefinitions());
    }

    public function testGetFieldDefinitionsReturnsEmptyByDefault(): void
    {
        $entity = new TestContentEntity();

        $this->assertSame([], $entity->getFieldDefinitions());
    }

    public function testSetMakesFieldDiscoverable(): void
    {
        $entity = new TestContentEntity();

        $this->assertFalse($entity->hasField('dynamic'));

        $entity->set('dynamic', 'value');

        $this->assertTrue($entity->hasField('dynamic'));
    }

    public function testEntityTypeId(): void
    {
        $entity = new TestContentEntity();

        $this->assertSame('test_content', $entity->getEntityTypeId());
    }

    public function testToArrayIncludesSetFields(): void
    {
        $entity = new TestContentEntity(['title' => 'Test']);
        $entity->set('body', 'Content');

        $array = $entity->toArray();

        $this->assertSame('Test', $array['title']);
        $this->assertSame('Content', $array['body']);
    }

    public function testVariousValueTypes(): void
    {
        $entity = new TestContentEntity();

        $entity->set('string', 'text');
        $entity->set('int', 42);
        $entity->set('float', 3.14);
        $entity->set('bool', true);
        $entity->set('array', ['a', 'b']);
        $entity->set('null', null);

        $this->assertSame('text', $entity->get('string'));
        $this->assertSame(42, $entity->get('int'));
        $this->assertSame(3.14, $entity->get('float'));
        $this->assertTrue($entity->get('bool'));
        $this->assertSame(['a', 'b'], $entity->get('array'));
        $this->assertNull($entity->get('null'));
    }

    public function testHasFieldReturnsTrueForNullValue(): void
    {
        $entity = new TestContentEntity(['field' => null]);

        // array_key_exists returns true even for null values.
        $this->assertTrue($entity->hasField('field'));
    }

    public function testDuplicatePreservesFieldDefinitions(): void
    {
        $definitions = ['body' => ['type' => 'text']];
        $entity = new TestContentEntity(
            values: ['title' => 'T'],
            fieldDefinitions: $definitions,
        );
        $dup = $entity->duplicate();

        $this->assertNotSame($entity, $dup);
        $this->assertSame($definitions, $dup->getFieldDefinitions());
        $this->assertSame('T', $dup->get('title'));
    }
}

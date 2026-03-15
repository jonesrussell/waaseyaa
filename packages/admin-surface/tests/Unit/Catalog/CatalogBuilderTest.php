<?php

declare(strict_types=1);

namespace Waaseyaa\AdminSurface\Tests\Unit\Catalog;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\AdminSurface\Catalog\CatalogBuilder;
use Waaseyaa\AdminSurface\Catalog\EntityDefinition;

#[CoversClass(CatalogBuilder::class)]
#[CoversClass(EntityDefinition::class)]
final class CatalogBuilderTest extends TestCase
{
    #[Test]
    public function emptyBuilderReturnsEmptyArray(): void
    {
        $builder = new CatalogBuilder();

        self::assertSame([], $builder->build());
    }

    #[Test]
    public function defineEntityReturnsFluentEntityDefinition(): void
    {
        $builder = new CatalogBuilder();
        $entity = $builder->defineEntity('node', 'Content');

        self::assertInstanceOf(EntityDefinition::class, $entity);
    }

    #[Test]
    public function buildReturnsAllDefinedEntities(): void
    {
        $builder = new CatalogBuilder();
        $builder->defineEntity('node', 'Content')->group('content');
        $builder->defineEntity('user', 'Users')->group('admin');

        $result = $builder->build();

        self::assertCount(2, $result);
        self::assertSame('node', $result[0]['id']);
        self::assertSame('Content', $result[0]['label']);
        self::assertSame('content', $result[0]['group']);
        self::assertSame('user', $result[1]['id']);
        self::assertSame('admin', $result[1]['group']);
    }

    #[Test]
    public function entityWithFieldsAndActions(): void
    {
        $builder = new CatalogBuilder();
        $entity = $builder->defineEntity('article', 'Articles');
        $entity->field('title', 'Title', 'string')->required()->widget('text');
        $entity->field('body', 'Body', 'string')->widget('richtext')->weight(10);
        $entity->action('publish', 'Publish');
        $entity->action('delete', 'Delete')->dangerous()->confirm('Are you sure?');

        $result = $builder->build();

        self::assertCount(2, $result[0]['fields']);
        self::assertSame('title', $result[0]['fields'][0]['name']);
        self::assertTrue($result[0]['fields'][0]['required']);
        self::assertSame('richtext', $result[0]['fields'][1]['widget']);

        self::assertCount(2, $result[0]['actions']);
        self::assertSame('publish', $result[0]['actions'][0]['id']);
        self::assertTrue($result[0]['actions'][1]['dangerous']);
    }

    #[Test]
    public function defaultCapabilitiesAreAllTrue(): void
    {
        $builder = new CatalogBuilder();
        $builder->defineEntity('node', 'Content');

        $result = $builder->build();
        $caps = $result[0]['capabilities'];

        self::assertTrue($caps['list']);
        self::assertTrue($caps['get']);
        self::assertTrue($caps['create']);
        self::assertTrue($caps['update']);
        self::assertTrue($caps['delete']);
        self::assertTrue($caps['schema']);
    }

    #[Test]
    public function readOnlyDisablesCrudCapabilities(): void
    {
        $builder = new CatalogBuilder();
        $builder->defineEntity('log', 'Logs')->readOnly();

        $result = $builder->build();
        $caps = $result[0]['capabilities'];

        self::assertTrue($caps['list']);
        self::assertTrue($caps['get']);
        self::assertFalse($caps['create']);
        self::assertFalse($caps['update']);
        self::assertFalse($caps['delete']);
    }

    #[Test]
    public function customCapabilities(): void
    {
        $builder = new CatalogBuilder();
        $builder->defineEntity('config', 'Config')
            ->capabilities(['delete' => false, 'schema' => false]);

        $result = $builder->build();
        $caps = $result[0]['capabilities'];

        self::assertTrue($caps['list']);
        self::assertTrue($caps['create']);
        self::assertFalse($caps['delete']);
        self::assertFalse($caps['schema']);
    }

    #[Test]
    public function invalidCapabilityThrows(): void
    {
        $entity = new EntityDefinition('test', 'Test');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown capability: fly');

        $entity->capabilities(['fly' => true]);
    }
}

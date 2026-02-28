<?php

declare(strict_types=1);

namespace Aurora\Api\Tests\Unit\OpenApi;

use Aurora\Api\OpenApi\SchemaBuilder;
use Aurora\Api\Tests\Fixtures\TestEntity;
use Aurora\Entity\EntityType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(SchemaBuilder::class)]
final class SchemaBuilderTest extends TestCase
{
    private SchemaBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new SchemaBuilder();
    }

    #[Test]
    public function attributesSchemaIsOpenObject(): void
    {
        $entityType = new EntityType(
            id: 'article',
            label: 'Article',
            class: TestEntity::class,
        );

        $schema = $this->builder->buildAttributesSchema($entityType);

        $this->assertSame('object', $schema['type']);
        $this->assertTrue($schema['additionalProperties']);
        $this->assertStringContainsString('Article', $schema['description']);
    }

    #[Test]
    public function attributesSchemaUsesEntityLabel(): void
    {
        $entityType = new EntityType(
            id: 'node',
            label: 'Content',
            class: TestEntity::class,
        );

        $schema = $this->builder->buildAttributesSchema($entityType);

        $this->assertStringContainsString('Content', $schema['description']);
    }

    #[Test]
    public function resourceSchemaHasTypeIdAttributesAndLinks(): void
    {
        $entityType = new EntityType(
            id: 'article',
            label: 'Article',
            class: TestEntity::class,
        );

        $schema = $this->builder->buildResourceSchema($entityType);

        $this->assertSame('object', $schema['type']);
        $this->assertContains('type', $schema['required']);
        $this->assertContains('id', $schema['required']);

        // Check properties exist.
        $this->assertArrayHasKey('type', $schema['properties']);
        $this->assertArrayHasKey('id', $schema['properties']);
        $this->assertArrayHasKey('attributes', $schema['properties']);
        $this->assertArrayHasKey('links', $schema['properties']);
    }

    #[Test]
    public function resourceSchemaTypePropertyHasExampleMatchingEntityTypeId(): void
    {
        $entityType = new EntityType(
            id: 'article',
            label: 'Article',
            class: TestEntity::class,
        );

        $schema = $this->builder->buildResourceSchema($entityType);

        $this->assertSame('article', $schema['properties']['type']['example']);
        $this->assertSame('string', $schema['properties']['type']['type']);
    }

    #[Test]
    public function resourceSchemaAttributesRefersToAttributesSchema(): void
    {
        $entityType = new EntityType(
            id: 'article',
            label: 'Article',
            class: TestEntity::class,
        );

        $schema = $this->builder->buildResourceSchema($entityType);

        $this->assertSame(
            '#/components/schemas/ArticleAttributes',
            $schema['properties']['attributes']['$ref'],
        );
    }

    #[Test]
    public function resourceSchemaLinksContainsSelfUri(): void
    {
        $entityType = new EntityType(
            id: 'article',
            label: 'Article',
            class: TestEntity::class,
        );

        $schema = $this->builder->buildResourceSchema($entityType);

        $this->assertSame('object', $schema['properties']['links']['type']);
        $this->assertSame('string', $schema['properties']['links']['properties']['self']['type']);
        $this->assertSame('uri', $schema['properties']['links']['properties']['self']['format']);
    }

    #[Test]
    public function createRequestSchemaWrapsDataTypeAndAttributes(): void
    {
        $entityType = new EntityType(
            id: 'article',
            label: 'Article',
            class: TestEntity::class,
        );

        $schema = $this->builder->buildCreateRequestSchema($entityType);

        $this->assertSame('object', $schema['type']);
        $this->assertContains('data', $schema['required']);

        $data = $schema['properties']['data'];
        $this->assertSame('object', $data['type']);
        $this->assertContains('type', $data['required']);
        $this->assertContains('attributes', $data['required']);

        // Create request should NOT require id.
        $this->assertNotContains('id', $data['required']);

        // Check type property.
        $this->assertSame('string', $data['properties']['type']['type']);
        $this->assertSame('article', $data['properties']['type']['example']);

        // Check attributes ref.
        $this->assertSame(
            '#/components/schemas/ArticleAttributes',
            $data['properties']['attributes']['$ref'],
        );
    }

    #[Test]
    public function createRequestSchemaDoesNotIncludeId(): void
    {
        $entityType = new EntityType(
            id: 'article',
            label: 'Article',
            class: TestEntity::class,
        );

        $schema = $this->builder->buildCreateRequestSchema($entityType);

        $data = $schema['properties']['data'];
        $this->assertArrayNotHasKey('id', $data['properties']);
    }

    #[Test]
    public function updateRequestSchemaWrapsDataTypeIdAndAttributes(): void
    {
        $entityType = new EntityType(
            id: 'article',
            label: 'Article',
            class: TestEntity::class,
        );

        $schema = $this->builder->buildUpdateRequestSchema($entityType);

        $this->assertSame('object', $schema['type']);
        $this->assertContains('data', $schema['required']);

        $data = $schema['properties']['data'];
        $this->assertSame('object', $data['type']);
        $this->assertContains('type', $data['required']);
        $this->assertContains('id', $data['required']);
        $this->assertContains('attributes', $data['required']);

        // Check type property.
        $this->assertSame('string', $data['properties']['type']['type']);

        // Check id property.
        $this->assertSame('string', $data['properties']['id']['type']);

        // Check attributes ref.
        $this->assertSame(
            '#/components/schemas/ArticleAttributes',
            $data['properties']['attributes']['$ref'],
        );
    }

    #[Test]
    public function toSchemaNameConvertsToPascalCase(): void
    {
        $this->assertSame('Node', $this->builder->toSchemaName('node'));
        $this->assertSame('UserRole', $this->builder->toSchemaName('user_role'));
        $this->assertSame('Article', $this->builder->toSchemaName('article'));
        $this->assertSame('ContentModerationState', $this->builder->toSchemaName('content_moderation_state'));
    }

    #[Test]
    public function resourceSchemaDescriptionIncludesLabel(): void
    {
        $entityType = new EntityType(
            id: 'user',
            label: 'User',
            class: TestEntity::class,
        );

        $schema = $this->builder->buildResourceSchema($entityType);

        $this->assertStringContainsString('User', $schema['description']);
    }

    #[Test]
    public function createRequestSchemaDescriptionIncludesLabel(): void
    {
        $entityType = new EntityType(
            id: 'user',
            label: 'User',
            class: TestEntity::class,
        );

        $schema = $this->builder->buildCreateRequestSchema($entityType);

        $this->assertStringContainsString('User', $schema['description']);
    }

    #[Test]
    public function updateRequestSchemaDescriptionIncludesLabel(): void
    {
        $entityType = new EntityType(
            id: 'user',
            label: 'User',
            class: TestEntity::class,
        );

        $schema = $this->builder->buildUpdateRequestSchema($entityType);

        $this->assertStringContainsString('User', $schema['description']);
    }

    #[Test]
    public function schemaReferencesUsePascalCaseNames(): void
    {
        $entityType = new EntityType(
            id: 'user_role',
            label: 'User Role',
            class: TestEntity::class,
        );

        $resourceSchema = $this->builder->buildResourceSchema($entityType);
        $createSchema = $this->builder->buildCreateRequestSchema($entityType);
        $updateSchema = $this->builder->buildUpdateRequestSchema($entityType);

        $this->assertSame(
            '#/components/schemas/UserRoleAttributes',
            $resourceSchema['properties']['attributes']['$ref'],
        );
        $this->assertSame(
            '#/components/schemas/UserRoleAttributes',
            $createSchema['properties']['data']['properties']['attributes']['$ref'],
        );
        $this->assertSame(
            '#/components/schemas/UserRoleAttributes',
            $updateSchema['properties']['data']['properties']['attributes']['$ref'],
        );
    }
}

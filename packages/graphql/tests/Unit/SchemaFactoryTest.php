<?php

declare(strict_types=1);

namespace Waaseyaa\GraphQL\Tests\Unit;

use GraphQL\GraphQL;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Access\AccessResult;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Access\EntityAccessHandler;
use Waaseyaa\Entity\EntityType;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\GraphQL\Access\GraphQlAccessGuard;
use Waaseyaa\GraphQL\Resolver\EntityResolver;
use Waaseyaa\GraphQL\Resolver\ReferenceLoader;
use Waaseyaa\GraphQL\Schema\SchemaFactory;
use Waaseyaa\GraphQL\Tests\Fixtures\AttributeFirstEntities\ArticleSchemaFixture;
use Waaseyaa\GraphQL\Tests\Fixtures\AttributeFirstEntities\LogEntrySchemaFixture;
use Waaseyaa\GraphQL\Tests\Fixtures\AttributeFirstEntities\PageSchemaFixture;
use Symfony\Component\EventDispatcher\EventDispatcher;

require_once __DIR__ . '/../Fixtures/AttributeFirstEntities/ArticleSchemaFixture.php';
require_once __DIR__ . '/../Fixtures/AttributeFirstEntities/LogEntrySchemaFixture.php';
require_once __DIR__ . '/../Fixtures/AttributeFirstEntities/PageSchemaFixture.php';

#[CoversClass(SchemaFactory::class)]
final class SchemaFactoryTest extends TestCase
{
    private EntityTypeManager $entityTypeManager;
    private EntityAccessHandler $accessHandler;
    private AccountInterface $account;

    protected function setUp(): void
    {
        SchemaFactory::resetCache();
        EntityType::clearFromClassCache();

        $this->entityTypeManager = new EntityTypeManager(new EventDispatcher());

        $this->entityTypeManager->registerCoreEntityType(EntityType::fromClass(ArticleSchemaFixture::class));

        // Open access — allow everything
        $this->accessHandler = new EntityAccessHandler([]);
        $this->account = $this->createStub(AccountInterface::class);
    }

    #[Test]
    public function buildProducesValidSchema(): void
    {
        $guard = new GraphQlAccessGuard($this->accessHandler, $this->account);
        $referenceLoader = new ReferenceLoader($this->entityTypeManager, $guard);
        $entityResolver = new EntityResolver($this->entityTypeManager, $guard);

        $factory = new SchemaFactory(
            entityTypeManager: $this->entityTypeManager,
            entityResolver: $entityResolver,
            referenceLoader: $referenceLoader,
        );

        $schema = $factory->build();

        // Schema should have Query and Mutation types
        $queryType = $schema->getQueryType();
        self::assertNotNull($queryType);
        self::assertTrue($queryType->hasField('article'));
        self::assertTrue($queryType->hasField('articleList'));

        $mutationType = $schema->getMutationType();
        self::assertNotNull($mutationType);
        self::assertTrue($mutationType->hasField('createArticle'));
        self::assertTrue($mutationType->hasField('updateArticle'));
        self::assertTrue($mutationType->hasField('deleteArticle'));
    }

    #[Test]
    public function objectTypeHasCorrectFields(): void
    {
        $guard = new GraphQlAccessGuard($this->accessHandler, $this->account);
        $referenceLoader = new ReferenceLoader($this->entityTypeManager, $guard);
        $entityResolver = new EntityResolver($this->entityTypeManager, $guard);

        $factory = new SchemaFactory(
            entityTypeManager: $this->entityTypeManager,
            entityResolver: $entityResolver,
            referenceLoader: $referenceLoader,
        );

        $schema = $factory->build();
        $queryType = $schema->getQueryType();
        $articleField = $queryType->getField('article');

        // The type should be the Article ObjectType
        $articleType = $articleField->getType();
        self::assertInstanceOf(\GraphQL\Type\Definition\ObjectType::class, $articleType);
        self::assertSame('Article', $articleType->name);

        // Verify fields exist
        self::assertTrue($articleType->hasField('id'));
        self::assertTrue($articleType->hasField('uuid'));
        self::assertTrue($articleType->hasField('title'));
        self::assertTrue($articleType->hasField('body'));
        self::assertTrue($articleType->hasField('status'));
        self::assertTrue($articleType->hasField('created'));
    }

    #[Test]
    public function introspectionQuerySucceeds(): void
    {
        $guard = new GraphQlAccessGuard($this->accessHandler, $this->account);
        $referenceLoader = new ReferenceLoader($this->entityTypeManager, $guard);
        $entityResolver = new EntityResolver($this->entityTypeManager, $guard);

        $factory = new SchemaFactory(
            entityTypeManager: $this->entityTypeManager,
            entityResolver: $entityResolver,
            referenceLoader: $referenceLoader,
        );

        $schema = $factory->build();

        $result = GraphQL::executeQuery($schema, '{ __schema { queryType { name } } }');
        $data = $result->toArray();

        self::assertArrayNotHasKey('errors', $data);
        self::assertSame('Query', $data['data']['__schema']['queryType']['name']);
    }

    #[Test]
    public function listResultTypeHasItemsAndTotal(): void
    {
        $guard = new GraphQlAccessGuard($this->accessHandler, $this->account);
        $referenceLoader = new ReferenceLoader($this->entityTypeManager, $guard);
        $entityResolver = new EntityResolver($this->entityTypeManager, $guard);

        $factory = new SchemaFactory(
            entityTypeManager: $this->entityTypeManager,
            entityResolver: $entityResolver,
            referenceLoader: $referenceLoader,
        );

        $schema = $factory->build();
        $queryType = $schema->getQueryType();
        $listField = $queryType->getField('articleList');
        $listType = $listField->getType();

        self::assertInstanceOf(\GraphQL\Type\Definition\ObjectType::class, $listType);
        self::assertSame('ArticleListResult', $listType->name);
        self::assertTrue($listType->hasField('items'));
        self::assertTrue($listType->hasField('total'));
    }

    #[Test]
    public function buildReturnsCachedSchemaOnSecondCall(): void
    {
        $guard = new GraphQlAccessGuard($this->accessHandler, $this->account);
        $referenceLoader = new ReferenceLoader($this->entityTypeManager, $guard);
        $entityResolver = new EntityResolver($this->entityTypeManager, $guard);

        $factory = new SchemaFactory(
            entityTypeManager: $this->entityTypeManager,
            entityResolver: $entityResolver,
            referenceLoader: $referenceLoader,
        );

        $schema1 = $factory->build();
        $schema2 = $factory->build();

        self::assertSame($schema1, $schema2, 'Second build() call should return the same cached instance');
    }

    #[Test]
    public function resetCacheInvalidatesStaticCache(): void
    {
        $guard = new GraphQlAccessGuard($this->accessHandler, $this->account);
        $referenceLoader = new ReferenceLoader($this->entityTypeManager, $guard);
        $entityResolver = new EntityResolver($this->entityTypeManager, $guard);

        $factory = new SchemaFactory(
            entityTypeManager: $this->entityTypeManager,
            entityResolver: $entityResolver,
            referenceLoader: $referenceLoader,
        );

        $schema1 = $factory->build();

        SchemaFactory::resetCache();

        $schema2 = $factory->build();

        self::assertNotSame($schema1, $schema2, 'After resetCache(), build() should return a new instance');
    }

    #[Test]
    public function cacheKeyIncludesEntityTypeIds(): void
    {
        $guard = new GraphQlAccessGuard($this->accessHandler, $this->account);
        $referenceLoader = new ReferenceLoader($this->entityTypeManager, $guard);
        $entityResolver = new EntityResolver($this->entityTypeManager, $guard);

        $factory = new SchemaFactory(
            entityTypeManager: $this->entityTypeManager,
            entityResolver: $entityResolver,
            referenceLoader: $referenceLoader,
        );

        $schema1 = $factory->build();

        // Register a new entity type — changes the definitions
        $this->entityTypeManager->registerCoreEntityType(EntityType::fromClass(PageSchemaFixture::class));

        $schema2 = $factory->build();

        self::assertNotSame($schema1, $schema2, 'Different entity type definitions should produce a new schema');
    }

    #[Test]
    public function inputTypeExcludesReadOnlyFields(): void
    {
        // Register a type with a readOnly field
        $this->entityTypeManager->registerCoreEntityType(EntityType::fromClass(LogEntrySchemaFixture::class));

        $guard = new GraphQlAccessGuard($this->accessHandler, $this->account);
        $referenceLoader = new ReferenceLoader($this->entityTypeManager, $guard);
        $entityResolver = new EntityResolver($this->entityTypeManager, $guard);

        $factory = new SchemaFactory(
            entityTypeManager: $this->entityTypeManager,
            entityResolver: $entityResolver,
            referenceLoader: $referenceLoader,
        );

        $schema = $factory->build();
        $mutationType = $schema->getMutationType();
        $createField = $mutationType->getField('createLogEntry');

        $inputType = $createField->getArg('input')->getType();
        // Unwrap NonNull
        if ($inputType instanceof \GraphQL\Type\Definition\NonNull) {
            $inputType = $inputType->getWrappedType();
        }

        self::assertInstanceOf(\GraphQL\Type\Definition\InputObjectType::class, $inputType);
        self::assertTrue($inputType->hasField('message'));
        self::assertFalse($inputType->hasField('timestamp'));
    }
}

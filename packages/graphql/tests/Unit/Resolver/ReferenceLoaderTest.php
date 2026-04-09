<?php

declare(strict_types=1);

namespace Waaseyaa\GraphQL\Tests\Unit\Resolver;

use GraphQL\Deferred;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Waaseyaa\Access\AccessPolicyInterface;
use Waaseyaa\Access\AccessResult;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Access\EntityAccessHandler;
use Waaseyaa\Access\FieldAccessPolicyInterface;
use Waaseyaa\Api\Tests\Fixtures\InMemoryEntityStorage;
use Waaseyaa\Entity\EntityInterface;
use Waaseyaa\Entity\EntityType;
use Waaseyaa\Entity\EntityTypeInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\GraphQL\Access\GraphQlAccessGuard;
use Waaseyaa\GraphQL\Resolver\ReferenceLoader;

#[CoversClass(ReferenceLoader::class)]
final class ReferenceLoaderTest extends TestCase
{
    private EntityTypeManager $entityTypeManager;
    private InMemoryEntityStorage $storage;
    private AccountInterface $account;

    protected function setUp(): void
    {
        $this->storage = new InMemoryEntityStorage('article');

        $this->entityTypeManager = new EntityTypeManager(
            new EventDispatcher(),
            fn () => $this->storage,
        );
        $this->entityTypeManager->registerCoreEntityType(new EntityType(
            id: 'article',
            label: 'Article',
            class: \Waaseyaa\Api\Tests\Fixtures\TestEntity::class,
            keys: ['id' => 'id', 'uuid' => 'uuid', 'label' => 'title'],
        ));

        $this->account = $this->createStub(AccountInterface::class);
        $this->account->method('id')->willReturn(1);
        $this->account->method('hasPermission')->willReturn(true);
        $this->account->method('getRoles')->willReturn(['authenticated']);
        $this->account->method('isAuthenticated')->willReturn(true);
    }

    private function openAccessGuard(): GraphQlAccessGuard
    {
        $policy = new class implements AccessPolicyInterface, FieldAccessPolicyInterface {
            public function access(EntityInterface $entity, string $operation, AccountInterface $account): AccessResult
            {
                return AccessResult::allowed();
            }

            public function createAccess(string $entityTypeId, string $bundle, AccountInterface $account): AccessResult
            {
                return AccessResult::allowed();
            }

            public function appliesTo(string $entityTypeId): bool
            {
                return true;
            }

            public function fieldAccess(EntityInterface $entity, string $fieldName, string $operation, AccountInterface $account): AccessResult
            {
                return AccessResult::neutral();
            }
        };

        return new GraphQlAccessGuard(new EntityAccessHandler([$policy]), $this->account);
    }

    private function seedArticle(string $title): EntityInterface
    {
        $entity = $this->storage->create(['title' => $title]);
        $entity->enforceIsNew();
        $this->storage->save($entity);

        return $entity;
    }

    // ── Depth cutoff ─────────────────────────────────────────────

    #[Test]
    public function loadReturnsNullWhenDepthExceedsMax(): void
    {
        $this->seedArticle('Deep');
        $loader = new ReferenceLoader($this->entityTypeManager, $this->openAccessGuard(), maxDepth: 2);

        $deferred = $loader->load('article', 1, currentDepth: 2);
        Deferred::runQueue();

        // Depth 2 == maxDepth 2 => cutoff (>= comparison)
        self::assertNull($deferred->result);
    }

    #[Test]
    public function loadResolvesWhenDepthBelowMax(): void
    {
        $entity = $this->seedArticle('Shallow');
        $loader = new ReferenceLoader($this->entityTypeManager, $this->openAccessGuard(), maxDepth: 3);

        $deferred = $loader->load('article', $entity->id(), currentDepth: 1);
        Deferred::runQueue();

        self::assertNotNull($deferred->result);
        self::assertSame('Shallow', $deferred->result['title']);
        self::assertSame(1, $deferred->result['_graphql_depth']);
    }

    // ── Unknown entity type ──────────────────────────────────────

    #[Test]
    public function loadReturnsNullForUnknownEntityType(): void
    {
        $loader = new ReferenceLoader($this->entityTypeManager, $this->openAccessGuard());

        $deferred = $loader->load('nonexistent_type', 1, currentDepth: 0);
        Deferred::runQueue();

        self::assertNull($deferred->result);
    }

    // ── Batch deduplication ──────────────────────────────────────

    #[Test]
    public function loadBatchesMultipleRequestsForSameType(): void
    {
        $a = $this->seedArticle('Article A');
        $b = $this->seedArticle('Article B');

        $loader = new ReferenceLoader($this->entityTypeManager, $this->openAccessGuard());

        $deferredA = $loader->load('article', $a->id(), currentDepth: 0);
        $deferredB = $loader->load('article', $b->id(), currentDepth: 0);
        Deferred::runQueue();

        self::assertNotNull($deferredA->result);
        self::assertNotNull($deferredB->result);
        self::assertSame('Article A', $deferredA->result['title']);
        self::assertSame('Article B', $deferredB->result['title']);
    }

    #[Test]
    public function loadDeduplicatesSameIdInBuffer(): void
    {
        $entity = $this->seedArticle('Deduplicated');

        $loader = new ReferenceLoader($this->entityTypeManager, $this->openAccessGuard());

        // Queue same entity twice
        $deferred1 = $loader->load('article', $entity->id(), currentDepth: 0);
        $deferred2 = $loader->load('article', $entity->id(), currentDepth: 0);
        Deferred::runQueue();

        self::assertNotNull($deferred1->result);
        self::assertNotNull($deferred2->result);
        self::assertSame($deferred1->result['title'], $deferred2->result['title']);
    }

    // ── Access filtering in deferred resolution ──────────────────

    #[Test]
    public function loadReturnsNullWhenViewAccessDenied(): void
    {
        $entity = $this->seedArticle('Secret');

        // No policies = Neutral = not isAllowed()
        $guard = new GraphQlAccessGuard(new EntityAccessHandler([]), $this->account);
        $loader = new ReferenceLoader($this->entityTypeManager, $guard);

        $deferred = $loader->load('article', $entity->id(), currentDepth: 0);
        Deferred::runQueue();

        self::assertNull($deferred->result);
    }

    #[Test]
    public function loadFiltersFieldsOnResolvedEntity(): void
    {
        $entity = $this->seedArticle('Filtered');

        $policy = new class implements AccessPolicyInterface, FieldAccessPolicyInterface {
            public function access(EntityInterface $entity, string $operation, AccountInterface $account): AccessResult
            {
                return AccessResult::allowed();
            }

            public function createAccess(string $entityTypeId, string $bundle, AccountInterface $account): AccessResult
            {
                return AccessResult::allowed();
            }

            public function appliesTo(string $entityTypeId): bool
            {
                return true;
            }

            public function fieldAccess(EntityInterface $entity, string $fieldName, string $operation, AccountInterface $account): AccessResult
            {
                // Forbid the uuid field
                return $fieldName === 'uuid' ? AccessResult::forbidden('hidden') : AccessResult::neutral();
            }
        };

        $guard = new GraphQlAccessGuard(new EntityAccessHandler([$policy]), $this->account);
        $loader = new ReferenceLoader($this->entityTypeManager, $guard);

        $deferred = $loader->load('article', $entity->id(), currentDepth: 0);
        Deferred::runQueue();

        self::assertNotNull($deferred->result);
        self::assertArrayHasKey('title', $deferred->result);
        self::assertArrayNotHasKey('uuid', $deferred->result);
    }

    // ── Cache reuse ──────────────────────────────────────────────

    #[Test]
    public function loadReusesAlreadyLoadedEntities(): void
    {
        $entity = $this->seedArticle('Cached');

        $loader = new ReferenceLoader($this->entityTypeManager, $this->openAccessGuard());

        // First load
        $deferred1 = $loader->load('article', $entity->id(), currentDepth: 0);
        Deferred::runQueue();
        self::assertNotNull($deferred1->result);

        // Second load should reuse cache (not re-query storage)
        $deferred2 = $loader->load('article', $entity->id(), currentDepth: 0);
        Deferred::runQueue();
        self::assertNotNull($deferred2->result);
        self::assertSame($deferred1->result['title'], $deferred2->result['title']);
    }

    #[Test]
    public function loadReturnsNullForNonexistentEntity(): void
    {
        $loader = new ReferenceLoader($this->entityTypeManager, $this->openAccessGuard());

        $deferred = $loader->load('article', 999, currentDepth: 0);
        Deferred::runQueue();

        self::assertNull($deferred->result);
    }

    #[Test]
    public function loadUsesSingleLoadMultiplePerEntityTypePerDeferredQueue(): void
    {
        $articleStorage = new CountingInMemoryStorage('article');

        $manager = new EntityTypeManager(
            new EventDispatcher(),
            static fn (EntityTypeInterface $_def): CountingInMemoryStorage => $articleStorage,
        );
        $manager->registerCoreEntityType(new EntityType(
            id: 'article',
            label: 'Article',
            class: \Waaseyaa\Api\Tests\Fixtures\TestEntity::class,
            keys: ['id' => 'id', 'uuid' => 'uuid', 'label' => 'title'],
        ));

        foreach (['A', 'B', 'C'] as $title) {
            $e = $articleStorage->create(['title' => $title]);
            $e->enforceIsNew();
            $articleStorage->save($e);
        }

        $guard = $this->openAccessGuard();
        $loader = new ReferenceLoader($manager, $guard);

        $d1 = $loader->load('article', 1, 0);
        $d2 = $loader->load('article', 2, 0);
        $d3 = $loader->load('article', 3, 0);
        Deferred::runQueue();

        self::assertSame(1, $articleStorage->loadMultipleInvocations);
        self::assertNotNull($d1->result);
        self::assertNotNull($d2->result);
        self::assertNotNull($d3->result);
    }
}

final class CountingInMemoryStorage extends InMemoryEntityStorage
{
    public int $loadMultipleInvocations = 0;

    public function __construct(string $entityTypeId = 'article')
    {
        parent::__construct($entityTypeId);
    }

    public function loadMultiple(array $ids = []): array
    {
        ++$this->loadMultipleInvocations;

        return parent::loadMultiple($ids);
    }
}

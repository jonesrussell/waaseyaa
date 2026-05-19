<?php

declare(strict_types=1);

namespace Waaseyaa\EntityStorage\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Waaseyaa\Database\DBALDatabase;
use Waaseyaa\Entity\EntityType;
use Waaseyaa\EntityStorage\SqlEntityQuery;
use Waaseyaa\EntityStorage\SqlEntityQueryResultCache;
use Waaseyaa\EntityStorage\SqlSchemaHandler;
use Waaseyaa\EntityStorage\SqlEntityStorage;
use Waaseyaa\EntityStorage\Tests\Fixtures\TestStorageEntity;

#[CoversClass(SqlEntityQueryResultCache::class)]
#[CoversClass(SqlEntityQuery::class)]
final class SqlEntityQueryResultCacheTest extends TestCase
{
    private DBALDatabase $database;

    private EntityType $entityType;

    protected function setUp(): void
    {
        $this->database = DBALDatabase::createSqlite();
        $this->entityType = new EntityType(
            id: 'cache_test_entity',
            label: 'Cache Test',
            class: TestStorageEntity::class,
            keys: [
                'id' => 'id',
                'uuid' => 'uuid',
                'bundle' => 'bundle',
                'label' => 'label',
                'langcode' => 'langcode',
            ],
        );

        $handler = new SqlSchemaHandler($this->entityType, $this->database);
        $handler->ensureTable();
        $handler->addFieldColumns([
            'status' => [
                'type' => 'int',
                'not null' => false,
            ],
        ]);

        $this->database->insert('cache_test_entity')
            ->fields(['id', 'uuid', 'bundle', 'label', 'langcode', 'status'])
            ->values([1, 'uuid-1', 'article', 'First', 'en', 1])
            ->execute();
    }

    #[Test]
    public function execute_returns_cached_ids_until_invalidated(): void
    {
        $cache = new SqlEntityQueryResultCache();
        $query = new SqlEntityQuery($this->entityType, $this->database, $cache);
        $query->accessCheck(false);

        $idsFirst = $query->condition('bundle', 'article')->execute();
        $this->assertSame([1], $idsFirst);

        $this->database->insert('cache_test_entity')
            ->fields(['id', 'uuid', 'bundle', 'label', 'langcode', 'status'])
            ->values([2, 'uuid-2', 'article', 'Second', 'en', 1])
            ->execute();

        $query2 = new SqlEntityQuery($this->entityType, $this->database, $cache);
        $query2->accessCheck(false);
        $idsStale = $query2->condition('bundle', 'article')->execute();
        $this->assertSame([1], $idsStale);

        $cache->invalidate('cache_test_entity');

        $query3 = new SqlEntityQuery($this->entityType, $this->database, $cache);
        $query3->accessCheck(false);
        $idsFresh = $query3->condition('bundle', 'article')->execute();
        $this->assertCount(2, $idsFresh);
        $this->assertContains(1, $idsFresh);
        $this->assertContains(2, $idsFresh);
    }

    #[Test]
    public function storage_save_invalidates_query_cache(): void
    {
        $cache = new SqlEntityQueryResultCache();
        $dispatcher = new EventDispatcher();
        $storage = new SqlEntityStorage($this->entityType, $this->database, $dispatcher, queryResultCache: $cache);

        $before = $storage->getQuery()->accessCheck(false)->condition('bundle', 'article')->execute();
        $this->assertSame([1], $before);

        $entity = $storage->create([
            'uuid' => 'uuid-new',
            'bundle' => 'article',
            'label' => 'New row',
            'langcode' => 'en',
            'status' => 1,
        ]);
        $storage->save($entity);

        $after = $storage->getQuery()->accessCheck(false)->condition('bundle', 'article')->execute();
        $this->assertCount(2, $after);
    }
}

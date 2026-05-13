<?php

declare(strict_types=1);

namespace Waaseyaa\EntityStorage\Tests\Contract;

use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Waaseyaa\Database\DBALDatabase;
use Waaseyaa\Entity\ContentEntityBase;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Entity\Storage\EntityStorageInterface;
use Waaseyaa\Entity\Testing\Translation\TranslatableEntityContractTest;
use Waaseyaa\EntityStorage\Backend\ReservedBackendIds;
use Waaseyaa\EntityStorage\EntitySchemaSync;
use Waaseyaa\EntityStorage\SqlEntityStorage;
use Waaseyaa\EntityStorage\Tests\Fixtures\TestTranslatableEntityTypeFactory;

/**
 * sql-blob backend coverage of the translatable entity contract (FR-058..FR-061).
 *
 * Wires {@see TestTranslatableEntityTypeFactory} against the default sql-blob
 * SqlEntityStorage layout and runs the 12 invariants T01..T12 inherited from
 * {@see TranslatableEntityContractTest}.
 */
#[CoversClass(SqlEntityStorage::class)]
final class SqlBlobTranslatableContractTest extends TranslatableEntityContractTest
{
    private ?DBALDatabase $database = null;
    private ?SqlEntityStorage $storage = null;

    protected function fixtureEntityTypeId(): string
    {
        return TestTranslatableEntityTypeFactory::ENTITY_TYPE_ID;
    }

    protected function makeStorage(): EntityStorageInterface
    {
        $this->database = DBALDatabase::createSqlite();

        $entityType = TestTranslatableEntityTypeFactory::build(
            primaryStorageBackend: ReservedBackendIds::SQL_BLOB,
        );

        $sync = new EntitySchemaSync($this->database);
        $sync->syncAll([$entityType]);

        $eventDispatcher = new EventDispatcher();
        $manager = new EntityTypeManager($eventDispatcher);
        $manager->registerEntityType($entityType);
        ContentEntityBase::setEntityTypeManager($manager);

        $this->storage = new SqlEntityStorage(
            entityType: $entityType,
            database: $this->database,
            eventDispatcher: $eventDispatcher,
        );

        return $this->storage;
    }

    protected function tearDown(): void
    {
        ContentEntityBase::setEntityTypeManager(null);
        $this->storage = null;
        $this->database = null;
        parent::tearDown();
    }
}

<?php

declare(strict_types=1);

namespace Waaseyaa\EntityStorage\Tests\Contract\Backend;

use PHPUnit\Framework\Attributes\CoversNothing;
use Waaseyaa\Database\DBALDatabase;
use Waaseyaa\Entity\EntityBase;
use Waaseyaa\Entity\EntityInterface;
use Waaseyaa\Entity\EntityType;
use Waaseyaa\EntityStorage\Backend\FieldStorageBackendInterface;
use Waaseyaa\EntityStorage\Backend\ReservedBackendIds;
use Waaseyaa\EntityStorage\Backend\SqlColumnBackend;
use Waaseyaa\EntityStorage\Backend\SqlColumnSchemaBuilder;
use Waaseyaa\EntityStorage\Testing\Contract\FieldStorageBackendContractTestCase;
use Waaseyaa\Field\FieldDefinition;

/**
 * Conformance suite for SqlColumnBackend (T064, FR-049, FR-050).
 *
 * Extends the shared {@see FieldStorageBackendContractTestCase} harness and
 * verifies that `sql-column` passes every contract test. Uses an in-memory
 * SQLite database; no filesystem writes.
 *
 * supportsQuery() is expected to return true for non-vector string fields —
 * the column backend can satisfy column-predicate queries (FR-014, FR-015).
 */
#[CoversNothing]
final class SqlColumnConformanceTest extends FieldStorageBackendContractTestCase
{
    /** @internal */
    public const ENTITY_TYPE_ID = 'conformance_column_entity';

    private const TABLE = 'conformance_column_entity';
    private const ID_KEY = 'id';
    private const FIELD_NAME = 'description';

    private DBALDatabase $db;

    protected function setUp(): void
    {
        $this->db = DBALDatabase::createSqlite(':memory:');
        $this->buildSchemaAndSeedRow();
        // Call parent last so createBackend()/prepareFixtureEntity() see a seeded DB.
        parent::setUp();
    }

    // -------------------------------------------------------------------------
    // FieldStorageBackendContractTestCase — template method implementations
    // -------------------------------------------------------------------------

    protected function createBackend(): FieldStorageBackendInterface
    {
        return new SqlColumnBackend(
            database: $this->db,
            entityTableName: self::TABLE,
            idKey: self::ID_KEY,
            entityTypeId: self::ENTITY_TYPE_ID,
        );
    }

    protected function prepareFixtureEntity(): EntityInterface
    {
        return new ConformanceColumnEntity(['id' => 1]);
    }

    protected function fixtureField(): FieldDefinition
    {
        // A dedicated column field in the sql-column table.
        return new FieldDefinition(name: self::FIELD_NAME, type: 'string');
    }

    protected function fixtureValue(): mixed
    {
        return 'conformance-column-value';
    }

    protected function alternateValue(): mixed
    {
        return 'conformance-column-alt';
    }

    protected function supportsQueryField(): FieldDefinition
    {
        return new FieldDefinition(name: self::FIELD_NAME, type: 'string');
    }

    protected function expectSupportsQuery(): bool
    {
        // sql-column returns true for all non-vector field types (FR-014, FR-015).
        return true;
    }

    // -------------------------------------------------------------------------
    // Additional column-specific check: id() constant
    // -------------------------------------------------------------------------

    public function testIdMatchesReservedConstant(): void
    {
        self::assertSame(ReservedBackendIds::SQL_COLUMN, $this->createBackend()->id());
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Build the entity table with the fixture field column and insert one row.
     *
     * SqlColumnBackend uses dedicated columns — the table must include both the
     * entity-key base columns (id, uuid, bundle, title, langcode) and the
     * fixture field column (description).
     */
    private function buildSchemaAndSeedRow(): void
    {
        $entityType = new EntityType(
            id: self::ENTITY_TYPE_ID,
            label: 'Conformance Column Entity',
            class: ConformanceColumnEntity::class,
            keys: ['id' => 'id', 'uuid' => 'uuid', 'label' => 'title', 'bundle' => 'bundle', 'langcode' => 'langcode'],
        );

        $baseSpec = [
            'fields' => [
                'id'       => ['type' => 'serial', 'not null' => true],
                'uuid'     => ['type' => 'varchar', 'length' => 128, 'not null' => true, 'default' => ''],
                'bundle'   => ['type' => 'varchar', 'length' => 128, 'not null' => true, 'default' => ''],
                'title'    => ['type' => 'varchar', 'length' => 255, 'not null' => true, 'default' => ''],
                'langcode' => ['type' => 'varchar', 'length' => 12, 'not null' => true, 'default' => 'en'],
            ],
            'primary key' => ['id'],
            'indexes'     => [],
        ];

        $builder = new SqlColumnSchemaBuilder($this->db);
        $builder->buildTable(
            $entityType,
            self::TABLE,
            [new FieldDefinition(name: self::FIELD_NAME, type: 'string')],
            $baseSpec,
        );

        // Insert the fixture row so read()/write() have a target to UPDATE.
        $this->db->insert(self::TABLE)
            ->fields(['id', 'uuid', 'bundle', 'title', 'langcode', self::FIELD_NAME])
            ->values([
                'id'            => 1,
                'uuid'          => 'cc000000-0000-0000-0000-000000000001',
                'bundle'        => 'default',
                'title'         => 'Conformance Fixture',
                'langcode'      => 'en',
                self::FIELD_NAME => null,
            ])
            ->execute();
    }
}

/**
 * Minimal entity fixture for SqlColumnConformanceTest.
 *
 * Defined here (not under src/) so it remains autoload-dev only and does not
 * trigger the production boot gotcha described in CLAUDE.md.
 */
final class ConformanceColumnEntity extends EntityBase
{
    public function __construct(array $values = [])
    {
        parent::__construct(
            $values,
            SqlColumnConformanceTest::ENTITY_TYPE_ID,
            ['id' => 'id', 'uuid' => 'uuid', 'label' => 'title', 'bundle' => 'bundle', 'langcode' => 'langcode'],
        );
    }
}

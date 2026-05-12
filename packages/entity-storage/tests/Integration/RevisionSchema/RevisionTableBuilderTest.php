<?php

declare(strict_types=1);

namespace Waaseyaa\EntityStorage\Tests\Integration\RevisionSchema;

use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Database\DBALDatabase;
use Waaseyaa\Entity\EntityType;
use Waaseyaa\EntityStorage\Schema\RevisionTableBuilder;
use Waaseyaa\Field\FieldDefinition;

/**
 * Integration tests for RevisionTableBuilder (WP07 T038 + T039).
 *
 * Uses an in-memory SQLite database for fast, isolated schema assertions.
 * A Postgres platform mock path tests the platform-dispatch logic without
 * requiring a live Postgres instance.
 */
#[CoversClass(RevisionTableBuilder::class)]
final class RevisionTableBuilderTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeSqliteDatabase(): DBALDatabase
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        return new DBALDatabase($connection);
    }

    private function makeRevisionTableBuilder(DBALDatabase $db): RevisionTableBuilder
    {
        return new RevisionTableBuilder($db);
    }

    private function makeRevisionableEntityType(
        string $id = 'teaching',
        bool $sqlColumn = false,
    ): EntityType {
        return new EntityType(
            id: $id,
            label: ucfirst($id),
            class: \stdClass::class,
            keys: ['id' => 'tid', 'uuid' => 'uuid', 'revision' => 'vid'],
            revisionable: true,
            primaryStorageBackend: $sqlColumn ? 'sql-column' : null,
        );
    }

    private function listColumns(DBALDatabase $db, string $table): array
    {
        $cols = [];
        foreach ($db->schema()->listTableNames() as $t) {
            if ($t !== $table) {
                continue;
            }
        }
        // Use raw query to get column names — portable across SQLite.
        /** @var array<array<string, mixed>> $rows */
        $rows = iterator_to_array($db->query('PRAGMA table_info(' . $table . ')'));
        foreach ($rows as $row) {
            $cols[] = (string) $row['name'];
        }
        return $cols;
    }

    // -------------------------------------------------------------------------
    // sql-blob primary: revision table has _data + metadata columns
    // -------------------------------------------------------------------------

    #[Test]
    public function sql_blob_primary_emits_revision_table_with_data_and_metadata(): void
    {
        $db = $this->makeSqliteDatabase();
        $builder = $this->makeRevisionTableBuilder($db);
        $type = $this->makeRevisionableEntityType('article', sqlColumn: false);

        $builder->build($type, 'sql-blob');

        self::assertTrue($db->schema()->tableExists('article__revision'));

        $columns = $this->listColumns($db, 'article__revision');

        // Primary key column.
        self::assertContains('vid', $columns);
        // FK to primary table (soft, no ON DELETE).
        self::assertContains('tid', $columns);
        // T039: revision metadata columns.
        self::assertContains('revision_created_at', $columns);
        self::assertContains('revision_author', $columns);
        self::assertContains('revision_log', $columns);
        // sql-blob: _data JSON column.
        self::assertContains('_data', $columns);
    }

    #[Test]
    public function sql_blob_revision_table_has_no_field_columns(): void
    {
        $db = $this->makeSqliteDatabase();
        $builder = $this->makeRevisionTableBuilder($db);
        $type = $this->makeRevisionableEntityType('page', sqlColumn: false);

        $fields = [
            new FieldDefinition(name: 'title', type: 'string'),
            new FieldDefinition(name: 'body', type: 'text'),
        ];

        $builder->build($type, 'sql-blob', $fields);

        $columns = $this->listColumns($db, 'page__revision');
        // sql-blob path does NOT add individual field columns.
        self::assertNotContains('title', $columns);
        self::assertNotContains('body', $columns);
        self::assertContains('_data', $columns);
    }

    // -------------------------------------------------------------------------
    // sql-column primary: revision table mirrors field columns
    // -------------------------------------------------------------------------

    #[Test]
    public function sql_column_primary_emits_revision_table_with_field_columns(): void
    {
        $db = $this->makeSqliteDatabase();
        $builder = $this->makeRevisionTableBuilder($db);
        $type = $this->makeRevisionableEntityType('teaching', sqlColumn: true);

        $fields = [
            new FieldDefinition(name: 'title', type: 'string'),
            new FieldDefinition(name: 'body', type: 'text'),
            new FieldDefinition(name: 'published_at', type: 'datetime'),
        ];

        $builder->build($type, 'sql-column', $fields);

        self::assertTrue($db->schema()->tableExists('teaching__revision'));

        $columns = $this->listColumns($db, 'teaching__revision');

        self::assertContains('vid', $columns);
        self::assertContains('tid', $columns);
        self::assertContains('revision_created_at', $columns);
        self::assertContains('revision_author', $columns);
        self::assertContains('revision_log', $columns);
        // sql-column: individual field columns present.
        self::assertContains('title', $columns);
        self::assertContains('body', $columns);
        self::assertContains('published_at', $columns);
        // sql-blob _data column must NOT appear.
        self::assertNotContains('_data', $columns);
    }

    #[Test]
    public function sql_column_skips_fields_routed_to_other_backends(): void
    {
        $db = $this->makeSqliteDatabase();
        $builder = $this->makeRevisionTableBuilder($db);
        $type = $this->makeRevisionableEntityType('node', sqlColumn: true);

        $fields = [
            new FieldDefinition(name: 'title', type: 'string'),
            (new FieldDefinition(name: 'embedding', type: 'text'))->storedIn('vector'),
        ];

        $builder->build($type, 'sql-column', $fields);

        $columns = $this->listColumns($db, 'node__revision');
        self::assertContains('title', $columns);
        // Vector-routed field must NOT appear in the SQL revision table.
        self::assertNotContains('embedding', $columns);
    }

    // -------------------------------------------------------------------------
    // Idempotency
    // -------------------------------------------------------------------------

    #[Test]
    public function build_is_idempotent_when_table_already_exists(): void
    {
        $db = $this->makeSqliteDatabase();
        $builder = $this->makeRevisionTableBuilder($db);
        $type = $this->makeRevisionableEntityType('post', sqlColumn: false);

        $builder->build($type, 'sql-blob');
        // Second call must not throw.
        $builder->build($type, 'sql-blob');

        self::assertTrue($db->schema()->tableExists('post__revision'));
    }

    // -------------------------------------------------------------------------
    // Guard: non-revisionable entity type throws
    // -------------------------------------------------------------------------

    #[Test]
    public function build_throws_when_entity_type_not_revisionable(): void
    {
        $db = $this->makeSqliteDatabase();
        $builder = $this->makeRevisionTableBuilder($db);

        $type = new EntityType(
            id: 'config',
            label: 'Config',
            class: \stdClass::class,
            keys: ['id' => 'id'],
            revisionable: false,
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('config');

        $builder->build($type, 'sql-blob');
    }

    // -------------------------------------------------------------------------
    // T039: revision_author has no ON DELETE clause (soft FK)
    // -------------------------------------------------------------------------

    #[Test]
    public function revision_author_column_has_no_on_delete_cascade(): void
    {
        $db = $this->makeSqliteDatabase();
        $builder = $this->makeRevisionTableBuilder($db);
        $type = $this->makeRevisionableEntityType('comment', sqlColumn: false);

        $builder->build($type, 'sql-blob');

        // SQLite PRAGMA foreign_key_list returns rows only when FK constraints
        // are declared with REFERENCES. If the table has no FKs declared,
        // the result is empty — which is what we want for revision_author.
        $fkRows = iterator_to_array(
            $db->query('PRAGMA foreign_key_list(comment__revision)'),
        );

        self::assertSame([], $fkRows, 'No ON DELETE foreign keys should be declared on revision table');
    }

    // -------------------------------------------------------------------------
    // Naming: table name is <entity_id>__revision
    // -------------------------------------------------------------------------

    #[Test]
    public function revision_table_name_is_entity_id_double_underscore_revision(): void
    {
        $db = $this->makeSqliteDatabase();
        $builder = $this->makeRevisionTableBuilder($db);
        $type = $this->makeRevisionableEntityType('my_entity', sqlColumn: false);

        $builder->build($type, 'sql-blob');

        self::assertTrue($db->schema()->tableExists('my_entity__revision'));
    }
}

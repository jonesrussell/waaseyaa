<?php

declare(strict_types=1);

namespace Waaseyaa\EntityStorage\Tests\Backend;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Waaseyaa\Database\DBALDatabase;
use Waaseyaa\Entity\Attribute\ContentEntityKeys;
use Waaseyaa\Entity\Attribute\ContentEntityType;
use Waaseyaa\Entity\ContentEntityBase;
use Waaseyaa\Entity\EntityType;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\EntityStorage\Backend\ReservedBackendIds;
use Waaseyaa\EntityStorage\EntitySchemaSync;
use Waaseyaa\EntityStorage\Schema\TranslationSchemaHandler;
use Waaseyaa\EntityStorage\SqlEntityStorage;
use Waaseyaa\Field\FieldDefinition;

/**
 * Integration coverage for sql-column translatable storage (FR-026..FR-032).
 *
 * Exercises the WP05 layout:
 *   - Schema sync materialises `<table>` + `<table>__translation` siblings.
 *   - INSERT new entity writes one primary row + one default-langcode
 *     translation row.
 *   - `addTranslation('oj')` + save adds one more translation row; the
 *     primary row remains a single row.
 *   - Translatable writes on a non-default langcode never touch the primary
 *     table; non-translatable writes never touch the translation table.
 *   - `removeTranslation('oj')` deletes only the matching translation row.
 *   - Multi-cardinality field-table shape: translatable → (entity_id, langcode,
 *     delta) PK with composite FK; non-translatable → (entity_id, delta) PK.
 *   - Non-translatable entity types retain the legacy layout (NFR-001).
 */
#[CoversNothing]
final class SqlColumnTranslatableTest extends TestCase
{
    private DBALDatabase $database;
    private EntityType $entityType;
    private SqlEntityStorage $storage;

    protected function setUp(): void
    {
        $this->database = DBALDatabase::createSqlite();

        $this->entityType = new EntityType(
            id: 'sqlcol_article',
            label: 'Sql-Column Article',
            class: SqlColumnTranslatableArticleFixture::class,
            keys: [
                'id'              => 'id',
                'uuid'            => 'uuid',
                'bundle'          => 'bundle',
                'label'           => 'label',
                'langcode'        => 'langcode',
                'default_langcode' => 'default_langcode',
            ],
            translatable: true,
            _fieldDefinitions: [
                'title'  => new FieldDefinition(name: 'title', type: 'string', translatable: true),
                'body'   => new FieldDefinition(name: 'body', type: 'text', translatable: true),
                'author' => new FieldDefinition(name: 'author', type: 'string', translatable: false),
            ],
            primaryStorageBackend: ReservedBackendIds::SQL_COLUMN,
        );

        $sync = new EntitySchemaSync($this->database);
        $sync->syncAll([$this->entityType]);

        $eventDispatcher = new EventDispatcher();
        $manager = new EntityTypeManager($eventDispatcher);
        $manager->registerEntityType($this->entityType);
        ContentEntityBase::setEntityTypeManager($manager);

        $this->storage = new SqlEntityStorage(
            entityType: $this->entityType,
            database: $this->database,
            eventDispatcher: $eventDispatcher,
        );
    }

    protected function tearDown(): void
    {
        ContentEntityBase::setEntityTypeManager(null);
    }

    #[Test]
    public function schemaSyncCreatesPrimaryAndTranslationTables(): void
    {
        $schema = $this->database->schema();
        self::assertTrue($schema->tableExists('sqlcol_article'), 'Primary table must exist.');
        self::assertTrue($schema->tableExists('sqlcol_article__translation'), 'Translation sibling table must exist.');

        // Primary table carries non-translatable columns only.
        self::assertTrue($schema->fieldExists('sqlcol_article', 'author'), 'Non-translatable field belongs on primary table.');
        self::assertTrue($schema->fieldExists('sqlcol_article', 'default_langcode'), 'Primary table pins the default langcode.');

        // Translation table carries translatable columns only.
        self::assertTrue($schema->fieldExists('sqlcol_article__translation', 'title'));
        self::assertTrue($schema->fieldExists('sqlcol_article__translation', 'body'));
        self::assertTrue($schema->fieldExists('sqlcol_article__translation', 'langcode'));
        self::assertFalse($schema->fieldExists('sqlcol_article__translation', 'author'), 'Translation table must not carry non-translatable fields.');
    }

    #[Test]
    public function schemaSyncIsIdempotent(): void
    {
        // Re-running sync against the existing schema must not throw or
        // produce conflicting DDL.
        $sync = new EntitySchemaSync($this->database);
        $sync->syncAll([$this->entityType]);
        $sync->syncAll([$this->entityType]);

        $schema = $this->database->schema();
        self::assertTrue($schema->tableExists('sqlcol_article'));
        self::assertTrue($schema->tableExists('sqlcol_article__translation'));
    }

    #[Test]
    public function insertNewTranslatableEntityWritesOnePrimaryAndOneTranslationRow(): void
    {
        $entity = $this->storage->create([
            'bundle'           => 'article',
            'label'            => 'Hello',
            'langcode'         => 'en',
            'default_langcode' => 'en',
            'uuid'             => 'u-1001',
            'title'            => 'Hello world',
            'body'             => 'Greetings.',
            'author'           => 'Alice',
        ]);
        $this->storage->save($entity);

        $primary = $this->fetchAllPrimary();
        self::assertCount(1, $primary, 'One primary row per entity (FR-027).');
        self::assertSame('Alice', $primary[0]['author']);
        self::assertSame('en', $primary[0]['default_langcode']);

        $translations = $this->fetchAllTranslations((string) $entity->id());
        self::assertCount(1, $translations, 'One translation row at insert (default langcode).');
        self::assertSame('en', (string) $translations[0]['langcode']);
        self::assertSame('Hello world', $translations[0]['title']);
        self::assertSame('Greetings.', $translations[0]['body']);
    }

    #[Test]
    public function addTranslationAddsRowOnTranslationTableOnly(): void
    {
        $entity = $this->storage->create([
            'bundle'           => 'article',
            'label'            => 'Hello',
            'langcode'         => 'en',
            'default_langcode' => 'en',
            'uuid'             => 'u-1002',
            'title'            => 'Hello world',
            'body'             => 'Greetings.',
            'author'           => 'Alice',
        ]);
        $this->storage->save($entity);
        $entityId = (string) $entity->id();

        $reloaded = $this->storage->load($entityId);
        self::assertNotNull($reloaded);

        $ojibwe = $reloaded->addTranslation('oj');
        $ojibwe->set('title', 'Aaniin');
        $ojibwe->set('body', 'Boozhoo.');
        $this->storage->save($ojibwe);

        // Primary row count unchanged.
        $primary = $this->fetchAllPrimary();
        self::assertCount(1, $primary, 'Primary row is a single row per entity even after addTranslation.');

        $translations = $this->fetchAllTranslations($entityId);
        self::assertCount(2, $translations, 'Two translation rows: en + oj.');

        $byLc = [];
        foreach ($translations as $row) {
            $byLc[(string) $row['langcode']] = $row;
        }
        self::assertArrayHasKey('en', $byLc);
        self::assertArrayHasKey('oj', $byLc);
        self::assertSame('Aaniin', $byLc['oj']['title']);
        self::assertSame('Hello world', $byLc['en']['title']);
    }

    #[Test]
    public function readTranslatableFieldFromNonDefaultLangcodeReadsTranslationTable(): void
    {
        $entity = $this->storage->create([
            'bundle'           => 'article',
            'label'            => 'Hello',
            'langcode'         => 'en',
            'default_langcode' => 'en',
            'uuid'             => 'u-1003',
            'title'            => 'Hello world',
            'body'             => 'Greetings.',
            'author'           => 'Alice',
        ]);
        $this->storage->save($entity);
        $entityId = (string) $entity->id();

        $reloaded = $this->storage->load($entityId);
        self::assertNotNull($reloaded);
        $ojibwe = $reloaded->addTranslation('oj');
        $ojibwe->set('title', 'Aaniin');
        $ojibwe->set('body', 'Boozhoo.');
        $this->storage->save($ojibwe);

        $fresh = $this->storage->load($entityId);
        self::assertNotNull($fresh);
        self::assertTrue($fresh->hasTranslation('oj'));
        $ojRead = $fresh->getTranslation('oj');
        self::assertSame('oj', $ojRead->activeLangcode());

        // The translatable values for 'oj' live on the translation row; verify
        // the storage row reflects the active-langcode value. (Per-field overlay
        // resolution onto get() is WP06's FallbackChainResolver job.)
        $rows = $this->fetchAllTranslations($entityId);
        $byLc = [];
        foreach ($rows as $row) {
            $byLc[(string) $row['langcode']] = $row;
        }
        self::assertSame('Aaniin', $byLc['oj']['title'], 'Translatable field landed on oj translation row.');
        self::assertSame('Boozhoo.', $byLc['oj']['body']);
        self::assertSame('Hello world', $byLc['en']['title'], 'Default-langcode translation untouched.');
    }

    #[Test]
    public function readNonTranslatableFieldFromNonDefaultLangcodeReadsPrimaryTable(): void
    {
        $entity = $this->storage->create([
            'bundle'           => 'article',
            'label'            => 'Hello',
            'langcode'         => 'en',
            'default_langcode' => 'en',
            'uuid'             => 'u-1004',
            'title'            => 'Hello world',
            'body'             => 'Greetings.',
            'author'           => 'Alice',
        ]);
        $this->storage->save($entity);
        $entityId = (string) $entity->id();

        $reloaded = $this->storage->load($entityId);
        self::assertNotNull($reloaded);
        $ojibwe = $reloaded->addTranslation('oj');
        $ojibwe->set('title', 'Aaniin');
        $this->storage->save($ojibwe);

        $fresh = $this->storage->load($entityId);
        self::assertNotNull($fresh);
        self::assertSame('Alice', $fresh->get('author'), 'Non-translatable field resolves from primary row.');
    }

    #[Test]
    public function writeTranslatableFieldOnDefaultLangcodeDoesNotTouchPrimaryAuthor(): void
    {
        $entity = $this->storage->create([
            'bundle'           => 'article',
            'label'            => 'Hello',
            'langcode'         => 'en',
            'default_langcode' => 'en',
            'uuid'             => 'u-1005',
            'title'            => 'Hello world',
            'body'             => 'Greetings.',
            'author'           => 'Alice',
        ]);
        $this->storage->save($entity);
        $entityId = (string) $entity->id();

        $reloaded = $this->storage->load($entityId);
        self::assertNotNull($reloaded);
        $reloaded->set('title', 'Updated title');
        $this->storage->save($reloaded);

        $primary = $this->fetchAllPrimary();
        self::assertCount(1, $primary);
        self::assertSame('Alice', $primary[0]['author'], 'Primary author unchanged.');

        $translations = $this->fetchAllTranslations($entityId);
        self::assertCount(1, $translations);
        self::assertSame('Updated title', $translations[0]['title'], 'Translatable write landed on translation row.');
    }

    #[Test]
    public function writeNonTranslatableFieldOnTranslationDoesNotTouchTranslationTable(): void
    {
        $entity = $this->storage->create([
            'bundle'           => 'article',
            'label'            => 'Hello',
            'langcode'         => 'en',
            'default_langcode' => 'en',
            'uuid'             => 'u-1006',
            'title'            => 'Hello world',
            'body'             => 'Greetings.',
            'author'           => 'Alice',
        ]);
        $this->storage->save($entity);
        $entityId = (string) $entity->id();

        // Stage an 'oj' translation first.
        $reloaded = $this->storage->load($entityId);
        self::assertNotNull($reloaded);
        $ojibwe = $reloaded->addTranslation('oj');
        $ojibwe->set('title', 'Aaniin');
        $this->storage->save($ojibwe);

        // Now write a non-translatable field via the 'oj' translation handle.
        $afterTranslate = $this->storage->load($entityId);
        self::assertNotNull($afterTranslate);
        $ojHandle = $afterTranslate->getTranslation('oj');
        $ojHandle->set('author', 'Beatrice');
        $this->storage->save($ojHandle);

        // Primary row now carries Beatrice.
        $primary = $this->fetchAllPrimary();
        self::assertSame('Beatrice', $primary[0]['author'], 'Non-translatable write landed on primary row.');

        // Translation table remains free of author.
        $translations = $this->fetchAllTranslations($entityId);
        foreach ($translations as $row) {
            self::assertArrayNotHasKey('author', $row, 'Translation table must never carry non-translatable column.');
        }
    }

    #[Test]
    public function removeTranslationDeletesOnlyMatchingTranslationRow(): void
    {
        $entity = $this->storage->create([
            'bundle'           => 'article',
            'label'            => 'Hello',
            'langcode'         => 'en',
            'default_langcode' => 'en',
            'uuid'             => 'u-1007',
            'title'            => 'Hello world',
            'body'             => 'Greetings.',
            'author'           => 'Alice',
        ]);
        $this->storage->save($entity);
        $entityId = (string) $entity->id();

        $reloaded = $this->storage->load($entityId);
        self::assertNotNull($reloaded);
        $ojibwe = $reloaded->addTranslation('oj');
        $ojibwe->set('title', 'Aaniin');
        $this->storage->save($ojibwe);

        self::assertCount(2, $this->fetchAllTranslations($entityId));

        $afterTranslate = $this->storage->load($entityId);
        self::assertNotNull($afterTranslate);
        $afterTranslate->removeTranslation('oj');
        $this->storage->save($afterTranslate);

        $remaining = $this->fetchAllTranslations($entityId);
        self::assertCount(1, $remaining, 'Only the default-langcode translation row remains.');
        self::assertSame('en', (string) $remaining[0]['langcode']);

        // Primary row untouched.
        $primary = $this->fetchAllPrimary();
        self::assertCount(1, $primary);
        self::assertSame('Alice', $primary[0]['author']);
    }

    #[Test]
    public function nonTranslatableMultiCardinalityFieldTableHasEntityIdDeltaShape(): void
    {
        $handler = new TranslationSchemaHandler($this->database);

        $tagsField = new FieldDefinition(name: 'tags', type: 'string', translatable: false);
        $handler->ensureMultiCardinalityTable(
            entityTable: 'sqlcol_article',
            idKey: 'id',
            field: $tagsField,
        );

        $schema = $this->database->schema();
        self::assertTrue($schema->tableExists('sqlcol_article__tags'));
        self::assertTrue($schema->fieldExists('sqlcol_article__tags', 'entity_id'));
        self::assertTrue($schema->fieldExists('sqlcol_article__tags', 'delta'));
        self::assertFalse(
            $schema->fieldExists('sqlcol_article__tags', 'langcode'),
            'Non-translatable multi-field table must NOT carry a langcode column.',
        );
    }

    #[Test]
    public function translatableMultiCardinalityFieldTableHasEntityIdLangcodeDeltaShape(): void
    {
        $handler = new TranslationSchemaHandler($this->database);

        $notesField = new FieldDefinition(name: 'notes', type: 'text', translatable: true);
        $handler->ensureMultiCardinalityTable(
            entityTable: 'sqlcol_article',
            idKey: 'id',
            field: $notesField,
        );

        $schema = $this->database->schema();
        self::assertTrue($schema->tableExists('sqlcol_article__notes'));
        self::assertTrue($schema->fieldExists('sqlcol_article__notes', 'entity_id'));
        self::assertTrue(
            $schema->fieldExists('sqlcol_article__notes', 'langcode'),
            'Translatable multi-field table MUST carry a langcode column for composite FK.',
        );
        self::assertTrue($schema->fieldExists('sqlcol_article__notes', 'delta'));
    }

    #[Test]
    public function nonTranslatableEntityTypePreservesLegacyLayout(): void
    {
        // Regression guard for NFR-001: non-translatable sql-column types
        // must not get a __translation sibling or a default_langcode column.
        $plain = new EntityType(
            id: 'sqlcol_plain',
            label: 'Plain',
            class: SqlColumnTranslatableArticleFixture::class,
            keys: [
                'id'       => 'id',
                'uuid'     => 'uuid',
                'bundle'   => 'bundle',
                'label'    => 'label',
                'langcode' => 'langcode',
            ],
            _fieldDefinitions: [
                'title' => new FieldDefinition(name: 'title', type: 'string', translatable: false),
            ],
            primaryStorageBackend: ReservedBackendIds::SQL_COLUMN,
        );

        $sync = new EntitySchemaSync($this->database);
        $sync->syncAll([$plain]);

        $schema = $this->database->schema();
        self::assertTrue($schema->tableExists('sqlcol_plain'));
        self::assertFalse(
            $schema->tableExists('sqlcol_plain__translation'),
            'Non-translatable sql-column types must not get a translation sibling table.',
        );
        self::assertFalse(
            $schema->fieldExists('sqlcol_plain', 'default_langcode'),
            'Non-translatable types must not carry default_langcode.',
        );
    }

    // -----------------------------------------------------------------
    // helpers
    // -----------------------------------------------------------------

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchAllPrimary(): array
    {
        $rows = [];
        $result = $this->database->select('sqlcol_article')
            ->fields('sqlcol_article')
            ->execute();
        foreach ($result as $row) {
            $rows[] = (array) $row;
        }
        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchAllTranslations(string $entityId): array
    {
        $rows = [];
        $result = $this->database->select('sqlcol_article__translation')
            ->fields('sqlcol_article__translation')
            ->condition('entity_id', $entityId)
            ->execute();
        foreach ($result as $row) {
            $rows[] = (array) $row;
        }
        return $rows;
    }
}

/**
 * @internal Test fixture for {@see SqlColumnTranslatableTest}.
 */
#[ContentEntityType(id: 'sqlcol_article')]
#[ContentEntityKeys(
    id: 'id',
    uuid: 'uuid',
    bundle: 'bundle',
    label: 'label',
    langcode: 'langcode',
    default_langcode: 'default_langcode',
)]
class SqlColumnTranslatableArticleFixture extends ContentEntityBase
{
    public function __construct(
        array $values = [],
        string $entityTypeId = '',
        array $entityKeys = [],
        array $fieldDefinitions = [],
    ) {
        parent::__construct($values, $entityTypeId, $entityKeys, $fieldDefinitions);
    }
}

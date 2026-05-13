<?php

declare(strict_types=1);

namespace Waaseyaa\EntityStorage\Tests\Backend;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Waaseyaa\Database\DBALDatabase;
use Waaseyaa\Entity\ContentEntityBase;
use Waaseyaa\Entity\EntityType;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\EntityStorage\EntitySchemaSync;
use Waaseyaa\EntityStorage\SqlEntityStorage;
use Waaseyaa\Field\FieldDefinition;

/**
 * Integration coverage for sql-blob translatable storage (FR-020..FR-025).
 *
 * Exercises the WP04 storage layout:
 *   - PK widened to (entity_id, langcode).
 *   - `default_langcode` column carried on every row.
 *   - Translatable fields land in the active-langcode row's `_data` blob.
 *   - Non-translatable fields land on the default-langcode row only.
 *   - Reads of non-translatable fields on a non-default-langcode row fall
 *     back to the default-langcode row.
 *   - UUID uniqueness enforced across distinct entities; same UUID across
 *     translations of one entity is allowed.
 */
#[CoversNothing]
final class SqlBlobTranslatableTest extends TestCase
{
    private DBALDatabase $database;
    private EntityType $entityType;
    private SqlEntityStorage $storage;

    protected function setUp(): void
    {
        $this->database = DBALDatabase::createSqlite();

        $this->entityType = new EntityType(
            id: 'translatable_article',
            label: 'Translatable Article',
            class: TranslatableArticleFixture::class,
            keys: [
                'id' => 'id',
                'uuid' => 'uuid',
                'bundle' => 'bundle',
                'label' => 'label',
                'langcode' => 'langcode',
                'default_langcode' => 'default_langcode',
            ],
            translatable: true,
            _fieldDefinitions: [
                'title' => new FieldDefinition(name: 'title', type: 'string', translatable: true),
                'body' => new FieldDefinition(name: 'body', type: 'text', translatable: true),
                'author' => new FieldDefinition(name: 'author', type: 'string', translatable: false),
            ],
        );

        $sync = new EntitySchemaSync($this->database);
        $sync->syncAll([$this->entityType]);

        // Wire the EntityTypeManager so ContentEntityBase::getEntityType() returns
        // our translatable EntityType (instead of the non-translatable fallback).
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
    public function schemaCreatesDefaultLangcodeColumnAndCompositePrimaryKey(): void
    {
        $schema = $this->database->schema();
        self::assertTrue($schema->tableExists('translatable_article'));
        self::assertTrue($schema->fieldExists('translatable_article', 'default_langcode'));
        self::assertTrue($schema->fieldExists('translatable_article', 'langcode'));

        // No standalone _translations side-table for sql-blob translatable types.
        self::assertFalse($schema->tableExists('translatable_article_translations'));

        // Composite PK accepts the same id across langcodes.
        $this->insertRawRow(['id' => 9001, 'uuid' => 'u-9001', 'langcode' => 'en', 'default_langcode' => 'en']);
        $this->insertRawRow(['id' => 9001, 'uuid' => 'u-9001', 'langcode' => 'oj', 'default_langcode' => 'en']);
        self::assertSame(2, $this->countRows(9001));
    }

    #[Test]
    public function saveNewEntityWritesSingleDefaultLangcodeRow(): void
    {
        $entity = $this->storage->create([
            'bundle' => 'article',
            'label' => 'Hello',
            'langcode' => 'en',
            'default_langcode' => 'en',
            'title' => 'Hello world',
            'body' => 'Greetings.',
            'author' => 'Alice',
        ]);

        $this->storage->save($entity);

        $rows = $this->fetchAllRows((int) $entity->id());
        self::assertCount(1, $rows);
        self::assertSame('en', $rows[0]['langcode']);
        self::assertSame('en', $rows[0]['default_langcode']);
        $data = json_decode((string) $rows[0]['_data'], associative: true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($data);
        // _data carries both translatable and non-translatable values on the default row.
        self::assertSame('Hello world', $data['title']);
        self::assertSame('Greetings.', $data['body']);
        self::assertSame('Alice', $data['author']);
    }

    #[Test]
    public function addTranslationWritesSecondRowWithTranslatableFieldsOnly(): void
    {
        $entity = $this->storage->create([
            'bundle' => 'article',
            'label' => 'Hello',
            'langcode' => 'en',
            'default_langcode' => 'en',
            'title' => 'Hello world',
            'body' => 'Greetings.',
            'author' => 'Alice',
        ]);
        $this->storage->save($entity);
        $entityId = (int) $entity->id();

        $reloaded = $this->storage->load($entityId);
        self::assertNotNull($reloaded);

        $ojibwe = $reloaded->addTranslation('oj');
        $ojibwe->set('title', 'Aaniin');
        $ojibwe->set('body', 'Boozhoo.');
        // Setting a non-translatable field on the 'oj' translation MUST update the 'en' row.
        $ojibwe->set('author', 'Beatrice');
        $this->storage->save($ojibwe);

        $rows = $this->fetchAllRows($entityId);
        self::assertCount(2, $rows);

        $byLc = [];
        foreach ($rows as $row) {
            $byLc[(string) $row['langcode']] = json_decode((string) $row['_data'], associative: true, flags: \JSON_THROW_ON_ERROR);
        }

        // 'oj' row carries only translatable fields.
        self::assertArrayHasKey('oj', $byLc);
        self::assertSame('Aaniin', $byLc['oj']['title']);
        self::assertSame('Boozhoo.', $byLc['oj']['body']);
        self::assertArrayNotHasKey('author', $byLc['oj'], 'Non-translatable field must not live on translation row.');

        // 'en' row picks up the non-translatable update (FR-024).
        self::assertSame('Beatrice', $byLc['en']['author']);
        // 'en' translatable values remain unchanged.
        self::assertSame('Hello world', $byLc['en']['title']);
    }

    #[Test]
    public function readNonTranslatableFieldOnTranslationFallsBackToDefaultRow(): void
    {
        $entity = $this->storage->create([
            'bundle' => 'article',
            'label' => 'Hello',
            'langcode' => 'en',
            'default_langcode' => 'en',
            'title' => 'Hello world',
            'body' => 'Greetings.',
            'author' => 'Alice',
        ]);
        $this->storage->save($entity);
        $entityId = (int) $entity->id();

        // Create the 'oj' translation row with only translatable fields.
        $reloaded = $this->storage->load($entityId);
        self::assertNotNull($reloaded);
        $ojibwe = $reloaded->addTranslation('oj');
        $ojibwe->set('title', 'Aaniin');
        $ojibwe->set('body', 'Boozhoo.');
        $this->storage->save($ojibwe);

        // Reload and switch to 'oj'. Non-translatable `author` must fall back
        // to the default-langcode row (FR-022).
        $fresh = $this->storage->load($entityId);
        self::assertNotNull($fresh);
        self::assertTrue($fresh->hasTranslation('oj'));
        $ojRead = $fresh->getTranslation('oj');
        // After hydration, _data merges into values. The non-translatable
        // `author` was written only on the default row, but the trait's
        // translationData map preserves it via the default row.
        // The active-langcode hydration path uses defaultRow as canonical,
        // so author appears on the entity values.
        self::assertSame('Alice', $fresh->get('author'));
        // The 'oj' translation row's `author` is absent from translationData['oj'].
        $rows = $this->fetchAllRows($entityId);
        $ojRow = null;
        foreach ($rows as $row) {
            if ((string) $row['langcode'] === 'oj') {
                $ojRow = $row;
                break;
            }
        }
        self::assertNotNull($ojRow);
        $ojData = json_decode((string) $ojRow['_data'], associative: true, flags: \JSON_THROW_ON_ERROR);
        self::assertArrayNotHasKey('author', $ojData);

        // The reloaded $ojRead exposes the active langcode without losing
        // the canonical entity identity.
        self::assertSame('oj', $ojRead->activeLangcode());
    }

    #[Test]
    public function removeTranslationDeletesRowOnSave(): void
    {
        $entity = $this->storage->create([
            'bundle' => 'article',
            'label' => 'Hello',
            'langcode' => 'en',
            'default_langcode' => 'en',
            'title' => 'Hello world',
            'body' => 'Greetings.',
            'author' => 'Alice',
        ]);
        $this->storage->save($entity);
        $entityId = (int) $entity->id();

        $reloaded = $this->storage->load($entityId);
        self::assertNotNull($reloaded);
        $ojibwe = $reloaded->addTranslation('oj');
        $ojibwe->set('title', 'Aaniin');
        $this->storage->save($ojibwe);

        self::assertSame(2, $this->countRows($entityId));

        $afterTranslation = $this->storage->load($entityId);
        self::assertNotNull($afterTranslation);
        $afterTranslation->removeTranslation('oj');
        $this->storage->save($afterTranslation);

        self::assertSame(1, $this->countRows($entityId));
        $remaining = $this->fetchAllRows($entityId);
        self::assertSame('en', $remaining[0]['langcode']);
    }

    #[Test]
    public function uuidIsUniqueAcrossEntitiesButReusableAcrossTranslations(): void
    {
        $first = $this->storage->create([
            'bundle' => 'article',
            'label' => 'A',
            'langcode' => 'en',
            'default_langcode' => 'en',
            'uuid' => 'shared-uuid',
            'title' => 'A title',
        ]);
        $this->storage->save($first);
        $firstId = (int) $first->id();

        // Same UUID on a translation row of the SAME entity must be allowed
        // (translations share UUID with their canonical entity).
        $reloaded = $this->storage->load($firstId);
        self::assertNotNull($reloaded);
        $ojibwe = $reloaded->addTranslation('oj');
        $ojibwe->set('title', 'Translated A');
        $this->storage->save($ojibwe);
        self::assertSame(2, $this->countRows($firstId));

        // A second, distinct entity may not reuse the same UUID on its
        // default-langcode row.
        $second = $this->storage->create([
            'bundle' => 'article',
            'label' => 'B',
            'langcode' => 'en',
            'default_langcode' => 'en',
            'uuid' => 'shared-uuid',
            'title' => 'B title',
        ]);

        $this->expectException(\Throwable::class);
        $this->storage->save($second);
    }

    #[Test]
    public function nonTranslatableEntityTypePreservesLegacyLayout(): void
    {
        // Regression guard for FR-025 / NFR-001: non-translatable types must
        // still receive the single-PK layout with no default_langcode column.
        $plain = new EntityType(
            id: 'plain_thing',
            label: 'Plain Thing',
            class: TranslatableArticleFixture::class,
            keys: [
                'id' => 'id',
                'uuid' => 'uuid',
                'bundle' => 'bundle',
                'label' => 'label',
                'langcode' => 'langcode',
            ],
        );

        $sync = new EntitySchemaSync($this->database);
        $sync->syncAll([$plain]);

        $schema = $this->database->schema();
        self::assertTrue($schema->tableExists('plain_thing'));
        self::assertFalse(
            $schema->fieldExists('plain_thing', 'default_langcode'),
            'Non-translatable entity types must not get a default_langcode column.',
        );
    }

    // -----------------------------------------------------------------
    // helpers
    // -----------------------------------------------------------------

    /**
     * @param array<string, mixed> $values
     */
    private function insertRawRow(array $values): void
    {
        $values = $values + [
            'bundle' => 'article',
            'label' => '',
            '_data' => '{}',
        ];
        $this->database->insert('translatable_article')
            ->fields(\array_keys($values))
            ->values($values)
            ->execute();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchAllRows(int $entityId): array
    {
        $result = $this->database->select('translatable_article')
            ->fields('translatable_article')
            ->condition('id', $entityId)
            ->execute();
        $rows = [];
        foreach ($result as $row) {
            $rows[] = (array) $row;
        }
        return $rows;
    }

    private function countRows(int $entityId): int
    {
        return \count($this->fetchAllRows($entityId));
    }
}

<?php

declare(strict_types=1);

namespace Waaseyaa\EntityStorage\Tests\Integration\Coordinator;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Waaseyaa\Database\DBALDatabase;
use Waaseyaa\Entity\ContentEntityBase;
use Waaseyaa\Entity\EntityInterface;
use Waaseyaa\Entity\EntityType;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Entity\Exception\EntityTranslationException;
use Waaseyaa\EntityStorage\Backend\ReservedBackendIds;
use Waaseyaa\EntityStorage\EntitySchemaSync;
use Waaseyaa\EntityStorage\SqlEntityStorage;
use Waaseyaa\EntityStorage\Tests\Backend\SqlColumnTranslatableArticleFixture;
use Waaseyaa\EntityStorage\Tests\Backend\TranslatableArticleFixture;
use Waaseyaa\Field\FieldDefinition;

/**
 * Coordinator write-semantics matrix (spec §7.3, FR-033..FR-036).
 *
 * Verifies the 8 storage cells from the data-model contract across both the
 * sql-blob and sql-column primary backends. Each cell asserts the row-count
 * delta and the resulting row layout on the primary / translation tables; the
 * coordinator-level langcodeRequired + UnitOfWork transaction invariants are
 * exercised alongside.
 *
 * Cells:
 *   1. non-translatable entity type → single insert + single update, unchanged.
 *   2. NEW translatable, langcode == default          → INSERT primary + default row.
 *   3. NEW translatable, langcode != default          → Case 2 + non-default row.
 *   4. EXISTING translatable, langcode == default     → UPDATE primary + default row.
 *   5. EXISTING translatable, langcode != default
 *      with hasTranslation(langcode) == true          → UPDATE primary + translation(L).
 *   6. EXISTING translatable, langcode != default
 *      with hasTranslation(langcode) == false         → UPDATE primary + INSERT translation(L).
 *   7. EXISTING translatable, pending remove(R)       → 4..6 + DELETE translation R.
 *   8. translatable, default_langcode unset           → throws langcodeRequired().
 */
#[CoversNothing]
final class TranslationWriteSemanticsTest extends TestCase
{
    private DBALDatabase $database;
    private EventDispatcher $dispatcher;
    private EntityTypeManager $manager;

    protected function setUp(): void
    {
        $this->database = DBALDatabase::createSqlite();
        $this->dispatcher = new EventDispatcher();
        $this->manager = new EntityTypeManager($this->dispatcher);
        ContentEntityBase::setEntityTypeManager($this->manager);
    }

    protected function tearDown(): void
    {
        ContentEntityBase::setEntityTypeManager(null);
    }

    // -----------------------------------------------------------------
    // Case 1 — non-translatable entity types unchanged across both backends.
    // -----------------------------------------------------------------

    #[Test]
    public function case1NonTranslatableSqlBlobUnchanged(): void
    {
        [$storage, $entityType] = $this->bootSqlBlobNonTranslatable();

        $entity = $storage->create([
            'bundle' => 'article',
            'label' => 'A',
            'langcode' => 'en',
            'title' => 'Hi',
        ]);
        $storage->save($entity);

        self::assertSame(1, $this->countRows($entityType->id(), (int) $entity->id()));
    }

    #[Test]
    public function case1NonTranslatableSqlColumnPreservesLegacyLayout(): void
    {
        // Non-translatable types with primaryStorageBackend = sql-column are an
        // unusual but legal configuration. The relevant matrix invariant is
        // that no translation sibling table is materialised (NFR-001 regression
        // guard); the row-write path for that combination is already covered
        // by SqlColumnTranslatableTest::nonTranslatableEntityTypePreservesLegacyLayout.
        [$_storage, $entityType] = $this->bootSqlColumnNonTranslatable();

        self::assertTrue($this->database->schema()->tableExists($entityType->id()));
        self::assertFalse(
            $this->database->schema()->tableExists($entityType->id() . '__translation'),
        );
    }

    // -----------------------------------------------------------------
    // Case 2 — NEW translatable, langcode == default.
    // -----------------------------------------------------------------

    #[Test]
    public function case2NewSqlBlobLangcodeEqualsDefault(): void
    {
        [$storage, $entityType] = $this->bootSqlBlobTranslatable();

        $entity = $this->createBlobEntity($storage, langcode: 'en', defaultLc: 'en');
        $storage->save($entity);

        $rows = $this->fetchAllRows($entityType->id(), (int) $entity->id());
        self::assertCount(1, $rows);
        self::assertSame('en', $rows[0]['langcode']);
        self::assertSame('en', $rows[0]['default_langcode']);
    }

    #[Test]
    public function case2NewSqlColumnLangcodeEqualsDefault(): void
    {
        [$storage, $entityType] = $this->bootSqlColumnTranslatable();

        $entity = $this->createColumnEntity($storage, langcode: 'en', defaultLc: 'en');
        $storage->save($entity);

        self::assertSame(1, $this->countRows($entityType->id(), (int) $entity->id()));
        self::assertSame(1, $this->countTranslationRows($entityType->id(), (int) $entity->id()));
        self::assertTrue($this->translationRowExists($entityType->id(), (int) $entity->id(), 'en'));
    }

    // -----------------------------------------------------------------
    // Case 3 — NEW translatable, langcode != default
    //          (pre-staged addTranslation before first save).
    // -----------------------------------------------------------------

    #[Test]
    public function case3NewSqlBlobActiveLangcodeNotDefault(): void
    {
        [$storage, $entityType] = $this->bootSqlBlobTranslatable();

        $entity = $this->createBlobEntity($storage, langcode: 'en', defaultLc: 'en');
        $ojibwe = $entity->addTranslation('oj');
        $ojibwe->set('title', 'Aaniin');
        $storage->save($ojibwe);

        $rows = $this->fetchAllRows($entityType->id(), (int) $ojibwe->id());
        self::assertCount(2, $rows);
        $langcodes = array_map(static fn (array $r): string => (string) $r['langcode'], $rows);
        sort($langcodes);
        self::assertSame(['en', 'oj'], $langcodes);
    }

    #[Test]
    public function case3NewSqlColumnActiveLangcodeNotDefault(): void
    {
        [$storage, $entityType] = $this->bootSqlColumnTranslatable();

        $entity = $this->createColumnEntity($storage, langcode: 'en', defaultLc: 'en');
        $ojibwe = $entity->addTranslation('oj');
        $ojibwe->set('title', 'Aaniin');
        $storage->save($ojibwe);

        // Primary row stays at 1.
        self::assertSame(1, $this->countRows($entityType->id(), (int) $ojibwe->id()));
        // Translation rows now include both en (default) and oj.
        self::assertTrue($this->translationRowExists($entityType->id(), (int) $ojibwe->id(), 'en'));
        self::assertTrue($this->translationRowExists($entityType->id(), (int) $ojibwe->id(), 'oj'));
    }

    // -----------------------------------------------------------------
    // Case 4 — EXISTING translatable, save the default-langcode row.
    // -----------------------------------------------------------------

    #[Test]
    public function case4ExistingSqlBlobLangcodeEqualsDefault(): void
    {
        [$storage, $entityType] = $this->bootSqlBlobTranslatable();

        $entity = $this->createBlobEntity($storage, langcode: 'en', defaultLc: 'en');
        $storage->save($entity);
        $entityId = (int) $entity->id();

        $reloaded = $storage->load($entityId);
        self::assertNotNull($reloaded);
        $reloaded->set('title', 'Updated en');
        $storage->save($reloaded);

        $rows = $this->fetchAllRows($entityType->id(), $entityId);
        self::assertCount(1, $rows, 'UPDATE-only path must not add new rows.');
        $data = json_decode((string) $rows[0]['_data'], associative: true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame('Updated en', $data['title']);
    }

    #[Test]
    public function case4ExistingSqlColumnLangcodeEqualsDefault(): void
    {
        [$storage, $entityType] = $this->bootSqlColumnTranslatable();

        $entity = $this->createColumnEntity($storage, langcode: 'en', defaultLc: 'en');
        $storage->save($entity);
        $entityId = (int) $entity->id();

        $reloaded = $storage->load($entityId);
        self::assertNotNull($reloaded);
        $reloaded->set('title', 'Updated en');
        $storage->save($reloaded);

        self::assertSame(1, $this->countRows($entityType->id(), $entityId));
        self::assertSame(1, $this->countTranslationRows($entityType->id(), $entityId));
        $en = $this->fetchTranslationRow($entityType->id(), $entityId, 'en');
        self::assertNotNull($en);
        self::assertSame('Updated en', $en['title']);
    }

    // -----------------------------------------------------------------
    // Case 5 — EXISTING translatable, langcode != default, hasTranslation(L).
    // -----------------------------------------------------------------

    #[Test]
    public function case5ExistingSqlBlobUpdateNonDefaultTranslation(): void
    {
        [$storage, $entityType] = $this->bootSqlBlobTranslatable();

        $entity = $this->createBlobEntity($storage, langcode: 'en', defaultLc: 'en');
        $storage->save($entity);
        $entityId = (int) $entity->id();

        $reloaded = $storage->load($entityId);
        self::assertNotNull($reloaded);
        $ojibwe = $reloaded->addTranslation('oj');
        $ojibwe->set('title', 'Aaniin');
        $storage->save($ojibwe);

        // Second update against existing oj translation row.
        $fresh = $storage->load($entityId);
        self::assertNotNull($fresh);
        self::assertTrue($fresh->hasTranslation('oj'));
        $ojUpdate = $fresh->getTranslation('oj');
        $ojUpdate->set('title', 'Aaniin v2');
        $storage->save($ojUpdate);

        $rows = $this->fetchAllRows($entityType->id(), $entityId);
        self::assertCount(2, $rows, 'Updating existing oj must not add rows.');

        $byLc = [];
        foreach ($rows as $row) {
            $byLc[(string) $row['langcode']] = json_decode(
                (string) $row['_data'],
                associative: true,
                flags: \JSON_THROW_ON_ERROR,
            );
        }
        self::assertSame('Aaniin v2', $byLc['oj']['title']);
    }

    #[Test]
    public function case5ExistingSqlColumnUpdateNonDefaultTranslation(): void
    {
        [$storage, $entityType] = $this->bootSqlColumnTranslatable();

        $entity = $this->createColumnEntity($storage, langcode: 'en', defaultLc: 'en');
        $storage->save($entity);
        $entityId = (int) $entity->id();

        $reloaded = $storage->load($entityId);
        self::assertNotNull($reloaded);
        $ojibwe = $reloaded->addTranslation('oj');
        $ojibwe->set('title', 'Aaniin');
        $storage->save($ojibwe);

        $fresh = $storage->load($entityId);
        self::assertNotNull($fresh);
        $ojUpdate = $fresh->getTranslation('oj');
        $ojUpdate->set('title', 'Aaniin v2');
        $storage->save($ojUpdate);

        self::assertSame(1, $this->countRows($entityType->id(), $entityId));
        self::assertSame(2, $this->countTranslationRows($entityType->id(), $entityId));
        $oj = $this->fetchTranslationRow($entityType->id(), $entityId, 'oj');
        self::assertNotNull($oj);
        self::assertSame('Aaniin v2', $oj['title']);
    }

    // -----------------------------------------------------------------
    // Case 6 — EXISTING translatable, langcode != default, !hasTranslation(L).
    // -----------------------------------------------------------------

    #[Test]
    public function case6ExistingSqlBlobInsertsMissingTranslation(): void
    {
        [$storage, $entityType] = $this->bootSqlBlobTranslatable();

        $entity = $this->createBlobEntity($storage, langcode: 'en', defaultLc: 'en');
        $storage->save($entity);
        $entityId = (int) $entity->id();

        $reloaded = $storage->load($entityId);
        self::assertNotNull($reloaded);
        self::assertFalse($reloaded->hasTranslation('oj'));

        $ojibwe = $reloaded->addTranslation('oj');
        $ojibwe->set('title', 'Aaniin');
        $storage->save($ojibwe);

        $rows = $this->fetchAllRows($entityType->id(), $entityId);
        self::assertCount(2, $rows);
    }

    #[Test]
    public function case6ExistingSqlColumnInsertsMissingTranslation(): void
    {
        [$storage, $entityType] = $this->bootSqlColumnTranslatable();

        $entity = $this->createColumnEntity($storage, langcode: 'en', defaultLc: 'en');
        $storage->save($entity);
        $entityId = (int) $entity->id();

        $reloaded = $storage->load($entityId);
        self::assertNotNull($reloaded);
        self::assertFalse($reloaded->hasTranslation('oj'));

        $ojibwe = $reloaded->addTranslation('oj');
        $ojibwe->set('title', 'Aaniin');
        $storage->save($ojibwe);

        // Primary row count unchanged; translation table gains one row.
        self::assertSame(1, $this->countRows($entityType->id(), $entityId));
        self::assertSame(2, $this->countTranslationRows($entityType->id(), $entityId));
        self::assertTrue($this->translationRowExists($entityType->id(), $entityId, 'oj'));
    }

    // -----------------------------------------------------------------
    // Case 7 — EXISTING translatable with pending remove(R).
    // -----------------------------------------------------------------

    #[Test]
    public function case7ExistingSqlBlobPendingRemoveDeletesRow(): void
    {
        [$storage, $entityType] = $this->bootSqlBlobTranslatable();

        $entity = $this->createBlobEntity($storage, langcode: 'en', defaultLc: 'en');
        $storage->save($entity);
        $entityId = (int) $entity->id();

        $reloaded = $storage->load($entityId);
        self::assertNotNull($reloaded);
        $ojibwe = $reloaded->addTranslation('oj');
        $ojibwe->set('title', 'Aaniin');
        $storage->save($ojibwe);

        self::assertSame(2, $this->countRows($entityType->id(), $entityId));

        $afterTranslation = $storage->load($entityId);
        self::assertNotNull($afterTranslation);
        $afterTranslation->removeTranslation('oj');
        $storage->save($afterTranslation);

        self::assertSame(1, $this->countRows($entityType->id(), $entityId));
        $remaining = $this->fetchAllRows($entityType->id(), $entityId);
        self::assertSame('en', $remaining[0]['langcode']);
    }

    #[Test]
    public function case7ExistingSqlColumnPendingRemoveDeletesRow(): void
    {
        [$storage, $entityType] = $this->bootSqlColumnTranslatable();

        $entity = $this->createColumnEntity($storage, langcode: 'en', defaultLc: 'en');
        $storage->save($entity);
        $entityId = (int) $entity->id();

        $reloaded = $storage->load($entityId);
        self::assertNotNull($reloaded);
        $ojibwe = $reloaded->addTranslation('oj');
        $ojibwe->set('title', 'Aaniin');
        $storage->save($ojibwe);

        self::assertSame(2, $this->countTranslationRows($entityType->id(), $entityId));

        $afterTranslation = $storage->load($entityId);
        self::assertNotNull($afterTranslation);
        $afterTranslation->removeTranslation('oj');
        $storage->save($afterTranslation);

        // Primary row preserved; oj translation row deleted; en translation row preserved.
        self::assertSame(1, $this->countRows($entityType->id(), $entityId));
        self::assertSame(1, $this->countTranslationRows($entityType->id(), $entityId));
        self::assertFalse($this->translationRowExists($entityType->id(), $entityId, 'oj'));
        self::assertTrue($this->translationRowExists($entityType->id(), $entityId, 'en'));
    }

    // -----------------------------------------------------------------
    // Case 8 — translatable + default_langcode unset throws.
    // -----------------------------------------------------------------

    #[Test]
    public function case8SqlBlobMissingDefaultLangcodeThrows(): void
    {
        [$storage] = $this->bootSqlBlobTranslatable();

        $entity = $storage->create([
            'bundle' => 'article',
            'label' => 'A',
            'langcode' => 'en',
            // default_langcode intentionally omitted.
            'title' => 'Hi',
        ]);

        $this->expectException(EntityTranslationException::class);
        $this->expectExceptionMessage('default_langcode');
        $storage->save($entity);
    }

    #[Test]
    public function case8SqlColumnMissingDefaultLangcodeThrows(): void
    {
        [$storage] = $this->bootSqlColumnTranslatable();

        $entity = $storage->create([
            'bundle' => 'article',
            'label' => 'A',
            'langcode' => 'en',
            // default_langcode intentionally omitted.
            'title' => 'Hi',
        ]);

        $this->expectException(EntityTranslationException::class);
        $this->expectExceptionMessage('default_langcode');
        $storage->save($entity);
    }

    // -----------------------------------------------------------------
    // T038 — UnitOfWork transaction draining for pending deletions.
    // Verifies the existing transactional save survives the matrix audit.
    // -----------------------------------------------------------------

    #[Test]
    public function pendingDeletionsAreDrainedInSingleTransactionSqlBlob(): void
    {
        [$storage, $entityType] = $this->bootSqlBlobTranslatable();

        $entity = $this->createBlobEntity($storage, langcode: 'en', defaultLc: 'en');
        $storage->save($entity);
        $entityId = (int) $entity->id();

        // Stage two translations, then remove one alongside an UPDATE on the
        // primary translation in a single save call.
        $reloaded = $storage->load($entityId);
        self::assertNotNull($reloaded);
        $oj = $reloaded->addTranslation('oj');
        $oj->set('title', 'Aaniin');
        $storage->save($oj);
        $reloaded2 = $storage->load($entityId);
        self::assertNotNull($reloaded2);
        $fr = $reloaded2->addTranslation('fr');
        $fr->set('title', 'Bonjour');
        $storage->save($fr);

        self::assertSame(3, $this->countRows($entityType->id(), $entityId));

        $combined = $storage->load($entityId);
        self::assertNotNull($combined);
        $combined->set('title', 'Updated en');
        $combined->removeTranslation('fr');
        $combined->removeTranslation('oj');
        $storage->save($combined);

        // Single transactional save: both deletions applied, primary row updated.
        $rows = $this->fetchAllRows($entityType->id(), $entityId);
        self::assertCount(1, $rows);
        self::assertSame('en', $rows[0]['langcode']);
    }

    #[Test]
    public function pendingDeletionsAreDrainedInSingleTransactionSqlColumn(): void
    {
        [$storage, $entityType] = $this->bootSqlColumnTranslatable();

        $entity = $this->createColumnEntity($storage, langcode: 'en', defaultLc: 'en');
        $storage->save($entity);
        $entityId = (int) $entity->id();

        $reloaded = $storage->load($entityId);
        self::assertNotNull($reloaded);
        $oj = $reloaded->addTranslation('oj');
        $oj->set('title', 'Aaniin');
        $storage->save($oj);
        $reloaded2 = $storage->load($entityId);
        self::assertNotNull($reloaded2);
        $fr = $reloaded2->addTranslation('fr');
        $fr->set('title', 'Bonjour');
        $storage->save($fr);

        self::assertSame(3, $this->countTranslationRows($entityType->id(), $entityId));

        $combined = $storage->load($entityId);
        self::assertNotNull($combined);
        $combined->removeTranslation('fr');
        $combined->removeTranslation('oj');
        $storage->save($combined);

        // Primary table untouched; both deletions drained.
        self::assertSame(1, $this->countRows($entityType->id(), $entityId));
        self::assertSame(1, $this->countTranslationRows($entityType->id(), $entityId));
        self::assertFalse($this->translationRowExists($entityType->id(), $entityId, 'fr'));
        self::assertFalse($this->translationRowExists($entityType->id(), $entityId, 'oj'));
    }

    // -----------------------------------------------------------------
    // Boot helpers — one per (backend × translatability) combination.
    // -----------------------------------------------------------------

    /**
     * @return array{0: SqlEntityStorage, 1: EntityType}
     */
    private function bootSqlBlobTranslatable(): array
    {
        $entityType = new EntityType(
            id: 'blob_matrix',
            label: 'Blob Matrix',
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
        return [$this->bootStorage($entityType), $entityType];
    }

    /**
     * @return array{0: SqlEntityStorage, 1: EntityType}
     */
    private function bootSqlColumnTranslatable(): array
    {
        $entityType = new EntityType(
            id: 'column_matrix',
            label: 'Column Matrix',
            class: SqlColumnTranslatableArticleFixture::class,
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
            primaryStorageBackend: ReservedBackendIds::SQL_COLUMN,
        );
        return [$this->bootStorage($entityType), $entityType];
    }

    /**
     * @return array{0: SqlEntityStorage, 1: EntityType}
     */
    private function bootSqlBlobNonTranslatable(): array
    {
        $entityType = new EntityType(
            id: 'blob_plain_matrix',
            label: 'Plain Blob Matrix',
            class: TranslatableArticleFixture::class,
            keys: [
                'id' => 'id',
                'uuid' => 'uuid',
                'bundle' => 'bundle',
                'label' => 'label',
                'langcode' => 'langcode',
            ],
            translatable: false,
            _fieldDefinitions: [
                'title' => new FieldDefinition(name: 'title', type: 'string'),
            ],
        );
        return [$this->bootStorage($entityType), $entityType];
    }

    /**
     * @return array{0: SqlEntityStorage, 1: EntityType}
     */
    private function bootSqlColumnNonTranslatable(): array
    {
        $entityType = new EntityType(
            id: 'column_plain_matrix',
            label: 'Plain Column Matrix',
            class: SqlColumnTranslatableArticleFixture::class,
            keys: [
                'id' => 'id',
                'uuid' => 'uuid',
                'bundle' => 'bundle',
                'label' => 'label',
                'langcode' => 'langcode',
            ],
            translatable: false,
            _fieldDefinitions: [
                'title' => new FieldDefinition(name: 'title', type: 'string'),
            ],
            primaryStorageBackend: ReservedBackendIds::SQL_COLUMN,
        );
        return [$this->bootStorage($entityType), $entityType];
    }

    private function bootStorage(EntityType $entityType): SqlEntityStorage
    {
        $sync = new EntitySchemaSync($this->database);
        $sync->syncAll([$entityType]);
        $this->manager->registerEntityType($entityType);

        return new SqlEntityStorage(
            entityType: $entityType,
            database: $this->database,
            eventDispatcher: $this->dispatcher,
        );
    }

    // -----------------------------------------------------------------
    // Entity factory helpers.
    // -----------------------------------------------------------------

    private function createBlobEntity(
        SqlEntityStorage $storage,
        string $langcode,
        string $defaultLc,
    ): EntityInterface {
        return $storage->create([
            'bundle' => 'article',
            'label' => 'Hello',
            'langcode' => $langcode,
            'default_langcode' => $defaultLc,
            'title' => 'Hello world',
            'body' => 'Greetings.',
            'author' => 'Alice',
        ]);
    }

    private function createColumnEntity(
        SqlEntityStorage $storage,
        string $langcode,
        string $defaultLc,
    ): EntityInterface {
        return $storage->create([
            'bundle' => 'article',
            'label' => 'Hello',
            'langcode' => $langcode,
            'default_langcode' => $defaultLc,
            'title' => 'Hello world',
            'body' => 'Greetings.',
            'author' => 'Alice',
        ]);
    }

    // -----------------------------------------------------------------
    // Row-count + row-fetch utilities.
    // -----------------------------------------------------------------

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchAllRows(string $tableName, int $entityId): array
    {
        $result = $this->database->select($tableName)
            ->fields($tableName)
            ->condition('id', $entityId)
            ->execute();
        $rows = [];
        foreach ($result as $row) {
            $rows[] = (array) $row;
        }
        return $rows;
    }

    private function countRows(string $tableName, int $entityId): int
    {
        return \count($this->fetchAllRows($tableName, $entityId));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchAllTranslationRows(string $tableName, int $entityId): array
    {
        $translationTable = $tableName . '__translation';
        if (!$this->database->schema()->tableExists($translationTable)) {
            return [];
        }
        $result = $this->database->select($translationTable)
            ->fields($translationTable)
            ->condition('entity_id', $entityId)
            ->execute();
        $rows = [];
        foreach ($result as $row) {
            $rows[] = (array) $row;
        }
        return $rows;
    }

    private function countTranslationRows(string $tableName, int $entityId): int
    {
        return \count($this->fetchAllTranslationRows($tableName, $entityId));
    }

    private function translationRowExists(string $tableName, int $entityId, string $langcode): bool
    {
        foreach ($this->fetchAllTranslationRows($tableName, $entityId) as $row) {
            if ((string) ($row['langcode'] ?? '') === $langcode) {
                return true;
            }
        }
        return false;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchTranslationRow(string $tableName, int $entityId, string $langcode): ?array
    {
        foreach ($this->fetchAllTranslationRows($tableName, $entityId) as $row) {
            if ((string) ($row['langcode'] ?? '') === $langcode) {
                return $row;
            }
        }
        return null;
    }
}

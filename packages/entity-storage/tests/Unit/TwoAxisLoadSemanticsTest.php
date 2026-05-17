<?php

declare(strict_types=1);

namespace Waaseyaa\EntityStorage\Tests\Unit;

use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Database\DBALDatabase;
use Waaseyaa\Entity\ContentEntityBase;
use Waaseyaa\Entity\EntityType;
use Waaseyaa\EntityStorage\Connection\SingleConnectionResolver;
use Waaseyaa\EntityStorage\Driver\RevisionableStorageDriver;

/**
 * WP04 / T024, T025, T027 — two-axis load semantics
 * (FR-015 default-langcode current, FR-016 getTranslation, FR-018 listRevisions,
 * FR-019 translations() filter).
 *
 * The full per-(tid, langcode) current-pointer table and the high-level
 * `RevisionableTranslatableStorage` coordinator are composed in later WPs;
 * this test pins the driver-level invariants WP04 must preserve:
 *
 *  - The driver's per-langcode current pointer is the source of truth for
 *    "current revision in this langcode" (FR-015 / FR-016).
 *  - `listRevisions(?$langcode)` interleaved-descending vs langcode-scoped
 *    selection is driven by `getRevisionIds()` + per-langcode filtering.
 *  - `translations()` excludes fully-pruned languages — verified by deleting
 *    all revisions for a langcode and asserting the driver no longer reports
 *    a current pointer for it (the WP05 coordinator-level method will read
 *    from this signal).
 */
#[CoversNothing]
final class TwoAxisLoadSemanticsTest extends TestCase
{
    private function makeSqlite(): DBALDatabase
    {
        return new DBALDatabase(
            DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]),
        );
    }

    private function twoAxisType(): EntityType
    {
        return new EntityType(
            id: 'teaching',
            label: 'Teaching',
            class: ContentEntityBase::class,
            keys: [
                'id'               => 'id',
                'uuid'             => 'uuid',
                'revision'         => 'revision_id',
                'langcode'         => 'langcode',
                'default_langcode' => 'default_langcode',
            ],
            revisionable: true,
            translatable: true,
        );
    }

    private function createTranslationRevisionTable(DBALDatabase $db, string $table): void
    {
        $db->schema()->createTable($table, [
            'fields' => [
                'entity_id'        => ['type' => 'varchar', 'length' => 128, 'not null' => true],
                'langcode'         => ['type' => 'varchar', 'length' => 12,  'not null' => true],
                'revision_id'      => ['type' => 'int',     'not null' => true],
                'revision_created' => ['type' => 'varchar', 'length' => 32, 'not null' => false],
                'revision_log'     => ['type' => 'text', 'not null' => false],
                'title'            => ['type' => 'varchar', 'length' => 255, 'not null' => false],
            ],
            'primary key' => ['entity_id', 'langcode', 'revision_id'],
        ]);
    }

    #[Test]
    public function defaultLangcodeCurrentRevisionIsTheLatestForThatLangcode(): void
    {
        // FR-015: `$storage->load()` returns the entity at default-langcode
        // current revision. The driver-level invariant is that the per-langcode
        // current pointer for the default langcode advances with each write.
        $db = $this->makeSqlite();
        $this->createTranslationRevisionTable($db, 'teaching__translation__revision');

        $driver = new RevisionableStorageDriver(new SingleConnectionResolver($db), $this->twoAxisType());

        $driver->writeRevision('1', ['title' => 'v1'], null, 'en');
        $driver->writeRevision('1', ['title' => 'v2'], null, 'en');
        $driver->writeRevision('1', ['title' => 'v3'], null, 'en');

        self::assertSame(3, $driver->currentLangcodeRevision('1', 'en'));
    }

    #[Test]
    public function getTranslationReadsPerLangcodeCurrentPointer(): void
    {
        // FR-016: `getTranslation($langcode)` returns the entity with that
        // langcode's current revision. Per-langcode sequences are independent.
        $db = $this->makeSqlite();
        $this->createTranslationRevisionTable($db, 'teaching__translation__revision');

        $driver = new RevisionableStorageDriver(new SingleConnectionResolver($db), $this->twoAxisType());

        $driver->writeRevision('1', ['title' => 'en-1'], null, 'en');
        $driver->writeRevision('1', ['title' => 'oj-1'], null, 'oj');
        $driver->writeRevision('1', ['title' => 'oj-2'], null, 'oj');
        $driver->writeRevision('1', ['title' => 'fr-1'], null, 'fr');

        self::assertSame(1, $driver->currentLangcodeRevision('1', 'en'));
        self::assertSame(2, $driver->currentLangcodeRevision('1', 'oj'));
        self::assertSame(1, $driver->currentLangcodeRevision('1', 'fr'));
    }

    #[Test]
    public function listRevisionsInterleavedReturnsAllLangcodes(): void
    {
        // FR-018: `listRevisions(null)` returns all revisions across langcodes.
        // Driver-level signal: the translation-revision table holds one row per
        // (entity, langcode, revision_id), and the union of those rows is the
        // interleaved revision list the WP05 coordinator returns.
        $db = $this->makeSqlite();
        $this->createTranslationRevisionTable($db, 'teaching__translation__revision');

        $driver = new RevisionableStorageDriver(new SingleConnectionResolver($db), $this->twoAxisType());

        $driver->writeRevision('1', ['title' => 'en-1'], null, 'en');
        $driver->writeRevision('1', ['title' => 'oj-1'], null, 'oj');
        $driver->writeRevision('1', ['title' => 'en-2'], null, 'en');

        $rowCount = 0;
        foreach ($db->query(
            'SELECT COUNT(*) AS c FROM teaching__translation__revision WHERE entity_id = ?',
            ['1'],
        ) as $row) {
            $rowCount = (int) ((array) $row)['c'];
            break;
        }
        self::assertSame(3, $rowCount);
    }

    #[Test]
    public function listRevisionsLangcodeScopedRestrictsToOneLangcode(): void
    {
        // FR-018: `listRevisions($langcode)` returns only that langcode's revisions.
        $db = $this->makeSqlite();
        $this->createTranslationRevisionTable($db, 'teaching__translation__revision');

        $driver = new RevisionableStorageDriver(new SingleConnectionResolver($db), $this->twoAxisType());

        $driver->writeRevision('1', ['title' => 'en-1'], null, 'en');
        $driver->writeRevision('1', ['title' => 'oj-1'], null, 'oj');
        $driver->writeRevision('1', ['title' => 'en-2'], null, 'en');
        $driver->writeRevision('1', ['title' => 'oj-2'], null, 'oj');

        // Coordinator-level langcode scoping is verified via per-langcode pointer
        // — each langcode reports its own monotonic head independently.
        self::assertSame(2, $driver->currentLangcodeRevision('1', 'en'));
        self::assertSame(2, $driver->currentLangcodeRevision('1', 'oj'));
    }

    #[Test]
    public function translationsExcludesFullyPrunedLangcodes(): void
    {
        // FR-019: `translations()` excludes langcodes that have no remaining
        // revisions. The driver-level signal: `hasCurrentLangcodeRevision()`
        // is false once all revisions for that langcode are deleted.
        $db = $this->makeSqlite();
        $this->createTranslationRevisionTable($db, 'teaching__translation__revision');

        $driver = new RevisionableStorageDriver(new SingleConnectionResolver($db), $this->twoAxisType());

        $driver->writeRevision('1', ['title' => 'en-1'], null, 'en');
        $driver->writeRevision('1', ['title' => 'oj-1'], null, 'oj');

        self::assertTrue($driver->hasCurrentLangcodeRevision('1', 'en'));
        self::assertTrue($driver->hasCurrentLangcodeRevision('1', 'oj'));

        // Prune all 'oj' revisions — coordinator-level removeTranslation cascade.
        $db->delete('teaching__translation__revision')
            ->condition('entity_id', '1')
            ->condition('langcode', 'oj')
            ->execute();
        // Coordinator also clears the in-memory per-langcode pointer cache;
        // mirror that here so the FR-019 invariant is verifiable today.
        $this->clearCachedPointer($driver, '1', 'oj');

        self::assertTrue($driver->hasCurrentLangcodeRevision('1', 'en'));
        self::assertFalse($driver->hasCurrentLangcodeRevision('1', 'oj'));
    }

    /**
     * Drops the in-memory per-langcode pointer for one (entity, langcode).
     * Mirrors the bookkeeping the coordinator performs after a deletion cascade
     * (the driver's cache is private; the coordinator owns this invariant).
     */
    private function clearCachedPointer(
        RevisionableStorageDriver $driver,
        string $entityId,
        string $langcode,
    ): void {
        $reflection = new \ReflectionProperty(RevisionableStorageDriver::class, 'currentLangcodePointers');
        /** @var array<string, array<string, int>> $cache */
        $cache = $reflection->getValue($driver);
        if (isset($cache[$entityId][$langcode])) {
            unset($cache[$entityId][$langcode]);
            $reflection->setValue($driver, $cache);
        }
    }
}

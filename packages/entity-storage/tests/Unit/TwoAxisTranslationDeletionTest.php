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
use Waaseyaa\Entity\Exception\EntityTranslationException;
use Waaseyaa\EntityStorage\Connection\SingleConnectionResolver;
use Waaseyaa\EntityStorage\Driver\RevisionableStorageDriver;

/**
 * WP04 / T028 — translation-deletion semantics (FR-034, FR-035, FR-036).
 *
 * Pins the coordinator-level `removeTranslation($langcode)` contract:
 *
 *  - FR-034: deleting a non-default translation removes the (entity, langcode)
 *    row plus all its translation-revision rows.
 *  - FR-035: removing the default langcode raises
 *    `EntityTranslationException::cannotRemoveDefault` (M-006 factory reuse).
 *  - FR-036: removing a non-default translation does not affect other
 *    langcodes or the entity row itself.
 *
 * The high-level `removeTranslation` method lives on the coordinator that
 * lands in a later WP; this test exercises the deletion shape against the
 * driver directly so the invariants are anchored today.
 */
#[CoversNothing]
final class TwoAxisTranslationDeletionTest extends TestCase
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

    /**
     * Coordinator-level removeTranslation contract enacted against the driver:
     *  - Guards default langcode.
     *  - Deletes all rows for (entity, langcode).
     *  - Clears the per-langcode current pointer so `hasCurrentLangcodeRevision()` returns false.
     *
     * (The pointer-clear is part of the FR-019 contract: the coordinator must
     * keep the in-memory pointer cache and the persisted rows in lockstep.)
     */
    private function removeTranslation(
        DBALDatabase $db,
        RevisionableStorageDriver $driver,
        string $entityId,
        string $langcode,
        string $defaultLangcode,
    ): void {
        if ($langcode === $defaultLangcode) {
            throw EntityTranslationException::cannotRemoveDefault($langcode);
        }

        $db->delete('teaching__translation__revision')
            ->condition('entity_id', $entityId)
            ->condition('langcode', $langcode)
            ->execute();

        // Coordinator-level: invalidate the in-memory current-pointer cache so
        // subsequent reads observe the pruned state. The driver's pointer
        // bookkeeping is private; we reset via the same path it uses for new
        // entities (a fresh driver yields an empty cache).
        $this->clearCachedPointer($driver, $entityId, $langcode);
    }

    /**
     * Drops the in-memory per-langcode pointer for one (entity, langcode).
     * Mirrors what the WP05 coordinator does after a deletion cascade.
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

    #[Test]
    public function removeTranslationDeletesAllRevisionRowsForThatLangcode(): void
    {
        // FR-034: removeTranslation deletes the (entity, langcode) row + all revisions.
        $db = $this->makeSqlite();
        $this->createTranslationRevisionTable($db, 'teaching__translation__revision');

        $driver = new RevisionableStorageDriver(new SingleConnectionResolver($db), $this->twoAxisType());

        $driver->writeRevision('1', ['title' => 'oj-1'], null, 'oj');
        $driver->writeRevision('1', ['title' => 'oj-2'], null, 'oj');
        $driver->writeRevision('1', ['title' => 'oj-3'], null, 'oj');
        self::assertTrue($driver->hasCurrentLangcodeRevision('1', 'oj'));

        $this->removeTranslation($db, $driver, '1', 'oj', defaultLangcode: 'en');

        self::assertFalse($driver->hasCurrentLangcodeRevision('1', 'oj'));
        self::assertSame(0, $this->rowCount($db, '1', 'oj'));
    }

    #[Test]
    public function removeDefaultTranslationRaisesCannotRemoveDefault(): void
    {
        // FR-035: removing default langcode raises EntityTranslationException::cannotRemoveDefault.
        $db = $this->makeSqlite();
        $this->createTranslationRevisionTable($db, 'teaching__translation__revision');

        $driver = new RevisionableStorageDriver(new SingleConnectionResolver($db), $this->twoAxisType());
        $driver->writeRevision('1', ['title' => 'en-1'], null, 'en');

        $this->expectException(EntityTranslationException::class);
        $this->expectExceptionMessage('Cannot remove the default translation');

        $this->removeTranslation($db, $driver, '1', 'en', defaultLangcode: 'en');
    }

    #[Test]
    public function removeNonDefaultTranslationDoesNotAffectOtherLangcodes(): void
    {
        // FR-036: removing a non-default translation must not touch other langcodes.
        $db = $this->makeSqlite();
        $this->createTranslationRevisionTable($db, 'teaching__translation__revision');

        $driver = new RevisionableStorageDriver(new SingleConnectionResolver($db), $this->twoAxisType());

        $driver->writeRevision('1', ['title' => 'en-1'], null, 'en');
        $driver->writeRevision('1', ['title' => 'en-2'], null, 'en');
        $driver->writeRevision('1', ['title' => 'oj-1'], null, 'oj');
        $driver->writeRevision('1', ['title' => 'fr-1'], null, 'fr');

        $this->removeTranslation($db, $driver, '1', 'oj', defaultLangcode: 'en');

        // 'en' and 'fr' untouched; 'oj' fully pruned.
        self::assertSame(2, $this->rowCount($db, '1', 'en'));
        self::assertSame(1, $this->rowCount($db, '1', 'fr'));
        self::assertSame(0, $this->rowCount($db, '1', 'oj'));
        self::assertTrue($driver->hasCurrentLangcodeRevision('1', 'en'));
        self::assertTrue($driver->hasCurrentLangcodeRevision('1', 'fr'));
        self::assertFalse($driver->hasCurrentLangcodeRevision('1', 'oj'));
    }

    private function rowCount(DBALDatabase $db, string $entityId, string $langcode): int
    {
        $result = $db->query(
            'SELECT COUNT(*) AS c FROM teaching__translation__revision WHERE entity_id = ? AND langcode = ?',
            [$entityId, $langcode],
        );
        foreach ($result as $row) {
            return (int) ((array) $row)['c'];
        }
        return 0;
    }
}

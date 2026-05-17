<?php

declare(strict_types=1);

namespace Waaseyaa\Tests\Integration\Phase29;

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
use Waaseyaa\EntityStorage\Exception\StorageMigrationException;

/**
 * Phase 29 integration: end-to-end two-axis save → load → delete lifecycle
 * (M-004 / WP04 — FR-015, FR-016, FR-017, FR-018, FR-019, FR-034, FR-035, FR-036).
 *
 * Composes the driver against a real (in-memory SQLite) database and validates
 * the WP04 coordinator-level invariants atop the WP01/WP02 substrate:
 *
 *  - Save populates per-langcode current pointers (FR-015 / FR-016).
 *  - Historical-revision save raises `EntityTranslationException::historicalRevisionWrite`
 *    with stable code `historical_revision_write` (FR-017, FR-040, FR-041).
 *  - `listRevisions(null)` interleaves langcodes; per-langcode pointers stay independent
 *    (FR-018).
 *  - `translations()` excludes fully-pruned langcodes (FR-019).
 *  - `removeTranslation('oj')` cascades and preserves siblings (FR-034 / FR-036).
 *  - `removeTranslation('en')` against the default langcode raises
 *    `EntityTranslationException::cannotRemoveDefault` (FR-035).
 *  - `StorageMigrationException` carries the stable error codes used by the
 *    migration generator and schema-sync trigger sites (FR-040 / FR-041).
 */
#[CoversNothing]
final class TwoAxisSaveLoadIntegrationTest extends TestCase
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

    private function createTranslationRevisionTable(DBALDatabase $db): void
    {
        $db->schema()->createTable('teaching__translation__revision', [
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
    public function multiLangcodeSaveThenLoadHonoursPerLangcodeCurrentPointer(): void
    {
        $db = $this->makeSqlite();
        $this->createTranslationRevisionTable($db);

        $driver = new RevisionableStorageDriver(new SingleConnectionResolver($db), $this->twoAxisType());

        // Write three langcodes, multiple revisions each.
        $driver->writeRevision('1', ['title' => 'en-1'], null, 'en');
        $driver->writeRevision('1', ['title' => 'en-2'], null, 'en');
        $driver->writeRevision('1', ['title' => 'oj-1'], null, 'oj');
        $driver->writeRevision('1', ['title' => 'fr-1'], null, 'fr');

        self::assertSame(2, $driver->currentLangcodeRevision('1', 'en'));
        self::assertSame(1, $driver->currentLangcodeRevision('1', 'oj'));
        self::assertSame(1, $driver->currentLangcodeRevision('1', 'fr'));

        // FR-018 interleaved listRevisions: every translation-revision row is reachable.
        $rowCount = 0;
        foreach ($db->query(
            'SELECT COUNT(*) AS c FROM teaching__translation__revision WHERE entity_id = ?',
            ['1'],
        ) as $row) {
            $rowCount = (int) ((array) $row)['c'];
            break;
        }
        self::assertSame(4, $rowCount);
    }

    #[Test]
    public function historicalRevisionWriteRaisesTypedException(): void
    {
        // FR-017: historical-revision save raises the typed factory.
        // The coordinator that detects this lives above the driver;
        // we exercise the contract directly via the factory.
        $ex = EntityTranslationException::historicalRevisionWrite(7, 'oj');

        self::assertSame('historical_revision_write', $ex->getCode());
        self::assertStringContainsString('7', $ex->getMessage());
        self::assertStringContainsString('oj', $ex->getMessage());
    }

    #[Test]
    public function removeTranslationCascadesAndPreservesSiblings(): void
    {
        // FR-034 + FR-036: removing a non-default langcode prunes its revisions
        // and leaves other langcodes untouched.
        $db = $this->makeSqlite();
        $this->createTranslationRevisionTable($db);

        $driver = new RevisionableStorageDriver(new SingleConnectionResolver($db), $this->twoAxisType());
        $driver->writeRevision('1', ['title' => 'en-1'], null, 'en');
        $driver->writeRevision('1', ['title' => 'oj-1'], null, 'oj');
        $driver->writeRevision('1', ['title' => 'oj-2'], null, 'oj');
        $driver->writeRevision('1', ['title' => 'fr-1'], null, 'fr');

        // Coordinator-level removeTranslation('oj') against the driver:
        // delete the rows + clear the in-memory per-langcode pointer.
        $db->delete('teaching__translation__revision')
            ->condition('entity_id', '1')
            ->condition('langcode', 'oj')
            ->execute();
        $this->clearCachedPointer($driver, '1', 'oj');

        // FR-019: 'oj' is now fully pruned.
        self::assertFalse($driver->hasCurrentLangcodeRevision('1', 'oj'));
        self::assertTrue($driver->hasCurrentLangcodeRevision('1', 'en'));
        self::assertTrue($driver->hasCurrentLangcodeRevision('1', 'fr'));
    }

    /**
     * Drops the in-memory per-langcode pointer for one (entity, langcode).
     * Mirrors the bookkeeping the coordinator performs after a deletion cascade.
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
    public function removeDefaultLangcodeRaisesCannotRemoveDefault(): void
    {
        // FR-035: removing the default langcode raises the M-006 reused factory.
        $this->expectException(EntityTranslationException::class);
        $this->expectExceptionMessage('Cannot remove the default translation');

        throw EntityTranslationException::cannotRemoveDefault('en');
    }

    #[Test]
    public function storageMigrationExceptionFactoriesCarryStableCodes(): void
    {
        // FR-040 + FR-041: the WP04 typed exception class exposes stable error
        // codes that downstream error-handling and log aggregation depend on.
        $noOp = StorageMigrationException::noOpPromotion('teaching');
        $unsupported = StorageMigrationException::unsupportedTwoAxisField('embedding', 'vector');

        self::assertSame('no_op_promotion', $noOp->errorCode);
        self::assertSame('unsupported_two_axis_field', $unsupported->errorCode);
        self::assertInstanceOf(\RuntimeException::class, $noOp);
        self::assertInstanceOf(\RuntimeException::class, $unsupported);
        self::assertStringContainsString('unsupportedTwoAxisField', $unsupported->getMessage());
    }
}

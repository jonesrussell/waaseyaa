<?php

declare(strict_types=1);

namespace Waaseyaa\EntityStorage\Tests\Unit\Driver;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Database\DBALDatabase;
use Waaseyaa\Entity\EntityType;
use Waaseyaa\EntityStorage\Connection\SingleConnectionResolver;
use Waaseyaa\EntityStorage\Driver\RevisionableStorageDriver;
use Waaseyaa\EntityStorage\SqlSchemaHandler;
use Waaseyaa\EntityStorage\Tests\Fixtures\TestRevisionableEntity;

/**
 * Driver-level two-axis behaviour (M-004 / WP03, T015..T017).
 *
 * Verifies that `RevisionableStorageDriver::writeRevision()`:
 *
 * - Dispatches to the per-`(tid, langcode)` translation-revision path when a
 *   non-null `$langcode` is supplied AND the entity type is two-axis
 *   (FR-007, FR-009).
 * - Preserves the single-axis path byte-for-byte when `$langcode === null`
 *   (R-A regression gate).
 * - Allocates independent revision sequences per langcode — saving the French
 *   translation never advances the English revision counter (FR-010).
 * - Tracks the in-process `(entity_id, langcode) -> revision_id` pointer via
 *   {@see RevisionableStorageDriver::currentLangcodeRevision()} so the
 *   coordinator can update `<entity>__translation` without re-querying.
 * - Triggers the single-axis path on a single-axis entity type even when a
 *   langcode is passed — the type, not the argument, drives the dispatch.
 *
 * Schema setup: the M-006 single-axis revision table (`<entity>_revision`) is
 * created by SqlSchemaHandler; the two-axis translation-revision table
 * (`<entity>__translation__revision`) is materialised inline with the driver's
 * column-naming convention (`revision_id`, `revision_created`, `revision_log`)
 * so this test exercises the driver in isolation from WP01/WP02 schema
 * builders (which use a different surrogate-PK shape; their integration is
 * covered by TwoAxisSchemaIntegrationTest in Phase29).
 */
#[CoversClass(RevisionableStorageDriver::class)]
final class RevisionableStorageDriverTwoAxisTest extends TestCase
{
    private DBALDatabase $db;

    private RevisionableStorageDriver $twoAxisDriver;

    private RevisionableStorageDriver $singleAxisDriver;

    protected function setUp(): void
    {
        $this->db = DBALDatabase::createSqlite();

        $singleAxisType = new EntityType(
            id: 'single_axis_blog',
            label: 'Single Axis Blog',
            class: TestRevisionableEntity::class,
            keys: ['id' => 'id', 'uuid' => 'uuid', 'label' => 'title', 'revision' => 'revision_id'],
            revisionable: true,
            revisionDefault: true,
        );

        $twoAxisType = new EntityType(
            id: 'teaching',
            label: 'Teaching',
            class: TestRevisionableEntity::class,
            keys: [
                'id'               => 'id',
                'uuid'             => 'uuid',
                'label'            => 'title',
                'revision'         => 'revision_id',
                'langcode'         => 'langcode',
                'default_langcode' => 'default_langcode',
            ],
            revisionable: true,
            revisionDefault: true,
            translatable: true,
        );

        $singleAxisHandler = new SqlSchemaHandler($singleAxisType, $this->db);
        $singleAxisHandler->ensureTable();
        $singleAxisHandler->ensureRevisionTable();

        $twoAxisHandler = new SqlSchemaHandler($twoAxisType, $this->db);
        $twoAxisHandler->ensureTable();
        $twoAxisHandler->ensureRevisionTable();
        $this->createTranslationRevisionTable('teaching__translation__revision');

        $resolver = new SingleConnectionResolver($this->db);
        $this->singleAxisDriver = new RevisionableStorageDriver($resolver, $singleAxisType);
        $this->twoAxisDriver   = new RevisionableStorageDriver($resolver, $twoAxisType);
    }

    #[Test]
    public function single_axis_path_preserved_when_langcode_omitted(): void
    {
        $revisionId = $this->twoAxisDriver->writeRevision('42', [
            'title' => 'How fire learned',
            'uuid'  => 'abc-1',
        ], 'initial');

        self::assertSame(1, $revisionId);

        // Goes into the M-006 single-axis revision table.
        $row = $this->twoAxisDriver->readRevision('42', 1);
        self::assertNotNull($row);
        self::assertSame('How fire learned', $row['title']);
    }

    #[Test]
    public function langcode_pin_routes_to_translation_revision_table(): void
    {
        $rev = $this->twoAxisDriver->writeRevision(
            '42',
            ['title' => 'How fire learned', 'uuid' => 'abc-1'],
            'initial-en',
            'en',
        );

        self::assertSame(1, $rev);

        // Single-axis revision table is NOT touched.
        self::assertNull($this->twoAxisDriver->getLatestRevisionId('42'));

        // Translation-revision table received the row.
        $written = $this->fetchTranslationRevision('teaching__translation__revision', '42', 'en', 1);
        self::assertNotNull($written);
        self::assertSame('How fire learned', $written['title']);
        self::assertSame('initial-en', $written['revision_log']);
    }

    #[Test]
    public function per_langcode_sequences_are_independent_fr_007_and_fr_010(): void
    {
        // Two English revisions then one Anishinaabemowin revision; the oj
        // sequence must start at 1 (independent), and the en pointer must not
        // shift when oj is written (FR-010).
        $this->twoAxisDriver->writeRevision('42', ['title' => 'en v1', 'uuid' => 'a'], null, 'en');
        $this->twoAxisDriver->writeRevision('42', ['title' => 'en v2', 'uuid' => 'a'], null, 'en');

        self::assertSame(2, $this->twoAxisDriver->currentLangcodeRevision('42', 'en'));
        self::assertNull($this->twoAxisDriver->currentLangcodeRevision('42', 'oj'));

        $ojRev = $this->twoAxisDriver->writeRevision('42', ['title' => 'oj v1', 'uuid' => 'a'], null, 'oj');

        self::assertSame(1, $ojRev, 'oj sequence MUST start at 1, independent of en (FR-007)');
        self::assertSame(2, $this->twoAxisDriver->currentLangcodeRevision('42', 'en'), 'en pointer unchanged (FR-010)');
        self::assertSame(1, $this->twoAxisDriver->currentLangcodeRevision('42', 'oj'));
    }

    #[Test]
    public function single_axis_entity_type_ignores_langcode_argument(): void
    {
        // Single-axis types route through the M-006 path even when a langcode
        // is supplied — the type drives the dispatch, not the argument. This
        // is the regression gate (R-A): existing single-axis drivers MUST be
        // byte-identical to M-006 behaviour.
        $revisionId = $this->singleAxisDriver->writeRevision(
            '7',
            ['title' => 'hello', 'uuid' => 'u-7'],
            null,
            'en',
        );

        self::assertSame(1, $revisionId);
        self::assertSame(1, $this->singleAxisDriver->getLatestRevisionId('7'));
        self::assertNull(
            $this->singleAxisDriver->currentLangcodeRevision('7', 'en'),
            'single-axis types MUST NOT populate the per-langcode pointer map',
        );
    }

    #[Test]
    public function set_current_langcode_revision_seeds_in_process_pointer(): void
    {
        $this->twoAxisDriver->setCurrentLangcodeRevision('42', 'fr', 17);

        self::assertSame(17, $this->twoAxisDriver->currentLangcodeRevision('42', 'fr'));
        self::assertTrue($this->twoAxisDriver->hasCurrentLangcodeRevision('42', 'fr'));
    }

    private function createTranslationRevisionTable(string $tableName): void
    {
        $schema = $this->db->schema();
        if ($schema->tableExists($tableName)) {
            return;
        }

        $schema->createTable($tableName, [
            'fields' => [
                'entity_id'        => ['type' => 'varchar', 'length' => 128, 'not null' => true],
                'langcode'         => ['type' => 'varchar', 'length' => 12, 'not null' => true],
                'revision_id'      => ['type' => 'int', 'not null' => true],
                'revision_created' => ['type' => 'varchar', 'length' => 32, 'not null' => false],
                'revision_log'     => ['type' => 'text', 'not null' => false],
                'title'            => ['type' => 'varchar', 'length' => 255, 'not null' => false],
                'uuid'             => ['type' => 'varchar', 'length' => 64, 'not null' => false],
            ],
            'primary key' => ['entity_id', 'langcode', 'revision_id'],
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchTranslationRevision(string $table, string $entityId, string $langcode, int $revisionId): ?array
    {
        $result = $this->db->select($table)
            ->fields($table)
            ->condition('entity_id', $entityId)
            ->condition('langcode', $langcode)
            ->condition('revision_id', (string) $revisionId)
            ->execute();

        foreach ($result as $row) {
            return (array) $row;
        }

        return null;
    }
}

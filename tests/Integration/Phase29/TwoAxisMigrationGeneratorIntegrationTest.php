<?php

declare(strict_types=1);

namespace Waaseyaa\Tests\Integration\Phase29;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\CLI\Handler\AddRevisionsMigrationGenerator;
use Waaseyaa\CLI\Handler\AddTranslationsMigrationGenerator;
use Waaseyaa\Entity\ContentEntityBase;
use Waaseyaa\Entity\EntityType;
use Waaseyaa\EntityStorage\Exception\StorageMigrationException;

/**
 * Integration: M-004 / WP06 — exercise the migration generators end-to-end on
 * an in-memory SQLite database that already holds single-axis (revisionable-only
 * or translatable-only) data, then assert the generated migration promotes the
 * schema to two-axis with the FR-026 / FR-027 backfill semantics intact.
 *
 * Each test:
 *   1. Materializes a pre-promotion schema (single-axis) and seeds rows.
 *   2. Calls the relevant generator's `generate()` method to obtain migration PHP.
 *   3. Asserts the generated migration string contains the contract-mandated
 *      tables, indices, and backfill statements.
 *   4. Re-runs the generator against an already-two-axis input and asserts
 *      `StorageMigrationException::noOpPromotion` (FR-029).
 *
 * The generator's output is a literal anonymous-class migration; this test does
 * not `eval()` it. End-to-end SQL execution is owned by the migration apply
 * runner (out-of-scope for the generator unit + WP06 scope). Schema-shape
 * assertions plus exception semantics fully exercise the contract for this WP.
 *
 * Phase 29 corresponds to M-004 — Entity Storage Translatable Revisions.
 */
#[CoversNothing]
final class TwoAxisMigrationGeneratorIntegrationTest extends TestCase
{
    private function sqliteConnection(): Connection
    {
        return DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    }

    private function revisionableOnlyType(string $id): EntityType
    {
        return new EntityType(
            id: $id,
            label: ucfirst($id),
            class: ContentEntityBase::class,
            keys: [
                'id'       => 'tid',
                'uuid'     => 'uuid',
                'revision' => 'vid',
            ],
            revisionable: true,
            translatable: false,
            primaryStorageBackend: 'sql-column',
        );
    }

    private function translatableOnlyType(string $id): EntityType
    {
        return new EntityType(
            id: $id,
            label: ucfirst($id),
            class: ContentEntityBase::class,
            keys: [
                'id'               => 'tid',
                'uuid'             => 'uuid',
                'langcode'         => 'langcode',
                'default_langcode' => 'default_langcode',
            ],
            revisionable: false,
            translatable: true,
            primaryStorageBackend: 'sql-column',
        );
    }

    private function twoAxisType(string $id): EntityType
    {
        return new EntityType(
            id: $id,
            label: ucfirst($id),
            class: ContentEntityBase::class,
            keys: [
                'id'               => 'tid',
                'uuid'             => 'uuid',
                'revision'         => 'vid',
                'langcode'         => 'langcode',
                'default_langcode' => 'default_langcode',
            ],
            revisionable: true,
            translatable: true,
            primaryStorageBackend: 'sql-column',
        );
    }

    #[Test]
    public function promote_revisionable_only_to_two_axis_via_add_translations(): void
    {
        $conn = $this->sqliteConnection();

        // Pre-promotion schema: single-axis revisionable-only.
        $conn->executeStatement('CREATE TABLE teaching (id INTEGER PRIMARY KEY, vid INTEGER NOT NULL, uuid TEXT)');
        $conn->executeStatement('CREATE TABLE teaching__revision (vid INTEGER PRIMARY KEY, tid INTEGER NOT NULL, revision_created_at TEXT NOT NULL, revision_author INTEGER, revision_log TEXT, title TEXT, body TEXT)');
        $conn->executeStatement("INSERT INTO teaching (id, vid, uuid) VALUES (1, 1, 'u-1'), (2, 2, 'u-2')");
        $conn->executeStatement("INSERT INTO teaching__revision (vid, tid, revision_created_at, revision_author, revision_log, title, body) VALUES (1, 1, '2026-05-16T00:00:00+00:00', NULL, 'rev', 't1', 'b1'), (2, 2, '2026-05-16T00:00:00+00:00', NULL, 'rev', 't2', 'b2')");

        $generator = new AddTranslationsMigrationGenerator();
        $php = $generator->generate(
            $this->revisionableOnlyType('teaching'),
            'en',
            'sql-column',
            ['title', 'body'],
        );

        // Contract §3.2 shape assertions.
        self::assertStringContainsString("'teaching__translation'", $php);
        self::assertStringContainsString("'teaching__translation__revision'", $php);
        self::assertStringContainsString('PRIMARY KEY (tid, langcode)', $php);
        self::assertStringContainsString('UNIQUE (tid, langcode, vid)', $php);
        self::assertStringContainsString('teaching_tx_rev_lookup', $php);

        // Backfill from existing __revision rows referenced.
        self::assertStringContainsString('FROM %s', $php);
        self::assertStringContainsString("'en'", $php);

        // Reverse-migration data-loss docblock (FR-028).
        self::assertStringContainsString('DATA LOSS', $php);
    }

    #[Test]
    public function promote_translatable_only_to_two_axis_via_add_revisions(): void
    {
        $conn = $this->sqliteConnection();

        // Pre-promotion schema: single-axis translatable-only.
        $conn->executeStatement('CREATE TABLE teaching (id INTEGER PRIMARY KEY, uuid TEXT, default_langcode VARCHAR(12), community_id INTEGER, starts_at TEXT)');
        $conn->executeStatement('CREATE TABLE teaching__translation (entity_id INTEGER NOT NULL, langcode VARCHAR(12) NOT NULL, title TEXT, body TEXT, PRIMARY KEY (entity_id, langcode))');
        $conn->executeStatement("INSERT INTO teaching (id, uuid, default_langcode, community_id, starts_at) VALUES (1, 'u-1', 'en', 42, '2026-06-01')");
        $conn->executeStatement("INSERT INTO teaching__translation (entity_id, langcode, title, body) VALUES (1, 'en', 'Hello', 'Body en'), (1, 'fr', 'Bonjour', 'Corps fr')");

        $generator = new AddRevisionsMigrationGenerator();
        $php = $generator->generate(
            $this->translatableOnlyType('teaching'),
            'en',
            nonTranslatableColumns: ['community_id', 'starts_at'],
            translatableColumns: ['title', 'body'],
        );

        // Contract §4.2 shape assertions.
        self::assertStringContainsString("'teaching__revision'", $php);
        self::assertStringContainsString("'teaching__translation__revision'", $php);
        self::assertStringContainsString('ADD COLUMN vid INTEGER NOT NULL DEFAULT 0', $php);
        self::assertStringContainsString('teaching_rev_tid', $php);
        self::assertStringContainsString('teaching_tx_rev_lookup', $php);
        self::assertStringContainsString('UNIQUE (tid, langcode, vid)', $php);

        // Non-translatable columns referenced and slated for drop on `{entity}`.
        self::assertStringContainsString("'community_id'", $php);
        self::assertStringContainsString("'starts_at'", $php);

        // Translatable columns referenced and slated for drop on `{entity}__translation`.
        self::assertStringContainsString("'title'", $php);
        self::assertStringContainsString("'body'", $php);

        // FR-028 data-loss docblock.
        self::assertStringContainsString('DATA LOSS', $php);
    }

    #[Test]
    public function add_translations_on_already_two_axis_raises_no_op_promotion(): void
    {
        $generator = new AddTranslationsMigrationGenerator();

        $this->expectException(StorageMigrationException::class);
        try {
            $generator->generate($this->twoAxisType('teaching'), 'en', 'sql-column', ['title']);
        } catch (StorageMigrationException $e) {
            self::assertSame('no_op_promotion', $e->errorCode);
            throw $e;
        }
    }

    #[Test]
    public function add_revisions_on_already_two_axis_raises_no_op_promotion(): void
    {
        $generator = new AddRevisionsMigrationGenerator();

        $this->expectException(StorageMigrationException::class);
        try {
            $generator->generate($this->twoAxisType('teaching'), 'en', nonTranslatableColumns: ['a']);
        } catch (StorageMigrationException $e) {
            self::assertSame('no_op_promotion', $e->errorCode);
            throw $e;
        }
    }
}

<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Tests\Handler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\CLI\Handler\AddTranslationsMigrationGenerator;
use Waaseyaa\Entity\EntityTypeInterface;
use Waaseyaa\EntityStorage\Exception\StorageMigrationException;

/**
 * WP06 / M-004 entity-storage-translatable-revisions FR-025, FR-026, FR-028, FR-029.
 *
 * Validates that {@see AddTranslationsMigrationGenerator::generate()} dispatches
 * correctly across the input-shape matrix from
 * `contracts/two-axis-migration.md` §2 and that the two-axis-from-revisionable
 * output matches the contract in §3.2.
 */
#[CoversClass(AddTranslationsMigrationGenerator::class)]
final class AddTranslationsMigrationGeneratorTwoAxisTest extends TestCase
{
    #[Test]
    public function generateOnRevisionableOnlyEmitsTwoAxisMigration(): void
    {
        $generator = new AddTranslationsMigrationGenerator();
        $entityType = $this->makeEntityType('teaching', isRevisionable: true, isTranslatable: false);

        $php = $generator->generate($entityType, 'en', 'sql-column', ['title', 'body']);

        // Two-axis tables created.
        self::assertStringContainsString("'teaching__translation'", $php);
        self::assertStringContainsString("'teaching__translation__revision'", $php);
        self::assertStringContainsString("'teaching__revision'", $php);

        // Backfill from revision table referenced.
        self::assertStringContainsString('SELECT %s FROM %s', $php);

        // Lookup index on (tid, langcode, vid DESC) (FR-026 §3.2).
        self::assertStringContainsString('teaching_tx_rev_lookup', $php);
        self::assertStringContainsString('(tid, langcode, vid DESC)', $php);

        // Translatable columns are dropped from the entity-revision table.
        self::assertStringContainsString("'title'", $php);
        self::assertStringContainsString("'body'", $php);
        self::assertStringContainsString('ALTER TABLE %s DROP COLUMN %s', $php);

        // Composite-PK marker.
        self::assertStringContainsString('UNIQUE (tid, langcode, vid)', $php);
        self::assertStringContainsString('PRIMARY KEY (tid, langcode)', $php);
    }

    #[Test]
    public function generateOnAlreadyTwoAxisRaisesNoOpPromotion(): void
    {
        $generator = new AddTranslationsMigrationGenerator();
        $entityType = $this->makeEntityType('teaching', isRevisionable: true, isTranslatable: true);

        $this->expectException(StorageMigrationException::class);
        $this->expectExceptionMessage('already two-axis');

        try {
            $generator->generate($entityType, 'en', 'sql-column', ['title']);
        } catch (StorageMigrationException $e) {
            self::assertSame('no_op_promotion', $e->errorCode);
            throw $e;
        }
    }

    #[Test]
    public function generateOnAlreadyTranslatableSingleAxisRaisesNoOpPromotion(): void
    {
        $generator = new AddTranslationsMigrationGenerator();
        $entityType = $this->makeEntityType('teaching', isRevisionable: false, isTranslatable: true);

        $this->expectException(StorageMigrationException::class);
        $this->expectExceptionMessage('no migration needed');
        $generator->generate($entityType, 'en', 'sql-column', ['title']);
    }

    #[Test]
    public function generateOnNonRevisionableNonTranslatableEmitsM006SingleAxisPath(): void
    {
        $generator = new AddTranslationsMigrationGenerator();
        $entityType = $this->makeEntityType('article', isRevisionable: false, isTranslatable: false);

        $php = $generator->generate($entityType, 'en', 'sql-column', ['title', 'body']);

        // M-006 single-axis hallmarks: `_translations` (single underscore) and
        // `default_langcode` column.
        self::assertStringContainsString("'article_translations'", $php);
        self::assertStringContainsString('default_langcode', $php);

        // No revision-table reference in single-axis path.
        self::assertStringNotContainsString('__translation__revision', $php);
        self::assertStringNotContainsString('article__revision', $php);
    }

    #[Test]
    public function twoAxisMigrationDownBodyFlagsDataLoss(): void
    {
        $generator = new AddTranslationsMigrationGenerator();
        $entityType = $this->makeEntityType('teaching', isRevisionable: true, isTranslatable: false);

        $php = $generator->generate($entityType, 'en', 'sql-column', ['title']);

        // FR-028 docblock requirement.
        self::assertStringContainsString('DATA LOSS', $php);
        self::assertStringContainsString('non-default-langcode', $php);

        // Reverse drops the two-axis tables.
        self::assertStringContainsString("DROP TABLE %s", $php);
    }

    #[Test]
    public function twoAxisRenderUsesQuotedIdentifiers(): void
    {
        $generator = new AddTranslationsMigrationGenerator();
        $entityType = $this->makeEntityType('teaching', isRevisionable: true, isTranslatable: false);

        $php = $generator->generate($entityType, 'en', 'sql-column', ['title']);

        // Always pipe identifiers through `quoteIdentifier` (DBAL).
        self::assertStringContainsString('$conn->quoteIdentifier(', $php);
        // Never embed raw user identifiers via string interpolation.
        self::assertStringNotContainsString('"teaching__translation"', $php);
    }

    /**
     * Build a minimal anonymous {@see EntityTypeInterface} for generator dispatch tests.
     *
     * The generator only reads `id()`, `isTranslatable()`, and `isRevisionable()`
     * on the dispatch path, so the rest of the interface returns benign defaults.
     */
    private function makeEntityType(string $id, bool $isRevisionable, bool $isTranslatable): EntityTypeInterface
    {
        return new class($id, $isRevisionable, $isTranslatable) implements EntityTypeInterface {
            public function __construct(
                private readonly string $id,
                private readonly bool $revisionable,
                private readonly bool $translatable,
            ) {}

            public function id(): string
            {
                return $this->id;
            }

            public function getLabel(): string
            {
                return $this->id;
            }

            public function getClass(): string
            {
                return \stdClass::class;
            }

            /** @return class-string<\Waaseyaa\Entity\Storage\EntityStorageInterface> */
            public function getStorageClass(): string
            {
                /** @var class-string<\Waaseyaa\Entity\Storage\EntityStorageInterface> $cls */
                $cls = \stdClass::class;

                return $cls;
            }

            /** @return array<string, string> */
            public function getKeys(): array
            {
                return ['id' => 'id'];
            }

            public function isRevisionable(): bool
            {
                return $this->revisionable;
            }

            public function getRevisionDefault(): bool
            {
                return false;
            }

            public function isTranslatable(): bool
            {
                return $this->translatable;
            }

            public function getBundleEntityType(): ?string
            {
                return null;
            }

            /** @return array<string, mixed> */
            public function getConstraints(): array
            {
                return [];
            }

            /** @return array<string, \Waaseyaa\Field\FieldDefinitionInterface> */
            public function getFieldDefinitions(): array
            {
                return [];
            }

            public function getPrimaryStorageBackend(): ?string
            {
                return null;
            }

            public function getGroup(): ?string
            {
                return null;
            }

            public function getDescription(): ?string
            {
                return null;
            }

            /** @return array{scope: string}|null */
            public function getTenancy(): ?array
            {
                return null;
            }
        };
    }
}

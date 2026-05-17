<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Tests\Handler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\CLI\Handler\AddRevisionsMigrationGenerator;
use Waaseyaa\Entity\EntityTypeInterface;
use Waaseyaa\EntityStorage\Exception\StorageMigrationException;

/**
 * WP06 / M-004 entity-storage-translatable-revisions FR-025, FR-027, FR-028, FR-029.
 *
 * Validates that {@see AddRevisionsMigrationGenerator::generate()} dispatches
 * correctly across the input-shape matrix from
 * `contracts/two-axis-migration.md` §2 and that the two-axis-from-translatable
 * output matches the contract in §4.2.
 */
#[CoversClass(AddRevisionsMigrationGenerator::class)]
final class AddRevisionsMigrationGeneratorTest extends TestCase
{
    #[Test]
    public function generateOnTranslatableOnlyEmitsTwoAxisMigration(): void
    {
        $generator = new AddRevisionsMigrationGenerator();
        $entityType = $this->makeEntityType('teaching', isRevisionable: false, isTranslatable: true);

        $php = $generator->generate(
            $entityType,
            'en',
            nonTranslatableColumns: ['community_id', 'starts_at'],
            translatableColumns: ['title', 'body'],
        );

        // Two-axis tables created.
        self::assertStringContainsString("'teaching__revision'", $php);
        self::assertStringContainsString("'teaching__translation__revision'", $php);
        // Existing translation table is modified, not recreated.
        self::assertStringContainsString("'teaching__translation'", $php);

        // vid pointer columns added to both current-state tables.
        self::assertStringContainsString('ADD COLUMN vid INTEGER NOT NULL DEFAULT 0', $php);

        // Non-translatable columns referenced and moved off `{entity}`.
        self::assertStringContainsString("'community_id'", $php);
        self::assertStringContainsString("'starts_at'", $php);
        self::assertStringContainsString('DROP COLUMN %s', $php);

        // Translatable columns referenced and moved off `{entity}__translation`.
        self::assertStringContainsString("'title'", $php);
        self::assertStringContainsString("'body'", $php);

        // Lookup indices.
        self::assertStringContainsString('teaching_rev_tid', $php);
        self::assertStringContainsString('teaching_tx_rev_lookup', $php);
        self::assertStringContainsString('UNIQUE (tid, langcode, vid)', $php);
    }

    #[Test]
    public function generateOnNonRevisionableNonTranslatableEmitsSingleAxisRevisionablePath(): void
    {
        $generator = new AddRevisionsMigrationGenerator();
        $entityType = $this->makeEntityType('article', isRevisionable: false, isTranslatable: false);

        $php = $generator->generate(
            $entityType,
            'en',
            nonTranslatableColumns: ['title', 'body'],
        );

        // Single-axis revisionable hallmarks.
        self::assertStringContainsString("'article__revision'", $php);
        self::assertStringContainsString('vid INTEGER NOT NULL PRIMARY KEY', $php);
        self::assertStringContainsString('ADD COLUMN vid INTEGER NOT NULL DEFAULT 0', $php);

        // No translation-revision plumbing in single-axis path.
        self::assertStringNotContainsString('__translation__revision', $php);
        self::assertStringNotContainsString("'article__translation'", $php);
    }

    #[Test]
    public function generateOnAlreadyTwoAxisRaisesNoOpPromotion(): void
    {
        $generator = new AddRevisionsMigrationGenerator();
        $entityType = $this->makeEntityType('teaching', isRevisionable: true, isTranslatable: true);

        $this->expectException(StorageMigrationException::class);
        $this->expectExceptionMessage('already two-axis');

        try {
            $generator->generate($entityType, 'en', nonTranslatableColumns: ['a']);
        } catch (StorageMigrationException $e) {
            self::assertSame('no_op_promotion', $e->errorCode);
            throw $e;
        }
    }

    #[Test]
    public function generateOnAlreadyRevisionableSingleAxisRaisesNoOpPromotion(): void
    {
        $generator = new AddRevisionsMigrationGenerator();
        $entityType = $this->makeEntityType('teaching', isRevisionable: true, isTranslatable: false);

        $this->expectException(StorageMigrationException::class);
        $this->expectExceptionMessage('no migration needed');
        $generator->generate($entityType, 'en', nonTranslatableColumns: ['a']);
    }

    #[Test]
    public function twoAxisMigrationDownBodyFlagsDataLoss(): void
    {
        $generator = new AddRevisionsMigrationGenerator();
        $entityType = $this->makeEntityType('teaching', isRevisionable: false, isTranslatable: true);

        $php = $generator->generate(
            $entityType,
            'en',
            nonTranslatableColumns: ['community_id'],
            translatableColumns: ['title'],
        );

        // FR-028 docblock requirement.
        self::assertStringContainsString('DATA LOSS', $php);
        self::assertStringContainsString('non-current revision', $php);

        // Reverse drops the revision tables.
        self::assertStringContainsString('DROP TABLE %s', $php);
        self::assertStringContainsString('DROP COLUMN vid', $php);
    }

    #[Test]
    public function singleAxisMigrationDownBodyFlagsDataLoss(): void
    {
        $generator = new AddRevisionsMigrationGenerator();
        $entityType = $this->makeEntityType('article', isRevisionable: false, isTranslatable: false);

        $php = $generator->generate(
            $entityType,
            'en',
            nonTranslatableColumns: ['title'],
        );

        self::assertStringContainsString('DATA LOSS', $php);
        self::assertStringContainsString('revision history', $php);
    }

    #[Test]
    public function nonTranslatableColumnsHelperFiltersByTranslatableFlag(): void
    {
        $generator = new AddRevisionsMigrationGenerator();

        $entityType = new class implements EntityTypeInterface {
            public function id(): string
            {
                return 'teaching';
            }

            public function getLabel(): string
            {
                return 'Teaching';
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
                return false;
            }

            public function getRevisionDefault(): bool
            {
                return false;
            }

            public function isTranslatable(): bool
            {
                return true;
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
                return [
                    'title' => new \Waaseyaa\Field\FieldDefinition(
                        name: 'title',
                        type: 'string',
                        translatable: true,
                        label: 'Title',
                    ),
                    'community_id' => new \Waaseyaa\Field\FieldDefinition(
                        name: 'community_id',
                        type: 'integer',
                        translatable: false,
                        label: 'Community',
                    ),
                ];
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

        self::assertSame(['community_id'], $generator->nonTranslatableColumns($entityType));
        self::assertSame(['title'], $generator->translatableColumns($entityType));
    }

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

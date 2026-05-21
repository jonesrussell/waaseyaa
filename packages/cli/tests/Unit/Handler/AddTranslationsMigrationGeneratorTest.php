<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Tests\Unit\Handler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\CLI\Handler\AddTranslationsMigrationGenerator;
use Waaseyaa\Entity\EntityTypeInterface;
use Waaseyaa\Field\FieldDefinitionInterface;

#[CoversClass(AddTranslationsMigrationGenerator::class)]
final class AddTranslationsMigrationGeneratorTest extends TestCase
{
    #[Test]
    public function renderRejectsInjectionPayloadInDefaultLangcode(): void
    {
        $generator = new AddTranslationsMigrationGenerator();
        $entityType = $this->makeEntityTypeStub('article');

        $this->expectException(\InvalidArgumentException::class);
        $generator->render($entityType, "en-US'); DROP TABLE users;--", 'sql-blob', []);
    }

    #[Test]
    public function renderRejectsNullByteInDefaultLangcode(): void
    {
        $generator = new AddTranslationsMigrationGenerator();
        $entityType = $this->makeEntityTypeStub('article');

        $this->expectException(\InvalidArgumentException::class);
        $generator->render($entityType, "en\x00null-byte", 'sql-blob', []);
    }

    #[Test]
    public function generateRejectsInjectionPayloadInDefaultLangcode(): void
    {
        $generator = new AddTranslationsMigrationGenerator();
        $entityType = $this->makeEntityTypeStub('article');

        $this->expectException(\InvalidArgumentException::class);
        // No file should be written — InvalidArgumentException must be thrown before render.
        $generator->generate($entityType, "en\x00null-byte", 'sql-blob', []);
    }

    #[Test]
    public function generateRejectsEmptyLangcode(): void
    {
        $generator = new AddTranslationsMigrationGenerator();
        $entityType = $this->makeEntityTypeStub('article');

        $this->expectException(\InvalidArgumentException::class);
        $generator->generate($entityType, '', 'sql-blob', []);
    }

    #[Test]
    public function renderRejectsLeadingWhitespaceLangcode(): void
    {
        $generator = new AddTranslationsMigrationGenerator();
        $entityType = $this->makeEntityTypeStub('article');

        $this->expectException(\InvalidArgumentException::class);
        $generator->render($entityType, ' en', 'sql-blob', []);
    }

    #[Test]
    public function renderRejectsTrailingWhitespaceLangcode(): void
    {
        $generator = new AddTranslationsMigrationGenerator();
        $entityType = $this->makeEntityTypeStub('article');

        $this->expectException(\InvalidArgumentException::class);
        $generator->render($entityType, 'en ', 'sql-blob', []);
    }

    /** @return array<string, array{string}> */
    public static function validLangcodes(): array
    {
        return [
            'simple'          => ['en'],
            'language+region' => ['en-US'],
            'script subtag'   => ['zh-Hant'],
            'script+region'   => ['zh-Hant-TW'],
            'fr-CA'           => ['fr-CA'],
        ];
    }

    #[Test]
    #[DataProvider('validLangcodes')]
    public function renderAcceptsValidBcp47Langcodes(string $langcode): void
    {
        $generator = new AddTranslationsMigrationGenerator();
        $entityType = $this->makeEntityTypeStub('article');

        // Should not throw — just assert the returned string is non-empty PHP.
        $result = $generator->render($entityType, $langcode, 'sql-blob', []);
        $this->assertStringContainsString('<?php', $result);
        $this->assertStringContainsString($langcode, $result);
    }

    private function makeEntityTypeStub(string $id): EntityTypeInterface
    {
        return new class ($id) implements EntityTypeInterface {
            public function __construct(private string $entityId) {}

            public function id(): string
            {
                return $this->entityId;
            }

            public function getLabel(): string
            {
                return $this->entityId;
            }

            public function getClass(): string
            {
                return 'stdClass';
            }

            /** @return class-string<\Waaseyaa\Entity\Storage\EntityStorageInterface> */
            public function getStorageClass(): string
            {
                return \Waaseyaa\Entity\Storage\EntityStorageInterface::class;
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
                return false;
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

            /** @return array<string, FieldDefinitionInterface> */
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

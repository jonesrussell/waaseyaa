<?php

declare(strict_types=1);

namespace Waaseyaa\StructuredImport\Tests\Unit\Gfm;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Entity\Field\FieldDefinitionRegistryInterface;
use Waaseyaa\Field\FieldDefinition;
use Waaseyaa\Field\FieldStorage;
use Waaseyaa\StructuredImport\Gfm\GfmTableImporter;
use Waaseyaa\StructuredImport\Gfm\GfmTableParser;
use Waaseyaa\StructuredImport\Gfm\PromptNormalizer;
use Waaseyaa\StructuredImport\ImportResult;

#[CoversClass(GfmTableImporter::class)]
#[CoversClass(PromptNormalizer::class)]
final class GfmTableImporterTest extends TestCase
{
    private GfmTableParser $parser;
    private PromptNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->parser = new GfmTableParser();
        $this->normalizer = new PromptNormalizer();
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Build a stub FieldDefinitionRegistryInterface returning given fields for any bundle.
     *
     * @param array<string, FieldDefinition> $fields
     */
    private function stubRegistry(array $fields): FieldDefinitionRegistryInterface
    {
        return new class ($fields) implements FieldDefinitionRegistryInterface {
            /** @param array<string, FieldDefinition> $fields */
            public function __construct(private readonly array $fields) {}

            public function bundleFieldsFor(string $entityTypeId, string $bundle): array
            {
                return $this->fields;
            }

            public function registerCoreFields(string $entityTypeId, array $fields): void {}

            public function mergeCoreFields(string $entityTypeId, array $fields): void {}

            public function registerBundleFields(string $entityTypeId, string $bundle, array $fields): void {}

            public function coreFieldsFor(string $entityTypeId): array
            {
                return [];
            }

            public function bundleNamesFor(string $entityTypeId): array
            {
                return [];
            }

            public function bundlesDefiningField(string $entityTypeId, string $fieldName): array
            {
                return [];
            }
        };
    }

    /**
     * Build a FieldDefinition with optional prompt aliases.
     *
     * @param list<string> $promptAliases
     */
    private function field(string $name, array $promptAliases = []): FieldDefinition
    {
        return new FieldDefinition(
            name: $name,
            type: 'string',
            targetEntityTypeId: 'article',
            stored: FieldStorage::Column,
            promptAliases: $promptAliases,
        );
    }

    private function makeImporter(array $fields): GfmTableImporter
    {
        return new GfmTableImporter(
            registry: $this->stubRegistry($fields),
            parser: $this->parser,
            normalizer: $this->normalizer,
        );
    }

    private function gfmTable(array $rows): string
    {
        $lines = ['| Prompt | Value |', '| --- | --- |'];
        foreach ($rows as [$prompt, $value]) {
            $lines[] = "| {$prompt} | {$value} |";
        }

        return implode("\n", $lines);
    }

    // -------------------------------------------------------------------------
    // T1: Happy path — alias matches produce matched entries
    // -------------------------------------------------------------------------

    #[Test]
    public function happyPathAliasMatchesProduceMatched(): void
    {
        $importer = $this->makeImporter([
            'title' => $this->field('title', ['Title', 'Heading']),
            'body'  => $this->field('body', ['Body', 'Content']),
        ]);

        $payload = $this->gfmTable([
            ['Title', 'Hello World'],
            ['Content', 'Some body text'],
        ]);

        $result = $importer->import($payload, 'article');

        self::assertInstanceOf(ImportResult::class, $result);
        self::assertSame(['title' => 'Hello World', 'body' => 'Some body text'], $result->matched);
        self::assertSame([], $result->unmatched);
        self::assertSame([], $result->errors);
    }

    // -------------------------------------------------------------------------
    // T2: Implicit alias from field name (no aliases declared)
    // -------------------------------------------------------------------------

    #[Test]
    public function implicitAliasFromFieldName(): void
    {
        $importer = $this->makeImporter([
            'title' => $this->field('title'),   // no explicit aliases
            'body'  => $this->field('body'),
        ]);

        $payload = $this->gfmTable([
            ['title', 'My Title'],
            ['BODY', 'My Body'],
        ]);

        $result = $importer->import($payload, 'article');

        self::assertSame(['title' => 'My Title', 'body' => 'My Body'], $result->matched);
        self::assertSame([], $result->unmatched);
        self::assertSame([], $result->errors);
    }

    // -------------------------------------------------------------------------
    // T3: Unknown prompt lands in unmatched
    // -------------------------------------------------------------------------

    #[Test]
    public function unknownPromptLandsInUnmatched(): void
    {
        $importer = $this->makeImporter([
            'title' => $this->field('title'),
        ]);

        $payload = $this->gfmTable([
            ['title', 'My Title'],
            ['unknown_field', 'orphan value'],
        ]);

        $result = $importer->import($payload, 'article');

        self::assertSame(['title' => 'My Title'], $result->matched);
        self::assertCount(1, $result->unmatched);
        self::assertSame('unknown_field', $result->unmatched[0]->prompt);
        self::assertSame('orphan value', $result->unmatched[0]->value);
    }

    // -------------------------------------------------------------------------
    // T4: Mixed — 4 matched + 1 unmatched
    // -------------------------------------------------------------------------

    #[Test]
    public function mixedFourMatchedOneUnmatched(): void
    {
        $importer = $this->makeImporter([
            'title'    => $this->field('title', ['Title']),
            'body'     => $this->field('body', ['Body']),
            'summary'  => $this->field('summary', ['Summary']),
            'published' => $this->field('published', ['Published']),
        ]);

        $payload = $this->gfmTable([
            ['Title', 'Hello'],
            ['Body', 'Content here'],
            ['Nope', 'orphan'],
            ['Summary', 'Short desc'],
            ['Published', 'true'],
        ]);

        $result = $importer->import($payload, 'article');

        self::assertCount(4, $result->matched);
        self::assertCount(1, $result->unmatched);
        self::assertSame('Nope', $result->unmatched[0]->prompt);
        self::assertSame('orphan', $result->unmatched[0]->value);
    }

    // -------------------------------------------------------------------------
    // T5: Empty document — errors from parser propagate
    // -------------------------------------------------------------------------

    #[Test]
    public function emptyDocumentReturnsParserErrors(): void
    {
        $importer = $this->makeImporter([
            'title' => $this->field('title'),
        ]);

        $result = $importer->import('', 'article');

        self::assertSame([], $result->matched);
        self::assertSame([], $result->unmatched);
        self::assertSame(['No table found'], $result->errors);
    }

    // -------------------------------------------------------------------------
    // T6: Bundle defaults to entityTypeId when null (FR-013)
    // -------------------------------------------------------------------------

    #[Test]
    public function bundleDefaultsToEntityTypeIdWhenNull(): void
    {
        $capturedArgs = [];

        $registry = new class ($capturedArgs) implements FieldDefinitionRegistryInterface {
            /** @param array<int, array{entityTypeId: string, bundle: string}> &$captured */
            public function __construct(private array &$captured) {}

            public function bundleFieldsFor(string $entityTypeId, string $bundle): array
            {
                $this->captured[] = ['entityTypeId' => $entityTypeId, 'bundle' => $bundle];

                return [];
            }

            public function registerCoreFields(string $entityTypeId, array $fields): void {}

            public function mergeCoreFields(string $entityTypeId, array $fields): void {}

            public function registerBundleFields(string $entityTypeId, string $bundle, array $fields): void {}

            public function coreFieldsFor(string $entityTypeId): array
            {
                return [];
            }

            public function bundleNamesFor(string $entityTypeId): array
            {
                return [];
            }

            public function bundlesDefiningField(string $entityTypeId, string $fieldName): array
            {
                return [];
            }
        };

        $importer = new GfmTableImporter(
            registry: $registry,
            parser: $this->parser,
            normalizer: $this->normalizer,
        );

        $importer->import('', 'article', null);

        self::assertCount(1, $capturedArgs);
        self::assertSame('article', $capturedArgs[0]['entityTypeId']);
        self::assertSame('article', $capturedArgs[0]['bundle']);
    }

    // -------------------------------------------------------------------------
    // T7: Diacritic preservation — 'café' matches 'Café'; 'cafe' does NOT
    // -------------------------------------------------------------------------

    #[Test]
    public function diacriticPreservationCafeMatchesCafe(): void
    {
        $importer = $this->makeImporter([
            'location' => $this->field('location', ['café']),
        ]);

        $payload = $this->gfmTable([
            ['Café', 'Paris'],
        ]);

        $result = $importer->import($payload, 'article');

        // 'Café' lowercases to 'café' which matches alias 'café'
        self::assertSame(['location' => 'Paris'], $result->matched);
        self::assertSame([], $result->unmatched);
    }

    #[Test]
    public function diacriticPreservationCafeWithoutAccentDoesNotMatchCafeWithAccent(): void
    {
        $importer = $this->makeImporter([
            'location' => $this->field('location', ['cafe']),   // no accent
        ]);

        $payload = $this->gfmTable([
            ['Café', 'Paris'],   // with accent
        ]);

        $result = $importer->import($payload, 'article');

        // No transliteration: 'café' != 'cafe'
        self::assertSame([], $result->matched);
        self::assertCount(1, $result->unmatched);
        // Original prompt text is preserved (un-normalized)
        self::assertSame('Café', $result->unmatched[0]->prompt);
    }

    // -------------------------------------------------------------------------
    // T8: Whitespace tolerance
    // -------------------------------------------------------------------------

    #[Test]
    public function whitespaceTolerance(): void
    {
        $importer = $this->makeImporter([
            'title' => $this->field('title', ['Article Title']),
        ]);

        // Extra internal whitespace in prompt; GfmTableParser trims cells,
        // but PromptNormalizer collapses internal runs.
        $payload = "| Prompt | Value |\n| --- | --- |\n|  Article   Title  | Hello |";

        $result = $importer->import($payload, 'article');

        self::assertSame(['title' => 'Hello'], $result->matched);
        self::assertSame([], $result->unmatched);
    }

    // -------------------------------------------------------------------------
    // T9: Errors from parser propagate verbatim
    // -------------------------------------------------------------------------

    #[Test]
    public function parserErrorsPropagateVerbatim(): void
    {
        $importer = $this->makeImporter([
            'title' => $this->field('title'),
        ]);

        // 3-column table — parser adds an error and then "No table found".
        $payload = "| A | B | C |\n| --- | --- | --- |\n| 1 | 2 | 3 |";

        $result = $importer->import($payload, 'article');

        self::assertSame([], $result->matched);
        self::assertSame([], $result->unmatched);
        self::assertNotEmpty($result->errors);
        self::assertStringContainsString('expected 2 columns, got 3', implode(' ', $result->errors));
    }

    // -------------------------------------------------------------------------
    // T10: Unmatched rows preserve original (un-normalized) text
    // -------------------------------------------------------------------------

    #[Test]
    public function unmatchedRowsPreserveOriginalText(): void
    {
        $importer = $this->makeImporter([]);

        $payload = $this->gfmTable([
            ['My  FIELD', 'Some Value'],
        ]);

        $result = $importer->import($payload, 'article');

        self::assertCount(1, $result->unmatched);
        // Parser trims cells but does not normalize; original capitalization/spacing preserved
        self::assertSame('My  FIELD', $result->unmatched[0]->prompt);
        self::assertSame('Some Value', $result->unmatched[0]->value);
    }

    // -------------------------------------------------------------------------
    // T11: Explicit bundle is passed through to registry
    // -------------------------------------------------------------------------

    #[Test]
    public function explicitBundleIsPassedToRegistry(): void
    {
        $capturedArgs = [];

        $registry = new class ($capturedArgs) implements FieldDefinitionRegistryInterface {
            /** @param array<int, array{entityTypeId: string, bundle: string}> &$captured */
            public function __construct(private array &$captured) {}

            public function bundleFieldsFor(string $entityTypeId, string $bundle): array
            {
                $this->captured[] = ['entityTypeId' => $entityTypeId, 'bundle' => $bundle];

                return [];
            }

            public function registerCoreFields(string $entityTypeId, array $fields): void {}

            public function mergeCoreFields(string $entityTypeId, array $fields): void {}

            public function registerBundleFields(string $entityTypeId, string $bundle, array $fields): void {}

            public function coreFieldsFor(string $entityTypeId): array
            {
                return [];
            }

            public function bundleNamesFor(string $entityTypeId): array
            {
                return [];
            }

            public function bundlesDefiningField(string $entityTypeId, string $fieldName): array
            {
                return [];
            }
        };

        $importer = new GfmTableImporter(
            registry: $registry,
            parser: $this->parser,
            normalizer: $this->normalizer,
        );

        $importer->import('', 'node', 'page');

        self::assertSame('node', $capturedArgs[0]['entityTypeId']);
        self::assertSame('page', $capturedArgs[0]['bundle']);
    }
}

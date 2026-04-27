<?php

declare(strict_types=1);

namespace Waaseyaa\StructuredImport\Tests\Contract;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Entity\Field\FieldDefinitionRegistryInterface;
use Waaseyaa\StructuredImport\Gfm\GfmTableImporter;
use Waaseyaa\StructuredImport\Gfm\GfmTableParser;
use Waaseyaa\StructuredImport\Gfm\PromptNormalizer;
use Waaseyaa\StructuredImport\ImportResult;
use Waaseyaa\StructuredImport\StructuredImporterInterface;

/**
 * Abstract contract tests for StructuredImporterInterface implementations.
 *
 * Concrete subclasses must implement createImporter() to provide a configured
 * StructuredImporterInterface instance. All tests here verify the public
 * contract only — no coverage annotations so subclasses choose their own.
 */
#[CoversNothing]
abstract class StructuredImporterContractTest extends TestCase
{
    abstract protected function createImporter(): StructuredImporterInterface;

    // -------------------------------------------------------------------------
    // Contract: empty payload never throws, always returns ImportResult
    // -------------------------------------------------------------------------

    #[Test]
    public function emptyPayloadReturnsImportResultWithoutThrowing(): void
    {
        $importer = $this->createImporter();

        $result = $importer->import('', 'article');

        self::assertInstanceOf(ImportResult::class, $result);
    }

    // -------------------------------------------------------------------------
    // Contract: import() always returns ImportResult
    // -------------------------------------------------------------------------

    #[Test]
    public function importAlwaysReturnsImportResult(): void
    {
        $importer = $this->createImporter();

        $payload = "| Field | Value |\n| --- | --- |\n| title | Hello |";
        $result = $importer->import($payload, 'article');

        self::assertInstanceOf(ImportResult::class, $result);
    }

    // -------------------------------------------------------------------------
    // Contract: stateless across calls — two calls with identical input must
    // produce identical output (no state leakage between invocations)
    // -------------------------------------------------------------------------

    #[Test]
    public function statelessAcrossCalls(): void
    {
        $importer = $this->createImporter();

        $payload = "| Field | Value |\n| --- | --- |\n| title | Hello |";

        $result1 = $importer->import($payload, 'article');
        $result2 = $importer->import($payload, 'article');

        self::assertSame($result1->matched, $result2->matched);
        self::assertSame($result1->errors, $result2->errors);
        self::assertCount(count($result1->unmatched), $result2->unmatched);
    }

    // -------------------------------------------------------------------------
    // Contract: null bundle does not throw
    // -------------------------------------------------------------------------

    #[Test]
    public function nullBundleDoesNotThrow(): void
    {
        $importer = $this->createImporter();

        $result = $importer->import('', 'article', null);

        self::assertInstanceOf(ImportResult::class, $result);
    }
}

/**
 * Concrete contract test for GfmTableImporter.
 */
#[CoversNothing]
final class GfmTableImporterContractTest extends StructuredImporterContractTest
{
    protected function createImporter(): StructuredImporterInterface
    {
        $registry = new class implements FieldDefinitionRegistryInterface {
            public function bundleFieldsFor(string $entityTypeId, string $bundle): array
            {
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

        return new GfmTableImporter(
            registry: $registry,
            parser: new GfmTableParser(),
            normalizer: new PromptNormalizer(),
        );
    }
}

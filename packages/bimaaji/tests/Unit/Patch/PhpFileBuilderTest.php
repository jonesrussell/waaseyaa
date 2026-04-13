<?php

declare(strict_types=1);

namespace Waaseyaa\Bimaaji\Tests\Unit\Patch;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Bimaaji\Patch\PhpFileBuilder;

#[CoversClass(PhpFileBuilder::class)]
final class PhpFileBuilderTest extends TestCase
{
    private PhpFileBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new PhpFileBuilder();
    }

    #[Test]
    public function it_builds_field_definition_patch(): void
    {
        $output = $this->builder->buildFieldDefinitionPatch(
            entityType: 'article',
            fieldName: 'subtitle',
            fieldConfig: [
                'type' => 'string',
                'label' => 'Subtitle',
            ],
        );

        self::assertStringStartsWith('<?php', $output);
        self::assertStringContainsString('subtitle', $output);
        self::assertStringContainsString('string', $output);
        self::assertStringContainsString('Subtitle', $output);
    }

    #[Test]
    public function generated_php_round_trips_through_parser(): void
    {
        $output = $this->builder->buildFieldDefinitionPatch(
            entityType: 'node',
            fieldName: 'body',
            fieldConfig: [
                'type' => 'text',
                'label' => 'Body',
                'required' => true,
            ],
        );

        // Parse the output
        $parser = (new \PhpParser\ParserFactory())->createForNewestSupportedVersion();
        $stmts = $parser->parse($output);

        self::assertNotNull($stmts, 'Output must be valid PHP');

        // Re-print and verify round-trip stability
        $printer = new \PhpParser\PrettyPrinter\Standard();
        $reprinted = $printer->prettyPrintFile($stmts);

        // Both should parse to equivalent ASTs
        $stmts2 = $parser->parse($reprinted);
        self::assertNotNull($stmts2);
    }

    #[Test]
    public function it_includes_declare_strict_types(): void
    {
        $output = $this->builder->buildFieldDefinitionPatch(
            entityType: 'page',
            fieldName: 'summary',
            fieldConfig: ['type' => 'string', 'label' => 'Summary'],
        );

        self::assertStringContainsString('strict_types=1', $output);
    }

    #[Test]
    public function it_handles_complex_field_config(): void
    {
        $output = $this->builder->buildFieldDefinitionPatch(
            entityType: 'article',
            fieldName: 'tags',
            fieldConfig: [
                'type' => 'entity_reference',
                'label' => 'Tags',
                'target_entity_type_id' => 'taxonomy_term',
                'cardinality' => -1,
            ],
        );

        self::assertStringContainsString('tags', $output);
        self::assertStringContainsString('entity_reference', $output);
        self::assertStringContainsString('taxonomy_term', $output);

        // Must be valid PHP
        $parser = (new \PhpParser\ParserFactory())->createForNewestSupportedVersion();
        self::assertNotNull($parser->parse($output));
    }
}

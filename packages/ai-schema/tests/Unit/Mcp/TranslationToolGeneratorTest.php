<?php

declare(strict_types=1);

namespace Aurora\AI\Schema\Tests\Unit\Mcp;

use Aurora\AI\Schema\Mcp\McpToolDefinition;
use Aurora\AI\Schema\Mcp\TranslationToolGenerator;
use Aurora\Entity\EntityType;
use Aurora\Entity\EntityTypeManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(TranslationToolGenerator::class)]
final class TranslationToolGeneratorTest extends TestCase
{
    private EntityTypeManagerInterface $entityTypeManager;

    protected function setUp(): void
    {
        $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    }

    #[Test]
    public function generateForEntityTypeProducesFourTools(): void
    {
        $entityType = new EntityType(
            id: 'node',
            label: 'Content',
            class: \stdClass::class,
        );

        $this->entityTypeManager
            ->method('getDefinition')
            ->with('node')
            ->willReturn($entityType);

        $generator = new TranslationToolGenerator($this->entityTypeManager);
        $tools = $generator->generateForEntityType('node');

        $this->assertCount(4, $tools);
        $this->assertContainsOnlyInstancesOf(McpToolDefinition::class, $tools);
    }

    #[Test]
    public function toolNamesFollowTranslationConvention(): void
    {
        $entityType = new EntityType(
            id: 'node',
            label: 'Content',
            class: \stdClass::class,
        );

        $this->entityTypeManager
            ->method('getDefinition')
            ->with('node')
            ->willReturn($entityType);

        $generator = new TranslationToolGenerator($this->entityTypeManager);
        $tools = $generator->generateForEntityType('node');

        $names = \array_map(fn(McpToolDefinition $t) => $t->name, $tools);

        $this->assertContains('node_translations_list', $names);
        $this->assertContains('node_translation_create', $names);
        $this->assertContains('node_translation_update', $names);
        $this->assertContains('node_translation_delete', $names);
    }

    #[Test]
    public function listTranslationsToolHasCorrectSchema(): void
    {
        $entityType = new EntityType(
            id: 'node',
            label: 'Content',
            class: \stdClass::class,
        );

        $this->entityTypeManager
            ->method('getDefinition')
            ->with('node')
            ->willReturn($entityType);

        $generator = new TranslationToolGenerator($this->entityTypeManager);
        $tools = $generator->generateForEntityType('node');

        $listTool = $this->findToolByName($tools, 'node_translations_list');
        $this->assertNotNull($listTool);

        $schema = $listTool->inputSchema;
        $this->assertSame('object', $schema['type']);
        $this->assertArrayHasKey('id', $schema['properties']);
        $this->assertSame(['id'], $schema['required']);
        $this->assertFalse($schema['additionalProperties']);
    }

    #[Test]
    public function createTranslationToolHasLangcodeAndAttributes(): void
    {
        $entityType = new EntityType(
            id: 'node',
            label: 'Content',
            class: \stdClass::class,
        );

        $this->entityTypeManager
            ->method('getDefinition')
            ->with('node')
            ->willReturn($entityType);

        $generator = new TranslationToolGenerator($this->entityTypeManager);
        $tools = $generator->generateForEntityType('node');

        $createTool = $this->findToolByName($tools, 'node_translation_create');
        $this->assertNotNull($createTool);

        $schema = $createTool->inputSchema;
        $this->assertSame('object', $schema['type']);
        $this->assertArrayHasKey('id', $schema['properties']);
        $this->assertArrayHasKey('langcode', $schema['properties']);
        $this->assertArrayHasKey('attributes', $schema['properties']);
        $this->assertSame('string', $schema['properties']['langcode']['type']);
        $this->assertContains('id', $schema['required']);
        $this->assertContains('langcode', $schema['required']);
        $this->assertContains('attributes', $schema['required']);
    }

    #[Test]
    public function updateTranslationToolHasLangcodeAndAttributes(): void
    {
        $entityType = new EntityType(
            id: 'node',
            label: 'Content',
            class: \stdClass::class,
        );

        $this->entityTypeManager
            ->method('getDefinition')
            ->with('node')
            ->willReturn($entityType);

        $generator = new TranslationToolGenerator($this->entityTypeManager);
        $tools = $generator->generateForEntityType('node');

        $updateTool = $this->findToolByName($tools, 'node_translation_update');
        $this->assertNotNull($updateTool);

        $schema = $updateTool->inputSchema;
        $this->assertSame('object', $schema['type']);
        $this->assertArrayHasKey('id', $schema['properties']);
        $this->assertArrayHasKey('langcode', $schema['properties']);
        $this->assertArrayHasKey('attributes', $schema['properties']);
        $this->assertContains('id', $schema['required']);
        $this->assertContains('langcode', $schema['required']);
        $this->assertContains('attributes', $schema['required']);
    }

    #[Test]
    public function deleteTranslationToolHasIdAndLangcode(): void
    {
        $entityType = new EntityType(
            id: 'node',
            label: 'Content',
            class: \stdClass::class,
        );

        $this->entityTypeManager
            ->method('getDefinition')
            ->with('node')
            ->willReturn($entityType);

        $generator = new TranslationToolGenerator($this->entityTypeManager);
        $tools = $generator->generateForEntityType('node');

        $deleteTool = $this->findToolByName($tools, 'node_translation_delete');
        $this->assertNotNull($deleteTool);

        $schema = $deleteTool->inputSchema;
        $this->assertSame('object', $schema['type']);
        $this->assertArrayHasKey('id', $schema['properties']);
        $this->assertArrayHasKey('langcode', $schema['properties']);
        $this->assertSame(['id', 'langcode'], $schema['required']);
        $this->assertFalse($schema['additionalProperties']);
    }

    #[Test]
    public function toolDescriptionsContainEntityLabel(): void
    {
        $entityType = new EntityType(
            id: 'node',
            label: 'Content',
            class: \stdClass::class,
        );

        $this->entityTypeManager
            ->method('getDefinition')
            ->with('node')
            ->willReturn($entityType);

        $generator = new TranslationToolGenerator($this->entityTypeManager);
        $tools = $generator->generateForEntityType('node');

        foreach ($tools as $tool) {
            $this->assertNotEmpty($tool->description);
            $this->assertStringContainsString('Content', $tool->description);
        }
    }

    #[Test]
    public function generateAllProducesToolsForAllEntityTypes(): void
    {
        $nodeType = new EntityType(
            id: 'node',
            label: 'Content',
            class: \stdClass::class,
        );

        $userType = new EntityType(
            id: 'user',
            label: 'User',
            class: \stdClass::class,
        );

        $this->entityTypeManager
            ->method('getDefinitions')
            ->willReturn(['node' => $nodeType, 'user' => $userType]);

        $this->entityTypeManager
            ->method('getDefinition')
            ->willReturnCallback(fn(string $id) => match ($id) {
                'node' => $nodeType,
                'user' => $userType,
            });

        $generator = new TranslationToolGenerator($this->entityTypeManager);
        $tools = $generator->generateAll();

        // 4 tools per entity type * 2 entity types = 8 tools.
        $this->assertCount(8, $tools);

        $names = \array_map(fn(McpToolDefinition $t) => $t->name, $tools);
        $this->assertContains('node_translations_list', $names);
        $this->assertContains('node_translation_create', $names);
        $this->assertContains('user_translations_list', $names);
        $this->assertContains('user_translation_delete', $names);
    }

    #[Test]
    public function toolNamesWorkWithUnderscoreEntityTypes(): void
    {
        $entityType = new EntityType(
            id: 'taxonomy_term',
            label: 'Taxonomy Term',
            class: \stdClass::class,
        );

        $this->entityTypeManager
            ->method('getDefinition')
            ->with('taxonomy_term')
            ->willReturn($entityType);

        $generator = new TranslationToolGenerator($this->entityTypeManager);
        $tools = $generator->generateForEntityType('taxonomy_term');

        $names = \array_map(fn(McpToolDefinition $t) => $t->name, $tools);

        $this->assertContains('taxonomy_term_translations_list', $names);
        $this->assertContains('taxonomy_term_translation_create', $names);
        $this->assertContains('taxonomy_term_translation_update', $names);
        $this->assertContains('taxonomy_term_translation_delete', $names);
    }

    #[Test]
    public function toolsCanBeSerializedToArray(): void
    {
        $entityType = new EntityType(
            id: 'node',
            label: 'Content',
            class: \stdClass::class,
        );

        $this->entityTypeManager
            ->method('getDefinition')
            ->with('node')
            ->willReturn($entityType);

        $generator = new TranslationToolGenerator($this->entityTypeManager);
        $tools = $generator->generateForEntityType('node');

        foreach ($tools as $tool) {
            $array = $tool->toArray();
            $this->assertArrayHasKey('name', $array);
            $this->assertArrayHasKey('description', $array);
            $this->assertArrayHasKey('inputSchema', $array);
        }
    }

    /**
     * @param McpToolDefinition[] $tools
     */
    private function findToolByName(array $tools, string $name): ?McpToolDefinition
    {
        foreach ($tools as $tool) {
            if ($tool->name === $name) {
                return $tool;
            }
        }

        return null;
    }
}

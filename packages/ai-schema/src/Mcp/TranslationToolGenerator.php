<?php

declare(strict_types=1);

namespace Aurora\AI\Schema\Mcp;

use Aurora\Entity\EntityTypeManagerInterface;

/**
 * Generates MCP tool definitions for translation CRUD operations.
 *
 * For each registered entity type, four translation tools are generated:
 * - {type}_translations_list: List available translations for an entity
 * - {type}_translation_create: Create a new translation for an entity
 * - {type}_translation_update: Update an existing translation
 * - {type}_translation_delete: Delete a translation for an entity
 */
final class TranslationToolGenerator
{
    public function __construct(
        private readonly EntityTypeManagerInterface $entityTypeManager,
    ) {}

    /**
     * Generate translation tools for a single entity type.
     *
     * @return McpToolDefinition[] Four tools: list, create, update, delete
     */
    public function generateForEntityType(string $entityTypeId): array
    {
        $entityType = $this->entityTypeManager->getDefinition($entityTypeId);
        $label = $entityType->getLabel();

        return [
            $this->listTranslationsTool($entityTypeId, $label),
            $this->createTranslationTool($entityTypeId, $label),
            $this->updateTranslationTool($entityTypeId, $label),
            $this->deleteTranslationTool($entityTypeId, $label),
        ];
    }

    /**
     * Generate translation tools for all registered entity types.
     *
     * @return McpToolDefinition[] All translation tools across all entity types
     */
    public function generateAll(): array
    {
        $tools = [];

        foreach ($this->entityTypeManager->getDefinitions() as $id => $definition) {
            $tools = \array_merge($tools, $this->generateForEntityType($id));
        }

        return $tools;
    }

    private function listTranslationsTool(string $entityTypeId, string $label): McpToolDefinition
    {
        return new McpToolDefinition(
            name: "{$entityTypeId}_translations_list",
            description: "List all available translations for a {$label} entity.",
            inputSchema: [
                'type' => 'object',
                'properties' => [
                    'id' => [
                        'type' => ['integer', 'string'],
                        'description' => "The ID of the {$label} to list translations for.",
                    ],
                ],
                'required' => ['id'],
                'additionalProperties' => false,
            ],
        );
    }

    private function createTranslationTool(string $entityTypeId, string $label): McpToolDefinition
    {
        return new McpToolDefinition(
            name: "{$entityTypeId}_translation_create",
            description: "Create a new translation for a {$label} entity.",
            inputSchema: [
                'type' => 'object',
                'properties' => [
                    'id' => [
                        'type' => ['integer', 'string'],
                        'description' => "The ID of the {$label} to translate.",
                    ],
                    'langcode' => [
                        'type' => 'string',
                        'description' => 'The target language code for the translation (e.g. "fr", "de", "es").',
                    ],
                    'attributes' => [
                        'type' => 'object',
                        'description' => "The translated attribute values for the {$label}.",
                        'additionalProperties' => true,
                    ],
                ],
                'required' => ['id', 'langcode', 'attributes'],
                'additionalProperties' => false,
            ],
        );
    }

    private function updateTranslationTool(string $entityTypeId, string $label): McpToolDefinition
    {
        return new McpToolDefinition(
            name: "{$entityTypeId}_translation_update",
            description: "Update an existing translation of a {$label} entity.",
            inputSchema: [
                'type' => 'object',
                'properties' => [
                    'id' => [
                        'type' => ['integer', 'string'],
                        'description' => "The ID of the {$label} whose translation to update.",
                    ],
                    'langcode' => [
                        'type' => 'string',
                        'description' => 'The language code of the translation to update (e.g. "fr", "de", "es").',
                    ],
                    'attributes' => [
                        'type' => 'object',
                        'description' => "The translated attribute values to update on the {$label}.",
                        'additionalProperties' => true,
                    ],
                ],
                'required' => ['id', 'langcode', 'attributes'],
                'additionalProperties' => false,
            ],
        );
    }

    private function deleteTranslationTool(string $entityTypeId, string $label): McpToolDefinition
    {
        return new McpToolDefinition(
            name: "{$entityTypeId}_translation_delete",
            description: "Delete a translation of a {$label} entity.",
            inputSchema: [
                'type' => 'object',
                'properties' => [
                    'id' => [
                        'type' => ['integer', 'string'],
                        'description' => "The ID of the {$label} whose translation to delete.",
                    ],
                    'langcode' => [
                        'type' => 'string',
                        'description' => 'The language code of the translation to delete (e.g. "fr", "de", "es").',
                    ],
                ],
                'required' => ['id', 'langcode'],
                'additionalProperties' => false,
            ],
        );
    }
}

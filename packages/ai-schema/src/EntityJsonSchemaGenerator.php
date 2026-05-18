<?php

declare(strict_types=1);

namespace Waaseyaa\AI\Schema;

use Waaseyaa\Entity\EntityTypeManagerInterface;

/**
 * Generates JSON Schema (draft 2020-12) for entity types.
 *
 * Inspects entity type definitions and produces a standards-compliant
 * JSON Schema describing the shape of each entity type's data.
 *
 * @api
 */
final class EntityJsonSchemaGenerator
{
    public function __construct(
        private readonly EntityTypeManagerInterface $entityTypeManager,
    ) {}

    /**
     * Generate JSON Schema for a single entity type.
     *
     * @return array<string, mixed> JSON Schema array
     */
    public function generate(string $entityTypeId): array
    {
        $entityType = $this->entityTypeManager->getDefinition($entityTypeId);
        $keys = $entityType->getKeys();
        $label = $entityType->getLabel();

        $properties = [];
        $required = [];

        // Map entity keys to JSON Schema properties.
        if (isset($keys['id'])) {
            $properties[$keys['id']] = [
                'type' => ['integer', 'string'],
                'description' => 'The primary identifier.',
            ];
            $required[] = $keys['id'];
        }

        if (isset($keys['uuid'])) {
            $properties[$keys['uuid']] = [
                'type' => 'string',
                'format' => 'uuid',
                'description' => 'The universally unique identifier.',
            ];
            $required[] = $keys['uuid'];
        }

        if (isset($keys['label'])) {
            $properties[$keys['label']] = [
                'type' => 'string',
                'description' => 'The human-readable label.',
            ];
            $required[] = $keys['label'];
        }

        if (isset($keys['bundle'])) {
            $properties[$keys['bundle']] = [
                'type' => 'string',
                'description' => 'The bundle (sub-type).',
            ];
            $required[] = $keys['bundle'];
        }

        if (isset($keys['langcode'])) {
            $properties[$keys['langcode']] = [
                'type' => 'string',
                'description' => 'The language code.',
            ];
        }

        if (isset($keys['revision']) && $entityType->isRevisionable()) {
            $properties[$keys['revision']] = [
                'type' => 'integer',
                'description' => 'The revision identifier.',
            ];
        }

        return [
            '$schema' => 'https://json-schema.org/draft/2020-12/schema',
            'title' => $label,
            'description' => "Schema for {$label} entities",
            'type' => 'object',
            'properties' => $properties,
            'required' => $required,
            'additionalProperties' => true,
        ];
    }

    /**
     * Generate JSON Schema for all registered entity types.
     *
     * @return array<string, array<string, mixed>> Keyed by entity type ID
     */
    public function generateAll(): array
    {
        $schemas = [];

        foreach ($this->entityTypeManager->getDefinitions() as $id => $definition) {
            $schemas[$id] = $this->generate($id);
        }

        return $schemas;
    }
}

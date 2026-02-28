<?php

declare(strict_types=1);

namespace Aurora\Api\OpenApi;

use Aurora\Entity\EntityTypeInterface;

/**
 * Builds JSON Schema fragments for entity types.
 *
 * Generates OpenAPI 3.1-compatible JSON Schema objects for entity type
 * attributes, resources, and request bodies. Since field type introspection
 * is not yet available, attribute schemas use an open object schema
 * with additionalProperties: true.
 */
final class SchemaBuilder
{
    /**
     * Build a JSON Schema for an entity type's attributes.
     *
     * Entity keys (id, uuid, label, bundle, revision, langcode) are system
     * fields and are excluded from the attributes schema. Since we don't
     * have field introspection yet, this generates a generic object schema
     * with additionalProperties: true.
     *
     * @return array<string, mixed>
     */
    public function buildAttributesSchema(EntityTypeInterface $entityType): array
    {
        return [
            'type' => 'object',
            'description' => \sprintf('Attributes for %s entities.', $entityType->getLabel()),
            'additionalProperties' => true,
        ];
    }

    /**
     * Build a JSON:API resource schema for an entity type.
     *
     * The resource schema follows the JSON:API specification and includes
     * type, id, attributes, and links members.
     *
     * @return array<string, mixed>
     */
    public function buildResourceSchema(EntityTypeInterface $entityType): array
    {
        $schemaName = $this->toSchemaName($entityType->id());

        return [
            'type' => 'object',
            'description' => \sprintf('A JSON:API resource representing a %s entity.', $entityType->getLabel()),
            'required' => ['type', 'id'],
            'properties' => [
                'type' => [
                    'type' => 'string',
                    'example' => $entityType->id(),
                ],
                'id' => [
                    'type' => 'string',
                    'description' => \sprintf('The unique identifier of the %s.', $entityType->getLabel()),
                ],
                'attributes' => [
                    '$ref' => '#/components/schemas/' . $schemaName . 'Attributes',
                ],
                'links' => [
                    'type' => 'object',
                    'properties' => [
                        'self' => [
                            'type' => 'string',
                            'format' => 'uri',
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Build a request body schema for creating an entity.
     *
     * The create request wraps the resource data in a JSON:API document
     * structure with data.type and data.attributes. The id is NOT required
     * for creation as it will be generated server-side.
     *
     * @return array<string, mixed>
     */
    public function buildCreateRequestSchema(EntityTypeInterface $entityType): array
    {
        $schemaName = $this->toSchemaName($entityType->id());

        return [
            'type' => 'object',
            'description' => \sprintf('Request body for creating a %s entity.', $entityType->getLabel()),
            'required' => ['data'],
            'properties' => [
                'data' => [
                    'type' => 'object',
                    'required' => ['type', 'attributes'],
                    'properties' => [
                        'type' => [
                            'type' => 'string',
                            'example' => $entityType->id(),
                        ],
                        'attributes' => [
                            '$ref' => '#/components/schemas/' . $schemaName . 'Attributes',
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Build a request body schema for updating an entity.
     *
     * The update request wraps the resource data in a JSON:API document
     * structure with data.type, data.id, and data.attributes. The id IS
     * required for update operations.
     *
     * @return array<string, mixed>
     */
    public function buildUpdateRequestSchema(EntityTypeInterface $entityType): array
    {
        $schemaName = $this->toSchemaName($entityType->id());

        return [
            'type' => 'object',
            'description' => \sprintf('Request body for updating a %s entity.', $entityType->getLabel()),
            'required' => ['data'],
            'properties' => [
                'data' => [
                    'type' => 'object',
                    'required' => ['type', 'id', 'attributes'],
                    'properties' => [
                        'type' => [
                            'type' => 'string',
                            'example' => $entityType->id(),
                        ],
                        'id' => [
                            'type' => 'string',
                            'description' => \sprintf('The unique identifier of the %s to update.', $entityType->getLabel()),
                        ],
                        'attributes' => [
                            '$ref' => '#/components/schemas/' . $schemaName . 'Attributes',
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Convert an entity type ID to a PascalCase schema name.
     *
     * Examples: 'node' -> 'Node', 'user_role' -> 'UserRole', 'article' -> 'Article'
     */
    public function toSchemaName(string $entityTypeId): string
    {
        return str_replace(' ', '', ucwords(str_replace('_', ' ', $entityTypeId)));
    }
}

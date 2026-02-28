<?php

declare(strict_types=1);

namespace Aurora\Api;

use Aurora\Entity\EntityInterface;
use Aurora\Entity\EntityTypeManagerInterface;

/**
 * Converts EntityInterface objects to JsonApiResource value objects.
 *
 * Maps entity fields to JSON:API attributes, excluding entity keys
 * (id, uuid) which become the resource's top-level id/type.
 */
final class ResourceSerializer
{
    public function __construct(
        private readonly EntityTypeManagerInterface $entityTypeManager,
        private readonly string $basePath = '/api',
    ) {}

    /**
     * Serialize a single entity to a JsonApiResource.
     */
    public function serialize(EntityInterface $entity): JsonApiResource
    {
        $entityTypeId = $entity->getEntityTypeId();
        $entityType = $this->entityTypeManager->getDefinition($entityTypeId);
        $keys = $entityType->getKeys();

        // Use UUID as the resource ID if available, otherwise fall back to entity ID.
        $resourceId = $entity->uuid() !== '' ? $entity->uuid() : (string) $entity->id();

        // Build attributes from entity values, excluding entity keys (id, uuid).
        $allValues = $entity->toArray();
        $excludedFields = $this->getExcludedFields($keys);
        $attributes = array_diff_key($allValues, array_flip($excludedFields));

        // Build self link.
        $selfLink = $this->basePath . '/' . $entityTypeId . '/' . $resourceId;

        return new JsonApiResource(
            type: $entityTypeId,
            id: $resourceId,
            attributes: $attributes,
            links: ['self' => $selfLink],
        );
    }

    /**
     * Serialize a collection of entities to an array of JsonApiResource objects.
     *
     * @param array<EntityInterface> $entities
     * @return array<JsonApiResource>
     */
    public function serializeCollection(array $entities): array
    {
        return array_values(array_map(
            fn(EntityInterface $entity): JsonApiResource => $this->serialize($entity),
            $entities,
        ));
    }

    /**
     * Get the list of field names to exclude from attributes.
     *
     * Entity keys like 'id' and 'uuid' are represented at the top level
     * of the JSON:API resource, not in attributes.
     *
     * @param array<string, string> $keys
     * @return array<string>
     */
    private function getExcludedFields(array $keys): array
    {
        $excluded = [];

        // Always exclude id and uuid keys — they become the resource's top-level id.
        if (isset($keys['id'])) {
            $excluded[] = $keys['id'];
        }
        if (isset($keys['uuid'])) {
            $excluded[] = $keys['uuid'];
        }

        return array_unique($excluded);
    }
}

<?php

declare(strict_types=1);

namespace Waaseyaa\Api;

use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Access\EntityAccessHandler;
use Waaseyaa\Entity\EntityInterface;
use Waaseyaa\Entity\EntityTypeManagerInterface;
use Waaseyaa\Entity\EntityValues;

/**
 * Converts EntityInterface objects to JsonApiResource value objects.
 *
 * Maps entity fields to JSON:API attributes, excluding entity keys
 * which become the resource's top-level id/type. Content entities use
 * UUID as the resource ID; config entities use their string machine name.
 *
 * Attribute values are read with {@see EntityInterface::get()} so entity
 * {@see \Waaseyaa\Entity\EntityBase::$casts} apply (#1181 ST-7); they are then
 * coerced to JSON-serializable scalars/arrays (enums, {@see \DateTimeInterface}, etc.).
 */
final class ResourceSerializer
{
    public function __construct(
        private readonly EntityTypeManagerInterface $entityTypeManager,
        private readonly string $basePath = '/api',
    ) {}

    /**
     * Serialize a single entity to a JsonApiResource.
     *
     * When an access handler and account are provided, fields that the account
     * cannot view are omitted from the attributes.
     */
    public function serialize(
        EntityInterface $entity,
        ?EntityAccessHandler $accessHandler = null,
        ?AccountInterface $account = null,
    ): JsonApiResource {
        $entityTypeId = $entity->getEntityTypeId();
        $entityType = $this->entityTypeManager->getDefinition($entityTypeId);
        $keys = $entityType->getKeys();

        // Content entities use UUID as resource ID; config entities use their string ID.
        $resourceId = $entity->uuid() !== '' ? $entity->uuid() : (string) $entity->id();

        $attributes = $this->attributesFromEntity($entity, $keys);

        // Filter out fields the account cannot view.
        if ($accessHandler !== null && $account !== null) {
            $allowedFields = $accessHandler->filterFields($entity, array_keys($attributes), 'view', $account);
            $attributes = array_intersect_key($attributes, array_flip($allowedFields));
        }

        $attributes = $this->castAttributes($attributes, $entityType->getFieldDefinitions());
        $attributes = $this->normalizeAttributesForJson($attributes);

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
    public function serializeCollection(
        array $entities,
        ?EntityAccessHandler $accessHandler = null,
        ?AccountInterface $account = null,
    ): array {
        return array_values(array_map(
            fn(EntityInterface $entity): JsonApiResource => $this->serialize($entity, $accessHandler, $account),
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

    /**
     * Build the attributes map using {@see EntityInterface::get()} per stored field name
     * so {@see \Waaseyaa\Entity\EntityBase::$casts} apply. Keys follow {@see EntityInterface::toArray()}.
     *
     * @param array<string, string> $keys
     *
     * @return array<string, mixed>
     */
    private function attributesFromEntity(EntityInterface $entity, array $keys): array
    {
        $excluded = array_flip($this->getExcludedFields($keys));
        $attributes = [];

        foreach (EntityValues::toCastAwareMap($entity) as $fieldName => $value) {
            if (isset($excluded[$fieldName])) {
                continue;
            }
            $attributes[$fieldName] = $value;
        }

        return $attributes;
    }

    /**
     * Cast attribute values based on field type definitions.
     *
     * @param array<string, mixed> $attributes
     * @param array<string, array<string, mixed>> $fieldDefinitions
     * @return array<string, mixed>
     */
    private function castAttributes(array $attributes, array $fieldDefinitions): array
    {
        foreach ($attributes as $name => $value) {
            $type = $fieldDefinitions[$name]['type'] ?? null;

            $attributes[$name] = match ($type) {
                'boolean' => (bool) $value,
                'timestamp', 'datetime' => $this->formatTimestamp($value),
                default => $value,
            };
        }

        return $attributes;
    }

    /**
     * Ensure attribute values are JSON-serializable (enums, dates, nested arrays).
     *
     * @param array<string, mixed> $attributes
     *
     * @return array<string, mixed>
     */
    private function normalizeAttributesForJson(array $attributes): array
    {
        foreach ($attributes as $name => $value) {
            $attributes[$name] = $this->normalizeValueForJson($value);
        }

        return $attributes;
    }

    private function normalizeValueForJson(mixed $value): mixed
    {
        if ($value instanceof \BackedEnum) {
            return $value->value;
        }

        if ($value instanceof \UnitEnum) {
            return $value->name;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format(\DateTimeInterface::ATOM);
        }

        if ($value instanceof \JsonSerializable) {
            return $this->normalizeValueForJson($value->jsonSerialize());
        }

        if (\is_array($value)) {
            $normalized = [];
            foreach ($value as $key => $item) {
                $normalized[$key] = $this->normalizeValueForJson($item);
            }

            return $normalized;
        }

        return $value;
    }

    /**
     * Convert a Unix timestamp to ISO 8601 string, or null if zero/empty.
     */
    private function formatTimestamp(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format(\DateTimeInterface::ATOM);
        }

        $ts = (int) $value;
        if ($ts === 0) {
            return null;
        }

        return (new \DateTimeImmutable('@' . $ts))->format(\DateTimeInterface::ATOM);
    }
}

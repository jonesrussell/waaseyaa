<?php

declare(strict_types=1);

namespace Waaseyaa\Testing\Factory;

use Waaseyaa\Entity\EntityTypeInterface;

/**
 * Test data generator for entity values.
 *
 * Allows defining default values for an entity type and then creating
 * individual or multiple entity value arrays with optional overrides.
 *
 * Usage:
 *
 *     $factory = new EntityFactory();
 *     $factory->define('article', [
 *         'title' => 'Default title',
 *         'status' => 1,
 *     ]);
 *
 *     $values = $factory->create('article', ['title' => 'Custom']);
 *     // => ['title' => 'Custom', 'status' => 1]
 *
 *     $many = $factory->createMany('article', 3, ['status' => 0]);
 *     // => array of 3 value arrays, each with status=0
 */
final class EntityFactory
{
    /**
     * Registered entity type defaults.
     *
     * @var array<string, array<string, mixed>>
     */
    private array $definitions = [];

    /**
     * Registered sequence callbacks per entity type and field.
     *
     * @var array<string, array<string, callable(int): mixed>>
     */
    private array $sequences = [];

    /**
     * Current sequence counter per entity type.
     *
     * @var array<string, int>
     */
    private array $counters = [];

    /**
     * Register default values for an entity type.
     *
     * @param string $entityTypeId The entity type machine name.
     * @param array<string, mixed> $defaults Default values for this entity type.
     */
    public function define(string $entityTypeId, array $defaults): self
    {
        $this->definitions[$entityTypeId] = $defaults;
        $this->counters[$entityTypeId] = 0;

        return $this;
    }

    /**
     * Register defaults from {@see EntityTypeFixtureValues} (field definitions + merged validation constraints).
     *
     * @param array<string, mixed> $overrides Merged into generated defaults before registering.
     * @param (callable(string, list<\Symfony\Component\Validator\Constraint>): mixed|null)|null $customResolver
     *   Passed to {@see EntityTypeFixtureValues}; return null to use built-in generation for that field.
     */
    public function defineFromEntityType(
        EntityTypeInterface $type,
        array $overrides = [],
        ?int $sequence = null,
        ?callable $customResolver = null,
    ): self {
        $generator = new EntityTypeFixtureValues(
            sequence: $sequence ?? 1,
            customResolver: $customResolver,
        );

        return $this->define($type->id(), $generator->values($type, $overrides));
    }

    /**
     * Register a sequence callback for a specific field.
     *
     * The callback receives an incrementing integer starting from 1.
     *
     * @param string $entityTypeId The entity type machine name.
     * @param string $field The field name.
     * @param callable(int): mixed $callback Callback that generates a value from a sequence number.
     */
    public function sequence(string $entityTypeId, string $field, callable $callback): self
    {
        $this->sequences[$entityTypeId][$field] = $callback;

        return $this;
    }

    /**
     * Create a single entity values array.
     *
     * @param string $entityTypeId The entity type to create values for.
     * @param array<string, mixed> $overrides Values to override the defaults.
     * @return array<string, mixed> The merged entity values.
     *
     * @throws \InvalidArgumentException When the entity type has not been defined.
     */
    public function create(string $entityTypeId, array $overrides = []): array
    {
        if (!isset($this->definitions[$entityTypeId])) {
            throw new \InvalidArgumentException(
                sprintf('Entity type "%s" has not been defined in the factory.', $entityTypeId),
            );
        }

        $this->counters[$entityTypeId]++;
        $counter = $this->counters[$entityTypeId];

        $values = $this->definitions[$entityTypeId];

        // Apply sequence callbacks for fields not overridden.
        if (isset($this->sequences[$entityTypeId])) {
            foreach ($this->sequences[$entityTypeId] as $field => $callback) {
                if (!array_key_exists($field, $overrides)) {
                    $values[$field] = $callback($counter);
                }
            }
        }

        return array_merge($values, $overrides);
    }

    /**
     * Create multiple entity value arrays.
     *
     * @param string $entityTypeId The entity type to create values for.
     * @param int $count Number of entities to create.
     * @param array<string, mixed> $overrides Values to override the defaults for all entities.
     * @return array<int, array<string, mixed>> List of entity value arrays.
     *
     * @throws \InvalidArgumentException When the entity type has not been defined.
     * @throws \InvalidArgumentException When count is less than 1.
     */
    public function createMany(string $entityTypeId, int $count, array $overrides = []): array
    {
        if ($count < 1) {
            throw new \InvalidArgumentException(
                sprintf('Count must be at least 1, got %d.', $count),
            );
        }

        $results = [];
        for ($i = 0; $i < $count; $i++) {
            $results[] = $this->create($entityTypeId, $overrides);
        }

        return $results;
    }

    /**
     * Check whether a definition exists for the given entity type.
     */
    public function hasDefinition(string $entityTypeId): bool
    {
        return isset($this->definitions[$entityTypeId]);
    }

    /**
     * Reset all definitions, sequences, and counters.
     */
    public function reset(): self
    {
        $this->definitions = [];
        $this->sequences = [];
        $this->counters = [];

        return $this;
    }
}

<?php

declare(strict_types=1);

namespace Waaseyaa\Entity\Field;

/**
 * Registry keying field definitions by (entityTypeId, targetBundle).
 *
 * Core fields (targetBundle === null) apply to every bundle of their entity
 * type; bundle fields (targetBundle === '{bundle}') apply only to that bundle.
 * See docs/specs/bundle-scoped-fields.md for the full contract.
 *
 * The interface lives in packages/entity to keep EntityTypeManager free of
 * dependencies on packages/field. The concrete implementation lives in
 * packages/field as Waaseyaa\Field\FieldDefinitionRegistry.
 */
interface FieldDefinitionRegistryInterface
{
    /**
     * Register the core field metadata for an entity type.
     *
     * Called once per entity type at registration time. Core field names
     * occupy the entity type's global namespace for collision detection.
     *
     * @param array<string, mixed> $fields Field metadata keyed by field name.
     */
    public function registerCoreFields(string $entityTypeId, array $fields): void;

    /**
     * Register bundle-scoped fields for (entityTypeId, bundle).
     *
     * Each entry must be a FieldDefinitionInterface instance whose
     * targetEntityTypeId and targetBundle match the arguments.
     *
     * @param array<string|int, object> $fields FieldDefinition objects.
     *
     * @throws \InvalidArgumentException If an entry is not a FieldDefinition,
     *                                   self-description mismatches, a name
     *                                   collides with a core field, or a name
     *                                   duplicates an existing bundle field.
     */
    public function registerBundleFields(string $entityTypeId, string $bundle, array $fields): void;

    /**
     * Returns the core field metadata registered for an entity type.
     *
     * @return array<string, mixed> Empty if nothing registered.
     */
    public function coreFieldsFor(string $entityTypeId): array;

    /**
     * Returns the bundle-scoped fields registered for (entityTypeId, bundle).
     *
     * @return array<string, object> Field objects keyed by field name. Empty
     *                               when no bundle fields are registered.
     */
    public function bundleFieldsFor(string $entityTypeId, string $bundle): array;
}

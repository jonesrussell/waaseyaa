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
     * Called once per entity type at registration time. Metadata-array entries
     * are synthesized into FieldDefinition objects with targetBundle === null
     * and targetEntityTypeId === $entityTypeId; already-constructed
     * FieldDefinition instances pass through unchanged. Core field names
     * occupy the entity type's global namespace for collision detection.
     *
     * @param array<string, array<string, mixed>|object> $fields Metadata
     *        arrays or FieldDefinitionInterface instances keyed by field name.
     */
    public function registerCoreFields(string $entityTypeId, array $fields): void;

    /**
     * Merge additional core fields into an entity type that already registered core fields.
     *
     * Used by host apps to attach product-only columns (e.g. overlays) without forking
     * package entity types. Names must not collide with existing core fields.
     *
     * @param array<string, array<string, mixed>|object> $fields
     */
    public function mergeCoreFields(string $entityTypeId, array $fields): void;

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
     * Returns the core FieldDefinition objects registered for an entity type.
     *
     * Values are FieldDefinitionInterface instances with targetBundle === null.
     *
     * @return array<string, object> Field objects keyed by field name. Empty
     *                               when no core fields are registered.
     */
    public function coreFieldsFor(string $entityTypeId): array;

    /**
     * Returns the bundle-scoped fields registered for (entityTypeId, bundle).
     *
     * @return array<string, object> Field objects keyed by field name. Empty
     *                               when no bundle fields are registered.
     */
    public function bundleFieldsFor(string $entityTypeId, string $bundle): array;

    /**
     * Returns the bundle identifiers that have at least one registered field
     * for the given entity type.
     *
     * @return array<int, string> Unordered list of bundle identifiers. Empty
     *                            when no bundle fields are registered.
     */
    public function bundleNamesFor(string $entityTypeId): array;

    /**
     * Returns the bundles that define a field with the given name, so that
     * query routing can detect bundle-scoped ambiguity before compiling SQL.
     *
     * Core fields are intentionally excluded — only bundle-scoped definitions
     * contribute. Result order is insertion order of bundle registration.
     *
     * @return array<int, string> List of bundle identifiers defining that
     *                            field name. Empty when the field is not
     *                            registered against any bundle of the type.
     */
    public function bundlesDefiningField(string $entityTypeId, string $fieldName): array;
}

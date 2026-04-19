<?php

declare(strict_types=1);

namespace Waaseyaa\Field;

use Waaseyaa\Entity\Field\FieldDefinitionRegistryInterface;

/**
 * Default FieldDefinitionRegistry implementation.
 *
 * Stores core field metadata (array shape) and bundle FieldDefinition objects
 * keyed by (entityTypeId, bundle). Enforces collision rules at registration
 * time per docs/specs/bundle-scoped-fields.md §Collision rules.
 */
final class FieldDefinitionRegistry implements FieldDefinitionRegistryInterface
{
    /** @var array<string, array<string, mixed>> [entityTypeId] => metadata keyed by field name. */
    private array $coreFields = [];

    /** @var array<string, array<string, array<string, FieldDefinitionInterface>>> [entityTypeId][bundle][fieldName]. */
    private array $bundleFields = [];

    public function registerCoreFields(string $entityTypeId, array $fields): void
    {
        $this->coreFields[$entityTypeId] = $fields;
    }

    public function registerBundleFields(string $entityTypeId, string $bundle, array $fields): void
    {
        $byName = [];
        foreach ($fields as $key => $field) {
            if (!$field instanceof FieldDefinitionInterface) {
                throw new \InvalidArgumentException(\sprintf(
                    'Bundle field registration for entity type "%s" bundle "%s" expects FieldDefinitionInterface instances; got %s at key "%s".',
                    $entityTypeId,
                    $bundle,
                    \get_debug_type($field),
                    (string) $key,
                ));
            }

            if ($field->getTargetEntityTypeId() !== $entityTypeId) {
                throw new \InvalidArgumentException(\sprintf(
                    'FieldDefinition "%s" declares targetEntityTypeId "%s" but is being registered against entity type "%s".',
                    $field->getName(),
                    $field->getTargetEntityTypeId(),
                    $entityTypeId,
                ));
            }

            if ($field->getTargetBundle() !== $bundle) {
                throw new \InvalidArgumentException(\sprintf(
                    'FieldDefinition "%s" declares targetBundle "%s" but is being registered against entity type "%s" bundle "%s".',
                    $field->getName(),
                    $field->getTargetBundle() ?? '(null)',
                    $entityTypeId,
                    $bundle,
                ));
            }

            $name = $field->getName();
            if (isset($byName[$name])) {
                throw new \InvalidArgumentException(\sprintf(
                    'Duplicate bundle field "%s" in registration for entity type "%s" bundle "%s".',
                    $name,
                    $entityTypeId,
                    $bundle,
                ));
            }
            $byName[$name] = $field;
        }

        $coreNames = $this->coreFields[$entityTypeId] ?? [];
        foreach ($byName as $name => $_field) {
            if (isset($coreNames[$name])) {
                throw new \InvalidArgumentException(\sprintf(
                    'Field "%s" on entity type "%s" bundle "%s" collides with core field "%s" on entity type "%s".',
                    $name,
                    $entityTypeId,
                    $bundle,
                    $name,
                    $entityTypeId,
                ));
            }
        }

        $existing = $this->bundleFields[$entityTypeId][$bundle] ?? [];
        foreach ($byName as $name => $_field) {
            if (isset($existing[$name])) {
                throw new \InvalidArgumentException(\sprintf(
                    'Duplicate bundle field "%s" for entity type "%s" bundle "%s"; already registered.',
                    $name,
                    $entityTypeId,
                    $bundle,
                ));
            }
        }

        foreach ($byName as $name => $field) {
            $this->bundleFields[$entityTypeId][$bundle][$name] = $field;
        }
    }

    public function coreFieldsFor(string $entityTypeId): array
    {
        return $this->coreFields[$entityTypeId] ?? [];
    }

    public function bundleFieldsFor(string $entityTypeId, string $bundle): array
    {
        return $this->bundleFields[$entityTypeId][$bundle] ?? [];
    }
}

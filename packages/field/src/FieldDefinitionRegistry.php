<?php

declare(strict_types=1);

namespace Waaseyaa\Field;

use Waaseyaa\Entity\Field\FieldDefinitionRegistryInterface;

/**
 * Default FieldDefinitionRegistry implementation.
 *
 * Stores FieldDefinition objects keyed by (entityTypeId, targetBundle). Core
 * fields are synthesized from EntityType::fieldDefinitions metadata arrays at
 * registration time — the registry is the normalization boundary per
 * docs/specs/bundle-scoped-fields.md §Resolution. Bundle fields are registered
 * as FieldDefinition objects directly and validated for self-description.
 */
final class FieldDefinitionRegistry implements FieldDefinitionRegistryInterface
{
    /** @var array<string, array<string, FieldDefinitionInterface>> [entityTypeId][fieldName]. */
    private array $coreFields = [];

    /** @var array<string, array<string, array<string, FieldDefinitionInterface>>> [entityTypeId][bundle][fieldName]. */
    private array $bundleFields = [];

    public function registerCoreFields(string $entityTypeId, array $fields): void
    {
        $synthesized = [];
        foreach ($fields as $name => $meta) {
            if ($meta instanceof FieldDefinitionInterface) {
                $synthesized[$name] = $meta;
                continue;
            }
            if (!\is_array($meta)) {
                throw new \InvalidArgumentException(\sprintf(
                    'Core field "%s" on entity type "%s" must be a metadata array or FieldDefinitionInterface; got %s.',
                    $name,
                    $entityTypeId,
                    \get_debug_type($meta),
                ));
            }
            $synthesized[$name] = self::synthesizeCoreField($name, $entityTypeId, $meta);
        }
        $this->coreFields[$entityTypeId] = $synthesized;
    }

    public function mergeCoreFields(string $entityTypeId, array $fields): void
    {
        $existing = $this->coreFields[$entityTypeId] ?? [];
        foreach ($fields as $name => $_meta) {
            if (isset($existing[$name])) {
                throw new \InvalidArgumentException(\sprintf(
                    'Cannot merge core field "%s" on entity type "%s": name already registered.',
                    $name,
                    $entityTypeId,
                ));
            }
        }

        $this->registerCoreFields($entityTypeId, $existing + $fields);
    }

    /**
     * @param array<string, mixed> $meta
     */
    private static function synthesizeCoreField(string $name, string $entityTypeId, array $meta): FieldDefinition
    {
        $known = ['type', 'label', 'description', 'required', 'readOnly', 'read_only',
            'cardinality', 'translatable', 'revisionable', 'default', 'defaultValue',
            'settings', 'constraints', 'stored'];

        $settings = $meta['settings'] ?? [];
        if (!\is_array($settings)) {
            $settings = [];
        }
        foreach ($meta as $key => $value) {
            if (!\in_array($key, $known, true)) {
                $settings[$key] = $value;
            }
        }

        $stored = $meta['stored'] ?? FieldStorage::Column;
        if (\is_string($stored)) {
            $stored = FieldStorage::tryFrom($stored) ?? FieldStorage::Column;
        }
        if (!$stored instanceof FieldStorage) {
            $stored = FieldStorage::Column;
        }

        return new FieldDefinition(
            name: $name,
            type: (string) ($meta['type'] ?? 'string'),
            cardinality: (int) ($meta['cardinality'] ?? 1),
            settings: $settings,
            targetEntityTypeId: $entityTypeId,
            targetBundle: null,
            translatable: (bool) ($meta['translatable'] ?? false),
            revisionable: (bool) ($meta['revisionable'] ?? false),
            defaultValue: $meta['defaultValue'] ?? ($meta['default'] ?? null),
            label: (string) ($meta['label'] ?? ''),
            description: (string) ($meta['description'] ?? ''),
            required: (bool) ($meta['required'] ?? false),
            readOnly: (bool) ($meta['readOnly'] ?? $meta['read_only'] ?? false),
            constraints: \is_array($meta['constraints'] ?? null) ? $meta['constraints'] : [],
            stored: $stored,
        );
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
            if (\array_key_exists($name, $coreNames)) {
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

    public function bundleNamesFor(string $entityTypeId): array
    {
        return \array_keys($this->bundleFields[$entityTypeId] ?? []);
    }

    public function bundlesDefiningField(string $entityTypeId, string $fieldName): array
    {
        $bundles = [];
        foreach ($this->bundleFields[$entityTypeId] ?? [] as $bundle => $fields) {
            if (\array_key_exists($fieldName, $fields)) {
                $bundles[] = $bundle;
            }
        }

        return $bundles;
    }
}

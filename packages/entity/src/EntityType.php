<?php

declare(strict_types=1);

namespace Waaseyaa\Entity;

use Waaseyaa\Field\FieldDefinition;
use Waaseyaa\Field\FieldDefinitionInterface;
use Waaseyaa\Field\FieldStorage;

/**
 * Value object representing an entity type definition.
 *
 * Entity types are registered with the EntityTypeManager and describe
 * the structure and behavior of a class of entities.
 */
final readonly class EntityType implements EntityTypeInterface
{
    /**
     * @param string $id Machine name of the entity type (e.g. 'node', 'user').
     * @param string $label Human-readable label.
     * @param class-string<EntityInterface> $class The entity class.
     * @param class-string<Storage\EntityStorageInterface> $storageClass The storage handler class.
     * @param array<string, string> $keys Entity keys mapping (id, uuid, label, bundle, revision, langcode).
     * @param bool $revisionable Whether this entity type supports revisions.
     * @param bool $translatable Whether this entity type supports translations.
     * @param string|null $bundleEntityType The entity type ID that provides bundles (e.g. 'node_type' for 'node').
     * @param array<string, mixed> $constraints Validation constraints.
     * @param array<string, FieldDefinitionInterface|array<string, mixed>> $fieldDefinitions Field definitions keyed by field name.
     * @param string|null $description Human-readable description of the entity type.
     */
    public function __construct(
        private string $id,
        private string $label,
        private string $class,
        private string $storageClass = '',
        private array $keys = [],
        private bool $revisionable = false,
        private bool $revisionDefault = false,
        private bool $translatable = false,
        private ?string $bundleEntityType = null,
        private array $constraints = [],
        private array $fieldDefinitions = [],
        private ?string $group = null,
        private ?string $description = null,
    ) {}

    public function id(): string
    {
        return $this->id;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getClass(): string
    {
        return $this->class;
    }

    /** @return class-string<Storage\EntityStorageInterface> */
    public function getStorageClass(): string
    {
        return $this->storageClass;
    }

    /** @return array<string, string> */
    public function getKeys(): array
    {
        return $this->keys;
    }

    public function isRevisionable(): bool
    {
        return $this->revisionable;
    }

    public function getRevisionDefault(): bool
    {
        return $this->revisionDefault;
    }

    public function isTranslatable(): bool
    {
        return $this->translatable;
    }

    public function getBundleEntityType(): ?string
    {
        return $this->bundleEntityType;
    }

    /** @return array<string, mixed> */
    public function getConstraints(): array
    {
        return $this->constraints;
    }

    /** @return array<string, FieldDefinitionInterface> */
    public function getFieldDefinitions(): array
    {
        $normalized = [];
        foreach ($this->fieldDefinitions as $name => $definition) {
            if ($definition instanceof FieldDefinitionInterface) {
                $normalized[$name] = $definition;
                continue;
            }
            /** @var array<string, mixed> $meta */
            $meta = $definition;
            $settings = $meta['settings'] ?? [];
            if (!is_array($settings)) {
                $settings = [];
            }
            foreach ($meta as $key => $value) {
                if (!in_array($key, ['type', 'label', 'description', 'required', 'readOnly', 'read_only', 'cardinality', 'translatable', 'revisionable', 'default', 'defaultValue', 'settings', 'constraints', 'stored'], true)) {
                    $settings[$key] = $value;
                }
            }
            $stored = $meta['stored'] ?? FieldStorage::Column;
            if (is_string($stored)) {
                $stored = FieldStorage::tryFrom($stored) ?? FieldStorage::Column;
            }
            if (!$stored instanceof FieldStorage) {
                $stored = FieldStorage::Column;
            }
            $normalized[$name] = new FieldDefinition(
                name: $name,
                type: (string) ($meta['type'] ?? 'string'),
                cardinality: (int) ($meta['cardinality'] ?? 1),
                settings: $settings,
                targetEntityTypeId: $this->id,
                targetBundle: null,
                translatable: (bool) ($meta['translatable'] ?? false),
                revisionable: (bool) ($meta['revisionable'] ?? false),
                defaultValue: $meta['defaultValue'] ?? ($meta['default'] ?? null),
                label: (string) ($meta['label'] ?? ''),
                description: (string) ($meta['description'] ?? ''),
                required: (bool) ($meta['required'] ?? false),
                readOnly: (bool) ($meta['readOnly'] ?? $meta['read_only'] ?? false),
                constraints: is_array($meta['constraints'] ?? null) ? $meta['constraints'] : [],
                stored: $stored,
            );
        }

        return $normalized;
    }

    public function getGroup(): ?string
    {
        return $this->group;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }
}

<?php

declare(strict_types=1);

namespace Waaseyaa\Entity;

/**
 * Abstract base class for content entities (nodes, users, terms, etc.).
 *
 * Content entities are fieldable: they support dynamic fields that can be
 * added and removed through configuration. For v0.1.0, field values are
 * stored as raw values in the values array. Full FieldItemList integration
 * will come with the waaseyaa/field package.
 *
 * Unlike Drupal, a ContentEntityBase object represents ONE language at a time.
 * getTranslation() returns a separate entity object for the requested language.
 */
abstract class ContentEntityBase extends EntityBase implements ContentEntityInterface
{
    /**
     * Field definitions for this entity.
     *
     * @var array<string, mixed>
     */
    protected array $fieldDefinitions = [];

    /**
     * @param array<string, mixed> $values Initial entity values.
     * @param string $entityTypeId The entity type machine name.
     * @param array<string, string> $entityKeys Entity key mappings.
     * @param array<string, mixed> $fieldDefinitions Field definitions keyed by field name.
     */
    public function __construct(
        array $values = [],
        string $entityTypeId = '',
        array $entityKeys = [],
        array $fieldDefinitions = [],
    ) {
        parent::__construct($values, $entityTypeId, $entityKeys);
        $this->fieldDefinitions = $fieldDefinitions;
    }

    public function hasField(string $name): bool
    {
        return \array_key_exists($name, $this->values)
            || \array_key_exists($name, $this->fieldDefinitions);
    }

    public function get(string $name): mixed
    {
        return $this->values[$name] ?? null;
    }

    public function set(string $name, mixed $value): static
    {
        $this->values[$name] = $value;

        return $this;
    }

    /** @return array<string, mixed> */
    public function getFieldDefinitions(): array
    {
        return $this->fieldDefinitions;
    }
}

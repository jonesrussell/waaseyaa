<?php

declare(strict_types=1);

namespace Waaseyaa\Entity;

use Waaseyaa\Entity\Attribute\EntityMetadataReader;
use Waaseyaa\Entity\Exception\EntityMetadataException;
use Waaseyaa\Entity\Field\FieldDefinitionRegistryInterface;
use Waaseyaa\Entity\Hydration\HydratableFromStorageInterface;
use Waaseyaa\Entity\Hydration\HydrationContext;
use Waaseyaa\Field\FieldDefinitionInterface;

/**
 * Abstract base class for content entities (nodes, users, terms, etc.).
 *
 * Content entities are fieldable: they support dynamic fields that can be
 * added and removed through configuration. Field values live in the values array
 * in storage-canonical form; optional {@see EntityBase::$casts} on subclasses make
 * {@see get()} / {@see set()} cast-aware. Full FieldItemList integration will come
 * with the waaseyaa/field package.
 *
 * Unlike Drupal, a ContentEntityBase object represents ONE language at a time.
 * getTranslation() returns a separate entity object for the requested language.
 *
 * @phpstan-consistent-constructor
 */
abstract class ContentEntityBase extends EntityBase implements ContentEntityInterface, HydratableFromStorageInterface
{
    /**
     * Process-wide field registry consulted by {@see getFieldDefinitions()}.
     *
     * AbstractKernel wires this at boot with the same FieldDefinitionRegistry
     * held by EntityTypeManager. When set, getFieldDefinitions() returns the
     * bundle-aware union per docs/specs/bundle-scoped-fields.md §Resolution;
     * when null, it returns the per-instance legacy array. Tests that wire
     * this must reset it in tearDown() to avoid bleed between cases.
     */
    private static ?FieldDefinitionRegistryInterface $fieldRegistry = null;

    /**
     * Field definitions passed into the entity constructor (legacy path).
     *
     * @var array<string, FieldDefinitionInterface>
     */
    protected array $fieldDefinitions = [];

    /**
     * @param array<string, mixed> $values Initial entity values.
     * @param string $entityTypeId The entity type machine name.
     * @param array<string, string> $entityKeys Entity key mappings.
     * @param array<string, FieldDefinitionInterface> $fieldDefinitions Field definitions keyed by field name.
     */
    public function __construct(
        array $values = [],
        string $entityTypeId = '',
        array $entityKeys = [],
        array $fieldDefinitions = [],
    ) {
        if ($entityTypeId === '' || $entityKeys === []) {
            $meta = EntityMetadataReader::forClass(static::class);
            if ($entityTypeId === '') {
                $entityTypeId = $meta->typeId ?? '';
            }
            if ($entityKeys === []) {
                $entityKeys = $meta->keys;
            }
        }

        if ($entityTypeId === '') {
            throw new EntityMetadataException(\sprintf(
                'Concrete content entity %s must declare #[ContentEntityType(id: "…")].',
                static::class,
            ));
        }

        parent::__construct($values, $entityTypeId, $entityKeys);
        $this->fieldDefinitions = $fieldDefinitions;
    }

    public static function setFieldRegistry(?FieldDefinitionRegistryInterface $registry): void
    {
        self::$fieldRegistry = $registry;
    }

    public function hasField(string $name): bool
    {
        return \array_key_exists($name, $this->values)
            || \array_key_exists($name, $this->getFieldDefinitions());
    }

    /** @return array<string, FieldDefinitionInterface> */
    public function getFieldDefinitions(): array
    {
        if (self::$fieldRegistry === null) {
            return $this->fieldDefinitions;
        }

        $core = self::$fieldRegistry->coreFieldsFor($this->entityTypeId);
        $bundle = self::$fieldRegistry->bundleFieldsFor($this->entityTypeId, $this->bundle());

        if ($core === [] && $bundle === []) {
            return $this->fieldDefinitions;
        }

        return $core + $bundle;
    }

    /**
     * @param array<string, mixed> $values
     */
    protected function duplicateInstance(array $values): static
    {
        $class = static::class;

        return new $class($values, $this->entityTypeId, $this->entityKeys, $this->fieldDefinitions);
    }

    public static function fromStorage(array $values, HydrationContext $context): static
    {
        return new static(
            values: $values,
            entityTypeId: $context->entityTypeId,
            entityKeys: $context->entityKeys,
            fieldDefinitions: [],
        );
    }
}

<?php

declare(strict_types=1);

namespace Waaseyaa\Entity\Tests\Fixtures\AttributeFirstEntities;

use Waaseyaa\Entity\Attribute\ContentEntityType;
use Waaseyaa\Entity\Attribute\Field;
use Waaseyaa\Entity\ContentEntityBase;
use Waaseyaa\Field\FieldStorage;

/**
 * Simple entity with label, description, and a mix of inferred field types.
 */
#[ContentEntityType(id: 'metadata_simple', label: 'Simple', description: 'A simple test entity.')]
final class MetadataSimpleEntity extends ContentEntityBase
{
    #[Field(label: 'Title', description: 'Entity title.')]
    public string $title = '';

    #[Field]
    public ?int $count = null;

    #[Field(default: false)]
    public bool $active = false;

    #[Field(translatable: true, revisionable: true, readOnly: true)]
    public ?\DateTimeImmutable $createdAt = null;
}

/**
 * Parent class for the inheritance fixture pair. Declares two fields.
 */
#[ContentEntityType(id: 'metadata_parent', label: 'Parent')]
class MetadataParentEntity extends ContentEntityBase
{
    #[Field(label: 'Parent name')]
    public string $name = '';

    #[Field]
    public ?int $weight = null;
}

/**
 * Child class that overrides the `name` field with a richer declaration and adds
 * its own `extra` field.
 */
#[ContentEntityType(id: 'metadata_child', label: 'Child')]
final class MetadataChildEntity extends MetadataParentEntity
{
    #[Field(label: 'Child name', description: 'Overridden in child.')]
    public string $name = '';

    #[Field]
    public string $extra = '';
}

/**
 * Entity declaring no label/description — both should default to ''.
 */
#[ContentEntityType(id: 'metadata_no_label')]
final class MetadataNoLabelEntity extends ContentEntityBase
{
    #[Field]
    public string $value = '';
}

/**
 * Entity with an explicit `type:` override that still aligns with the inferred
 * PHP type (string → text is in the same compatibility group).
 */
#[ContentEntityType(id: 'metadata_typed_override', label: 'Typed Override')]
final class MetadataTypedOverrideEntity extends ContentEntityBase
{
    #[Field(type: 'text', label: 'Body')]
    public string $body = '';
}

/**
 * Plain class that does NOT extend ContentEntityBase. Used to assert that
 * `resolveFields()` returns an empty array for non-content-entities.
 */
final class MetadataNonEntity
{
    #[Field]
    public string $ignored = '';
}

/**
 * Entity with one column-stored field and one data-stored field. Used to
 * assert that `EntityMetadataReader::resolveFields()` forwards `stored:`
 * verbatim into `FieldDefinition`.
 */
#[ContentEntityType(id: 'metadata_stored_mixed', label: 'Stored Mixed')]
final class MetadataStoredMixedEntity extends ContentEntityBase
{
    #[Field(label: 'Title')]
    public string $title = '';

    #[Field(stored: FieldStorage::Data, label: 'Status')]
    public ?int $status = null;
}

<?php

declare(strict_types=1);

namespace Waaseyaa\Groups;

use Waaseyaa\Entity\ContentEntityBase;
use Waaseyaa\Field\FieldDefinitionInterface;

/**
 * Multi-bundle Group content entity.
 *
 * Bundle partitioning is provided by GroupType. Bundle-scoped field values are
 * stored in per-bundle subtables (`group__{bundle}`); core keys live on the
 * base `group` table. See docs/specs/bundle-scoped-storage.md.
 */
final class Group extends ContentEntityBase
{
    protected string $entityTypeId = 'group';

    protected array $entityKeys = [
        'id' => 'gid',
        'uuid' => 'uuid',
        'bundle' => 'type',
        'label' => 'name',
        'langcode' => 'langcode',
    ];

    /**
     * @param array<string, mixed> $values Initial entity values.
     * @param string $entityTypeId Override machine name (defaults to `group` when empty).
     * @param array<string, string> $entityKeys Explicit keys when reconstructing via {@see ContentEntityBase::duplicateInstance()}.
     * @param array<string, FieldDefinitionInterface> $fieldDefinitions Field definitions keyed by field name.
     */
    public function __construct(
        array $values = [],
        string $entityTypeId = '',
        array $entityKeys = [],
        array $fieldDefinitions = [],
    ) {
        $entityTypeId = $entityTypeId !== '' ? $entityTypeId : $this->entityTypeId;
        $entityKeys = $entityKeys !== [] ? $entityKeys : $this->entityKeys;

        parent::__construct($values, $entityTypeId, $entityKeys, $fieldDefinitions);
    }

    public function getName(): string
    {
        return $this->label();
    }

    public function setName(string $name): static
    {
        return $this->set('name', $name);
    }

    /**
     * Returns the group_type bundle id this group belongs to.
     */
    public function getGroupTypeId(): string
    {
        return $this->bundle();
    }
}

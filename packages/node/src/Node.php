<?php

declare(strict_types=1);

namespace Waaseyaa\Node;

use DateTimeInterface;
use Waaseyaa\Entity\Attribute\ContentEntityKeys;
use Waaseyaa\Entity\Attribute\ContentEntityType;
use Waaseyaa\Entity\ContentEntityBase;
use Waaseyaa\Entity\Hydration\HydratableFromStorageInterface;
use Waaseyaa\Entity\Hydration\HydrationContext;

/**
 * Represents a piece of content (a node).
 *
 * Nodes are the primary content entity in Waaseyaa. Each node belongs
 * to a node type (bundle) and has properties like title, author, status,
 * and timestamps.
 */
#[ContentEntityType(id: 'node')]
#[ContentEntityKeys(id: 'nid', uuid: 'uuid', label: 'title', bundle: 'type')]
final class Node extends ContentEntityBase implements HydratableFromStorageInterface
{
    /**
     * @var array<string, string|array<string, mixed>>
     */
    protected array $casts = [
        'created' => ['type' => 'datetime_immutable', 'storage' => 'unix'],
        'changed' => ['type' => 'datetime_immutable', 'storage' => 'unix'],
    ];

    /**
     * @param array<string, mixed> $values Initial entity values.
     * @param array<string, string> $entityKeys Explicit keys when reconstructing via {@see ContentEntityBase::duplicateInstance()}.
     */
    public function __construct(
        array $values = [],
        string $entityTypeId = '',
        array $entityKeys = [],
        array $fieldDefinitions = [],
    ) {
        // Ensure defaults for optional properties.
        if (!array_key_exists('status', $values)) {
            $values['status'] = 1;
        }
        if (!array_key_exists('promote', $values)) {
            $values['promote'] = 0;
        }
        if (!array_key_exists('sticky', $values)) {
            $values['sticky'] = 0;
        }
        if (!array_key_exists('created', $values)) {
            $values['created'] = 0;
        }
        if (!array_key_exists('changed', $values)) {
            $values['changed'] = 0;
        }

        parent::__construct($values, $entityTypeId, $entityKeys, $fieldDefinitions);
    }

    /**
     * @param array<string, mixed> $values
     */
    public static function make(array $values): self
    {
        return new self($values);
    }

    public static function fromStorage(array $values, HydrationContext $context): static
    {
        return new self(
            values: $values,
            entityTypeId: $context->entityTypeId,
            entityKeys: $context->entityKeys,
            fieldDefinitions: [],
        );
    }

    protected function duplicateInstance(array $values): static
    {
        return new static(
            values: $values,
            entityTypeId: $this->getEntityTypeId(),
            entityKeys: $this->entityKeys,
            fieldDefinitions: $this->getFieldDefinitions(),
        );
    }

    /**
     * Returns the node title.
     */
    public function getTitle(): string
    {
        return (string) ($this->get('title') ?? '');
    }

    /**
     * Sets the node title.
     */
    public function setTitle(string $title): static
    {
        $this->set('title', $title);

        return $this;
    }

    /**
     * Returns the node type (bundle) machine name.
     */
    public function getType(): string
    {
        return $this->bundle();
    }

    /**
     * Returns the author user ID.
     */
    public function getAuthorId(): int
    {
        return (int) ($this->get('uid') ?? 0);
    }

    /**
     * Sets the author user ID.
     */
    public function setAuthorId(int $uid): static
    {
        $this->set('uid', $uid);

        return $this;
    }

    /**
     * Whether this node is published.
     */
    public function isPublished(): bool
    {
        return (int) ($this->get('status') ?? 0) === 1;
    }

    /**
     * Sets the published status.
     */
    public function setPublished(bool $published): static
    {
        $this->set('status', $published ? 1 : 0);

        return $this;
    }

    /**
     * Whether this node is promoted to the front page.
     */
    public function isPromoted(): bool
    {
        return (int) ($this->get('promote') ?? 0) === 1;
    }

    /**
     * Sets the promoted status.
     */
    public function setPromoted(bool $promoted): static
    {
        $this->set('promote', $promoted ? 1 : 0);

        return $this;
    }

    /**
     * Whether this node is sticky at the top of lists.
     */
    public function isSticky(): bool
    {
        return (int) ($this->get('sticky') ?? 0) === 1;
    }

    /**
     * Sets the sticky status.
     */
    public function setSticky(bool $sticky): static
    {
        $this->set('sticky', $sticky ? 1 : 0);

        return $this;
    }

    /**
     * Returns the creation timestamp.
     */
    public function getCreatedTime(): int
    {
        $v = $this->get('created');
        if ($v instanceof DateTimeInterface) {
            return $v->getTimestamp();
        }

        return (int) ($v ?? 0);
    }

    /**
     * Sets the creation timestamp.
     */
    public function setCreatedTime(int $timestamp): static
    {
        $this->set('created', $timestamp);

        return $this;
    }

    /**
     * Returns the last changed timestamp.
     */
    public function getChangedTime(): int
    {
        $v = $this->get('changed');
        if ($v instanceof DateTimeInterface) {
            return $v->getTimestamp();
        }

        return (int) ($v ?? 0);
    }

    /**
     * Sets the last changed timestamp.
     */
    public function setChangedTime(int $timestamp): static
    {
        $this->set('changed', $timestamp);

        return $this;
    }
}

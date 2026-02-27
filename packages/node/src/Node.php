<?php

declare(strict_types=1);

namespace Aurora\Node;

use Aurora\Entity\ContentEntityBase;

/**
 * Represents a piece of content (a node).
 *
 * Nodes are the primary content entity in Aurora CMS. Each node belongs
 * to a node type (bundle) and has properties like title, author, status,
 * and timestamps.
 */
final class Node extends ContentEntityBase
{
    protected string $entityTypeId = 'node';

    protected array $entityKeys = [
        'id' => 'nid',
        'uuid' => 'uuid',
        'label' => 'title',
        'bundle' => 'type',
    ];

    /**
     * @param array<string, mixed> $values Initial entity values.
     */
    public function __construct(array $values = [])
    {
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

        parent::__construct($values, $this->entityTypeId, $this->entityKeys);
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
        return (int) ($this->get('created') ?? 0);
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
        return (int) ($this->get('changed') ?? 0);
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

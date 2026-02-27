<?php

declare(strict_types=1);

namespace Aurora\Taxonomy;

use Aurora\Entity\ContentEntityBase;

/**
 * Represents a taxonomy term within a vocabulary.
 *
 * Terms are content entities that belong to a vocabulary (the bundle).
 * They support hierarchical relationships through parent term IDs,
 * and can be published or unpublished.
 */
final class Term extends ContentEntityBase
{
    protected string $entityTypeId = 'taxonomy_term';

    protected array $entityKeys = [
        'id' => 'tid',
        'uuid' => 'uuid',
        'label' => 'name',
        'bundle' => 'vid',
    ];

    /**
     * @param array<string, mixed> $values Initial entity values.
     */
    public function __construct(array $values = [])
    {
        // Ensure defaults for optional properties.
        if (!array_key_exists('description', $values)) {
            $values['description'] = '';
        }
        if (!array_key_exists('weight', $values)) {
            $values['weight'] = 0;
        }
        if (!array_key_exists('parent_id', $values)) {
            $values['parent_id'] = null;
        }
        if (!array_key_exists('status', $values)) {
            $values['status'] = true;
        }

        parent::__construct($values, $this->entityTypeId, $this->entityKeys);
    }

    /**
     * Returns the term name.
     */
    public function getName(): string
    {
        return $this->label();
    }

    /**
     * Sets the term name.
     */
    public function setName(string $name): static
    {
        $this->values['name'] = $name;

        return $this;
    }

    /**
     * Returns the vocabulary ID (bundle) this term belongs to.
     */
    public function getVocabularyId(): string
    {
        return $this->bundle();
    }

    /**
     * Returns the term description.
     */
    public function getDescription(): string
    {
        return (string) ($this->values['description'] ?? '');
    }

    /**
     * Sets the term description.
     */
    public function setDescription(string $description): static
    {
        $this->values['description'] = $description;

        return $this;
    }

    /**
     * Returns the sort weight.
     */
    public function getWeight(): int
    {
        return (int) ($this->values['weight'] ?? 0);
    }

    /**
     * Sets the sort weight.
     */
    public function setWeight(int $weight): static
    {
        $this->values['weight'] = $weight;

        return $this;
    }

    /**
     * Returns the parent term ID for hierarchy (null = root term).
     */
    public function getParentId(): ?int
    {
        $parentId = $this->values['parent_id'] ?? null;

        if ($parentId === null || $parentId === 0) {
            return $parentId === 0 ? 0 : null;
        }

        return (int) $parentId;
    }

    /**
     * Sets the parent term ID.
     *
     * @param int|null $parentId The parent term ID, or null for a root term.
     */
    public function setParentId(?int $parentId): static
    {
        $this->values['parent_id'] = $parentId;

        return $this;
    }

    /**
     * Returns true if this is a root term (no parent).
     */
    public function isRoot(): bool
    {
        $parentId = $this->values['parent_id'] ?? null;

        return $parentId === null || $parentId === 0;
    }

    /**
     * Returns whether this term is published.
     */
    public function isPublished(): bool
    {
        return (bool) ($this->values['status'] ?? true);
    }

    /**
     * Sets the published status.
     */
    public function setPublished(bool $published): static
    {
        $this->values['status'] = $published;

        return $this;
    }
}

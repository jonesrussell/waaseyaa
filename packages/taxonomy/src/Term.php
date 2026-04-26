<?php

declare(strict_types=1);

namespace Waaseyaa\Taxonomy;

use Waaseyaa\Entity\Attribute\ContentEntityKeys;
use Waaseyaa\Entity\Attribute\ContentEntityType;
use Waaseyaa\Entity\ContentEntityBase;

/**
 * Represents a taxonomy term within a vocabulary.
 *
 * Terms are content entities that belong to a vocabulary (the bundle).
 * They support hierarchical relationships through parent term IDs,
 * and can be published or unpublished.
 */
#[ContentEntityType(id: 'taxonomy_term')]
#[ContentEntityKeys(id: 'tid', uuid: 'uuid', label: 'name', bundle: 'vid')]
final class Term extends ContentEntityBase
{
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

        parent::__construct($values, $entityTypeId, $entityKeys, $fieldDefinitions);
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
        return $this->set('name', $name);
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
        return (string) ($this->get('description') ?? '');
    }

    /**
     * Sets the term description.
     */
    public function setDescription(string $description): static
    {
        return $this->set('description', $description);
    }

    /**
     * Returns the sort weight.
     */
    public function getWeight(): int
    {
        return (int) ($this->get('weight') ?? 0);
    }

    /**
     * Sets the sort weight.
     */
    public function setWeight(int $weight): static
    {
        return $this->set('weight', $weight);
    }

    /**
     * Returns the parent term ID for hierarchy (null = root term).
     */
    public function getParentId(): ?int
    {
        $parentId = $this->get('parent_id');

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
        return $this->set('parent_id', $parentId);
    }

    /**
     * Returns true if this is a root term (no parent).
     */
    public function isRoot(): bool
    {
        $parentId = $this->get('parent_id');

        return $parentId === null || $parentId === 0;
    }

    /**
     * Returns whether this term is published.
     */
    public function isPublished(): bool
    {
        return (bool) ($this->get('status') ?? true);
    }

    /**
     * Sets the published status.
     */
    public function setPublished(bool $published): static
    {
        return $this->set('status', $published);
    }
}

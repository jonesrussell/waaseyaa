<?php

declare(strict_types=1);

namespace Aurora\Taxonomy;

use Aurora\Entity\ConfigEntityBase;

/**
 * Represents a taxonomy vocabulary (e.g. "tags", "categories").
 *
 * Vocabularies are configuration entities that define groupings of terms.
 * Each vocabulary has a machine name (vid), a human-readable name,
 * an optional description, and a sort weight.
 */
final class Vocabulary extends ConfigEntityBase
{
    protected string $entityTypeId = 'taxonomy_vocabulary';

    protected array $entityKeys = [
        'id' => 'vid',
        'label' => 'name',
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

        parent::__construct($values, $this->entityTypeId, $this->entityKeys);
    }

    /**
     * Returns the vocabulary machine name (the id).
     */
    public function getVid(): string
    {
        return (string) ($this->id() ?? '');
    }

    /**
     * Returns the human-readable vocabulary name.
     */
    public function getName(): string
    {
        return $this->label();
    }

    /**
     * Sets the human-readable vocabulary name.
     */
    public function setName(string $name): static
    {
        $this->values['name'] = $name;

        return $this;
    }

    /**
     * Returns the vocabulary description.
     */
    public function getDescription(): string
    {
        return (string) ($this->values['description'] ?? '');
    }

    /**
     * Sets the vocabulary description.
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
}

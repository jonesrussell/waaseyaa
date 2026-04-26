<?php

declare(strict_types=1);

namespace Waaseyaa\Note;

use Waaseyaa\Entity\Attribute\ContentEntityKeys;
use Waaseyaa\Entity\Attribute\ContentEntityType;
use Waaseyaa\Entity\ContentEntityBase;

/**
 * Represents a built-in Note entity (core.note).
 *
 * A Note is the minimal default content type shipped with Waaseyaa.
 * It is non-deletable via API — use NoteAccessPolicy to enforce that.
 */
#[ContentEntityType(id: 'note')]
#[ContentEntityKeys(label: 'title')]
final class Note extends ContentEntityBase
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
        parent::__construct($values, $entityTypeId, $entityKeys, $fieldDefinitions);
    }

    public function getTitle(): string
    {
        return (string) ($this->get('title') ?? '');
    }

    public function setTitle(string $title): static
    {
        $this->set('title', $title);

        return $this;
    }

    public function getBody(): string
    {
        return (string) ($this->get('body') ?? '');
    }
}

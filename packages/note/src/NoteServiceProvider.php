<?php

declare(strict_types=1);

namespace Waaseyaa\Note;

use Waaseyaa\Entity\EntityType;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;

final class NoteServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Note's type metadata (id, label, keys, fields) lives on the Note class
        // via #[ContentEntityType], #[ContentEntityKeys], and #[Field] attributes.
        // The defaults/core.note.yaml file remains authoritative for content-seed
        // defaults, not type metadata.
        $this->entityType(EntityType::fromClass(
            Note::class,
            group: 'content',
        ));
    }
}

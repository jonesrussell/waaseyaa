<?php

declare(strict_types=1);

namespace Waaseyaa\Engagement;

use Waaseyaa\Entity\EntityType;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;

final class EngagementServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Type metadata (id, label, keys, fields) lives on each entity class
        // via #[ContentEntityType], #[ContentEntityKeys], and #[Field] attributes.
        $this->entityType(EntityType::fromClass(Reaction::class, group: 'engagement'));
        $this->entityType(EntityType::fromClass(Comment::class, group: 'engagement'));
        $this->entityType(EntityType::fromClass(Follow::class, group: 'engagement'));
    }
}

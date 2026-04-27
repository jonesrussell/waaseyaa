<?php

declare(strict_types=1);

namespace Waaseyaa\Messaging;

use Waaseyaa\Entity\EntityType;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;

final class MessagingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Type metadata (id, label, keys, fields) lives on each entity class
        // via #[ContentEntityType], #[ContentEntityKeys], and #[Field] attributes.
        $this->entityType(EntityType::fromClass(MessageThread::class, group: 'messaging'));
        $this->entityType(EntityType::fromClass(ThreadParticipant::class, group: 'messaging'));
        $this->entityType(EntityType::fromClass(ThreadMessage::class, group: 'messaging'));
    }
}

<?php

declare(strict_types=1);

namespace Waaseyaa\Attachment;

use Waaseyaa\Attachment\Schema\AttachmentSchema;
use Waaseyaa\Database\DatabaseInterface;
use Waaseyaa\Entity\EntityType;
use Waaseyaa\Entity\Repository\EntityRepositoryInterface;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;

/**
 * Service provider for the Attachment package.
 *
 * Registers the Attachment entity type and binds AttachmentRepository.
 * Schema creation (AttachmentSchema::ensureTable()) should be invoked
 * separately during install/migration — see EntitySchemaSync.
 *
 * The access policy (AttachmentAccessPolicy) is shipped in WP06 and is
 * auto-discovered via the #[PolicyAttribute] attribute; no registration
 * is needed here.
 */
final class AttachmentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->entityType(EntityType::fromClass(Attachment::class));

        $this->singleton(AttachmentSchema::class, fn() => new AttachmentSchema(
            $this->resolve(DatabaseInterface::class),
        ));

        $this->singleton(AttachmentRepository::class, fn() => new AttachmentRepository(
            $this->resolve(EntityRepositoryInterface::class),
            $this->resolve(DatabaseInterface::class),
        ));
    }
}

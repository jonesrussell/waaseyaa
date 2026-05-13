<?php

declare(strict_types=1);

namespace Waaseyaa\Migration\Tests\Fixtures;

use Waaseyaa\Access\AccessPolicyInterface;
use Waaseyaa\Access\AccessResult;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityInterface;

/**
 * Forbid-all access policy fixture, scoped to a single entity type.
 *
 * @internal Test fixture only.
 */
final class ForbidAllPolicy implements AccessPolicyInterface
{
    public function __construct(private readonly string $entityTypeId)
    {
    }

    public function access(EntityInterface $entity, string $operation, AccountInterface $account): AccessResult
    {
        return AccessResult::forbidden('denied by fixture');
    }

    public function createAccess(string $entityTypeId, string $bundle, AccountInterface $account): AccessResult
    {
        return AccessResult::forbidden('denied by fixture');
    }

    public function appliesTo(string $entityTypeId): bool
    {
        return $entityTypeId === $this->entityTypeId;
    }
}

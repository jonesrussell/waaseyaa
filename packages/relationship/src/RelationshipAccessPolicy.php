<?php

declare(strict_types=1);

namespace Waaseyaa\Relationship;

use Waaseyaa\Access\AccessPolicyInterface;
use Waaseyaa\Access\AccessResult;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Access\Gate\PolicyAttribute;
use Waaseyaa\Entity\EntityInterface;

#[PolicyAttribute(entityType: 'relationship')]
final class RelationshipAccessPolicy implements AccessPolicyInterface
{
    public function appliesTo(string $entityTypeId): bool
    {
        return $entityTypeId === 'relationship';
    }

    public function access(EntityInterface $entity, string $operation, AccountInterface $account): AccessResult
    {
        if ($account->hasPermission('administer nodes')) {
            return AccessResult::allowed('User has administer nodes permission.');
        }

        $status = (int) ($entity->toArray()['status'] ?? 0);

        return match ($operation) {
            'view' => $status === 1 && $account->hasPermission('access content')
                ? AccessResult::allowed('Published relationship view allowed.')
                : AccessResult::neutral('Relationship view denied.'),
            'update' => $account->hasPermission('edit any relationship content')
                ? AccessResult::allowed('User has edit any relationship content permission.')
                : AccessResult::neutral('Relationship update denied.'),
            'delete' => $account->hasPermission('delete any relationship content')
                ? AccessResult::allowed('User has delete any relationship content permission.')
                : AccessResult::neutral('Relationship delete denied.'),
            default => AccessResult::neutral(),
        };
    }

    public function createAccess(string $entityTypeId, string $bundle, AccountInterface $account): AccessResult
    {
        if ($account->hasPermission('administer nodes')) {
            return AccessResult::allowed('User has administer nodes permission.');
        }

        if ($account->hasPermission('create relationship content')) {
            return AccessResult::allowed('User has create relationship content permission.');
        }

        return AccessResult::neutral('Relationship create denied.');
    }
}

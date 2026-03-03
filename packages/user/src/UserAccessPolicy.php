<?php

declare(strict_types=1);

namespace Waaseyaa\User;

use Waaseyaa\Access\AccessPolicyInterface;
use Waaseyaa\Access\AccessResult;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Access\Gate\PolicyAttribute;
use Waaseyaa\Entity\EntityInterface;

/**
 * Access policy for user entities.
 *
 * - View: active accounts visible with 'access user profiles'; blocked accounts admin-only.
 * - Update: own account always allowed; others require 'administer users'.
 * - Delete: admin-only; self-deletion is forbidden.
 * - Create: admin-only.
 */
#[PolicyAttribute(entityType: 'user')]
final class UserAccessPolicy implements AccessPolicyInterface
{
    public function appliesTo(string $entityTypeId): bool
    {
        return $entityTypeId === 'user';
    }

    public function access(EntityInterface $entity, string $operation, AccountInterface $account): AccessResult
    {
        if ($account->hasPermission('administer users')) {
            // Admin can do everything except delete themselves.
            if ($operation === 'delete' && $account->id() === $entity->id()) {
                return AccessResult::forbidden('Cannot delete own account.');
            }

            return AccessResult::allowed('User has "administer users" permission.');
        }

        assert($entity instanceof User);

        return match ($operation) {
            'view' => $this->viewAccess($entity, $account),
            'update' => $this->updateAccess($entity, $account),
            'delete' => AccessResult::neutral('Only administrators can delete accounts.'),
            default => AccessResult::neutral("No opinion on '$operation' operation."),
        };
    }

    public function createAccess(string $entityTypeId, string $bundle, AccountInterface $account): AccessResult
    {
        if ($account->hasPermission('administer users')) {
            return AccessResult::allowed('User has "administer users" permission.');
        }

        return AccessResult::neutral('Only administrators can create accounts.');
    }

    private function viewAccess(User $user, AccountInterface $account): AccessResult
    {
        if (!$user->isActive()) {
            return AccessResult::neutral('Blocked accounts are only visible to administrators.');
        }

        if ($account->hasPermission('access user profiles')) {
            return AccessResult::allowed('User has "access user profiles" permission.');
        }

        return AccessResult::neutral('User lacks "access user profiles" permission.');
    }

    private function updateAccess(User $user, AccountInterface $account): AccessResult
    {
        if ($account->id() === $user->id()) {
            return AccessResult::allowed('Users can edit their own account.');
        }

        return AccessResult::neutral('Only administrators or account owners can edit accounts.');
    }
}

<?php

declare(strict_types=1);

namespace Waaseyaa\Media;

use Waaseyaa\Access\AccessPolicyInterface;
use Waaseyaa\Access\AccessResult;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityInterface;

/**
 * Access policy for media entities.
 *
 * Mirrors the Node access model: admin bypass, ownership checks for
 * edit/delete, published status for view, bundle-specific permissions.
 */
final class MediaAccessPolicy implements AccessPolicyInterface
{
    public function appliesTo(string $entityTypeId): bool
    {
        return $entityTypeId === 'media';
    }

    public function access(EntityInterface $entity, string $operation, AccountInterface $account): AccessResult
    {
        if ($account->hasPermission('administer media')) {
            return AccessResult::allowed('User has "administer media" permission.');
        }

        assert($entity instanceof Media);

        $bundle = $entity->bundle();
        $isOwner = $entity->getOwnerId() !== null && $account->id() === $entity->getOwnerId();

        return match ($operation) {
            'view' => $this->viewAccess($entity, $account, $isOwner),
            'update' => $this->editAccess($bundle, $account, $isOwner),
            'delete' => $this->deleteAccess($bundle, $account, $isOwner),
            default => AccessResult::neutral("No opinion on '$operation' operation."),
        };
    }

    public function createAccess(string $entityTypeId, string $bundle, AccountInterface $account): AccessResult
    {
        if ($account->hasPermission('administer media')) {
            return AccessResult::allowed('User has "administer media" permission.');
        }

        if ($account->hasPermission("create $bundle media")) {
            return AccessResult::allowed("User has 'create $bundle media' permission.");
        }

        return AccessResult::neutral("User lacks 'create $bundle media' permission.");
    }

    private function viewAccess(Media $media, AccountInterface $account, bool $isOwner): AccessResult
    {
        if ($media->isPublished()) {
            if ($account->hasPermission('access media')) {
                return AccessResult::allowed('Published media and user has "access media" permission.');
            }

            return AccessResult::neutral('User lacks "access media" permission.');
        }

        if ($isOwner && $account->hasPermission('view own unpublished media')) {
            return AccessResult::allowed('Owner viewing own unpublished media.');
        }

        return AccessResult::neutral('User cannot view this unpublished media.');
    }

    private function editAccess(string $bundle, AccountInterface $account, bool $isOwner): AccessResult
    {
        if ($account->hasPermission("edit any $bundle media")) {
            return AccessResult::allowed("User has 'edit any $bundle media' permission.");
        }

        if ($isOwner && $account->hasPermission("edit own $bundle media")) {
            return AccessResult::allowed("Owner has 'edit own $bundle media' permission.");
        }

        return AccessResult::neutral("User lacks edit permission for $bundle media.");
    }

    private function deleteAccess(string $bundle, AccountInterface $account, bool $isOwner): AccessResult
    {
        if ($account->hasPermission("delete any $bundle media")) {
            return AccessResult::allowed("User has 'delete any $bundle media' permission.");
        }

        if ($isOwner && $account->hasPermission("delete own $bundle media")) {
            return AccessResult::allowed("Owner has 'delete own $bundle media' permission.");
        }

        return AccessResult::neutral("User lacks delete permission for $bundle media.");
    }
}

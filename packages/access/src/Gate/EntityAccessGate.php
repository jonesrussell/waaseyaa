<?php

declare(strict_types=1);

namespace Waaseyaa\Access\Gate;

use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Access\EntityAccessHandler;
use Waaseyaa\Entity\EntityInterface;

/**
 * Adapter that bridges GateInterface to EntityAccessHandler.
 *
 * Translates gate ability checks into EntityAccessHandler calls:
 * - Entity subject → check($entity, $ability, $account)
 * - String subject + "create" → checkCreateAccess($entityTypeId, '', $account)
 * - String subject + other ability → denied (instance required)
 */
final class EntityAccessGate implements GateInterface
{
    public function __construct(
        private readonly EntityAccessHandler $handler,
    ) {}

    public function allows(string $ability, mixed $subject, ?object $user = null): bool
    {
        if (!$user instanceof AccountInterface) {
            return false;
        }

        if ($subject instanceof EntityInterface) {
            return $this->handler->check($subject, $ability, $user)->isAllowed();
        }

        if (is_string($subject) && $ability === 'create') {
            return $this->handler->checkCreateAccess($subject, '', $user)->isAllowed();
        }

        return false;
    }

    public function denies(string $ability, mixed $subject, ?object $user = null): bool
    {
        return !$this->allows($ability, $subject, $user);
    }

    public function authorize(string $ability, mixed $subject, ?object $user = null): void
    {
        if ($this->denies($ability, $subject, $user)) {
            throw new AccessDeniedException(ability: $ability, subject: $subject);
        }
    }
}

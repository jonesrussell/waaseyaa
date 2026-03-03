<?php

declare(strict_types=1);

namespace Waaseyaa\Access;

use Waaseyaa\Entity\EntityInterface;

/**
 * Access policy for configuration entity types (node_type, taxonomy_vocabulary, etc.).
 *
 * Grants full access to accounts with the 'administrator' role.
 * Returns neutral for all other accounts, letting deny-by-default semantics
 * reject the request unless another policy grants access.
 */
final class ConfigEntityAccessPolicy implements AccessPolicyInterface
{
    private const ADMIN_ROLE = 'administrator';

    /** @var string[] */
    private readonly array $entityTypeIds;

    /**
     * @param string[] $entityTypeIds Entity type IDs this policy covers.
     */
    public function __construct(array $entityTypeIds)
    {
        if ($entityTypeIds === []) {
            throw new \InvalidArgumentException('At least one entity type ID is required.');
        }
        foreach ($entityTypeIds as $id) {
            if (!is_string($id) || $id === '') {
                throw new \InvalidArgumentException('Each entity type ID must be a non-empty string.');
            }
        }
        $this->entityTypeIds = array_values($entityTypeIds);
    }

    public function appliesTo(string $entityTypeId): bool
    {
        return in_array($entityTypeId, $this->entityTypeIds, true);
    }

    // Config entity access is role-based (not permission-based like NodeAccessPolicy/
    // TermAccessPolicy) because config entities lack granular per-type permissions.
    // Both access() and createAccess() are operation-agnostic: admin gets full access.

    public function access(EntityInterface $entity, string $operation, AccountInterface $account): AccessResult
    {
        return $this->adminCheck($account);
    }

    public function createAccess(string $entityTypeId, string $bundle, AccountInterface $account): AccessResult
    {
        return $this->adminCheck($account);
    }

    private function adminCheck(AccountInterface $account): AccessResult
    {
        if (in_array(self::ADMIN_ROLE, $account->getRoles(), true)) {
            return AccessResult::allowed('Account has administrator role.');
        }

        return AccessResult::neutral('Account lacks administrator role.');
    }
}

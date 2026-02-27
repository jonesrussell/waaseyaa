<?php

declare(strict_types=1);

namespace Aurora\Routing;

use Aurora\Access\AccessResult;
use Aurora\Access\AccountInterface;
use Symfony\Component\Routing\Route;

/**
 * Checks route-level access for an account.
 *
 * Routes can declare access requirements via options:
 *   - '_access_callback' => true — always allow access
 *   - '_permission' => 'administer site' — require a specific permission
 *   - '_role' => 'administrator' — require a specific role (or comma-separated list)
 *
 * Multiple requirements are combined with AND logic (all must pass).
 * If no access requirements are present, returns AccessResult::neutral().
 */
final class AccessChecker
{
    /**
     * Check access for a matched route.
     *
     * @param Route $route The matched route.
     * @param AccountInterface $account The current user account.
     * @return AccessResult The access result.
     */
    public function check(Route $route, AccountInterface $account): AccessResult
    {
        $hasRequirement = false;
        $result = AccessResult::allowed();

        // Check _access_callback option (blanket allow).
        $accessCallback = $route->getOption('_access_callback');
        if ($accessCallback === true) {
            $hasRequirement = true;
            // Remains allowed — no change needed.
        }

        // Check _permission option.
        $permission = $route->getOption('_permission');
        if ($permission !== null) {
            $hasRequirement = true;
            $permResult = $account->hasPermission($permission)
                ? AccessResult::allowed()
                : AccessResult::forbidden("The '{$permission}' permission is required.");
            $result = $result->andIf($permResult);
        }

        // Check _role option (comma-separated list of roles).
        $role = $route->getOption('_role');
        if ($role !== null) {
            $hasRequirement = true;
            $requiredRoles = array_map('trim', explode(',', $role));
            $accountRoles = $account->getRoles();
            $hasRole = !empty(array_intersect($requiredRoles, $accountRoles));
            $roleResult = $hasRole
                ? AccessResult::allowed()
                : AccessResult::forbidden('The required role is missing.');
            $result = $result->andIf($roleResult);
        }

        if (!$hasRequirement) {
            return AccessResult::neutral('No access requirements specified on route.');
        }

        return $result;
    }
}

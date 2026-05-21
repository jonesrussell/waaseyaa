<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Kernel\Bootstrap;

use Waaseyaa\Foundation\Kernel\Bootstrap\Exception\PolicyInstantiationException;

/**
 * Resolves constructor argument values for access policies during kernel boot.
 *
 * The registry calls this interface for each constructor parameter of each
 * #[PolicyAttribute] class discovered in the package manifest. Implementations
 * wrap the kernel's service locator (KernelServicesInterface) but expose a
 * policy-focused API that handles Waaseyaa-specific resolution conventions:
 * - array entity-types (ConfigEntityAccessPolicy shape)
 * - nullable parameters with defaults
 * - scalar parameters with defaults
 * - injected framework services
 *
 * This interface intentionally does NOT import any Symfony-specific container
 * types. It is PSR-11-compatible in semantics (throws on unresolvable) but
 * does not extend or reference PSR-11 interfaces in its signature (NFR-005).
 *
 * @api — Used by AccessPolicyRegistry (M-B) and adopted by M-D's resolver pattern.
 */
interface PolicyDependencyResolverInterface
{
    /**
     * Resolve a single constructor parameter for a policy class being instantiated.
     *
     * Resolution rules (in priority order):
     * 1. If parameter type is `array` → return the entity-types array from the manifest
     *    for this policy class (ConfigEntityAccessPolicy compatibility).
     * 2. If parameter type is a known service interface or class → return the resolved service.
     * 3. If parameter is nullable and the service is unbound → return null.
     * 4. If parameter has a default value and the service is unbound → return the default.
     * 5. Otherwise → throw PolicyInstantiationException.
     *
     * @param class-string         $policyClass  The policy class being instantiated (for error context).
     * @param \ReflectionParameter $param        The constructor parameter to resolve.
     * @param array<string>        $entityTypes  The entity types declared for this policy in the manifest.
     * @return mixed The resolved value: service object, array, scalar, or null.
     * @throws PolicyInstantiationException When the parameter cannot be resolved and has no default.
     */
    public function resolveParameter(string $policyClass, \ReflectionParameter $param, array $entityTypes): mixed;
}

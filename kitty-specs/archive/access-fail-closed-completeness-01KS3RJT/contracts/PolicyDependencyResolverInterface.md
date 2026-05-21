# Contract: PolicyDependencyResolverInterface

**FQCN**: `Waaseyaa\Foundation\Kernel\Bootstrap\PolicyDependencyResolverInterface`
**Layer**: L0 (`packages/foundation/`)
**File**: `packages/foundation/src/Kernel/Bootstrap/PolicyDependencyResolverInterface.php`
**WP**: WP02
**Stability**: `@api` — M-D adopts this interface

---

## Interface Definition

```php
<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Kernel\Bootstrap;

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
     * @param class-string       $policyClass The policy class being instantiated (for error context).
     * @param \ReflectionParameter $param      The constructor parameter to resolve.
     * @param array<string>      $entityTypes The entity types declared for this policy in the manifest.
     * @return mixed The resolved value: service object, array, scalar, or null.
     * @throws PolicyInstantiationException When the parameter cannot be resolved and has no default.
     */
    public function resolveParameter(string $policyClass, \ReflectionParameter $param, array $entityTypes): mixed;
}
```

---

## Implementation: KernelPolicyDependencyResolver

**FQCN**: `Waaseyaa\Foundation\Kernel\Bootstrap\KernelPolicyDependencyResolver`
**File**: `packages/foundation/src/Kernel/Bootstrap/KernelPolicyDependencyResolver.php`

```php
<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Kernel\Bootstrap;

use Waaseyaa\Foundation\ServiceProvider\KernelServicesInterface;

final class KernelPolicyDependencyResolver implements PolicyDependencyResolverInterface
{
    public function __construct(
        private readonly KernelServicesInterface $kernelServices,
    ) {}

    public function resolveParameter(string $policyClass, \ReflectionParameter $param, array $entityTypes): mixed
    {
        $type = $param->getType();
        $typeName = $type instanceof \ReflectionNamedType ? $type->getName() : null;

        // Rule 1: array → entity types (ConfigEntityAccessPolicy shape)
        if ($typeName === 'array') {
            return $entityTypes;
        }

        // Rule 2–4: service resolution
        if ($typeName !== null && !$type->isBuiltin()) {
            $service = $this->kernelServices->get($typeName);
            if ($service !== null) {
                return $service;
            }

            // Rule 3: nullable with unbound service → return null
            if ($param->allowsNull()) {
                return null;
            }

            // Rule 4: has default → use default
            if ($param->isDefaultValueAvailable()) {
                return $param->getDefaultValue();
            }

            // Rule 5: unresolvable
            throw new PolicyInstantiationException(sprintf(
                'Cannot resolve constructor parameter "%s" (type %s) for access policy %s: '
                . 'the service is not bound in the kernel container. '
                . 'Ensure the service is registered in a ServiceProvider::register() before kernel boot.',
                $param->getName(),
                $typeName,
                $policyClass,
            ));
        }

        // Scalar with default
        if ($param->isDefaultValueAvailable()) {
            return $param->getDefaultValue();
        }

        // Nullable scalar
        if ($param->allowsNull()) {
            return null;
        }

        throw new PolicyInstantiationException(sprintf(
            'Cannot resolve constructor parameter "%s" for access policy %s: '
            . 'no type hint and no default value.',
            $param->getName(),
            $policyClass,
        ));
    }
}
```

---

## Exception: PolicyInstantiationException

**FQCN**: `Waaseyaa\Foundation\Kernel\Bootstrap\Exception\PolicyInstantiationException`
**File**: `packages/foundation/src/Kernel/Bootstrap/Exception/PolicyInstantiationException.php`

```php
<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Kernel\Bootstrap\Exception;

/**
 * Thrown when AccessPolicyRegistry cannot instantiate a #[PolicyAttribute] class.
 *
 * This is a boot-time fatal error. Kernel boot fails immediately; no silent logging.
 */
final class PolicyInstantiationException extends \RuntimeException {}
```

---

## Updated AccessPolicyRegistry::discover() Protocol

The registry's two-phase algorithm (see research.md §R-001):

```
Phase 1:
  For each policy class in manifest:
    If constructor requires EntityAccessHandler → defer to phase-2 list
    Else resolve all params via PolicyDependencyResolverInterface → instantiate
    On failure → throw PolicyInstantiationException (no catch-and-continue)

  Build preliminary EntityAccessHandler from phase-1 policies.

Phase 2:
  For each deferred policy class:
    Resolve all params; substitute preliminary EntityAccessHandler where requested
    Instantiate → add to policy list
    On failure → throw PolicyInstantiationException

Build final EntityAccessHandler from (phase-1 + phase-2) policies.
Return final EntityAccessHandler.
```

---

## M-D Reuse Note

Mission M-D (container-resolved resolver protocol for its own registry) SHALL reference
`PolicyDependencyResolverInterface` by its FQCN
`Waaseyaa\Foundation\Kernel\Bootstrap\PolicyDependencyResolverInterface`
and reuse `KernelPolicyDependencyResolver` without modification.
M-D's plan must not re-derive the resolver — it adopts M-B's implementation.

<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Kernel\Bootstrap;

use Waaseyaa\Foundation\Kernel\Bootstrap\Exception\PolicyInstantiationException;

/**
 * Resolver used when no kernel services are available (e.g. ad-hoc manifest
 * scans outside the full kernel boot path). Handles zero-argument and
 * zero-dependency policies correctly; throws for anything that requires a
 * resolved service.
 *
 * @internal — used only as the default fallback inside AccessPolicyRegistry.
 */
final class NullPolicyDependencyResolver implements PolicyDependencyResolverInterface
{
    public function resolveParameter(string $policyClass, \ReflectionParameter $param, array $entityTypes): mixed
    {
        // Rule 1: array param → entity types from manifest.
        $type = $param->getType();
        if ($type instanceof \ReflectionNamedType && $type->getName() === 'array') {
            return $entityTypes;
        }

        // Rule 3: nullable → null.
        if ($param->allowsNull()) {
            return null;
        }

        // Rule 4: has default → use it.
        if ($param->isDefaultValueAvailable()) {
            return $param->getDefaultValue();
        }

        // Rule 5: unresolvable — no kernel services available.
        throw new PolicyInstantiationException(sprintf(
            'Cannot resolve constructor parameter "%s" for access policy %s: '
            . 'no kernel services available (NullPolicyDependencyResolver). '
            . 'This policy requires a full kernel boot.',
            $param->getName(),
            $policyClass,
        ));
    }
}

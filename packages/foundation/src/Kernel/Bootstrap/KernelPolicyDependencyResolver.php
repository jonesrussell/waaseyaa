<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Kernel\Bootstrap;

use Waaseyaa\Access\EntityAccessHandler;
use Waaseyaa\Foundation\Kernel\Bootstrap\Exception\PolicyInstantiationException;
use Waaseyaa\Foundation\ServiceProvider\KernelServicesInterface;

/**
 * Default implementation of PolicyDependencyResolverInterface backed by the
 * kernel's KernelServicesInterface.
 *
 * Supports an optional preliminary EntityAccessHandler for the two-phase
 * discovery algorithm: call setPreliminaryHandler() before phase-2 resolution
 * so deferred policies that request EntityAccessHandler receive the phase-1
 * handler rather than null.
 */
final class KernelPolicyDependencyResolver implements PolicyDependencyResolverInterface
{
    private ?EntityAccessHandler $preliminaryHandler = null;

    public function __construct(
        private readonly KernelServicesInterface $kernelServices,
    ) {}

    /**
     * Set the preliminary EntityAccessHandler for phase-2 deferred policy resolution.
     *
     * Call this before resolving policies that require EntityAccessHandler as a
     * constructor dependency (phase-2 of the two-phase discovery algorithm).
     */
    public function setPreliminaryHandler(EntityAccessHandler $handler): void
    {
        $this->preliminaryHandler = $handler;
    }

    public function resolveParameter(string $policyClass, \ReflectionParameter $param, array $entityTypes): mixed
    {
        $type = $param->getType();
        $typeName = $type instanceof \ReflectionNamedType ? $type->getName() : null;

        // Rule 1: array → entity types (ConfigEntityAccessPolicy shape)
        if ($typeName === 'array') {
            return $entityTypes;
        }

        // Rule 2–4: service resolution for non-builtin types
        if ($typeName !== null && !$type->isBuiltin()) {
            // Special case: EntityAccessHandler requested during phase-2
            // → return the preliminary handler built from phase-1 policies.
            if ($typeName === EntityAccessHandler::class && $this->preliminaryHandler !== null) {
                return $this->preliminaryHandler;
            }

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

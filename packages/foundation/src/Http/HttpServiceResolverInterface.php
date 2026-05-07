<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Http;

/**
 * Typed resolver handed to SSR ({@see \Waaseyaa\Ssr\SsrPageHandler}) for
 * controller-method dependency resolution.
 *
 * Distinct from {@see \Waaseyaa\Foundation\ServiceProvider\KernelServicesInterface},
 * which is a finite, kernel-owned bus. This contract describes an open-ended
 * lookup driven by user-authored app-controller method signatures: SSR receives
 * a class name from a `\ReflectionNamedType` parameter and asks the kernel to
 * supply an instance.
 *
 * Implementations walk the registered providers' bindings and may apply a
 * narrow kernel-owned fallback (e.g. {@see \Waaseyaa\Database\DatabaseInterface})
 * via {@see \Waaseyaa\Foundation\ServiceProvider\KernelServicesInterface} — not a
 * parallel resolution map.
 *
 * Returning `null` is part of the contract: callers decide whether the missing
 * dependency is a hard error or a tolerated injection point.
 */
interface HttpServiceResolverInterface
{
    /**
     * Resolve a class name to an instance.
     *
     * @return object|null The resolved service, or `null` when no provider has
     *                     bound the class and no kernel fallback applies.
     */
    public function resolve(string $className): ?object;
}

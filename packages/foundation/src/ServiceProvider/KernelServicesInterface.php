<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\ServiceProvider;

/**
 * Typed kernel-services resolver handed to {@see ServiceProvider} instances
 * during registration via {@see ServiceProvider::setKernelServices()}.
 *
 * Implementations resolve framework-core services that the kernel owns
 * (`EntityTypeManager`, `DatabaseInterface`, event dispatcher, logger, `\PDO`)
 * plus any binding declared by a sibling provider in the same registration
 * pass.
 *
 * Returning `null` is part of the contract: {@see ServiceProvider::resolve()}
 * tries its own bindings first, then asks the kernel, then throws.
 */
interface KernelServicesInterface
{
    /**
     * Resolve an abstract by class or service id.
     *
     * @return object|null The resolved service, or `null` when the kernel does
     *                     not know the abstract and no sibling provider has
     *                     bound it.
     */
    public function get(string $abstract): ?object;
}

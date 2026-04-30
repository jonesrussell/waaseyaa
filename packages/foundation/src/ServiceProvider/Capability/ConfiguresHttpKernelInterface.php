<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\ServiceProvider\Capability;

use Waaseyaa\Foundation\Kernel\HttpKernel;

/**
 * Capability marker for service providers that perform late HTTP wiring after
 * database caches and other foundation services exist.
 *
 * Providers opt in by declaring `implements ConfiguresHttpKernelInterface`;
 * `Waaseyaa\Foundation\Kernel\HttpKernel::finalizeBoot()` checks `instanceof`
 * before invoking `configureHttpKernel()`, so the abstract
 * `Waaseyaa\Foundation\ServiceProvider\ServiceProvider` no longer carries an
 * unused no-op default. Verb-led name (Configures*, not Has*) because this
 * hook mutates the kernel rather than contributing a list of values.
 *
 * Canonical use cases: SSR constructs `SsrPageHandler` here once render-cache
 * and inertia renderers exist; Genealogy primes static service references on
 * `GenealogyBootstrap` for legacy callers without constructor DI.
 *
 * Locked in step with the kernel call site by
 * `packages/foundation/tests/Contract/ServiceProviderContractTest.php`
 * (mission #824 WP03 surface G).
 */
interface ConfiguresHttpKernelInterface
{
    public function configureHttpKernel(HttpKernel $kernel): void;
}

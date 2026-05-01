<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\ServiceProvider\Capability;

use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Waaseyaa\Cache\CacheBackendInterface;

/**
 * Capability marker for service providers that subscribe render-cache entity
 * listeners (cache invalidation on entity lifecycle events).
 *
 * Providers opt in by declaring `implements HasRenderCacheListenersInterface`;
 * `Waaseyaa\Foundation\Kernel\HttpKernel` checks `instanceof` before invoking
 * `registerRenderCacheListeners()` during `finalizeBoot`, so the abstract
 * `Waaseyaa\Foundation\ServiceProvider\ServiceProvider` no longer carries an
 * unused no-op default. SSR is the canonical implementor — it wraps the
 * render-bin backend in a `RenderCache` and subscribes listeners to entity
 * insert/update/delete events.
 *
 * The `$renderCacheBackend` argument is nullable: HttpKernel passes the
 * resolved render-cache bin or `null` if no cache is configured. Implementors
 * decide whether to no-op when null or wire a fallback backend.
 *
 * Locked in step with the kernel call site by
 * `packages/foundation/tests/Contract/ServiceProviderContractTest.php`
 * (mission #824 WP03 surface F).
 */
interface HasRenderCacheListenersInterface
{
    public function registerRenderCacheListeners(
        EventDispatcherInterface $dispatcher,
        ?CacheBackendInterface $renderCacheBackend,
    ): void;
}

<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\ServiceProvider\Capability;

use Waaseyaa\Foundation\Http\Router\DomainRouterInterface;
use Waaseyaa\Foundation\Kernel\HttpKernel;

/**
 * Capability marker for service providers that contribute domain routers to
 * the HTTP kernel's router chain.
 *
 * Providers opt in by declaring `implements HasHttpDomainRoutersInterface`;
 * `Waaseyaa\Foundation\Kernel\HttpKernel::buildDomainRouterChain()` checks
 * `instanceof` before invoking `httpDomainRouters()`, so the abstract
 * `Waaseyaa\Foundation\ServiceProvider\ServiceProvider` no longer carries an
 * unused no-op default. Provider-supplied routers are merged after the
 * foundation built-ins (through `McpRouter`) and before the terminal
 * `BroadcastRouter`.
 *
 * The hook receives the live `HttpKernel` so domain routers can pull cache
 * bins, the access handler, or other late-resolved services they need to
 * construct themselves; routers should not perform side effects here, only
 * instantiate.
 *
 * Locked in step with the kernel call site by
 * `packages/foundation/tests/Contract/ServiceProviderContractTest.php`
 * (mission #824 WP03 surface I — the final capability split).
 */
interface HasHttpDomainRoutersInterface
{
    /**
     * Return domain router instances to merge into the kernel router chain.
     *
     * @return iterable<DomainRouterInterface>
     */
    public function httpDomainRouters(HttpKernel $httpKernel): iterable;
}

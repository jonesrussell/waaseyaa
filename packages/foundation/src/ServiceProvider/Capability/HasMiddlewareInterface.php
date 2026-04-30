<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\ServiceProvider\Capability;

use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Foundation\Middleware\HttpMiddlewareInterface;

/**
 * Capability marker for service providers that contribute HTTP middleware
 * instances to the kernel pipeline.
 *
 * Providers opt in by declaring `implements HasMiddlewareInterface`;
 * `Waaseyaa\Foundation\Kernel\HttpKernel::buildMiddlewarePipeline()` checks
 * `instanceof` before invoking `middleware()`, so the abstract
 * `Waaseyaa\Foundation\ServiceProvider\ServiceProvider` no longer carries an
 * unused no-op default.
 *
 * Returned middleware classes typically annotate their pipeline placement and
 * priority via `#[AsMiddleware]`; the kernel sorts the merged pipeline after
 * collection. Implementors should return middleware instances, not class
 * names, so the kernel never has to resolve them through the container.
 *
 * Locked in step with the kernel call site by
 * `packages/foundation/tests/Contract/ServiceProviderContractTest.php`
 * (mission #824 WP03 surface H).
 */
interface HasMiddlewareInterface
{
    /**
     * Return HTTP middleware instances to merge into the kernel pipeline.
     *
     * @return list<HttpMiddlewareInterface>
     */
    public function middleware(EntityTypeManager $entityTypeManager): array;
}

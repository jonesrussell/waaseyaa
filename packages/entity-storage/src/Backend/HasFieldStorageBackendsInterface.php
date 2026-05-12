<?php

declare(strict_types=1);

namespace Waaseyaa\EntityStorage\Backend;

/**
 * @api
 *
 * Provider capability marker — packages that ship field storage backends
 * implement this interface on their service provider and return their
 * backend instances.
 *
 * Discovery is via Composer installed.json order; no service locator.
 * {@see BackendRegistrar} discovers all providers implementing this interface.
 */
interface HasFieldStorageBackendsInterface
{
    /**
     * @return list<FieldStorageBackendInterface>
     */
    public function fieldStorageBackends(): array;
}

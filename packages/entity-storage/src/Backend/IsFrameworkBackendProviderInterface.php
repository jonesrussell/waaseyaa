<?php

declare(strict_types=1);

namespace Waaseyaa\EntityStorage\Backend;

/**
 * @api
 *
 * Marker interface that designates a {@see HasFieldStorageBackendsInterface}
 * provider as a framework-owned provider, granting it the right to register
 * backends under reserved ids (sql-blob, sql-column, vector).
 *
 * Third-party providers MUST NOT implement this interface. Only providers
 * shipped inside the `waaseyaa/*` package namespace should implement it.
 * {@see BackendRegistrarFactory} checks for this interface via instanceof
 * after instantiating each provider.
 *
 * Design note: using an interface marker (rather than a composer.json flag)
 * keeps the framework-provider designation self-contained in PHP and visible
 * to static analysis tools without requiring manifest parsing at the factory
 * layer.
 */
interface IsFrameworkBackendProviderInterface extends HasFieldStorageBackendsInterface {}

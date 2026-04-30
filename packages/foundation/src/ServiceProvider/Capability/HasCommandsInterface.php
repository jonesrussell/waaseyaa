<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\ServiceProvider\Capability;

use Symfony\Component\Console\Command\Command;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Waaseyaa\Database\DatabaseInterface;
use Waaseyaa\Entity\EntityTypeManager;

/**
 * Capability marker for service providers that contribute Symfony Console
 * commands to the CLI application.
 *
 * Providers opt in by declaring `implements HasCommandsInterface`;
 * `Waaseyaa\Foundation\Kernel\ConsoleKernel::handle()` checks `instanceof`
 * before invoking `commands()`, so the abstract
 * `Waaseyaa\Foundation\ServiceProvider\ServiceProvider` no longer carries an
 * unused no-op default. Surfaces this capability the same way
 * `LanguagePathStripperInterface` surfaces optional HTTP path stripping.
 *
 * Locked in step with the kernel call site by
 * `packages/foundation/tests/Contract/ServiceProviderContractTest.php`
 * (mission #824 WP03 surface E).
 */
interface HasCommandsInterface
{
    /**
     * Return CLI commands to register with the console application.
     *
     * @return list<Command>
     */
    public function commands(
        EntityTypeManager $entityTypeManager,
        DatabaseInterface $database,
        EventDispatcherInterface $dispatcher,
    ): array;
}

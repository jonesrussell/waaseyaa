<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Compat;

/**
 * @internal Temporary dual-boot bridge. Deleted in WP23 (mission native-cli-kernel-01KR2NR7).
 *           Do NOT depend on this from application code.
 */

use Waaseyaa\CLI\CommandRegistry;
use Waaseyaa\CLI\Exception\DuplicateCommandException;
use Waaseyaa\Database\DatabaseInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Foundation\Event\EventDispatcherInterface;
use Waaseyaa\Foundation\Log\LoggerInterface;
use Waaseyaa\Foundation\Log\NullLogger;
use Waaseyaa\Foundation\ServiceProvider\Capability\HasCommandsInterface;

/**
 * Iterates providers that implement the legacy HasCommandsInterface, adapts each
 * Symfony Command to a native CommandDefinition, and registers it into the registry.
 *
 * Registered AFTER native providers so that name collisions surface as
 * DuplicateCommandException — a command must be ported, not double-registered.
 *
 * @internal Temporary dual-boot bridge. Deleted in WP23 (mission native-cli-kernel-01KR2NR7).
 *           Do NOT depend on this from application code.
 */
final class LegacySymfonyCommandRegistrar
{
    /**
     * Register all legacy Symfony commands from HasCommandsInterface providers.
     *
     * @param list<object> $providers  All booted service provider instances.
     */
    public static function registerAll(
        CommandRegistry $registry,
        array $providers,
        EntityTypeManager $entityTypeManager,
        DatabaseInterface $database,
        EventDispatcherInterface $dispatcher,
        ?LoggerInterface $logger = null,
    ): void {
        $logger ??= new NullLogger();

        foreach ($providers as $provider) {
            if (!$provider instanceof HasCommandsInterface) {
                continue;
            }

            $commands = $provider->commands($entityTypeManager, $database, $dispatcher);

            foreach ($commands as $symfonyCommand) {
                try {
                    $definition = LegacySymfonyCommandAdapter::adapt($symfonyCommand);
                    $registry->register($definition);
                } catch (DuplicateCommandException $e) {
                    // T024: propagate — a duplicate means a port is half-done.
                    // The caller must fix the collision, not silently skip it.
                    throw $e;
                } catch (\Throwable $e) {
                    $logger->warning(sprintf(
                        '[compat] Could not adapt legacy command from provider "%s": %s',
                        $provider::class,
                        $e->getMessage(),
                    ));
                }
            }
        }
    }
}

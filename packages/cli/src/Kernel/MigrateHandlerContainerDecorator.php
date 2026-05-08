<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Kernel;

use Psr\Container\ContainerInterface;
use Waaseyaa\CLI\Handler\MigrateHandler;
use Waaseyaa\CLI\Handler\MigrateRollbackHandler;
use Waaseyaa\CLI\Handler\MigrateStatusHandler;
use Waaseyaa\Foundation\Kernel\AbstractKernel;

/**
 * Decorates the kernel handler container so migrate commands resolve
 * {@see MigrateHandler} and siblings with explicit {@see \Waaseyaa\Foundation\Migration\Migrator}
 * + migration-loader closures — they cannot be reflection-autowired (Migrator depends on
 * DBAL {@see \Doctrine\DBAL\Connection}, whose constructor is not container-friendly).
 */
final class MigrateHandlerContainerDecorator implements ContainerInterface
{
    public function __construct(
        private readonly ContainerInterface $inner,
        private readonly AbstractKernel $kernel,
    ) {}

    public function get(string $id): object
    {
        return match ($id) {
            MigrateHandler::class => new MigrateHandler(
                $this->kernel->getMigrator(),
                fn(): array => $this->kernel->getMigrationLoader()->loadAll(),
                fn(): array => $this->kernel->getMigrationLoader()->loadAllV2(),
            ),
            MigrateRollbackHandler::class => new MigrateRollbackHandler(
                $this->kernel->getMigrator(),
                fn(): array => $this->kernel->getMigrationLoader()->loadAll(),
            ),
            MigrateStatusHandler::class => new MigrateStatusHandler(
                $this->kernel->getMigrator(),
                fn(): array => $this->kernel->getMigrationLoader()->loadAll(),
            ),
            default => $this->inner->get($id),
        };
    }

    public function has(string $id): bool
    {
        return match ($id) {
            MigrateHandler::class,
            MigrateRollbackHandler::class,
            MigrateStatusHandler::class => true,
            default => $this->inner->has($id),
        };
    }
}

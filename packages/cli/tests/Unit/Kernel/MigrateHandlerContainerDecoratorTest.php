<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Tests\Unit\Kernel;

use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Waaseyaa\CLI\Handler\MigrateHandler;
use Waaseyaa\CLI\Handler\MigrateRollbackHandler;
use Waaseyaa\CLI\Handler\MigrateStatusHandler;
use Waaseyaa\CLI\Kernel\MigrateHandlerContainerDecorator;
use Waaseyaa\Foundation\Discovery\PackageManifest;
use Waaseyaa\Foundation\Kernel\AbstractKernel;
use Waaseyaa\Foundation\Migration\MigrationLoader;
use Waaseyaa\Foundation\Migration\MigrationRepository;
use Waaseyaa\Foundation\Migration\Migrator;

#[CoversClass(MigrateHandlerContainerDecorator::class)]
final class MigrateHandlerContainerDecoratorTest extends TestCase
{
    #[Test]
    public function it_resolves_migrate_handlers_without_touching_inner_for_those_ids(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $repository = new MigrationRepository($connection);
        $repository->createTable();
        $migrator = new Migrator($connection, $repository);

        $loader = new MigrationLoader(
            sys_get_temp_dir() . '/waaseyaa_cli_migrate_decorator_' . uniqid(),
            new PackageManifest(),
        );

        $kernel = $this->getMockBuilder(AbstractKernel::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getMigrator', 'getMigrationLoader'])
            ->getMock();
        $kernel->method('getMigrator')->willReturn($migrator);
        $kernel->method('getMigrationLoader')->willReturn($loader);

        $inner = new class implements ContainerInterface {
            public function get(string $id): object
            {
                throw new \RuntimeException('inner should not resolve migrate handlers');
            }

            public function has(string $id): bool
            {
                return false;
            }
        };

        $decorator = new MigrateHandlerContainerDecorator($inner, $kernel);

        self::assertInstanceOf(MigrateHandler::class, $decorator->get(MigrateHandler::class));
        self::assertInstanceOf(MigrateRollbackHandler::class, $decorator->get(MigrateRollbackHandler::class));
        self::assertInstanceOf(MigrateStatusHandler::class, $decorator->get(MigrateStatusHandler::class));

        self::assertTrue($decorator->has(MigrateHandler::class));
    }
}

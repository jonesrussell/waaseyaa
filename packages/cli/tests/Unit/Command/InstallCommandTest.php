<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Tests\Unit\Command;

use Waaseyaa\CLI\Command\InstallCommand;
use Waaseyaa\Config\ConfigManagerInterface;
use Waaseyaa\Config\StorageInterface;
use Waaseyaa\Entity\EntityInterface;
use Waaseyaa\Entity\EntityTypeManagerInterface;
use Waaseyaa\Entity\Storage\EntityStorageInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(InstallCommand::class)]
class InstallCommandTest extends TestCase
{
    #[Test]
    public function it_installs_with_default_site_name(): void
    {
        $mockStorage = $this->createMock(StorageInterface::class);
        $mockStorage->expects($this->once())
            ->method('write')
            ->with('system.site', $this->callback(function (array $data): bool {
                return $data['name'] === 'Waaseyaa' && $data['mail'] === 'admin@example.com';
            }));

        $mockConfigManager = $this->createMock(ConfigManagerInterface::class);
        $mockConfigManager->method('getActiveStorage')->willReturn($mockStorage);

        $mockEntity = $this->createMock(EntityInterface::class);
        $mockEntity->method('id')->willReturn(1);

        $mockEntityStorage = $this->createMock(EntityStorageInterface::class);
        $mockEntityStorage->expects($this->once())
            ->method('create')
            ->with($this->callback(function (array $values): bool {
                return $values['name'] === 'admin'
                    && $values['roles'] === ['administrator'];
            }))
            ->willReturn($mockEntity);
        $mockEntityStorage->expects($this->once())->method('save');

        $mockEntityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
        $mockEntityTypeManager->method('getStorage')
            ->with('user')
            ->willReturn($mockEntityStorage);

        $app = new Application();
        $app->add(new InstallCommand($mockEntityTypeManager, $mockConfigManager));
        $command = $app->find('install');
        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $display = $tester->getDisplay();
        $this->assertStringContainsString('Warning: No --admin-password provided', $display);
        $this->assertStringContainsString('Writing initial site configuration...', $display);
        $this->assertStringContainsString('Creating admin user...', $display);
        $this->assertStringContainsString('Waaseyaa "Waaseyaa" installed successfully.', $display);
    }

    #[Test]
    public function it_installs_with_custom_site_name(): void
    {
        $mockStorage = $this->createMock(StorageInterface::class);
        $mockStorage->expects($this->once())
            ->method('write')
            ->with('system.site', $this->callback(function (array $data): bool {
                return $data['name'] === 'My Site';
            }));

        $mockConfigManager = $this->createMock(ConfigManagerInterface::class);
        $mockConfigManager->method('getActiveStorage')->willReturn($mockStorage);

        $mockEntity = $this->createMock(EntityInterface::class);

        $mockEntityStorage = $this->createMock(EntityStorageInterface::class);
        $mockEntityStorage->method('create')->willReturn($mockEntity);

        $mockEntityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
        $mockEntityTypeManager->method('getStorage')->willReturn($mockEntityStorage);

        $app = new Application();
        $app->add(new InstallCommand($mockEntityTypeManager, $mockConfigManager));
        $command = $app->find('install');
        $tester = new CommandTester($command);
        $tester->execute(['--site-name' => 'My Site']);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('Waaseyaa "My Site" installed successfully.', $tester->getDisplay());
    }
}

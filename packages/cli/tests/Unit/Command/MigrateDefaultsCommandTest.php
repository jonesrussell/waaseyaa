<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Tests\Unit\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Waaseyaa\CLI\Command\MigrateDefaultsCommand;
use Waaseyaa\Entity\Audit\EntityAuditLogger;
use Waaseyaa\Entity\EntityType;
use Waaseyaa\Entity\EntityTypeLifecycleManager;
use Waaseyaa\Entity\EntityTypeManager;

#[CoversClass(MigrateDefaultsCommand::class)]
final class MigrateDefaultsCommandTest extends TestCase
{
    private string $tempDir;
    private EntityTypeLifecycleManager $lifecycle;
    private EntityTypeManager $entityTypeManager;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/waaseyaa_cli_migrate_test_' . uniqid();
        mkdir($this->tempDir . '/storage/framework', 0755, true);

        $this->lifecycle = new EntityTypeLifecycleManager($this->tempDir);

        $this->entityTypeManager = new EntityTypeManager(new EventDispatcher());
        $this->entityTypeManager->registerEntityType(new EntityType(
            id: 'note',
            label: 'Note',
            class: \stdClass::class,
            keys: ['id' => 'id'],
        ));
        $this->entityTypeManager->registerEntityType(new EntityType(
            id: 'article',
            label: 'Article',
            class: \stdClass::class,
            keys: ['id' => 'id'],
        ));
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->tempDir . '/storage/framework/*') ?: []);
        @rmdir($this->tempDir . '/storage/framework');
        @rmdir($this->tempDir . '/storage');
        @rmdir($this->tempDir);
    }

    #[Test]
    public function migrateEnablesTypeForTenantWithoutEnabledTypes(): void
    {
        $this->lifecycle->disable('note', 'setup', 'acme');
        $this->lifecycle->disable('article', 'setup', 'acme');

        $tester = $this->runCommand([
            '--tenant' => ['acme'],
            '--enable' => 'note',
            '--yes' => true,
        ]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertFalse($this->lifecycle->isDisabled('note', 'acme'));
    }

    #[Test]
    public function rollbackDisablesPreviouslyEnabledType(): void
    {
        $this->lifecycle->disable('note', 'setup', 'acme');
        $this->lifecycle->disable('article', 'setup', 'acme');

        $this->runCommand([
            '--tenant' => ['acme'],
            '--enable' => 'note',
            '--yes' => true,
        ]);

        $tester = $this->runCommand([
            '--tenant' => ['acme'],
            '--rollback' => true,
            '--yes' => true,
        ]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertTrue($this->lifecycle->isDisabled('note', 'acme'));
    }

    #[Test]
    public function dryRunDoesNotMutateState(): void
    {
        $this->lifecycle->disable('note', 'setup', 'acme');
        $this->lifecycle->disable('article', 'setup', 'acme');

        $tester = $this->runCommand([
            '--tenant' => ['acme'],
            '--enable' => 'note',
            '--yes' => true,
            '--dry-run' => true,
        ]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('[dry-run]', $tester->getDisplay());
        $this->assertTrue($this->lifecycle->isDisabled('note', 'acme'));
    }

    /**
     * @param array<string, mixed> $input
     */
    private function runCommand(array $input): CommandTester
    {
        $app = new Application();
        $app->add(new MigrateDefaultsCommand(
            $this->entityTypeManager,
            $this->lifecycle,
            new EntityAuditLogger($this->tempDir),
            $this->tempDir,
        ));

        $command = $app->find('migrate:defaults');
        $tester = new CommandTester($command);
        $tester->execute($input);

        return $tester;
    }
}

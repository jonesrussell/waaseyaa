<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Tests\Unit\Command;

use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Waaseyaa\CLI\Command\DbInitCommand;

#[CoversClass(DbInitCommand::class)]
final class DbInitCommandTest extends TestCase
{
    private string $projectRoot;

    protected function setUp(): void
    {
        $this->projectRoot = sys_get_temp_dir() . '/waaseyaa-db-init-' . bin2hex(random_bytes(6));
        mkdir($this->projectRoot, 0o755, true);
        mkdir($this->projectRoot . '/config', 0o755, true);
        mkdir($this->projectRoot . '/storage', 0o755, true);
        file_put_contents(
            $this->projectRoot . '/config/waaseyaa.php',
            "<?php return ['environment' => 'production', 'database' => '" . $this->projectRoot . "/storage/waaseyaa.sqlite'];\n",
        );
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->projectRoot);
    }

    #[Test]
    public function fresh_volume_creates_file_and_succeeds(): void
    {
        $dbPath = $this->projectRoot . '/storage/waaseyaa.sqlite';
        $this->assertFileDoesNotExist($dbPath);

        $tester = new CommandTester(new DbInitCommand($this->projectRoot));
        $exit = $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $exit);
        $this->assertFileExists($dbPath);
        $this->assertStringContainsString('Created database', $tester->getDisplay());
        $this->assertStringContainsString('Database ready', $tester->getDisplay());

        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'path' => $dbPath]);
        $this->assertTrue($connection->createSchemaManager()->tablesExist(['waaseyaa_migrations']));
    }

    #[Test]
    public function rerun_on_initialized_database_is_idempotent(): void
    {
        $command = new DbInitCommand($this->projectRoot);
        (new CommandTester($command))->execute([]);

        $tester = new CommandTester(new DbInitCommand($this->projectRoot));
        $exit = $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $exit);
        $this->assertStringContainsString('Database already present', $tester->getDisplay());
        $this->assertStringContainsString('No pending migrations', $tester->getDisplay());
    }

    #[Test]
    public function partially_initialized_database_is_refused(): void
    {
        $dbPath = $this->projectRoot . '/storage/waaseyaa.sqlite';
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'path' => $dbPath]);
        $connection->executeStatement('CREATE TABLE unrelated_stuff (id INTEGER PRIMARY KEY)');
        $connection->close();

        $tester = new CommandTester(new DbInitCommand($this->projectRoot));
        $exit = $tester->execute([]);

        $this->assertSame(Command::FAILURE, $exit);
        $this->assertStringContainsString('does not look Waaseyaa-initialized', $tester->getDisplay());
        $this->assertStringContainsString('Move the file aside', $tester->getDisplay());

        $check = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'path' => $dbPath]);
        $this->assertFalse($check->createSchemaManager()->tablesExist(['waaseyaa_migrations']));
    }

    #[Test]
    public function dry_run_on_fresh_volume_makes_no_changes(): void
    {
        $dbPath = $this->projectRoot . '/storage/waaseyaa.sqlite';

        $tester = new CommandTester(new DbInitCommand($this->projectRoot));
        $exit = $tester->execute(['--dry-run' => true]);

        $this->assertSame(Command::SUCCESS, $exit);
        $this->assertFileDoesNotExist($dbPath);
        $this->assertStringContainsString('--dry-run', $tester->getDisplay());
        $this->assertStringContainsString('absent (would be created)', $tester->getDisplay());
        $this->assertStringContainsString('Would run all pending migrations', $tester->getDisplay());
    }

    #[Test]
    public function dry_run_on_initialized_database_reports_pending(): void
    {
        (new CommandTester(new DbInitCommand($this->projectRoot)))->execute([]);

        $tester = new CommandTester(new DbInitCommand($this->projectRoot));
        $exit = $tester->execute(['--dry-run' => true]);

        $this->assertSame(Command::SUCCESS, $exit);
        $this->assertStringContainsString('present and initialized', $tester->getDisplay());
        $this->assertStringContainsString('No pending migrations', $tester->getDisplay());
    }

    #[Test]
    public function dry_run_on_partial_database_reports_refusal(): void
    {
        $dbPath = $this->projectRoot . '/storage/waaseyaa.sqlite';
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'path' => $dbPath]);
        $connection->executeStatement('CREATE TABLE unrelated_stuff (id INTEGER PRIMARY KEY)');
        $connection->close();

        $tester = new CommandTester(new DbInitCommand($this->projectRoot));
        $exit = $tester->execute(['--dry-run' => true]);

        $this->assertSame(Command::FAILURE, $exit);
        $this->assertStringContainsString('not Waaseyaa-initialized', $tester->getDisplay());
    }

    #[Test]
    public function parent_directory_not_writable_fails_with_clear_message(): void
    {
        if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
            $this->markTestSkipped('Permission enforcement is bypassed for root.');
        }

        $storage = $this->projectRoot . '/storage';
        chmod($storage, 0o500);

        try {
            $tester = new CommandTester(new DbInitCommand($this->projectRoot));
            $exit = $tester->execute([]);

            $this->assertSame(Command::FAILURE, $exit);
            $display = $tester->getDisplay();
            $this->assertStringContainsString('not writable', $display);
            $this->assertStringContainsString($storage, $display);
            $this->assertStringContainsString('user:', $display);
        } finally {
            chmod($storage, 0o755);
        }
    }

    #[Test]
    public function concurrent_invocation_bails_fast(): void
    {
        $storage = $this->projectRoot . '/storage';
        $lockPath = $storage . '/.db-init.lock';
        $holder = fopen($lockPath, 'c');
        $this->assertNotFalse($holder);
        $this->assertTrue(flock($holder, LOCK_EX | LOCK_NB));

        try {
            $tester = new CommandTester(new DbInitCommand($this->projectRoot));
            $exit = $tester->execute([]);

            $this->assertSame(Command::FAILURE, $exit);
            $this->assertStringContainsString('Another db:init is in progress', $tester->getDisplay());
        } finally {
            flock($holder, LOCK_UN);
            fclose($holder);
        }
    }

    #[Test]
    public function respects_waaseyaa_db_env_var_when_config_unset(): void
    {
        file_put_contents(
            $this->projectRoot . '/config/waaseyaa.php',
            "<?php return ['environment' => 'production', 'database' => null];\n",
        );

        $customPath = $this->projectRoot . '/storage/custom.sqlite';
        putenv('WAASEYAA_DB=' . $customPath);

        try {
            $tester = new CommandTester(new DbInitCommand($this->projectRoot));
            $exit = $tester->execute([]);

            $this->assertSame(Command::SUCCESS, $exit);
            $this->assertFileExists($customPath);
        } finally {
            putenv('WAASEYAA_DB');
        }
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            $filePath = $file->getPathname();
            if ($file->isDir()) {
                @chmod($filePath, 0o755);
                @rmdir($filePath);
            } else {
                @chmod($filePath, 0o644);
                @unlink($filePath);
            }
        }
        @chmod($path, 0o755);
        @rmdir($path);
    }
}

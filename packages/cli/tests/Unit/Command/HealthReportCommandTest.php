<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Tests\Unit\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Waaseyaa\CLI\Command\HealthReportCommand;
use Waaseyaa\Foundation\Diagnostic\HealthCheckerInterface;
use Waaseyaa\Foundation\Diagnostic\HealthCheckResult;

#[CoversClass(HealthReportCommand::class)]
final class HealthReportCommandTest extends TestCase
{
    private string $projectRoot;

    protected function setUp(): void
    {
        $this->projectRoot = sys_get_temp_dir() . '/waaseyaa_report_test_' . uniqid();
        mkdir($this->projectRoot . '/storage/framework', 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->projectRoot);
    }

    #[Test]
    public function tableOutputIncludesAllSections(): void
    {
        $checker = $this->createMock(HealthCheckerInterface::class);
        $checker->method('runAll')->willReturn([
            HealthCheckResult::pass('Database', 'OK'),
        ]);

        $tester = $this->createTester($checker);
        $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('System Information', $output);
        $this->assertStringContainsString('Health Checks', $output);
        $this->assertStringContainsString('PHP Version', $output);
    }

    #[Test]
    public function jsonOutputProducesValidJson(): void
    {
        $checker = $this->createMock(HealthCheckerInterface::class);
        $checker->method('runAll')->willReturn([
            HealthCheckResult::pass('Database', 'OK'),
        ]);

        $tester = $this->createTester($checker);
        $tester->execute(['--json' => true]);

        $decoded = json_decode($tester->getDisplay(), true);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('generated_at', $decoded);
        $this->assertArrayHasKey('system', $decoded);
        $this->assertArrayHasKey('health_checks', $decoded);
        $this->assertArrayHasKey('ingestion_summary', $decoded);
    }

    #[Test]
    public function outputOptionWritesToFile(): void
    {
        $checker = $this->createMock(HealthCheckerInterface::class);
        $checker->method('runAll')->willReturn([
            HealthCheckResult::pass('Database', 'OK'),
        ]);

        $outputFile = $this->projectRoot . '/report.json';

        $tester = $this->createTester($checker);
        $tester->execute(['--json' => true, '--output' => $outputFile]);

        $this->assertFileExists($outputFile);
        $decoded = json_decode(file_get_contents($outputFile), true);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('system', $decoded);
    }

    private function createTester(HealthCheckerInterface $checker): CommandTester
    {
        $app = new Application();
        $app->add(new HealthReportCommand($checker, $this->projectRoot));
        $command = $app->find('health:report');
        return new CommandTester($command);
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($dir);
    }
}

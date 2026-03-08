<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Tests\Unit\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Waaseyaa\CLI\Command\HealthCheckCommand;
use Waaseyaa\Foundation\Diagnostic\DiagnosticCode;
use Waaseyaa\Foundation\Diagnostic\HealthCheckerInterface;
use Waaseyaa\Foundation\Diagnostic\HealthCheckResult;

#[CoversClass(HealthCheckCommand::class)]
final class HealthCheckCommandTest extends TestCase
{
    #[Test]
    public function allPassingReturnsSuccessAndTable(): void
    {
        $checker = $this->createMock(HealthCheckerInterface::class);
        $checker->method('runAll')->willReturn([
            HealthCheckResult::pass('Database', 'DB is accessible.'),
            HealthCheckResult::pass('Entity types', '3 types registered.'),
        ]);

        $tester = $this->createTester($checker);
        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Database', $output);
        $this->assertStringContainsString('All health checks passed', $output);
    }

    #[Test]
    public function warningsReturnExitCode1(): void
    {
        $checker = $this->createMock(HealthCheckerInterface::class);
        $checker->method('runAll')->willReturn([
            HealthCheckResult::pass('Database', 'OK'),
            HealthCheckResult::warn('Cache', DiagnosticCode::CACHE_DIRECTORY_UNWRITABLE, 'Not writable.'),
        ]);

        $tester = $this->createTester($checker);
        $tester->execute([]);

        $this->assertSame(1, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('WARN', $output);
        $this->assertStringContainsString('passed with warnings', $output);
    }

    #[Test]
    public function failuresReturnExitCode2(): void
    {
        $checker = $this->createMock(HealthCheckerInterface::class);
        $checker->method('runAll')->willReturn([
            HealthCheckResult::fail('Database', DiagnosticCode::DATABASE_UNREACHABLE, 'Cannot connect.'),
        ]);

        $tester = $this->createTester($checker);
        $tester->execute([]);

        $this->assertSame(2, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('FAIL', $output);
        $this->assertStringContainsString('Health check failed', $output);
    }

    #[Test]
    public function jsonOptionOutputsJson(): void
    {
        $checker = $this->createMock(HealthCheckerInterface::class);
        $checker->method('runAll')->willReturn([
            HealthCheckResult::pass('Database', 'OK'),
        ]);

        $tester = $this->createTester($checker);
        $tester->execute(['--json' => true]);

        $output = $tester->getDisplay();
        $decoded = json_decode($output, true);
        $this->assertIsArray($decoded);
        $this->assertCount(1, $decoded);
        $this->assertSame('Database', $decoded[0]['name']);
        $this->assertSame('pass', $decoded[0]['status']);
    }

    #[Test]
    public function remediationsShownForNonPassingChecks(): void
    {
        $checker = $this->createMock(HealthCheckerInterface::class);
        $checker->method('runAll')->willReturn([
            HealthCheckResult::fail('Database', DiagnosticCode::DATABASE_UNREACHABLE),
        ]);

        $tester = $this->createTester($checker);
        $tester->execute([]);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('Remediations:', $output);
        $this->assertStringContainsString('WAASEYAA_DB', $output);
    }

    private function createTester(HealthCheckerInterface $checker): CommandTester
    {
        $app = new Application();
        $app->add(new HealthCheckCommand($checker));
        $command = $app->find('health:check');
        return new CommandTester($command);
    }
}

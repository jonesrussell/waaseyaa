<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Tests\Unit\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Waaseyaa\CLI\Command\SchemaCheckCommand;
use Waaseyaa\Foundation\Diagnostic\DiagnosticCode;
use Waaseyaa\Foundation\Diagnostic\HealthCheckerInterface;
use Waaseyaa\Foundation\Diagnostic\HealthCheckResult;

#[CoversClass(SchemaCheckCommand::class)]
final class SchemaCheckCommandTest extends TestCase
{
    #[Test]
    public function noDriftReturnsSuccess(): void
    {
        $checker = $this->createMock(HealthCheckerInterface::class);
        $checker->method('checkSchemaDrift')->willReturn([
            HealthCheckResult::pass('Schema drift', 'All schemas match.'),
        ]);

        $tester = $this->createTester($checker);
        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('All schemas match', $output);
    }

    #[Test]
    public function driftDetectedReturnsExitCode1(): void
    {
        $checker = $this->createMock(HealthCheckerInterface::class);
        $checker->method('checkSchemaDrift')->willReturn([
            HealthCheckResult::fail(
                'Schema: node_type',
                DiagnosticCode::DATABASE_SCHEMA_DRIFT,
                'Table "node_type" has 1 column(s) with schema drift.',
                ['table' => 'node_type', 'drift' => [['column' => 'type', 'issue' => 'type mismatch']]],
            ),
        ]);

        $tester = $this->createTester($checker);
        $tester->execute([]);

        $this->assertSame(1, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('DRIFT', $output);
        $this->assertStringContainsString('type mismatch', $output);
    }

    #[Test]
    public function jsonOptionOutputsJson(): void
    {
        $checker = $this->createMock(HealthCheckerInterface::class);
        $checker->method('checkSchemaDrift')->willReturn([
            HealthCheckResult::pass('Schema drift', 'OK'),
        ]);

        $tester = $this->createTester($checker);
        $tester->execute(['--json' => true]);

        $decoded = json_decode($tester->getDisplay(), true);
        $this->assertIsArray($decoded);
        $this->assertSame('pass', $decoded[0]['status']);
    }

    private function createTester(HealthCheckerInterface $checker): CommandTester
    {
        $app = new Application();
        $app->add(new SchemaCheckCommand($checker));
        $command = $app->find('schema:check');
        return new CommandTester($command);
    }
}

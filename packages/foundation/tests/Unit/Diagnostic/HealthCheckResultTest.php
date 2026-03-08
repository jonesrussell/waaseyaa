<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Tests\Unit\Diagnostic;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Foundation\Diagnostic\DiagnosticCode;
use Waaseyaa\Foundation\Diagnostic\HealthCheckResult;

#[CoversClass(HealthCheckResult::class)]
final class HealthCheckResultTest extends TestCase
{
    #[Test]
    public function passFactoryCreatesPassingResult(): void
    {
        $result = HealthCheckResult::pass('Database', 'DB is accessible.');

        $this->assertSame('Database', $result->name);
        $this->assertSame('pass', $result->status);
        $this->assertSame('DB is accessible.', $result->message);
        $this->assertNull($result->code);
        $this->assertSame('', $result->remediation);
    }

    #[Test]
    public function warnFactoryCreatesWarningResult(): void
    {
        $result = HealthCheckResult::warn(
            'Cache',
            DiagnosticCode::CACHE_DIRECTORY_UNWRITABLE,
            'Not writable.',
            ['path' => '/tmp'],
        );

        $this->assertSame('Cache', $result->name);
        $this->assertSame('warn', $result->status);
        $this->assertSame(DiagnosticCode::CACHE_DIRECTORY_UNWRITABLE, $result->code);
        $this->assertSame('Not writable.', $result->message);
        $this->assertNotEmpty($result->remediation);
        $this->assertSame(['path' => '/tmp'], $result->context);
    }

    #[Test]
    public function failFactoryCreatesFailResult(): void
    {
        $result = HealthCheckResult::fail(
            'Database',
            DiagnosticCode::DATABASE_UNREACHABLE,
        );

        $this->assertSame('fail', $result->status);
        $this->assertSame(DiagnosticCode::DATABASE_UNREACHABLE, $result->code);
        $this->assertSame(DiagnosticCode::DATABASE_UNREACHABLE->defaultMessage(), $result->message);
    }

    #[Test]
    public function toArrayIncludesAllFieldsForFailure(): void
    {
        $result = HealthCheckResult::fail(
            'Schema: node',
            DiagnosticCode::DATABASE_SCHEMA_DRIFT,
            'Drift detected.',
            ['table' => 'node'],
        );

        $arr = $result->toArray();

        $this->assertSame('Schema: node', $arr['name']);
        $this->assertSame('fail', $arr['status']);
        $this->assertSame('DATABASE_SCHEMA_DRIFT', $arr['code']);
        $this->assertSame('Drift detected.', $arr['message']);
        $this->assertArrayHasKey('remediation', $arr);
        $this->assertSame(['table' => 'node'], $arr['context']);
    }

    #[Test]
    public function toArrayOmitsOptionalFieldsForPass(): void
    {
        $result = HealthCheckResult::pass('Check', '');

        $arr = $result->toArray();

        $this->assertArrayNotHasKey('code', $arr);
        $this->assertArrayNotHasKey('remediation', $arr);
        $this->assertArrayNotHasKey('context', $arr);
    }
}

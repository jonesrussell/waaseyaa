<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Tests\Unit\Ingestion;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\CLI\Ingestion\ValidationDiagnosticEmitter;

#[CoversClass(ValidationDiagnosticEmitter::class)]
final class ValidationDiagnosticEmitterTest extends TestCase
{
    #[Test]
    public function it_emits_sorted_validation_diagnostics_with_fixed_messages(): void
    {
        $emitter = new ValidationDiagnosticEmitter();
        $diagnostics = $emitter->emit([
            [
                'code' => 'validation.visibility.relationship_requires_public_endpoints',
                'location' => '/relationships/0/status',
                'item_index' => null,
                'from_key' => 'a',
                'to_key' => 'b',
                'value' => 1,
                'expected' => 0,
                'remediation' => 'Publish both nodes.',
            ],
            [
                'code' => 'validation.workflow.status_state_mismatch',
                'location' => '/nodes/a/status',
                'item_index' => null,
                'value' => 0,
                'expected' => 1,
                'workflow_state' => 'published',
                'remediation' => 'Align status.',
            ],
        ]);

        $this->assertCount(2, $diagnostics);
        $this->assertSame('validation.visibility.relationship_requires_public_endpoints', $diagnostics[0]['code']);
        $this->assertSame('visibility', $diagnostics[0]['category']);
        $this->assertStringContainsString('requires both endpoints to be published', (string) $diagnostics[0]['message']);
        $this->assertSame(
            ['value', 'expected', 'from_key', 'to_key', 'remediation'],
            array_keys($diagnostics[0]['context']),
        );

        $this->assertSame('validation.workflow.status_state_mismatch', $diagnostics[1]['code']);
        $this->assertSame('workflow', $diagnostics[1]['category']);
        $this->assertStringContainsString('does not match workflow_state', (string) $diagnostics[1]['message']);
    }
}

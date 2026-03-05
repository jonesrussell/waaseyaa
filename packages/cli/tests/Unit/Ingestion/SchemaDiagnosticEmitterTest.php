<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Tests\Unit\Ingestion;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\CLI\Ingestion\SchemaDiagnosticEmitter;

#[CoversClass(SchemaDiagnosticEmitter::class)]
final class SchemaDiagnosticEmitterTest extends TestCase
{
    #[Test]
    public function it_emits_deterministic_message_and_context_shape(): void
    {
        $emitter = new SchemaDiagnosticEmitter();
        $diagnostics = $emitter->emit([
            [
                'code' => 'schema.unknown_source_set_scheme',
                'location' => '/source_set_uri',
                'item_index' => null,
                'value' => 'legacy',
                'expected' => ['dataset', 'manual'],
                'allowed_schemes' => ['dataset', 'manual'],
            ],
        ]);

        $this->assertCount(1, $diagnostics);
        $this->assertSame('schema.unknown_source_set_scheme', $diagnostics[0]['code']);
        $this->assertSame('/source_set_uri', $diagnostics[0]['location']);
        $this->assertStringContainsString('Allowed schemes: dataset, manual.', (string) $diagnostics[0]['message']);

        $context = $diagnostics[0]['context'];
        $this->assertSame(['value', 'expected', 'allowed_schemes'], array_keys($context));
        $this->assertSame('legacy', $context['value']);
        $this->assertSame(['dataset', 'manual'], $context['expected']);
    }

    #[Test]
    public function it_emits_fixed_templates_for_new_schema_rule_codes(): void
    {
        $emitter = new SchemaDiagnosticEmitter();
        $diagnostics = $emitter->emit([
            [
                'code' => 'schema.missing_required_envelope_field',
                'location' => '/batch_id',
                'item_index' => null,
                'value' => null,
                'expected' => 'non-empty string',
                'field_name' => 'batch_id',
            ],
            [
                'code' => 'schema.invalid_items_type',
                'location' => '/items',
                'item_index' => null,
                'value' => 'string',
                'expected' => 'array',
            ],
            [
                'code' => 'schema.malformed_ingested_at',
                'location' => '/items/0/ingested_at',
                'item_index' => 0,
                'value' => 'not-a-date',
                'expected' => 'unix_timestamp_or_iso8601',
            ],
        ]);

        $messages = array_values(array_map(static fn(array $row): string => (string) ($row['message'] ?? ''), $diagnostics));
        $this->assertContains('Missing required envelope field: "batch_id".', $messages);
        $this->assertContains('Invalid items field type: "string". Expected: "array".', $messages);
        $this->assertContains('Malformed ingested_at value: "not-a-date". Expected: "unix_timestamp_or_iso8601".', $messages);
    }
}

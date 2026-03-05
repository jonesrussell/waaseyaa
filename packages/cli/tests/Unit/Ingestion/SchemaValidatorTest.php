<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Tests\Unit\Ingestion;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\CLI\Ingestion\SchemaValidator;

#[CoversClass(SchemaValidator::class)]
final class SchemaValidatorTest extends TestCase
{
    #[Test]
    public function it_reports_schema_violations_for_policy_scheme_and_duplicate_provenance(): void
    {
        $validator = new SchemaValidator();
        $violations = $validator->validate([
            'batch_id' => 'batch-1',
            'source_set_uri' => 'unknown://set',
            'policy' => 'invalid',
            'items' => [
                ['source_uri' => 'a', 'ingested_at' => 1735689600, 'parser_version' => null],
                ['source_uri' => 'a', 'ingested_at' => null, 'parser_version' => null],
            ],
        ]);

        $codes = array_values(array_map(static fn(array $row): string => (string) ($row['code'] ?? ''), $violations));
        sort($codes);

        $this->assertContains('schema.invalid_policy_value', $codes);
        $this->assertContains('schema.unknown_source_set_scheme', $codes);
        $this->assertContains('schema.duplicate_source_uri', $codes);
        $this->assertContains('schema.missing_required_provenance_field', $codes);
    }

    #[Test]
    public function it_reports_malformed_source_set_uri_when_format_is_invalid(): void
    {
        $validator = new SchemaValidator();
        $violations = $validator->validate([
            'batch_id' => 'batch-1',
            'source_set_uri' => 'dataset:/missing-slash',
            'policy' => 'atomic_fail_fast',
            'items' => [['source_uri' => 'a', 'ingested_at' => 1735689600, 'parser_version' => null]],
        ]);

        $codes = array_values(array_map(static fn(array $row): string => (string) ($row['code'] ?? ''), $violations));
        $this->assertContains('schema.malformed_source_set_uri', $codes);
        $this->assertNotContains('schema.unknown_source_set_scheme', $codes);
    }

    #[Test]
    public function it_accepts_allowed_source_set_scheme_case_insensitively(): void
    {
        $validator = new SchemaValidator();
        $violations = $validator->validate([
            'batch_id' => 'batch-1',
            'source_set_uri' => 'DATASET://river',
            'policy' => 'atomic_fail_fast',
            'items' => [['source_uri' => 'a', 'ingested_at' => 1735689600, 'parser_version' => null]],
        ]);

        $codes = array_values(array_map(static fn(array $row): string => (string) ($row['code'] ?? ''), $violations));
        $this->assertNotContains('schema.malformed_source_set_uri', $codes);
        $this->assertNotContains('schema.unknown_source_set_scheme', $codes);
    }

    #[Test]
    public function it_reports_missing_required_item_provenance_fields_independently(): void
    {
        $validator = new SchemaValidator();
        $violations = $validator->validate([
            'batch_id' => 'batch-1',
            'source_set_uri' => 'manual://set',
            'policy' => 'validate_only',
            'items' => [['source_uri' => '', 'ingested_at' => '', 'parser_version' => null]],
        ]);

        $missing = array_values(array_filter(
            $violations,
            static fn(array $row): bool => (string) ($row['code'] ?? '') === 'schema.missing_required_provenance_field',
        ));

        $locations = array_values(array_map(static fn(array $row): string => (string) ($row['location'] ?? ''), $missing));
        sort($locations);

        $this->assertSame(['/items/0/ingested_at', '/items/0/source_uri'], $locations);
    }
}

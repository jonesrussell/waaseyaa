<?php

declare(strict_types=1);

namespace Waaseyaa\Migration\Tests\Unit\Plugin;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Migration\Plugin\DestinationRecord;
use Waaseyaa\Migration\Plugin\ProcessContext;
use Waaseyaa\Migration\Plugin\SourceRecord;
use Waaseyaa\Migration\Plugin\WriteResult;
use Waaseyaa\Migration\SourceId;

#[CoversClass(SourceRecord::class)]
#[CoversClass(SourceId::class)]
#[CoversClass(ProcessContext::class)]
#[CoversClass(WriteResult::class)]
#[CoversClass(DestinationRecord::class)]
final class DtoValueObjectsTest extends TestCase
{
    #[Test]
    public function source_record_field_accessor_returns_default_for_missing_keys(): void
    {
        $record = new SourceRecord('wp', ['title' => 'Hi', 'null_field' => null]);

        self::assertSame('Hi', $record->field('title'));
        self::assertNull($record->field('null_field', 'fallback'));
        self::assertSame('fallback', $record->field('absent', 'fallback'));
        self::assertNull($record->field('absent'));
    }

    #[Test]
    public function source_record_rejects_empty_source_type(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new SourceRecord('', ['k' => 'v']);
    }

    #[Test]
    public function source_record_rejects_malformed_source_type(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new SourceRecord('Bad-Name', []);
    }

    #[Test]
    public function source_record_rejects_non_string_field_keys(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new SourceRecord('wp', [0 => 'numeric-key']);
    }

    #[Test]
    public function source_id_constructor_validates_source_type_and_keys(): void
    {
        $id = new SourceId('wp_post', ['id' => 1, 'lang' => 'en']);
        self::assertSame('wp_post', $id->sourceType);
        self::assertSame(['id' => 1, 'lang' => 'en'], $id->keys);
    }

    #[Test]
    public function source_id_rejects_empty_source_type(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new SourceId('', ['id' => 1]);
    }

    #[Test]
    public function source_id_rejects_malformed_source_type(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new SourceId('UPPER', ['id' => 1]);
    }

    #[Test]
    public function source_id_rejects_empty_keys(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new SourceId('wp', []);
    }

    #[Test]
    public function source_id_rejects_non_scalar_key_value(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        /** @phpstan-ignore-next-line — intentionally passing non-scalar to verify guard. */
        new SourceId('wp', ['id' => new \stdClass()]);
    }

    #[Test]
    public function source_id_rejects_non_string_key_name(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new SourceId('wp', [0 => 'value']);
    }

    #[Test]
    public function source_id_hash_returns_a_sha256_hex_digest(): void
    {
        $id = new SourceId('wp', ['id' => 1]);

        // WP04: hash() returns a deterministic 64-char lowercase sha256 hex
        // digest. The full algorithmic contract is covered by
        // {@see \Waaseyaa\Migration\Tests\Unit\SourceIdTest}.
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $id->hash());
    }

    #[Test]
    public function process_context_lookup_closure_is_invocable(): void
    {
        $invocations = 0;
        $context = new ProcessContext(
            sourceRecord: new SourceRecord('wp', []),
            migrationId: 'm1',
            destinationField: 'title',
            lookup: static function (string $migrationId, SourceId $id) use (&$invocations): ?WriteResult {
                ++$invocations;
                return null;
            },
        );

        $lookup = $context->lookup;
        self::assertNull($lookup('m1', new SourceId('wp', ['id' => 1])));
        self::assertSame(1, $invocations);
    }

    #[Test]
    public function process_context_rejects_empty_migration_id(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new ProcessContext(
            sourceRecord: new SourceRecord('wp', []),
            migrationId: '',
            destinationField: 'title',
            lookup: static fn (): ?WriteResult => null,
        );
    }

    #[Test]
    public function process_context_rejects_empty_destination_field(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new ProcessContext(
            sourceRecord: new SourceRecord('wp', []),
            migrationId: 'm',
            destinationField: '',
            lookup: static fn (): ?WriteResult => null,
        );
    }

    #[Test]
    public function write_result_holds_all_required_fields_as_readonly(): void
    {
        $result = new WriteResult(
            destinationEntityType: 'node',
            destinationUuid: 'uuid-7',
            sourceRecordHash: 'hash',
            runId: 'run-7',
            writtenAt: '2026-05-12T22:56:07Z',
        );

        self::assertSame('node', $result->destinationEntityType);
        self::assertSame('uuid-7', $result->destinationUuid);
        self::assertSame('hash', $result->sourceRecordHash);
        self::assertSame('run-7', $result->runId);
        self::assertSame('2026-05-12T22:56:07Z', $result->writtenAt);
    }

    #[Test]
    public function write_result_rejects_empty_destination_entity_type(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new WriteResult('', 'u', 'h', 'r', 't');
    }

    #[Test]
    public function write_result_rejects_empty_destination_uuid(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new WriteResult('node', '', 'h', 'r', 't');
    }

    #[Test]
    public function write_result_rejects_empty_hash(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new WriteResult('node', 'u', '', 'r', 't');
    }

    #[Test]
    public function write_result_rejects_empty_run_id(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new WriteResult('node', 'u', 'h', '', 't');
    }

    #[Test]
    public function write_result_rejects_empty_written_at(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new WriteResult('node', 'u', 'h', 'r', '');
    }

    #[Test]
    public function destination_record_optional_bundle_and_langcode_default_to_null(): void
    {
        $sid = new SourceId('csv', ['row' => 1]);
        $record = new DestinationRecord(
            migrationId: 'csv_to_node',
            sourceId: $sid,
            values: ['title' => 'x'],
        );

        self::assertNull($record->bundle);
        self::assertNull($record->langcode);
        self::assertSame($sid, $record->sourceId);
        self::assertSame(['title' => 'x'], $record->values);
    }

    #[Test]
    public function destination_record_rejects_empty_migration_id(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new DestinationRecord('', new SourceId('csv', ['k' => 1]), []);
    }

    #[Test]
    public function destination_record_rejects_empty_bundle_string(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new DestinationRecord(
            migrationId: 'm',
            sourceId: new SourceId('csv', ['k' => 1]),
            values: [],
            bundle: '',
        );
    }

    #[Test]
    public function destination_record_rejects_empty_langcode_string(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new DestinationRecord(
            migrationId: 'm',
            sourceId: new SourceId('csv', ['k' => 1]),
            values: [],
            langcode: '',
        );
    }

    #[Test]
    public function destination_record_rejects_non_string_value_keys(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new DestinationRecord(
            migrationId: 'm',
            sourceId: new SourceId('csv', ['k' => 1]),
            values: [0 => 'numeric'],
        );
    }
}

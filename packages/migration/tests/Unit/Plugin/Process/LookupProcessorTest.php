<?php

declare(strict_types=1);

namespace Waaseyaa\Migration\Tests\Unit\Plugin\Process;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Migration\Exception\ProcessException;
use Waaseyaa\Migration\Plugin\Process\LookupProcessor;
use Waaseyaa\Migration\Plugin\ProcessContext;
use Waaseyaa\Migration\Plugin\ReservedPluginIds;
use Waaseyaa\Migration\Plugin\SourceRecord;
use Waaseyaa\Migration\Plugin\WriteResult;
use Waaseyaa\Migration\SourceId;

#[CoversClass(LookupProcessor::class)]
final class LookupProcessorTest extends TestCase
{
    #[Test]
    public function id_is_lookup(): void
    {
        $p = new LookupProcessor(sourceField: 'post_author', migration: 'wp_users');
        self::assertSame(ReservedPluginIds::LOOKUP, $p->id());
        self::assertSame('stable', $p->stability());
    }

    #[Test]
    public function resolves_destination_uuid_through_lookup_closure(): void
    {
        $writeResult = new WriteResult(
            destinationEntityType: 'user',
            destinationUuid: '0193f4d2-1111-7000-8000-000000000001',
            sourceRecordHash: 'h',
            runId: 'r',
            writtenAt: '2026-05-13T00:00:00Z',
        );

        $captured = null;
        $captureMigration = null;
        $ctx = $this->context(
            fields: ['post_author' => 42],
            lookup: function (string $migrationId, SourceId $id) use ($writeResult, &$captured, &$captureMigration): ?WriteResult {
                $captured = $id;
                $captureMigration = $migrationId;

                return $writeResult;
            },
        );

        $p = new LookupProcessor(
            sourceField: 'post_author',
            migration: 'wp_users',
            sourceType: 'wp_user',
            keyField: 'ID',
        );

        $result = $p->transform(null, $ctx);

        self::assertSame('0193f4d2-1111-7000-8000-000000000001', $result);
        self::assertSame('wp_users', $captureMigration);
        self::assertInstanceOf(SourceId::class, $captured);
        self::assertSame('wp_user', $captured->sourceType);
        self::assertSame(['ID' => 42], $captured->keys);
    }

    #[Test]
    public function falls_back_to_record_source_type_when_unset(): void
    {
        $capturedSourceType = null;
        $ctx = $this->context(
            fields: ['author' => 7],
            lookup: function (string $migrationId, SourceId $id) use (&$capturedSourceType): ?WriteResult {
                $capturedSourceType = $id->sourceType;

                return null;
            },
        );

        $p = new LookupProcessor(sourceField: 'author', migration: 'm');
        $p->transform(null, $ctx);

        self::assertSame('wp', $capturedSourceType);
    }

    #[Test]
    public function returns_null_on_miss_by_default(): void
    {
        $ctx = $this->context(
            fields: ['author' => 99],
            lookup: static fn (string $m, SourceId $id): ?WriteResult => null,
        );

        $p = new LookupProcessor(sourceField: 'author', migration: 'm');

        self::assertNull($p->transform(null, $ctx));
    }

    #[Test]
    public function raises_on_miss_when_on_miss_is_fail(): void
    {
        $ctx = $this->context(
            fields: ['author' => 99],
            lookup: static fn (string $m, SourceId $id): ?WriteResult => null,
        );

        $p = new LookupProcessor(
            sourceField: 'author',
            migration: 'm',
            onMiss: LookupProcessor::ON_MISS_FAIL,
        );

        try {
            $p->transform(null, $ctx);
            self::fail('Expected ProcessException');
        } catch (ProcessException $e) {
            self::assertSame(ProcessException::CODE_LOOKUP_MISS, $e->processCode);
            self::assertSame('author', $e->sourceField);
            self::assertSame('m1', $e->migrationId);
        }
    }

    #[Test]
    public function null_or_empty_source_value_returns_null_without_calling_lookup(): void
    {
        $invocations = 0;
        $lookup = static function (string $m, SourceId $id) use (&$invocations): ?WriteResult {
            ++$invocations;

            return null;
        };

        $p = new LookupProcessor(sourceField: 'author', migration: 'm');

        $ctx1 = $this->context(['author' => null], $lookup);
        self::assertNull($p->transform(null, $ctx1));

        $ctx2 = $this->context(['author' => ''], $lookup);
        self::assertNull($p->transform(null, $ctx2));

        self::assertSame(0, $invocations, 'Lookup closure must not fire for null/empty input.');
    }

    #[Test]
    public function raises_for_non_scalar_source_value(): void
    {
        $ctx = $this->context(
            fields: ['author' => ['nested' => true]],
            lookup: static fn (string $m, SourceId $id): ?WriteResult => null,
        );

        $p = new LookupProcessor(sourceField: 'author', migration: 'm');

        $this->expectException(ProcessException::class);
        $p->transform(null, $ctx);
    }

    #[Test]
    public function uses_chained_value_when_provided(): void
    {
        $capturedKey = null;
        $ctx = $this->context(
            fields: ['author' => 1],
            lookup: function (string $m, SourceId $id) use (&$capturedKey): ?WriteResult {
                $capturedKey = $id->keys;

                return null;
            },
        );

        $p = new LookupProcessor(sourceField: 'author', migration: 'm');
        $p->transform(42, $ctx); // chained value wins over source-record value

        self::assertSame(['id' => 42], $capturedKey);
    }

    #[Test]
    public function rejects_unknown_on_miss(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new LookupProcessor(sourceField: 'a', migration: 'm', onMiss: 'maybe');
    }

    #[Test]
    public function rejects_empty_source_field(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new LookupProcessor(sourceField: '', migration: 'm');
    }

    #[Test]
    public function rejects_empty_migration(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new LookupProcessor(sourceField: 'a', migration: '');
    }

    /**
     * @param array<string, mixed> $fields
     * @param \Closure(string, SourceId): ?WriteResult $lookup
     */
    private function context(array $fields, \Closure $lookup): ProcessContext
    {
        return new ProcessContext(
            sourceRecord: new SourceRecord('wp', $fields),
            migrationId: 'm1',
            destinationField: 'author_uuid',
            lookup: $lookup,
        );
    }
}

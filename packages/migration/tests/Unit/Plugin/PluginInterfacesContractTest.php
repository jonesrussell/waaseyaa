<?php

declare(strict_types=1);

namespace Waaseyaa\Migration\Tests\Unit\Plugin;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Migration\Plugin\DestinationPluginInterface;
use Waaseyaa\Migration\Plugin\DestinationRecord;
use Waaseyaa\Migration\Plugin\ProcessContext;
use Waaseyaa\Migration\Plugin\ProcessPluginInterface;
use Waaseyaa\Migration\Plugin\SourcePluginInterface;
use Waaseyaa\Migration\Plugin\SourceRecord;
use Waaseyaa\Migration\Plugin\WriteResult;
use Waaseyaa\Migration\SourceId;

#[CoversClass(SourceRecord::class)]
#[CoversClass(SourceId::class)]
#[CoversClass(ProcessContext::class)]
#[CoversClass(WriteResult::class)]
#[CoversClass(DestinationRecord::class)]
final class PluginInterfacesContractTest extends TestCase
{
    #[Test]
    public function source_plugin_anonymous_implementation_satisfies_contract(): void
    {
        $sourceId = new SourceId('wordpress_post', ['id' => 42]);
        $record = new SourceRecord('wordpress_post', ['title' => 'Hello']);

        $plugin = new class($sourceId, $record) implements SourcePluginInterface {
            public function __construct(
                private readonly SourceId $sourceId,
                private readonly SourceRecord $record,
            ) {
            }

            public function id(): string
            {
                return 'wordpress_post';
            }

            public function stability(): string
            {
                return 'stable';
            }

            public function records(): iterable
            {
                yield $this->record;
            }

            public function sourceIdFor(SourceRecord $record): SourceId
            {
                return $this->sourceId;
            }

            public function count(): ?int
            {
                return 1;
            }
        };

        self::assertSame('wordpress_post', $plugin->id());
        self::assertSame('stable', $plugin->stability());
        self::assertSame(1, $plugin->count());
        self::assertSame($sourceId, $plugin->sourceIdFor($record));
        $yielded = iterator_to_array($plugin->records(), false);
        self::assertCount(1, $yielded);
        self::assertSame($record, $yielded[0]);
    }

    #[Test]
    public function process_plugin_threads_context_into_transform(): void
    {
        $touched = [];
        $context = new ProcessContext(
            sourceRecord: new SourceRecord('wp', ['raw' => 'value']),
            migrationId: 'wp_to_node',
            destinationField: 'title',
            lookup: static function (string $migrationId, SourceId $id) use (&$touched): ?WriteResult {
                $touched[] = [$migrationId, $id->sourceType];
                return null;
            },
        );

        $plugin = new class() implements ProcessPluginInterface {
            public function id(): string
            {
                return 'uppercase';
            }

            public function stability(): string
            {
                return 'stable';
            }

            public function transform(mixed $value, ProcessContext $context): mixed
            {
                return strtoupper((string) $value) . ':' . $context->destinationField;
            }
        };

        self::assertSame('HELLO:title', $plugin->transform('hello', $context));

        // The closure should be invocable from inside a process plugin.
        $lookup = $context->lookup;
        self::assertNull($lookup('other_migration', new SourceId('csv', ['k' => 1])));
        self::assertSame([['other_migration', 'csv']], $touched);
    }

    #[Test]
    public function destination_plugin_round_trips_write_lookup_rollback(): void
    {
        $written = new WriteResult(
            destinationEntityType: 'node',
            destinationUuid: '01HZZZZZ-zzzz-7zzz-zzzz-zzzzzzzzzzzz',
            sourceRecordHash: 'sha256:placeholder',
            runId: '01HRRRRR-rrrr-7rrr-rrrr-rrrrrrrrrrrr',
            writtenAt: '2026-05-12T22:56:07Z',
        );

        $plugin = new class($written) implements DestinationPluginInterface {
            /** @var array<string, WriteResult> */
            public array $store = [];

            public function __construct(private readonly WriteResult $template)
            {
            }

            public function id(): string
            {
                return 'fake_destination';
            }

            public function stability(): string
            {
                return 'stable';
            }

            public function write(DestinationRecord $record): WriteResult
            {
                $this->store[$record->sourceId->sourceType] = $this->template;
                return $this->template;
            }

            public function rollback(WriteResult $result): void
            {
                $this->store = [];
            }

            public function lookup(SourceId $sourceId): ?WriteResult
            {
                return $this->store[$sourceId->sourceType] ?? null;
            }
        };

        $sourceId = new SourceId('csv', ['row' => '1']);
        $record = new DestinationRecord(
            migrationId: 'csv_to_node',
            sourceId: $sourceId,
            values: ['title' => 'Imported'],
            bundle: 'article',
            langcode: 'en',
        );

        $result = $plugin->write($record);
        self::assertSame($written, $result);
        self::assertSame($written, $plugin->lookup($sourceId));

        $plugin->rollback($result);
        self::assertNull($plugin->lookup($sourceId));
    }
}

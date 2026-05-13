<?php

declare(strict_types=1);

namespace Waaseyaa\Migration\PluginFixtures;

use Waaseyaa\Migration\Plugin\DestinationPluginInterface;
use Waaseyaa\Migration\Plugin\DestinationRecord;
use Waaseyaa\Migration\Plugin\ProcessContext;
use Waaseyaa\Migration\Plugin\ProcessPluginInterface;
use Waaseyaa\Migration\Plugin\SourcePluginInterface;
use Waaseyaa\Migration\Plugin\SourceRecord;
use Waaseyaa\Migration\Plugin\WriteResult;
use Waaseyaa\Migration\SourceId;

/**
 * Reusable plugin fixtures for WP02 tests.
 *
 * Provides minimal-no-op {@see SourcePluginInterface}, {@see ProcessPluginInterface},
 * and {@see DestinationPluginInterface} implementations. Each helper returns an
 * anonymous class whose only purpose is to satisfy the plugin contract surface
 * — these fixtures are never exercised end-to-end.
 *
 * Lives under `tests/_fixtures/` (not `src/`) so it is dev-only and never
 * reaches production consumer installs.
 */
trait PluginFixturesTrait
{
    protected function fixtureSource(string $id = 'wp_post'): SourcePluginInterface
    {
        return new class($id) implements SourcePluginInterface {
            public function __construct(private readonly string $pluginId) {}
            public function id(): string { return $this->pluginId; }
            public function stability(): string { return 'stable'; }
            public function records(): iterable { return []; }
            public function count(): ?int { return 0; }
            public function sourceIdFor(SourceRecord $record): SourceId
            {
                return new SourceId(sourceType: $this->pluginId, keys: ['id' => 1]);
            }
        };
    }

    protected function fixtureProcess(string $id = 'concat'): ProcessPluginInterface
    {
        return new class($id) implements ProcessPluginInterface {
            public function __construct(private readonly string $pluginId) {}
            public function id(): string { return $this->pluginId; }
            public function stability(): string { return 'stable'; }
            public function transform(mixed $value, ProcessContext $context): mixed
            {
                return $value;
            }
        };
    }

    protected function fixtureDestination(string $id = 'node_destination'): DestinationPluginInterface
    {
        return new class($id) implements DestinationPluginInterface {
            public function __construct(private readonly string $pluginId) {}
            public function id(): string { return $this->pluginId; }
            public function stability(): string { return 'stable'; }
            public function write(DestinationRecord $record): WriteResult
            {
                return new WriteResult(
                    destinationEntityType: 'node',
                    destinationUuid: '00000000-0000-7000-8000-000000000000',
                    sourceRecordHash: 'hash',
                    runId: '00000000-0000-7000-8000-000000000001',
                    writtenAt: '2026-05-12T00:00:00Z',
                );
            }
            public function rollback(WriteResult $writeResult): void {}
            public function lookup(SourceId $sourceId): ?WriteResult { return null; }
        };
    }
}

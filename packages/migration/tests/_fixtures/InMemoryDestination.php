<?php

declare(strict_types=1);

namespace Waaseyaa\Migration\PluginFixtures;

use Waaseyaa\Migration\Plugin\DestinationPluginInterface;
use Waaseyaa\Migration\Plugin\DestinationRecord;
use Waaseyaa\Migration\Plugin\WriteResult;
use Waaseyaa\Migration\SourceId;

/**
 * In-memory destination used by Runner tests. Records every write into the
 * public `$writes` array (keyed by source-id hash) so tests can introspect.
 *
 * Configurable failure injection: every record whose source-id hash matches
 * `$failOnSourceIdHash` raises `$throwForSourceIdHash` (must be a typed
 * destination throwable — typically {@see \Waaseyaa\Migration\Exception\DestinationWriteException}).
 */
final class InMemoryDestination implements DestinationPluginInterface
{
    /** @var array<string, WriteResult> Keyed by source-id hash for cheap re-lookup. */
    public array $writes = [];

    public function __construct(
        private readonly string $id = 'in_memory_dest',
        public ?\Throwable $throwForSourceIdHash = null,
        public ?string $failOnSourceIdHash = null,
    ) {}

    public function id(): string
    {
        return $this->id;
    }

    public function stability(): string
    {
        return 'stable';
    }

    public function write(DestinationRecord $record): WriteResult
    {
        $hash = $record->sourceId->hash();
        if (
            $this->failOnSourceIdHash !== null
            && $hash === $this->failOnSourceIdHash
            && $this->throwForSourceIdHash !== null
        ) {
            throw $this->throwForSourceIdHash;
        }

        $writeResult = new WriteResult(
            destinationEntityType: 'in_memory_entity',
            destinationUuid: 'uuid-' . \substr($hash, 0, 12),
            sourceRecordHash: \hash('sha256', \json_encode($record->values, \JSON_THROW_ON_ERROR)),
            // Placeholder UUIDv7-shaped string; the runner-level runId is the
            // canonical handle in the {@see \Waaseyaa\Migration\Runner\RunReport}.
            runId: '019683d3-' . \substr($hash, 0, 4) . '-7000-8000-' . \substr($hash, 0, 12),
            writtenAt: '2026-05-13T12:00:00Z',
        );

        $this->writes[$hash] = $writeResult;
        return $writeResult;
    }

    public function rollback(WriteResult $result): void
    {
        // no-op for test fixture
    }

    public function lookup(SourceId $sourceId): ?WriteResult
    {
        return $this->writes[$sourceId->hash()] ?? null;
    }
}

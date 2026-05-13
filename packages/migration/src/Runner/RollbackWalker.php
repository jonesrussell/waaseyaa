<?php

declare(strict_types=1);

namespace Waaseyaa\Migration\Runner;

use Waaseyaa\Foundation\Log\LoggerInterface;
use Waaseyaa\Foundation\Log\NullLogger;
use Waaseyaa\Migration\Discovery\MigrationRegistry;
use Waaseyaa\Migration\Exception\DestinationWriteException;
use Waaseyaa\Migration\MigrationIdMap;
use Waaseyaa\Migration\Plugin\DestinationPluginInterface;
use Waaseyaa\Migration\Plugin\WriteResult;

/**
 * Per-migration rollback orchestrator.
 *
 * Walks `migration_id_map` in reverse-creation order (delegated to
 * {@see MigrationIdMap::walkReverseCreationWithKeys()}) and asks the
 * destination plugin to undo each previously-written record.
 *
 * Best-effort semantics (FR-044): a per-record failure is captured in the
 * {@see RollbackReport}, logged on the `entity.lifecycle` channel, and the
 * walk continues. The id-map row stays on disk so an operator can retry
 * just the failed entries.
 *
 * The walker is single-threaded — concurrency safety against parallel
 * `import:*` invocations is provided by WP09's filesystem lock.
 *
 * @api
 *
 * @spec FR-041 — `DestinationPluginInterface::rollback()` per-record contract
 * @spec FR-043 — reverse-creation walk order
 * @spec FR-044 — best-effort per-record rollback with structured reporting
 */
final class RollbackWalker
{
    private readonly LoggerInterface $logger;
    private readonly \Closure $clock;

    /**
     * @param MigrationRegistry $registry Source of {@see \Waaseyaa\Migration\MigrationDefinition}s. Required so the walker can hand the correct {@see DestinationPluginInterface} instance to each {@see WriteResult}.
     * @param MigrationIdMap    $idMap    Id-map repository; owns the reverse-creation walk and per-row delete primitives.
     * @param ?LoggerInterface  $logger   Structured logger; defaults to {@see NullLogger}. Failures are logged on the `entity.lifecycle` channel.
     * @param ?\Closure         $clock    `\Closure(): \DateTimeImmutable` returning UTC; defaults to `new \DateTimeImmutable('now', new \DateTimeZone('UTC'))`. Tests pin time for deterministic report timestamps.
     */
    public function __construct(
        private readonly MigrationRegistry $registry,
        private readonly MigrationIdMap $idMap,
        ?LoggerInterface $logger = null,
        ?\Closure $clock = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
        $this->clock = $clock ?? static fn(): \DateTimeImmutable => new \DateTimeImmutable(
            'now',
            new \DateTimeZone('UTC'),
        );
    }

    /**
     * Roll back every id-map row for `$migrationId` in reverse-creation order.
     *
     * Per FR-044, the walker is best-effort: a `\Throwable` raised by the
     * destination's `rollback()` is captured into the report and the walk
     * proceeds. The id-map row for a failed rollback stays on disk; the
     * row for a successful rollback is deleted as part of the same
     * iteration (via {@see MigrationIdMap::deleteByHash()}).
     *
     * @throws \InvalidArgumentException When `$migrationId` is empty.
     * @throws \OutOfBoundsException When `$migrationId` is unknown to the
     *         registry (mirrors {@see MigrationRegistry::get()}).
     *
     * @spec FR-035 — `import:rollback <migration-id>` entry point
     * @spec FR-041 — per-record `DestinationPluginInterface::rollback()`
     * @spec FR-043 — reverse-creation walk order
     * @spec FR-044 — best-effort + per-record error capture
     */
    public function rollback(string $migrationId): RollbackReport
    {
        if ($migrationId === '') {
            throw new \InvalidArgumentException(
                'RollbackWalker::rollback(): $migrationId must be a non-empty string.',
            );
        }

        $definition = $this->registry->get($migrationId);
        $destination = $definition->destination;

        $startedAt = ($this->clock)();
        $visited = 0;
        $rolledBack = 0;
        $failed = 0;
        /** @var list<RollbackError> $errors */
        $errors = [];

        foreach ($this->idMap->walkReverseCreationWithKeys($migrationId) as [$sourceIdHash, $writeResult]) {
            $visited++;

            try {
                $this->rollbackOne($destination, $migrationId, $sourceIdHash, $writeResult);
                $rolledBack++;
            } catch (\Throwable $e) {
                $failed++;
                $this->logger->error(
                    'Migration rollback: per-record failure',
                    [
                        'channel' => 'entity.lifecycle',
                        'migration_id' => $migrationId,
                        'source_id_hash' => $sourceIdHash,
                        'destination_entity_type' => $writeResult->destinationEntityType,
                        'destination_uuid' => $writeResult->destinationUuid,
                        'code' => $this->codeFor($e),
                        'exception' => $e::class,
                        'message' => $e->getMessage(),
                    ],
                );

                if (\count($errors) < RollbackReport::ERROR_CAP) {
                    $errors[] = new RollbackError(
                        sourceIdHash: $sourceIdHash,
                        destinationEntityType: $writeResult->destinationEntityType,
                        destinationUuid: $writeResult->destinationUuid,
                        code: $this->codeFor($e),
                        message: $e->getMessage() !== '' ? $e->getMessage() : $e::class,
                    );
                }
            }
        }

        $finishedAt = ($this->clock)();

        $this->logger->info(
            'Migration rollback complete',
            [
                'channel' => 'entity.lifecycle',
                'migration_id' => $migrationId,
                'visited' => $visited,
                'rolled_back' => $rolledBack,
                'failed' => $failed,
            ],
        );

        return new RollbackReport(
            migrationId: $migrationId,
            visited: $visited,
            rolledBack: $rolledBack,
            failed: $failed,
            errors: $errors,
            startedAt: $startedAt,
            finishedAt: $finishedAt,
        );
    }

    /**
     * Undo one previously-written record.
     *
     * The destination is responsible for the entity delete (FR-041); on
     * success the walker also deletes the id-map row. Per FR-042 a
     * destination that finds the entity already gone treats it as a
     * silent success, which keeps id-map rows from getting orphaned by
     * external operator action.
     */
    private function rollbackOne(
        DestinationPluginInterface $destination,
        string $migrationId,
        string $sourceIdHash,
        WriteResult $writeResult,
    ): void {
        // FR-041: per-record rollback is the destination plugin's
        // responsibility. The plugin owns the access check, the storage
        // delete, and the lifecycle events.
        $destination->rollback($writeResult);

        // On success, drop the id-map row so a subsequent re-run
        // re-imports the source record as new (FR-036's first half:
        // rollback also clears the id-map for the rolled-back row).
        $this->idMap->deleteByHash($migrationId, $sourceIdHash);
    }

    /**
     * Map a thrown exception to a stable string code suitable for log
     * scraping. The codes are intentionally narrow — the message field
     * carries the variable detail.
     */
    private function codeFor(\Throwable $e): string
    {
        // DestinationWriteException carries a `$reason` property — surface
        // it verbatim so operators can grep on stable codes
        // (`entity_delete_denied`, `entity_delete_failed`, etc.).
        if ($e instanceof DestinationWriteException && $e->reason !== '') {
            return $e->reason;
        }

        // Some test fixtures expose a `reason()` accessor for code
        // taxonomy. Honour it when available.
        if (\method_exists($e, 'reason')) {
            $reason = $e->reason();
            if (\is_string($reason) && $reason !== '') {
                return $reason;
            }
        }

        return match ($e::class) {
            \LogicException::class => 'logic_error',
            \InvalidArgumentException::class => 'invalid_argument',
            \RuntimeException::class => 'runtime_error',
            default => 'rollback_failed',
        };
    }
}

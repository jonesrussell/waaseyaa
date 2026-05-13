<?php

declare(strict_types=1);

namespace Waaseyaa\Migration\Runner;

use Symfony\Component\Uid\Uuid;
use Waaseyaa\Foundation\Log\LoggerInterface;
use Waaseyaa\Foundation\Log\NullLogger;
use Waaseyaa\Migration\Discovery\MigrationRegistry;
use Waaseyaa\Migration\Exception\DestinationWriteException;
use Waaseyaa\Migration\Exception\MigrationAbortedException;
use Waaseyaa\Migration\Exception\ProcessException;
use Waaseyaa\Migration\Exception\SourceReadException;
use Waaseyaa\Migration\MigrationDefinition;
use Waaseyaa\Migration\MigrationIdMap;
use Waaseyaa\Migration\Plugin\Destination\EntityDestination;
use Waaseyaa\Migration\Plugin\DestinationPluginInterface;
use Waaseyaa\Migration\Plugin\DestinationRecord;
use Waaseyaa\Migration\Plugin\SourceRecord;
use Waaseyaa\Migration\Plugin\WriteResult;
use Waaseyaa\Migration\SourceId;

/**
 * Procedural orchestrator for a single migration end-to-end run.
 *
 * Composition seam between WP02 ({@see MigrationRegistry}), WP04 ({@see MigrationIdMap})
 * and the WP05 destination plugin. The runner does not own discovery — it
 * accepts the booted registry — and does not own the database — it accepts
 * the id-map repository.
 *
 * One run id (UUIDv7) is minted per {@see run()} invocation and threaded
 * through the destination via {@see EntityDestination::withRunId()} so every
 * id-map row produced by the run shares a single audit handle (WP06 forward
 * to WP07 resume / WP08 rollback).
 *
 * Error handling follows the spec §10 dichotomy:
 *  - Per-record errors ({@see SourceReadException}, {@see ProcessException},
 *    {@see DestinationWriteException}) are captured in the report (FR-046)
 *    and the loop continues unless `$options->haltOnError` flips on (FR-047).
 *  - Run-level errors (any other `\Throwable` plus source-mid-iteration
 *    generator throws) always halt regardless of flags (FR-048).
 *
 * @api
 *
 * @spec FR-032 — `import:run` single-migration orchestration
 * @spec FR-039 — dry-run flag honored at write boundary
 * @spec FR-040 — limit option short-circuits iteration
 * @spec FR-046 — per-record error capture (capped at {@see RunReport::ERROR_CAP})
 * @spec FR-047 — halt-on-error propagation
 * @spec FR-048 — run-level abort surface
 */
final class MigrationRunner
{
    private readonly LoggerInterface $logger;
    private readonly \Closure $clock;

    /**
     * @param MigrationRegistry $registry Booted registry; resolved per `run()` via {@see MigrationRegistry::get()}.
     * @param ProcessChainExecutor $chain Runtime collaborator that pipes one source field through its process chain.
     * @param MigrationIdMap $idMap Stable-surface id-map repository, used to inject the `$lookup` closure for process plugins (FR-028).
     * @param ?LoggerInterface $logger Optional structured logger. Defaults to {@see NullLogger}.
     * @param \Closure|null $clock Test seam returning `\DateTimeImmutable`. Defaults to `now in UTC`.
     */
    public function __construct(
        private readonly MigrationRegistry $registry,
        private readonly ProcessChainExecutor $chain,
        private readonly MigrationIdMap $idMap,
        ?LoggerInterface $logger = null,
        ?\Closure $clock = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
        $this->clock = $clock ?? static fn(): \DateTimeImmutable
            => new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    /**
     * Execute one migration end-to-end. Returns a {@see RunReport} on
     * normal completion; raises {@see MigrationAbortedException} on halt.
     *
     * @throws MigrationAbortedException Raised on `--halt-on-error` per-record halt (FR-047) and on any run-level halt (FR-048). The exception carries a partial {@see RunReport}.
     * @throws \OutOfBoundsException When `$migrationId` is not registered. This is a programmer error (callers must validate against the registry first); the CLI surface catches and renders it.
     */
    public function run(string $migrationId, RunOptions $options): RunReport
    {
        $definition = $this->registry->get($migrationId);
        $runId = $options->runId ?? Uuid::v7()->toRfc4122();
        $startedAt = ($this->clock)();

        $lookup = $this->buildLookupClosure();
        $destination = $this->stampRunId($definition->destination, $runId);

        // Counters and per-record error list — mutated as the iteration progresses.
        $counters = ['imported' => 0, 'skipped' => 0, 'failed' => 0];
        /** @var list<RecordError> $errors */
        $errors = [];

        $total = $this->safeSourceCount($definition);
        $processed = 0;

        // The outer try/catch handles run-level errors (FR-048 — always halt).
        // The per-record try/catch inside the foreach handles typed per-record
        // exceptions (FR-046 — continue unless halt-on-error).
        try {
            foreach ($this->iterateSource($definition) as $record) {
                if ($options->limit !== null && $processed >= $options->limit) {
                    break;
                }
                $processed++;

                try {
                    $this->processOne(
                        definition: $definition,
                        record: $record,
                        destination: $destination,
                        runId: $runId,
                        lookup: $lookup,
                        dryRun: $options->dryRun,
                        counters: $counters,
                    );
                } catch (SourceReadException | ProcessException | DestinationWriteException $e) {
                    $counters['failed']++;
                    $this->captureError($errors, $e, $record);
                    $this->logger->warning('MigrationRunner: per-record failure', [
                        'migration_id' => $definition->id,
                        'run_id' => $runId,
                        'error_class' => $e::class,
                        'error_code' => $this->codeFor($e),
                        'message' => $e->getMessage(),
                    ]);

                    if ($options->haltOnError) {
                        $finishedAt = ($this->clock)();
                        $report = new RunReport(
                            migrationId: $definition->id,
                            runId: $runId,
                            total: $total,
                            imported: $counters['imported'],
                            skipped: $counters['skipped'],
                            failed: $counters['failed'],
                            errors: $errors,
                            startedAt: $startedAt,
                            finishedAt: $finishedAt,
                            aborted: true,
                        );

                        throw new MigrationAbortedException(
                            report: $report,
                            reason: \sprintf(
                                'halt-on-error: first per-record failure (%s).',
                                $this->codeFor($e),
                            ),
                            previous: $e,
                        );
                    }
                }
            }
        } catch (MigrationAbortedException $e) {
            // Re-throw the halt-on-error report we just built.
            throw $e;
        } catch (\Throwable $e) {
            // FR-048 — run-level failure. Build a partial report and halt.
            $finishedAt = ($this->clock)();
            $report = new RunReport(
                migrationId: $definition->id,
                runId: $runId,
                total: $total,
                imported: $counters['imported'],
                skipped: $counters['skipped'],
                failed: $counters['failed'],
                errors: $errors,
                startedAt: $startedAt,
                finishedAt: $finishedAt,
                aborted: true,
            );

            $this->logger->error('MigrationRunner: run-level abort (FR-048)', [
                'migration_id' => $definition->id,
                'run_id' => $runId,
                'error_class' => $e::class,
                'message' => $e->getMessage(),
            ]);

            throw new MigrationAbortedException(
                report: $report,
                reason: \sprintf('run-level failure (%s): %s', $e::class, $e->getMessage()),
                previous: $e,
            );
        }

        $finishedAt = ($this->clock)();

        return new RunReport(
            migrationId: $definition->id,
            runId: $runId,
            total: $total,
            imported: $counters['imported'],
            skipped: $counters['skipped'],
            failed: $counters['failed'],
            errors: $errors,
            startedAt: $startedAt,
            finishedAt: $finishedAt,
            aborted: false,
        );
    }

    /**
     * Iterate the source plugin's records, wrapping any throw from the
     * underlying generator's `current()` or `next()` in a
     * {@see SourceReadException} so the runner's per-record try/catch can
     * decide between record-scoped capture and run-level halt.
     *
     * The plugin contract declares `records(): iterable<SourceRecord>`;
     * static analysis trusts that signature, so an in-band non-SourceRecord
     * value would surface as a TypeError at the per-record `processOne()`
     * call site rather than here. That TypeError is then caught by the
     * outer FR-048 catch and rewrapped as `MigrationAbortedException`.
     *
     * @return \Generator<int, SourceRecord>
     */
    private function iterateSource(MigrationDefinition $definition): \Generator
    {
        try {
            yield from $definition->source->records();
        } catch (SourceReadException $e) {
            throw $e;
        } catch (\Throwable $e) {
            // R2 mitigation: a generator's exception short-circuits the
            // outer foreach in run(). Rewrap as SourceReadException so the
            // run-level catch sees a typed signal. (`source_mid_iteration`
            // is a stable code consumers can pattern-match on.)
            throw new SourceReadException(
                sourceId: $definition->source->id(),
                migrationId: $definition->id,
                reason: \sprintf(
                    'source plugin threw mid-iteration (%s): %s',
                    $e::class,
                    $e->getMessage(),
                ),
                previous: $e,
            );
        }
    }

    /**
     * Per-record body. Mutates `$counters` in place.
     *
     * @param array{imported:int,skipped:int,failed:int} $counters
     */
    private function processOne(
        MigrationDefinition $definition,
        SourceRecord $record,
        DestinationPluginInterface $destination,
        string $runId,
        \Closure $lookup,
        bool $dryRun,
        array &$counters,
    ): void {
        try {
            $sourceId = $definition->source->sourceIdFor($record);
        } catch (SourceReadException $e) {
            // Plugin already typed the failure; surface unchanged.
            throw $e;
        } catch (\Throwable $e) {
            throw new SourceReadException(
                sourceId: $definition->source->id(),
                migrationId: $definition->id,
                reason: \sprintf('sourceIdFor() threw (%s): %s', $e::class, $e->getMessage()),
                previous: $e,
            );
        }

        // Process chain — one pass per destination field. The process-map keys
        // are guaranteed strings by MigrationDefinition's per-instance validator.
        /** @var array<string, mixed> $values */
        $values = [];
        foreach (\array_keys($definition->process) as $destinationField) {
            $values[$destinationField] = $this->chain->executeField(
                $definition,
                $destinationField,
                $record,
                $lookup,
            );
        }

        $destinationRecord = new DestinationRecord(
            migrationId: $definition->id,
            sourceId: $sourceId,
            values: $values,
        );

        if ($dryRun) {
            // FR-039 — process steps executed; destination write skipped; counts
            // toward the "skipped" tally so operators see what would have been
            // imported.
            $counters['skipped']++;
            $this->logger->debug('MigrationRunner: dry-run skip', [
                'migration_id' => $definition->id,
                'run_id' => $runId,
                'source_id_hash' => $sourceId->hash(),
            ]);

            return;
        }

        // FR-031 — pre-read the prior id-map row so we can distinguish the
        // hash-match skip path (still counts as a "skipped" record) from the
        // create-or-update path (counts as "imported"). EntityDestination
        // also performs this read internally; the small extra SELECT keeps
        // the counter accurate without leaking the destination's internals.
        $prior = $this->idMap->lookupDestination($definition->id, $sourceId);

        $writeResult = $destination->write($destinationRecord);

        $wasSkip = $prior !== null
            && $writeResult->destinationUuid === $prior->destinationUuid
            && $writeResult->sourceRecordHash === $prior->sourceRecordHash
            && $writeResult->runId === $prior->runId;

        if ($wasSkip) {
            $counters['skipped']++;
        } else {
            $counters['imported']++;
        }
    }

    /**
     * Return the destination plugin used by `$definition`, stamping it with
     * the run id when it supports the runner contract.
     *
     * Falls back to the definition's destination unchanged for plugins that
     * predate {@see EntityDestination::withRunId()} — they generate per-write
     * run ids per the WP05 contract. (A future WP could extract `WithRunId`
     * to a plugin-level interface; today only `EntityDestination` ships.)
     */
    private function stampRunId(DestinationPluginInterface $destination, string $runId): DestinationPluginInterface
    {
        if ($destination instanceof EntityDestination) {
            return $destination->withRunId($runId);
        }

        // Third-party destinations: leave alone. Their `WriteResult::$runId`
        // is whatever they chose; the runner's report carries the runner-
        // minted id regardless of what the destination stamped per row.
        return $destination;
    }

    /**
     * Build the `$lookup` closure injected into every {@see \Waaseyaa\Migration\Plugin\ProcessContext}.
     *
     * The closure resolves `(migrationId, SourceId) -> ?WriteResult` by
     * delegating to {@see MigrationIdMap::lookupDestination()}. This
     * fulfills FR-028 from the process-plugin side: process plugins MUST
     * NOT touch `MigrationIdMap` directly — they go through the closure so
     * the runner owns transactional scoping and test-double injection.
     */
    private function buildLookupClosure(): \Closure
    {
        return function (string $migrationId, SourceId $sourceId): ?WriteResult {
            return $this->idMap->lookupDestination($migrationId, $sourceId);
        };
    }

    /**
     * Try to ask the source plugin for a count; coerce a `\Throwable` to
     * `-1` so a flaky pre-count does not abort the whole run.
     */
    private function safeSourceCount(MigrationDefinition $definition): int
    {
        try {
            $count = $definition->source->count();
        } catch (\Throwable $e) {
            $this->logger->warning('MigrationRunner: source->count() threw; treating as unknown', [
                'migration_id' => $definition->id,
                'error_class' => $e::class,
                'message' => $e->getMessage(),
            ]);
            return -1;
        }

        return $count ?? -1;
    }

    /**
     * Append a per-record error to `$errors`, honoring the {@see RunReport::ERROR_CAP}.
     *
     * @param list<RecordError> $errors
     */
    private function captureError(array &$errors, \Throwable $e, SourceRecord $record): void
    {
        if (\count($errors) >= RunReport::ERROR_CAP) {
            return;
        }

        $stage = match (true) {
            $e instanceof SourceReadException => RecordError::STAGE_SOURCE,
            $e instanceof ProcessException => RecordError::STAGE_PROCESS,
            $e instanceof DestinationWriteException => RecordError::STAGE_DESTINATION,
            default => RecordError::STAGE_SOURCE,
        };

        // Best-effort source id hash — when the failure is upstream of
        // `sourceIdFor()`, fall back to a sentinel so the report stays
        // typed.
        $sourceIdHash = 'unknown';
        try {
            // SourceRecord has no SourceId yet (that requires the plugin's
            // key map). The best we can do is hash the record's field map.
            $sourceIdHash = \hash('sha256', \json_encode($record->fields, \JSON_THROW_ON_ERROR));
        } catch (\Throwable) {
            // keep sentinel
        }

        $sourceField = $e instanceof ProcessException ? $e->sourceField : null;

        $errors[] = new RecordError(
            sourceIdHash: $sourceIdHash,
            code: $this->codeFor($e),
            message: $e->getMessage(),
            stage: $stage,
            sourceField: $sourceField,
        );
    }

    /**
     * Extract a stable string code from any of the three typed exceptions.
     */
    private function codeFor(\Throwable $e): string
    {
        return match (true) {
            $e instanceof ProcessException => $e->processCode,
            $e instanceof DestinationWriteException => $e->reason,
            $e instanceof SourceReadException => SourceReadException::CODE,
            default => 'UNKNOWN',
        };
    }
}

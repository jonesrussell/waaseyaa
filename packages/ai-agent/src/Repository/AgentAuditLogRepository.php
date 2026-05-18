<?php

declare(strict_types=1);

namespace Waaseyaa\AI\Agent\Repository;

use Waaseyaa\AI\Agent\Entity\AgentAuditLog;
use Waaseyaa\Database\DatabaseInterface;
use Waaseyaa\Entity\Repository\EntityRepositoryInterface;

/**
 * Append-only repository for `agent_audit_log` rows.
 *
 * The only mutation surface aside from {@see append()} is
 * {@see purgeOlderThan()} — the audit-retention purge job. There is
 * intentionally no `update` / `delete` API: an audit row is a fact, not a
 * fact-and-then-some.
 *
 * Reads flow through the injected {@see EntityRepositoryInterface} so
 * downstream consumers (admin SPA, replay, exporter) get hydrated entities
 * with the normal lifecycle events.
 *
 * @api
 */
final class AgentAuditLogRepository
{
    private const TABLE = 'agent_audit_log';

    public function __construct(
        private readonly EntityRepositoryInterface $entityRepository,
        private readonly DatabaseInterface $database,
    ) {}

    /**
     * Persist a fresh audit-log row.
     *
     * Callers should construct rows via {@see AgentAuditLog::for()} so the
     * `isNew=true` enforcement and default field shape are guaranteed.
     */
    public function append(AgentAuditLog $log): void
    {
        $this->entityRepository->save($log);
    }

    /**
     * All audit rows for a given run, ordered by `occurred_at` ASC.
     *
     * Backed by `idx_agent_audit_run_occurred_at`.
     *
     * @return list<AgentAuditLog>
     */
    public function findByRunId(string $runId): array
    {
        /** @var list<AgentAuditLog> $results */
        $results = $this->entityRepository->findBy(
            ['run_id' => $runId],
            ['occurred_at' => 'ASC'],
        );

        return $results;
    }

    /**
     * Delete every audit row with `occurred_at < $threshold`.
     *
     * Bounded by the retention policy in `config.ai.audit_retention_days`.
     * Returns the number of rows purged.
     */
    public function purgeOlderThan(\DateTimeImmutable $threshold): int
    {
        $thresholdString = $threshold->format('Y-m-d H:i:s.uP');

        return $this->database
            ->delete(self::TABLE)
            ->condition('occurred_at', $thresholdString, '<')
            ->execute();
    }
}

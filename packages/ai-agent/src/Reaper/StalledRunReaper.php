<?php

declare(strict_types=1);

namespace Waaseyaa\AI\Agent\Reaper;

use Waaseyaa\AI\Agent\Broadcast\AgentRunBroadcasterInterface;
use Waaseyaa\AI\Agent\Entity\AgentAuditLog;
use Waaseyaa\AI\Agent\Enum\EventType;
use Waaseyaa\AI\Agent\Enum\RunStatus;
use Waaseyaa\AI\Agent\Repository\AgentAuditLogRepository;
use Waaseyaa\AI\Agent\Repository\AgentRunRepository;
use Waaseyaa\Foundation\Log\LoggerInterface;
use Waaseyaa\Foundation\Log\NullLogger;

/**
 * Recover {@see \Waaseyaa\AI\Agent\Entity\AgentRun} rows whose worker
 * crashed mid-execution (NFR-004, FR-007).
 *
 * Definition of "stalled": `status='running'` AND
 * `started_at < (now() - maxRuntimeSeconds)`. The worker would have
 * driven the row to a terminal state by now under normal operation, so
 * the only way the threshold is crossed is a worker crash, an OOM kill,
 * or a host reboot. The reaper flips the row to
 * `Failed/worker_crashed`, appends a matching audit row, and pushes a
 * `run_failed` SSE event.
 *
 * Honours C-014: transitions go through
 * {@see AgentRunRepository::markTerminal()}, which is a compare-and-swap
 * that refuses to overwrite an already-terminal row. A worker that
 * completed in the window between selection and update therefore "wins"
 * — the reaper sees `markTerminal() === false` and skips that row.
 *
 * @api
 */
final class StalledRunReaper
{
    private readonly LoggerInterface $logger;

    /** @var \Closure(): \DateTimeImmutable */
    private \Closure $now;

    /** @var \Closure(): string */
    private \Closure $idFactory;

    public function __construct(
        private readonly AgentRunRepository $runRepository,
        private readonly AgentAuditLogRepository $auditRepository,
        private readonly AgentRunBroadcasterInterface $broadcaster,
        ?LoggerInterface $logger = null,
        ?\Closure $now = null,
        ?\Closure $idFactory = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
        $this->now = $now ?? static fn(): \DateTimeImmutable => new \DateTimeImmutable('now');
        $this->idFactory = $idFactory ?? static fn(): string => self::uuidV4();
    }

    /**
     * Scan for stalled rows and flip them to terminal `failed`.
     *
     * @param int $maxRuntimeSeconds Threshold: rows whose `started_at`
     *     is older than this many seconds count as stalled.
     * @return int Count of rows successfully transitioned (excludes
     *     races where another worker reached terminal first).
     */
    public function reap(int $maxRuntimeSeconds): int
    {
        if ($maxRuntimeSeconds <= 0) {
            throw new \InvalidArgumentException(\sprintf(
                'StalledRunReaper: maxRuntimeSeconds must be positive; got %d.',
                $maxRuntimeSeconds,
            ));
        }

        $now = ($this->now)();
        $threshold = $now->sub(new \DateInterval('PT' . $maxRuntimeSeconds . 'S'));

        $rows = $this->runRepository->findStuckRunning($threshold);
        $flipped = 0;

        foreach ($rows as $run) {
            $runId = (string) $run->get('id');

            $advanced = $this->runRepository->markTerminal(
                $runId,
                RunStatus::Failed,
                $now,
                errorCode: 'worker_crashed',
                errorMessage: \sprintf(
                    'Worker crashed: started_at older than %d seconds (last started_at < %s).',
                    $maxRuntimeSeconds,
                    $threshold->format(\DateTimeInterface::ATOM),
                ),
            );

            if (!$advanced) {
                // C-014: the row reached terminal between our select
                // and our update. Leave it; the winner's terminal data
                // is authoritative.
                continue;
            }

            $this->appendErrorAudit($runId);
            $this->broadcastFailed($runId);
            $flipped++;
        }

        if ($flipped > 0) {
            $this->logger->info(\sprintf(
                'StalledRunReaper: flipped %d stalled run(s) to failed/worker_crashed.',
                $flipped,
            ));
        }

        return $flipped;
    }

    private function appendErrorAudit(string $runId): void
    {
        try {
            $entry = AgentAuditLog::for(
                id: ($this->idFactory)(),
                runId: $runId,
                iteration: 0,
                eventType: EventType::Error,
                occurredAt: ($this->now)(),
                success: false,
                toolName: null,
                toolResultSummary: 'worker_crashed',
            );
            $this->auditRepository->append($entry);
        } catch (\Throwable $e) {
            $this->logger->error(\sprintf(
                'StalledRunReaper: failed to append error audit for run "%s": %s',
                $runId,
                $e->getMessage(),
            ));
        }
    }

    private function broadcastFailed(string $runId): void
    {
        try {
            $this->broadcaster->push($runId, 'run_failed', [
                'error_code' => 'worker_crashed',
                'error_message' => 'Worker crashed; reaped by StalledRunReaper.',
            ]);
        } catch (\Throwable $e) {
            $this->logger->error(\sprintf(
                'StalledRunReaper: failed to push run_failed SSE for run "%s": %s',
                $runId,
                $e->getMessage(),
            ));
        }
    }

    private static function uuidV4(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}

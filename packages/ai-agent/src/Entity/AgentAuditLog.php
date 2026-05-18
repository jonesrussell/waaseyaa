<?php

declare(strict_types=1);

namespace Waaseyaa\AI\Agent\Entity;

use Waaseyaa\AI\Agent\Enum\EventType;
use Waaseyaa\Entity\ContentEntityBase;

/**
 * One audit-log row per executor event (`agent_audit_log`).
 *
 * Authoritative shape lives in `kitty-specs/agent-executor-01KRWPK7/data-model.md`
 * § AgentAuditLog. Append-only at the application layer — the only mutation
 * outside `append` is the bounded purge job
 * ({@see \Waaseyaa\AI\Agent\Repository\AgentAuditLogRepository::purgeOlderThan()}).
 *
 * Column mapping:
 *
 * - `id`                   — uuid PK
 * - `run_id`               — FK → `agent_run.id`
 * - `iteration`            — int (loop iteration number)
 * - `event_type`           — {@see EventType} string value
 * - `tool_name`            — string|null
 * - `tool_arguments_json`  — string|null (redacted per
 *                            `AgentToolInterface::argumentsForAudit`)
 * - `tool_result_summary`  — string|null (short summary)
 * - `success`              — bool
 * - `duration_ms`          — int|null
 * - `occurred_at`          — DateTimeImmutable
 *
 * @api
 */
final class AgentAuditLog extends ContentEntityBase
{
    /**
     * @param array<string, mixed> $values Initial storage-canonical values.
     * @param string $entityTypeId Hydration override; default hardcoded.
     * @param array<string, string> $entityKeys Hydration override; default hardcoded.
     * @param array<string, mixed> $fieldDefinitions Reserved for the field-definition registry.
     */
    public function __construct(
        array $values = [],
        string $entityTypeId = 'agent_audit_log',
        array $entityKeys = ['id' => 'id', 'uuid' => 'id', 'label' => 'event_type'],
        array $fieldDefinitions = [],
    ) {
        parent::__construct($values, $entityTypeId, $entityKeys, $fieldDefinitions);
    }

    /**
     * Construct a new audit-log row pre-seeded with sensible defaults.
     *
     * The returned entity is `isNew=true` so the caller can {@see save()}
     * it through `AgentAuditLogRepository::append()`.
     *
     * @param array<string, mixed> $extra Additional column overrides merged
     *                                    on top of the defaults below.
     */
    public static function for(
        string $id,
        string $runId,
        int $iteration,
        EventType $eventType,
        \DateTimeImmutable $occurredAt,
        bool $success = true,
        ?string $toolName = null,
        ?string $toolArgumentsJson = null,
        ?string $toolResultSummary = null,
        ?int $durationMs = null,
        array $extra = [],
    ): self {
        $values = [
            'id' => $id,
            'run_id' => $runId,
            'iteration' => $iteration,
            'event_type' => $eventType->value,
            'tool_name' => $toolName,
            'tool_arguments_json' => $toolArgumentsJson,
            'tool_result_summary' => $toolResultSummary,
            'success' => $success,
            'duration_ms' => $durationMs,
            'occurred_at' => $occurredAt->format('Y-m-d H:i:s.uP'),
        ];

        $log = new self(\array_replace($values, $extra));
        $log->enforceIsNew(true);

        return $log;
    }

    public function getEventType(): EventType
    {
        $raw = $this->get('event_type');
        if ($raw instanceof EventType) {
            return $raw;
        }

        return EventType::from((string) $raw);
    }

    public function getRunId(): string
    {
        return (string) $this->get('run_id');
    }

    public function isSuccess(): bool
    {
        return (bool) $this->get('success');
    }
}

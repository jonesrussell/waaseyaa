<?php

declare(strict_types=1);

namespace Waaseyaa\AI\Observability\Recorder;

use Symfony\Component\Uid\Uuid;
use Waaseyaa\AI\Observability\Handle\SpanHandle;
use Waaseyaa\AI\Observability\Handle\TraceHandle;
use Waaseyaa\AI\Observability\Trace;
use Waaseyaa\AI\Observability\TraceContext;
use Waaseyaa\AI\Observability\Value\DecisionTrace;
use Waaseyaa\AI\Observability\Value\Outcome;
use Waaseyaa\Database\DatabaseInterface;
use Waaseyaa\Entity\Repository\EntityRepositoryInterface;

/**
 * @api
 */
final class TraceRecorder implements TraceRecorderInterface
{
    public function __construct(
        private readonly EntityRepositoryInterface $traces,
        private readonly DatabaseInterface $database,
        private readonly TraceContext $context,
    ) {}

    public function startTrace(string $label, array $attributes = []): TraceHandle
    {
        $uuid = Uuid::v4()->toRfc4122();
        $startedAt = new \DateTimeImmutable();

        $trace = new Trace([
            'uuid' => $uuid,
            'label' => $label,
            'status' => 'running',
            'started_at' => $startedAt->format('Y-m-d H:i:s'),
            'attributes' => $attributes,
        ]);
        $trace->enforceIsNew();
        $this->traces->save($trace);

        $handle = new TraceHandle($uuid, $startedAt);
        $this->context->register($handle);

        return $handle;
    }

    public function completeTrace(TraceHandle $handle, string $status = 'ok'): void
    {
        $trace = $this->findTrace($handle->uuid);
        if ($trace === null) {
            return;
        }
        $trace->set('status', $status);
        $trace->set('ended_at', new \DateTimeImmutable()->format('Y-m-d H:i:s'));
        $this->traces->save($trace);
        $this->context->clear($handle->uuid);
    }

    public function span(TraceHandle $handle, string $kind, string $name, ?SpanHandle $parent = null): SpanHandle
    {
        $spanUuid = Uuid::v4()->toRfc4122();
        $startedAt = new \DateTimeImmutable();

        $this->database->insert('trace_span')
            ->values([
                'id' => $spanUuid,
                'uuid' => $spanUuid,
                'trace_uuid' => $handle->uuid,
                'parent_span_uuid' => $parent?->uuid,
                'kind' => $kind,
                'name' => $name,
                'started_at' => $startedAt->format('Y-m-d H:i:s.u'),
                'status' => 'ok',
                'attributes' => '{}',
            ])
            ->execute();

        return new SpanHandle($spanUuid, $handle->uuid, $kind, $startedAt, $parent?->uuid);
    }

    public function endSpan(SpanHandle $handle, array $attributes = [], string $status = 'ok'): void
    {
        $this->database->update('trace_span')
            ->fields([
                'ended_at' => new \DateTimeImmutable()->format('Y-m-d H:i:s.u'),
                'status' => $status,
                'attributes' => json_encode($attributes, JSON_THROW_ON_ERROR),
            ])
            ->condition('uuid', $handle->uuid)
            ->execute();
    }

    public function recordDecision(TraceHandle $handle, DecisionTrace $decision): void
    {
        $span = $this->span($handle, 'decision', 'decision');
        $this->endSpan($span, $decision->toAttributes());
    }

    public function recordOutcome(TraceHandle $handle, Outcome $outcome): void
    {
        $trace = $this->findTrace($handle->uuid);
        if ($trace === null) {
            return;
        }
        $trace->set('outcome_status', $outcome->status);
        $trace->set('outcome_feedback', $outcome->feedback);
        $trace->set('outcome_metadata', $outcome->metadata);
        $this->traces->save($trace);
    }

    private function findTrace(string $uuid): ?Trace
    {
        $results = $this->traces->findBy(['uuid' => $uuid], null, 1);
        $trace = $results[0] ?? null;

        return $trace instanceof Trace ? $trace : null;
    }
}

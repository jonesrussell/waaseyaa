<?php

declare(strict_types=1);

namespace Waaseyaa\AI\Observability;

use Waaseyaa\Entity\EntityBase;

/**
 * Trace entity: one full agent execution.
 *
 * Spans (tool calls, LLM calls, decisions) live in the `trace_span`
 * supporting table, keyed by trace_uuid. See ObservabilityServiceProvider
 * and migrations.
 */
final class Trace extends EntityBase
{
    public function __construct(array $values = [])
    {
        parent::__construct(
            $values,
            'trace',
            ['id' => 'id', 'uuid' => 'uuid', 'label' => 'label'],
        );
    }
}

<?php

declare(strict_types=1);

namespace Waaseyaa\AI\Observability;

use Waaseyaa\AI\Observability\Handle\TraceHandle;

/**
 * Per-request registry of active traces, keyed by uuid.
 * Event listeners consult this to correlate an event with its trace.
 */
final class TraceContext
{
    /** @var array<string, TraceHandle> */
    private array $handles = [];

    public function register(TraceHandle $handle): void
    {
        $this->handles[$handle->uuid] = $handle;
    }

    public function get(string $uuid): ?TraceHandle
    {
        return $this->handles[$uuid] ?? null;
    }

    public function clear(string $uuid): void
    {
        unset($this->handles[$uuid]);
    }
}

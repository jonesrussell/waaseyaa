<?php

declare(strict_types=1);

namespace Waaseyaa\AI\Observability\Recorder;

/**
 * No-op telescope recorder used as the default DI binding when no
 * Telescope-backed implementation is wired (tests, headless workers,
 * minimal kernels).
 *
 * @api
 */
final class NullAgentTelescopeRecorder implements AgentTelescopeRecorderInterface
{
    public function recordAgentRun(array $record): void
    {
        // intentionally a no-op
    }
}

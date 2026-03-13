<?php

declare(strict_types=1);

namespace Waaseyaa\Telescope\CodifiedContext;

use Waaseyaa\Telescope\CodifiedContext\Recorder\CodifiedContextEventRecorder;
use Waaseyaa\Telescope\CodifiedContext\Recorder\CodifiedContextSessionRecorder;
use Waaseyaa\Telescope\CodifiedContext\Recorder\CodifiedContextValidationRecorder;
use Waaseyaa\Telescope\Storage\TelescopeStoreInterface;

/**
 * Facade over the three codified-context recorders.
 *
 * Callers interact with a single object instead of three separate recorders.
 */
final class CodifiedContextObserver
{
    private readonly CodifiedContextSessionRecorder $sessionRecorder;
    private readonly CodifiedContextEventRecorder $eventRecorder;
    private readonly CodifiedContextValidationRecorder $validationRecorder;

    public function __construct(TelescopeStoreInterface $store)
    {
        $this->sessionRecorder = new CodifiedContextSessionRecorder(store: $store);
        $this->eventRecorder = new CodifiedContextEventRecorder(store: $store);
        $this->validationRecorder = new CodifiedContextValidationRecorder(store: $store);
    }

    public function recordSessionStart(string $sessionId, string $repoHash, array $metadata = []): void
    {
        $this->sessionRecorder->recordStart($sessionId, $repoHash, $metadata);
    }

    public function recordContextLoad(string $sessionId, string $contextHash, array $filePaths, int $totalBytes): void
    {
        $this->eventRecorder->recordContextLoad($sessionId, $contextHash, $filePaths, $totalBytes);
    }

    public function recordModelOutput(string $sessionId, string $outputHash, array $references, int $tokenCount): void
    {
        $this->eventRecorder->recordModelOutput($sessionId, $outputHash, $references, $tokenCount);
    }

    public function recordDrift(string $sessionId, int $driftScore, string $severity, array $issues): void
    {
        $this->eventRecorder->recordDriftDetected($sessionId, $driftScore, $severity, $issues);
    }

    public function recordValidation(
        string $sessionId,
        int $driftScore,
        array $components,
        array $issues,
        string $recommendation,
    ): void {
        $this->validationRecorder->record($sessionId, $driftScore, $components, $issues, $recommendation);
    }

    public function recordSessionEnd(string $sessionId, float $durationMs, int $eventCount): void
    {
        $this->sessionRecorder->recordEnd($sessionId, $durationMs, $eventCount);
    }
}

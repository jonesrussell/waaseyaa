<?php

declare(strict_types=1);

namespace Waaseyaa\Telescope\CodifiedContext\Recorder;

use Waaseyaa\Telescope\Storage\TelescopeStoreInterface;

/**
 * Records individual codified context events (load, hash, fail, output, drift).
 */
final class CodifiedContextEventRecorder
{
    public const string TYPE = 'cc_event';

    public function __construct(
        private readonly TelescopeStoreInterface $store,
    ) {}

    public function recordContextLoad(string $sessionId, string $contextHash, array $filePaths, int $totalBytes): void
    {
        $this->store->store(self::TYPE, [
            'session_id' => $sessionId,
            'event_type' => 'context_load',
            'context_hash' => $contextHash,
            'file_paths' => $filePaths,
            'total_bytes' => $totalBytes,
            'occurred_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s.u'),
        ]);
    }

    public function recordContextHash(string $sessionId, string $contextHash, string $algorithm = 'sha256'): void
    {
        $this->store->store(self::TYPE, [
            'session_id' => $sessionId,
            'event_type' => 'context_hash',
            'context_hash' => $contextHash,
            'algorithm' => $algorithm,
            'occurred_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s.u'),
        ]);
    }

    public function recordContextFail(string $sessionId, string $errorMessage, ?string $filePath = null): void
    {
        $data = [
            'session_id' => $sessionId,
            'event_type' => 'context_fail',
            'error_message' => $errorMessage,
            'occurred_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s.u'),
        ];

        if ($filePath !== null) {
            $data['file_path'] = $filePath;
        }

        $this->store->store(self::TYPE, $data);
    }

    public function recordModelOutput(string $sessionId, string $outputHash, array $references, int $tokenCount): void
    {
        $this->store->store(self::TYPE, [
            'session_id' => $sessionId,
            'event_type' => 'model_output',
            'output_hash' => $outputHash,
            'references' => $references,
            'token_count' => $tokenCount,
            'occurred_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s.u'),
        ]);
    }

    public function recordDriftDetected(string $sessionId, int $driftScore, string $severity, array $issues): void
    {
        $this->store->store(self::TYPE, [
            'session_id' => $sessionId,
            'event_type' => 'drift_detected',
            'drift_score' => $driftScore,
            'severity' => $severity,
            'issues' => $issues,
            'occurred_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s.u'),
        ]);
    }

    public function recordDriftCorrected(string $sessionId, int $originalScore, int $correctedScore, array $corrections): void
    {
        $this->store->store(self::TYPE, [
            'session_id' => $sessionId,
            'event_type' => 'drift_corrected',
            'original_score' => $originalScore,
            'corrected_score' => $correctedScore,
            'corrections' => $corrections,
            'occurred_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s.u'),
        ]);
    }
}

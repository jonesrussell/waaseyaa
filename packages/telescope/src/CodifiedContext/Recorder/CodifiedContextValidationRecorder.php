<?php

declare(strict_types=1);

namespace Waaseyaa\Telescope\CodifiedContext\Recorder;

use Waaseyaa\Telescope\Storage\TelescopeStoreInterface;

/**
 * Records codified context validation reports (drift scores, components, issues, recommendations).
 */
final class CodifiedContextValidationRecorder
{
    public const string TYPE = 'cc_validation';

    public function __construct(
        private readonly TelescopeStoreInterface $store,
    ) {}

    public function record(
        string $sessionId,
        int $driftScore,
        array $components,
        array $issues,
        string $recommendation,
    ): void {
        $this->store->store(self::TYPE, [
            'session_id' => $sessionId,
            'event_type' => 'validation_report',
            'drift_score' => $driftScore,
            'components' => $components,
            'issues' => $issues,
            'recommendation' => $recommendation,
            'occurred_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s.u'),
        ]);
    }
}

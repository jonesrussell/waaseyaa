<?php

declare(strict_types=1);

namespace Waaseyaa\AI\Observability\Outcome;

use Waaseyaa\AI\Observability\Handle\TraceHandle;
use Waaseyaa\AI\Observability\Recorder\TraceRecorderInterface;
use Waaseyaa\AI\Observability\Value\Outcome;

final class OutcomeTracker
{
    public function __construct(private readonly TraceRecorderInterface $recorder) {}

    public function record(TraceHandle $handle, Outcome $outcome): void
    {
        $this->recorder->recordOutcome($handle, $outcome);
    }
}

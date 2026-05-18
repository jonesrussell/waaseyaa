<?php

declare(strict_types=1);

namespace Waaseyaa\AI\Agent\Enum;

/**
 * Status of an {@see \Waaseyaa\AI\Agent\Entity\AgentRun}.
 *
 * State machine (per data-model.md § AgentRun):
 *
 *   queued ──► running ──► completed
 *      │          │
 *      │          ├─► failed
 *      │          ├─► awaiting_approval ──► running  (approved)
 *      │          │                    ╰─► failed   (denied/timeout)
 *      │          ╰─► cancelling ──► cancelled
 *      ╰─► cancelled  (cancel before worker pickup)
 *
 * Terminal statuses (`completed`, `failed`, `cancelled`) cannot regress.
 *
 * @api
 */
enum RunStatus: string
{
    case Queued = 'queued';
    case Running = 'running';
    case AwaitingApproval = 'awaiting_approval';
    case Cancelling = 'cancelling';
    case Cancelled = 'cancelled';
    case Completed = 'completed';
    case Failed = 'failed';

    /**
     * Terminal statuses — once entered, the run cannot leave.
     *
     * @return list<self>
     */
    public static function terminals(): array
    {
        return [self::Cancelled, self::Completed, self::Failed];
    }

    /**
     * Whether this status is terminal.
     */
    public function isTerminal(): bool
    {
        return \in_array($this, self::terminals(), strict: true);
    }
}

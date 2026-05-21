<?php

declare(strict_types=1);

namespace Waaseyaa\Api\Schedule;

use Waaseyaa\Api\Controller\BroadcastStorage;
use Waaseyaa\Scheduler\ScheduledTask;
use Waaseyaa\Scheduler\ScheduleEntriesInterface;
use Waaseyaa\Scheduler\ScheduleInterface;

/**
 * Registers the nightly _broadcast_log prune task.
 *
 * Default schedule: 0 2 * * * (02:00 UTC daily)
 * Default retention: 7 days (configurable via schedule.broadcast_log_retention_days)
 *
 * To disable: add Waaseyaa\Api\Schedule\BroadcastStorageScheduleEntries to
 * schedule.disabled_entries in your configuration.
 *
 * @api
 */
final class BroadcastStorageScheduleEntries implements ScheduleEntriesInterface
{
    private int $retentionDays;

    public function __construct(
        private readonly BroadcastStorage $broadcastStorage,
        array $config = [],
    ) {
        $this->retentionDays = (int) ($config['schedule']['broadcast_log_retention_days'] ?? 7);
    }

    public function register(ScheduleInterface $schedule): array
    {
        $retentionDays = $this->retentionDays;
        $broadcastStorage = $this->broadcastStorage;

        $pruneTask = new ScheduledTask(
            name: 'broadcast_log_prune',
            expression: '0 2 * * *',
            command: static function () use ($broadcastStorage, $retentionDays): void {
                $broadcastStorage->prune($retentionDays);
            },
            timezone: 'UTC',
            description: 'Nightly _broadcast_log prune (FR-006). Retention: ' . $retentionDays . ' days.',
        );

        $schedule->add($pruneTask);

        return ['prune' => $pruneTask];
    }
}

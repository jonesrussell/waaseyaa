<?php

declare(strict_types=1);

namespace Waaseyaa\Scheduler;

/**
 * @internal
 * @api
 */
interface ScheduleInterface
{
    /**
     * @return list<ScheduledTask>
     */
    public function tasks(): array;

    public function add(ScheduledTask $task): static;
}

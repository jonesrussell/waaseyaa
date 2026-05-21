<?php

declare(strict_types=1);

namespace Waaseyaa\Scheduler\Tests\Fixtures;

use Waaseyaa\Scheduler\ScheduleEntriesInterface;
use Waaseyaa\Scheduler\ScheduleInterface;
use Waaseyaa\Scheduler\ScheduledTask;

/**
 * Minimal fixture implementing ScheduleEntriesInterface for test discovery.
 *
 * Used by PackageManifestCompilerTest::discoversScheduleEntries to verify
 * that the compiler finds concrete implementors via class_implements() scan.
 */
final class TestScheduleEntries implements ScheduleEntriesInterface
{
    /**
     * @return array<string, ScheduledTask>
     */
    public function register(ScheduleInterface $schedule): array
    {
        return [];
    }
}

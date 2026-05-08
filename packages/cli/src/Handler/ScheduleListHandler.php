<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Handler;

use Waaseyaa\CLI\CliIO;
use Waaseyaa\Scheduler\ScheduleInterface;

final class ScheduleListHandler
{
    public function __construct(
        private readonly ScheduleInterface $schedule,
    ) {}

    public function execute(CliIO $io): int
    {
        $tasks = $this->schedule->tasks();

        if ($tasks === []) {
            $io->writeln('No scheduled tasks registered.');

            return 0;
        }

        $now = new \DateTimeImmutable();
        $io->writeln(sprintf('Found <info>%d</info> scheduled task(s):', count($tasks)));
        $io->writeln('');

        foreach ($tasks as $task) {
            $nextRun = $task->getNextRunDate($now)->format('Y-m-d H:i:s');
            $overlap = $task->preventOverlap ? ' [no-overlap]' : '';
            $desc = $task->description !== null ? " — {$task->description}" : '';

            $io->writeln(sprintf(
                '  <info>%s</info>  %s  Next: %s%s%s',
                $task->name,
                $task->expression,
                $nextRun,
                $overlap,
                $desc,
            ));
        }

        return 0;
    }
}

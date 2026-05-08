<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Handler;

use Waaseyaa\CLI\CliIO;
use Waaseyaa\Queue\FailedJobRepositoryInterface;

final class QueueFlushHandler
{
    public function __construct(
        private readonly FailedJobRepositoryInterface $failedJobRepository,
    ) {}

    public function execute(CliIO $io): int
    {
        $count = count($this->failedJobRepository->all());

        $this->failedJobRepository->flush();

        if ($count === 0) {
            $io->writeln('No failed jobs to flush.');
        } else {
            $label = $count === 1 ? 'job' : 'jobs';
            $io->writeln("Flushed {$count} failed {$label}.");
        }

        return 0;
    }
}

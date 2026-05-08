<?php

declare(strict_types=1);

namespace Waaseyaa\Queue\Worker;

/**
 * Configuration for a queue worker process.
 *
 * {@see memoryLimit}: Maximum **additional** memory (in MiB) this {@see Worker::run} loop may
 * allocate relative to usage at the start of that call — not total PHP process RSS.
 */
final class WorkerOptions
{
    public function __construct(
        public readonly int $sleep = 3,
        public readonly int $maxJobs = 0,
        public readonly int $maxTime = 0,
        /** Megabytes of heap growth allowed during {@see Worker::run()} before stopping. */
        public readonly int $memoryLimit = 128,
        public readonly int $timeout = 60,
        public readonly int $maxTries = 3,
    ) {}
}

<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Io;

/**
 * A stdin source backed by a pre-loaded queue of strings.
 *
 * Each call to readLine() pops the next line. Returns null when the queue is
 * exhausted. isInteractive() returns false — this is deterministic test input,
 * not a real TTY.
 */
final class StringQueueStdinSource implements StdinSource
{
    /** @var list<string> */
    private array $lines;

    /**
     * @param list<string> $lines Lines to return in order, one per readLine() call.
     */
    public function __construct(array $lines)
    {
        $this->lines = array_values($lines);
    }

    public function readLine(): ?string
    {
        if ($this->lines === []) {
            return null;
        }

        return array_shift($this->lines);
    }

    public function isInteractive(): bool
    {
        return false;
    }
}

<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Io;

/**
 * A stdin source backed by a real stream resource (e.g. STDIN).
 *
 * isInteractive() consults TtyDetector to determine TTY status at construction.
 */
final class StreamStdinSource implements StdinSource
{
    private readonly bool $interactive;

    /** @param resource $stream */
    public function __construct(private readonly mixed $stream)
    {
        $this->interactive = TtyDetector::isInteractive($stream);
    }

    public function readLine(): ?string
    {
        $line = fgets($this->stream);

        return $line === false ? null : rtrim($line, "\n\r");
    }

    public function isInteractive(): bool
    {
        return $this->interactive;
    }
}

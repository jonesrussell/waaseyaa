<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Io;

/**
 * Source of stdin input for CLI prompts.
 *
 * Implementations include StreamStdinSource (real TTY), StringQueueStdinSource
 * (deterministic test input), and EmptyStdinSource (non-interactive default).
 */
interface StdinSource
{
    /**
     * Read one line of input. Returns null on EOF or when no input is available.
     */
    public function readLine(): ?string;

    /**
     * Whether this source represents an interactive TTY.
     *
     * When false, CliIO will use defaults for ask/confirm without reading.
     */
    public function isInteractive(): bool;
}

<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Io;

/**
 * A stdin source that always returns null and reports non-interactive.
 *
 * Used as the default in CliTester so ask/confirm use their defaults silently.
 */
final class EmptyStdinSource implements StdinSource
{
    public function readLine(): ?string
    {
        return null;
    }

    public function isInteractive(): bool
    {
        return false;
    }
}
